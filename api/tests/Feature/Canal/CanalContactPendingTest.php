<?php

namespace Tests\Feature\Canal;

use App\Enums\CanalRole;
use App\Enums\ModelStatus;
use App\Enums\RegistrationSource;
use App\Models\Canal;
use App\Models\SystemLog;
use App\Models\User;
use App\Notifications\CanalContactNotice;
use App\Services\Canals\CanalContactVerifier;
use App\Services\Canals\CanalEmails;
use App\Services\Canals\CanalMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Novú adresu od tímu kanál nepoužíva, kým ju schránka nepotvrdí odkazom
 * (CanalEmails::request). Super-admin ju mení hneď.
 */
class CanalContactPendingTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Canal $canal;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Mail::fake();
        Http::fake(['*' => Http::response([])]);

        $this->owner = User::factory()->create(['email' => 'owner@divadlo.test', 'email_verified_at' => now(), 'last_login_at' => now(), 'last_activity' => now()]);
        $this->canal = Canal::factory()->active()->create([
            'registration_source' => RegistrationSource::SELF->value,
            'status' => ModelStatus::Published->value,
            'email' => 'stary@divadlo.test',
            'email_verified_at' => now(),
        ]);
        app(CanalMembership::class)->attach($this->canal, $this->owner, CanalRole::Owner);
        // Člen tímu pridal aj adresu svojho účtu — pre test len prekáža.
        $this->canal->emails()->where('email', 'owner@divadlo.test')->delete();
        app(CanalEmails::class)->refresh($this->canal);
        SystemLog::query()->delete();
    }

    #[Test]
    public function new_address_from_the_team_waits_for_confirmation(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->putJson("/api/dashboard/canals/{$this->canal->id}", $this->form('Novy@Divadlo.test'))
            ->assertOk()
            ->assertJsonPath('email', 'stary@divadlo.test')
            ->assertJsonPath('emails.1.email', 'novy@divadlo.test')
            ->assertJsonPath('emails.1.pending', true)
            ->assertJsonPath('emails.1.pending_primary', true);

        $this->assertSame('stary@divadlo.test', $this->canal->fresh()->email);
        $this->assertNotNull($this->canal->fresh()->email_verified_at);
        $this->assertTrue(SystemLog::where('event', 'canals.contact_requested')->exists());
        // Odkaz ide na novú adresu; pôvodná sa dozvie až o skutočnej zmene.
        $this->assertSame(['novy@divadlo.test'], $this->mailedTo());

        // Ďalšie uloženie formulára (pole ostáva na čakajúcej adrese) nič znova neposiela.
        $this->putJson("/api/dashboard/canals/{$this->canal->id}", $this->form('novy@divadlo.test'))->assertOk();
        $this->assertSame(['novy@divadlo.test'], $this->mailedTo());

        $this->app['auth']->forgetGuards();
        $this->get(app(CanalContactVerifier::class)->verifyUrl($this->canal, 'novy@divadlo.test'))
            ->assertRedirectContains('kontakt=overeny');

        $canal = $this->canal->fresh();
        $this->assertSame('novy@divadlo.test', $canal->email);
        $this->assertNotNull($canal->email_verified_at);
        $this->assertFalse($canal->emails()->where('email', 'novy@divadlo.test')->sole()->isPending());
        $this->assertSame(['novy@divadlo.test', 'stary@divadlo.test'], $this->mailedTo());
    }

    #[Test]
    public function super_admin_changes_the_address_at_once(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now(), 'last_login_at' => now(), 'last_activity' => now()]);
        $admin->assignRole('super-admin');

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/admin/canals/{$this->canal->id}", $this->form('novy@divadlo.test'))
            ->assertOk();

        $canal = $this->canal->fresh();
        $this->assertSame('novy@divadlo.test', $canal->email);
        $this->assertNull($canal->email_verified_at);
        $this->assertFalse($canal->emails()->where('email', 'novy@divadlo.test')->sole()->isPending());
    }

    #[Test]
    public function team_picks_an_address_the_canal_already_has_at_once(): void
    {
        app(CanalEmails::class)->sync($this->canal, 'stary@divadlo.test', ['druha@divadlo.test']);

        $this->actingAs($this->owner, 'sanctum')
            ->putJson("/api/dashboard/canals/{$this->canal->id}", $this->form('druha@divadlo.test'))
            ->assertOk()
            ->assertJsonPath('email', 'druha@divadlo.test');

        $this->assertFalse(SystemLog::where('event', 'canals.contact_requested')->exists());
    }

    #[Test]
    public function pending_address_never_becomes_primary_by_itself(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->putJson("/api/dashboard/canals/{$this->canal->id}", $this->form('novy@divadlo.test'))->assertOk();

        // Pôvodná adresa sa vrátila — čakajúca ju nenahradí, kanál ostane bez kontaktu.
        app(CanalEmails::class)->recordBounce('stary@divadlo.test');

        $this->assertNull($this->canal->fresh()->email);
    }

    #[Test]
    public function verification_link_can_be_sent_again_only_for_an_unconfirmed_address(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->putJson("/api/dashboard/canals/{$this->canal->id}", $this->form('novy@divadlo.test'))->assertOk();

        $this->postJson("/api/dashboard/canals/{$this->canal->id}/emails/verification", ['email' => 'novy@divadlo.test'])->assertOk();
        $this->assertSame(['novy@divadlo.test', 'novy@divadlo.test'], $this->mailedTo());

        $this->postJson("/api/dashboard/canals/{$this->canal->id}/emails/verification", ['email' => 'stary@divadlo.test'])
            ->assertJsonValidationErrors('email');
        $this->postJson("/api/dashboard/canals/{$this->canal->id}/emails/verification", ['email' => 'nikto@inde.test'])
            ->assertJsonValidationErrors('email');
    }

    #[Test]
    public function returned_claim_drops_unconfirmed_addresses_of_the_team(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->putJson("/api/dashboard/canals/{$this->canal->id}", $this->form('novy@divadlo.test'))->assertOk();

        app(CanalEmails::class)->forgetOwners($this->canal);

        $this->assertFalse($this->canal->emails()->where('email', 'novy@divadlo.test')->exists());
        $this->assertSame('stary@divadlo.test', $this->canal->fresh()->email);
    }

    /** @return array<string, mixed> */
    #[Test]
    public function only_super_admin_sees_and_sets_the_event_offer_switch(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now(), 'last_login_at' => now(), 'last_activity' => now()]);
        $admin->assignRole('super-admin');

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/admin/canals/{$this->canal->id}", $this->form('media@ecav.sk') + ['event_offer_disabled' => true])
            ->assertOk()
            ->assertJsonPath('event_offer_disabled', true)
            ->assertJsonPath('event_offer_domain_excluded', true);

        $this->assertTrue($this->canal->fresh()->event_offer_disabled);

        $this->actingAs($this->owner, 'sanctum')
            ->putJson("/api/dashboard/canals/{$this->canal->id}", $this->form('stary@divadlo.test') + ['event_offer_disabled' => false])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('event_offer_disabled');

        $this->actingAs($this->owner, 'sanctum')
            ->getJson("/api/dashboard/canals/{$this->canal->id}")
            ->assertOk()
            ->assertJsonMissingPath('event_offer_disabled');

        $this->getJson("/api/canals/{$this->canal->slug}")->assertJsonMissingPath('event_offer_disabled');
    }

    private function form(string $email): array
    {
        return [
            'name' => $this->canal->name,
            'municipality_id' => $this->canal->municipality_id,
            'email' => $email,
        ];
    }

    /** @return array<int, string> adresáti odoslaných kontaktných e-mailov v poradí */
    private function mailedTo(): array
    {
        $sent = Notification::sentNotifications()[AnonymousNotifiable::class][''][CanalContactNotice::class] ?? [];

        return array_map(fn (array $m) => $m['notifiable']->routes['mail'], $sent);
    }
}
