<?php

namespace Tests\Feature\Canal;

use App\Enums\CanalClaimStatus;
use App\Enums\CanalRole;
use App\Enums\ModelStatus;
use App\Enums\RegistrationSource;
use App\Models\Canal;
use App\Models\CanalClaim;
use App\Models\SystemLog;
use App\Models\User;
use App\Services\Canals\CanalClaims;
use App\Services\Canals\CanalContactVerifier;
use App\Services\Canals\CanalInviter;
use App\Services\Canals\CanalMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Fáza 3: prevzatie kanála ako proces (CanalClaims) — pozvánka pre iný
 * účet, žiadosť z verejnej stránky, admin fronta, námietka a vrátenie,
 * overenie zmeneného kontaktu. Všetky e-maily sú simulované.
 */
class CanalClaimsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Canal $canal;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Mail::fake();

        $this->admin = $this->user('admin@portal.test');
        $this->admin->assignRole('super-admin');

        $this->canal = Canal::factory()->create([
            'name' => 'Farnosť Hájske',
            'registration_source' => RegistrationSource::IMPORT->value,
            'email' => 'info@farnost.sk',
            'status' => ModelStatus::Published->value,
            'published_at' => now(),
        ]);
        app(CanalMembership::class)->attach($this->canal, $this->admin, CanalRole::Owner);
        SystemLog::query()->delete();
    }

    #[Test]
    public function system_invitation_can_be_accepted_by_another_account_and_then_contested_and_reverted(): void
    {
        $invitation = app(CanalInviter::class)->ensureOwnerInvitation($this->canal, 'info@farnost.sk');
        $jozef = $this->user('jozef@gmail.test');

        $this->actingAs($jozef, 'sanctum')->getJson('/api/invitations/'.$invitation->token)
            ->assertJsonPath('data.any_account', true)
            ->assertJsonPath('data.email_matches', false);
        $this->actingAs($jozef, 'sanctum')->postJson('/api/invitations/'.$invitation->token.'/accept')->assertOk();

        $claim = CanalClaim::sole();
        $this->assertSame(CanalClaimStatus::Completed, $claim->status);
        $this->assertSame('info@farnost.sk', $claim->contact_email);
        $this->assertTrue($this->canal->fresh()->isClaimed());

        // Kontakt dostal upozornenie s odkazom na námietku.
        $warning = SystemLog::where('event', 'mail.simulated')->where('recipient', 'info@farnost.sk')->sole();
        $this->assertStringEndsWith('/prevzatie/namietka/'.$claim->contest_token, $warning->context['action']);

        // Námietka z odkazu — bez prihlásenia.
        auth()->forgetGuards();
        $shown = $this->getJson('/api/canal-claims/contest/'.$claim->contest_token)
            ->assertOk()->assertJsonPath('data.contestable', true)->json('data.requester.email');
        $this->assertNotSame('jozef@gmail.test', $shown);
        $this->assertStringEndsWith('@gmail.test', $shown);
        $this->postJson('/api/canal-claims/contest/'.$claim->contest_token, ['note' => 'Nepoznáme ho.'])->assertOk();
        $this->assertSame(CanalClaimStatus::Contested, $claim->fresh()->status);
        $this->assertTrue(SystemLog::where('event', 'mail.simulated')->where('recipient', 'admin@portal.test')->exists());

        // Admin vráti prevzatie.
        $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/canal-claims')
            ->assertOk()->assertJsonPath('data.0.status', 'contested')->assertJsonPath('data.0.contest_note', 'Nepoznáme ho.');
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/canal-claims/{$claim->id}/resolve", ['revert' => true, 'note' => 'Overené telefonicky.'])
            ->assertOk();

        $canal = $this->canal->fresh();
        $this->assertFalse($canal->isManaged());
        $this->assertSame([$this->admin->id], $canal->users()->pluck('users.id')->all());
        $this->assertSame(CanalClaimStatus::Reverted, $claim->fresh()->status);
        $this->assertTrue(SystemLog::where('event', 'canals.claim_reverted')->exists());
        $reverted = SystemLog::where('event', 'mail.simulated')->where('recipient', 'jozef@gmail.test')->latest('id')->first();
        $this->assertSame(\App\Notifications\CanalClaimNotice::class, $reverted?->context['class']);
        $this->assertStringContainsString('Farnosť Hájske', $reverted->message);

        // Druhá námietka už nejde.
        $this->postJson('/api/canal-claims/contest/'.$claim->contest_token)->assertUnprocessable();
        Notification::assertNothingSent();
    }

    #[Test]
    public function request_confirmed_from_the_contact_mailbox_claims_the_canal(): void
    {
        $jozef = $this->user('jozef@gmail.test');

        $this->actingAs($jozef, 'sanctum')->getJson("/api/canals/{$this->canal->id}")
            ->assertJsonPath('claimable', true)->assertJsonPath('claim_contact', true);

        $this->actingAs($jozef, 'sanctum')
            ->postJson("/api/canals/{$this->canal->id}/claims", ['method' => 'contact_email', 'message' => 'Som predseda farskej rady.'])
            ->assertCreated();
        $this->actingAs($jozef, 'sanctum')
            ->postJson("/api/canals/{$this->canal->id}/claims", ['method' => 'contact_email'])
            ->assertUnprocessable();

        $claim = CanalClaim::sole();
        $verify = SystemLog::where('event', 'mail.simulated')->sole();
        $this->assertSame('info@farnost.sk', $verify->recipient);
        $this->assertStringEndsWith('/prevzatie/'.$claim->token, $verify->context['action']);

        // Potvrdí kontaktná schránka — bez prihlásenia.
        auth()->forgetGuards();
        $this->getJson('/api/canal-claims/'.$claim->token)
            ->assertOk()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.message', 'Som predseda farskej rady.');
        $this->postJson('/api/canal-claims/'.$claim->token.'/confirm')->assertOk();

        $canal = $this->canal->fresh();
        $this->assertTrue($canal->isClaimed());
        $this->assertSame($jozef->id, $canal->claimed_by_user_id);
        $this->assertTrue(SystemLog::where('event', 'canals.claim_confirmed')->exists());
        $this->postJson('/api/canal-claims/'.$claim->token.'/confirm')->assertNotFound();
    }

    #[Test]
    public function admin_reviews_requests_without_contact(): void
    {
        $this->canal->forceFill(['email' => null])->save();
        $jozef = $this->user('jozef@gmail.test');
        $maria = $this->user('maria@gmail.test');

        $this->actingAs($jozef, 'sanctum')
            ->postJson("/api/canals/{$this->canal->id}/claims", ['method' => 'contact_email'])
            ->assertUnprocessable();
        $this->actingAs($jozef, 'sanctum')
            ->postJson("/api/canals/{$this->canal->id}/claims", ['method' => 'admin_review', 'message' => 'Som farár.'])
            ->assertCreated();
        $this->actingAs($maria, 'sanctum')
            ->postJson("/api/canals/{$this->canal->id}/claims", ['method' => 'admin_review'])
            ->assertCreated();

        $this->assertSame(['admin@portal.test', 'admin@portal.test'],
            SystemLog::where('event', 'mail.simulated')->pluck('recipient')->all());

        [$first, $second] = CanalClaim::orderBy('id')->get()->all();
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/admin/canal-claims/{$first->id}/approve")->assertOk();

        $this->assertSame($jozef->id, $this->canal->fresh()->claimed_by_user_id);
        // Druhá žiadosť o ten istý kanál prepadla.
        $this->assertSame(CanalClaimStatus::Expired, $second->fresh()->status);
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/admin/canal-claims/{$second->id}/reject")->assertUnprocessable();

        // Na spravovaný kanál sa už žiadať nedá.
        $this->actingAs($maria, 'sanctum')
            ->postJson("/api/canals/{$this->canal->id}/claims", ['method' => 'admin_review'])
            ->assertUnprocessable();
        $this->actingAs($maria, 'sanctum')->getJson("/api/canals/{$this->canal->id}")->assertJsonPath('claimable', false);
    }

    #[Test]
    public function rejected_request_is_logged_and_the_requester_is_told(): void
    {
        $jozef = $this->user('jozef@gmail.test');
        $this->actingAs($jozef, 'sanctum')
            ->postJson("/api/canals/{$this->canal->id}/claims", ['method' => 'admin_review'])->assertCreated();
        $claim = CanalClaim::sole();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/canal-claims/{$claim->id}/reject", ['note' => 'Nedoložené.'])->assertOk();

        $this->assertSame(CanalClaimStatus::Rejected, $claim->fresh()->status);
        $this->assertFalse($this->canal->fresh()->isManaged());
        $this->assertTrue(SystemLog::where('event', 'canals.claim_rejected')->exists());
        $this->assertTrue(SystemLog::where('event', 'mail.simulated')->where('recipient', 'jozef@gmail.test')->exists());
    }

    #[Test]
    public function expired_confirmation_link_does_not_work(): void
    {
        $jozef = $this->user('jozef@gmail.test');
        $this->actingAs($jozef, 'sanctum')
            ->postJson("/api/canals/{$this->canal->id}/claims", ['method' => 'contact_email'])->assertCreated();
        $claim = CanalClaim::sole();
        $claim->forceFill(['expires_at' => now()->subMinute()])->save();

        auth()->forgetGuards();
        $this->postJson('/api/canal-claims/'.$claim->token.'/confirm')->assertUnprocessable();

        $this->assertSame(CanalClaimStatus::Expired, $claim->fresh()->status);
        $this->assertFalse($this->canal->fresh()->isManaged());
    }

    #[Test]
    public function one_account_cannot_flood_organizations_with_requests(): void
    {
        $spammer = $this->user('spam@gmail.test');

        foreach (range(1, CanalClaims::MAX_OPEN_PER_USER + 1) as $i) {
            $canal = Canal::factory()->create([
                'registration_source' => RegistrationSource::IMPORT->value,
                'email' => "info{$i}@org.test",
            ]);
            app(CanalMembership::class)->attach($canal, $this->admin, CanalRole::Owner);

            $response = $this->actingAs($spammer, 'sanctum')
                ->postJson("/api/canals/{$canal->id}/claims", ['method' => 'contact_email']);

            $i <= CanalClaims::MAX_OPEN_PER_USER ? $response->assertCreated() : $response->assertUnprocessable();
        }

        $this->assertSame(CanalClaims::MAX_OPEN_PER_USER, SystemLog::where('event', 'mail.simulated')->count());
    }

    #[Test]
    public function collection_canal_is_not_claimable(): void
    {
        $this->canal->forceFill(['name' => 'vyveska.sk'])->save();

        $this->actingAs($this->user('x@gmail.test'), 'sanctum')
            ->postJson("/api/canals/{$this->canal->id}/claims", ['method' => 'admin_review'])
            ->assertUnprocessable();
        $this->getJson("/api/canals/{$this->canal->id}")->assertJsonPath('claimable', false);
    }

    #[Test]
    public function changed_contact_must_be_verified_and_the_old_one_is_warned(): void
    {
        $owner = $this->user('owner@divadlo.test');
        $canal = Canal::factory()->create([
            'registration_source' => RegistrationSource::SELF->value,
            'email' => 'stary@divadlo.test',
            'email_verified_at' => now(),
        ]);
        app(CanalMembership::class)->attach($canal, $owner, CanalRole::Owner);
        SystemLog::query()->delete();

        app(\App\Repositories\Contracts\CanalRepository::class)->update($canal->id, ['email' => 'Novy@Divadlo.test']);

        $canal->refresh();
        $this->assertNull($canal->email_verified_at);
        $this->assertTrue(SystemLog::where('event', 'canals.contact_changed')->exists());
        $mails = SystemLog::where('event', 'mail.simulated')->orderBy('id')->get();
        $this->assertSame(['novy@divadlo.test', 'stary@divadlo.test'], $mails->pluck('recipient')->all());

        // Pozmenený odkaz neprejde, podpísaný áno.
        $url = app(CanalContactVerifier::class)->verifyUrl($canal, 'novy@divadlo.test');
        $this->get(str_replace('novy%40', 'iny%40', $url))->assertForbidden();
        $this->get($url)->assertRedirectContains('kontakt=overeny');
        $this->assertNotNull($canal->fresh()->email_verified_at);
    }

    /** Pevné časy prihlásenia — factory ich inak losuje a môže trafiť hodinu, ktorú preskočil prechod na letný čas. */
    private function user(string $email): User
    {
        return User::factory()->create([
            'email' => $email,
            'email_verified_at' => now(),
            'last_login_at' => now(),
            'last_activity' => now(),
        ]);
    }
}
