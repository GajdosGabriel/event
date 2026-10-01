<?php

namespace Tests\Feature\Canal;

use App\Enums\CanalRole;
use App\Enums\ModelStatus;
use App\Enums\RegistrationSource;
use App\Models\Canal;
use App\Models\SystemLog;
use App\Models\User;
use App\Notifications\CanalOwnershipChanged;
use App\Services\Canals\CanalInviter;
use App\Services\Canals\CanalMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Denník zmien tímu/vlastníctva (CanalAuditor) a simulované e-maily
 * CanalOwnershipChanged: kým platí `canals.simulate_ownership_mail`, e-mail
 * sa vyskladá a zapíše ako `mail.simulated`, ale reálne neodíde.
 */
class CanalAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $owner;

    private Canal $canal;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Mail::fake();

        $this->admin = $this->user();
        $this->admin->assignRole('super-admin');

        $this->owner = $this->user(['email' => 'owner@divadlo.test']);
        $this->canal = Canal::factory()->create([
            'name' => 'Divadlo',
            'registration_source' => RegistrationSource::SELF->value,
            'status' => ModelStatus::Published->value,
            'published_at' => now(),
        ]);
        app(CanalMembership::class)->attach($this->canal, $this->owner, CanalRole::Owner);

        // Východiskový stav nie je predmetom testov — denník začína prázdny.
        SystemLog::query()->delete();
    }

    #[Test]
    public function claim_is_logged_step_by_step_and_its_mails_are_only_simulated(): void
    {
        $canal = Canal::factory()->create([
            'name' => 'Farnosť Hájske',
            'registration_source' => RegistrationSource::IMPORT->value,
            'email' => 'farnost@example.sk',
        ]);
        app(CanalMembership::class)->attach($canal, $this->admin, CanalRole::Owner);

        $invitation = app(CanalInviter::class)->ensureOwnerInvitation($canal, 'farnost@example.sk');
        $farar = $this->user(['email' => 'farnost@example.sk']);
        $this->actingAs($farar, 'sanctum')
            ->withHeader('X-Locale', 'sk')
            ->postJson('/api/invitations/'.$invitation->token.'/accept')
            ->assertOk();

        $events = $this->canalLog($canal)->pluck('event')->all();
        $this->assertSame([
            'canals.member_added',          // technický vlastník z importu
            'canals.invitation_claim_created',
            'canals.claim_accepted',        // doklad o prevzatí (CanalClaims)
            'canals.member_added',          // organizátor
            'canals.member_removed',        // technický vlastník odchádza
            'canals.claimed',
            'mail.simulated',               // potvrdenie organizátorovi
            'canals.invitation_accepted',
        ], $events);

        $removed = $this->canalLog($canal)->firstWhere('event', 'canals.member_removed');
        $this->assertSame($this->admin->id, $removed->user_id);

        // Kontakt = adresa organizátora → upozornenie na kontakt sa neposiela.
        $mail = SystemLog::where('event', 'mail.simulated')->sole();
        $this->assertSame('simulated', $mail->status);
        $this->assertSame('farnost@example.sk', $mail->recipient);
        $this->assertSame('Kanál Farnosť Hájske má nového správcu', $mail->message);
        $this->assertSame(CanalOwnershipChanged::class, $mail->context['class']);

        Notification::assertNotSentTo($farar, CanalOwnershipChanged::class);
        Mail::assertNothingSent();
    }

    #[Test]
    public function claim_by_a_different_address_warns_the_canal_contact(): void
    {
        $canal = Canal::factory()->create([
            'registration_source' => RegistrationSource::IMPORT->value,
            'email' => 'info@farnost.sk',
        ]);
        app(CanalMembership::class)->attach($canal, $this->admin, CanalRole::Owner);
        $invitation = $canal->invitations()->create([
            'email' => 'jozef@example.sk',
            'role' => CanalRole::Owner->value,
            'expires_at' => now()->addDay(),
        ]);
        $jozef = $this->user(['email' => 'jozef@example.sk']);

        app(CanalInviter::class)->accept($invitation, $jozef);

        $mails = SystemLog::where('event', 'mail.simulated')->orderBy('id')->get();
        $this->assertSame(['jozef@example.sk', 'info@farnost.sk'], $mails->pluck('recipient')->all());
        $this->assertStringContainsString('j', $mails[1]->context['lines'][0]);
        $this->assertStringNotContainsString('jozef@example.sk', implode(' ', $mails[1]->context['lines']));
        // Kontakt môže prevzatie v lehote napadnúť priamo z e-mailu.
        $this->assertStringContainsString('/prevzatie/namietka/', $mails[1]->context['action']);
        Notification::assertNothingSent();
    }

    #[Test]
    public function promoting_to_owner_is_logged_and_simulated_for_everyone_but_the_actor(): void
    {
        $second = $this->user(['email' => 'druhy@divadlo.test']);
        $editor = $this->user(['email' => 'editor@divadlo.test']);
        app(CanalMembership::class)->attach($this->canal, $second, CanalRole::Owner);
        app(CanalMembership::class)->attach($this->canal, $editor, CanalRole::Editor);
        SystemLog::query()->delete();

        $this->actingAs($this->owner, 'sanctum')
            ->withHeader('X-Locale', 'sk')
            ->putJson("/api/dashboard/canals/{$this->canal->id}/team/{$editor->id}", ['role' => 'owner'])
            ->assertOk();

        $log = SystemLog::where('event', 'canals.role_changed')->sole();
        $this->assertSame($editor->id, $log->user_id);
        $this->assertSame(['editor', 'owner', $this->owner->id], [$log->context['from'], $log->context['to'], $log->context['actor_id']]);

        $recipients = SystemLog::where('event', 'mail.simulated')->pluck('recipient')->sort()->values()->all();
        $this->assertSame(['druhy@divadlo.test', 'editor@divadlo.test'], $recipients);
        Notification::assertNothingSent();
    }

    #[Test]
    public function removing_an_owner_tells_the_removed_member_and_the_rest(): void
    {
        $second = $this->user(['email' => 'druhy@divadlo.test']);
        app(CanalMembership::class)->attach($this->canal, $second, CanalRole::Owner);
        SystemLog::query()->delete();

        $this->actingAs($this->owner, 'sanctum')
            ->withHeader('X-Locale', 'sk')
            ->deleteJson("/api/dashboard/canals/{$this->canal->id}/team/{$second->id}")
            ->assertOk();

        $this->assertSame(1, SystemLog::where('event', 'canals.member_removed')->where('user_id', $second->id)->count());
        $mail = SystemLog::where('event', 'mail.simulated')->sole();
        $this->assertSame('druhy@divadlo.test', $mail->recipient);
        $this->assertSame('Zmena vlastníka kanála Divadlo', $mail->message);
    }

    #[Test]
    public function non_owner_changes_are_logged_without_any_mail(): void
    {
        $editor = $this->user();
        SystemLog::query()->delete();

        app(CanalMembership::class)->attach($this->canal, $editor, CanalRole::Editor);
        app(CanalMembership::class)->changeRole($this->canal, $editor, CanalRole::Checkin);
        app(CanalMembership::class)->detach($this->canal, $editor);

        $this->assertSame(
            ['canals.member_added', 'canals.role_changed', 'canals.member_removed'],
            SystemLog::orderBy('id')->pluck('event')->all(),
        );
    }

    #[Test]
    public function turning_simulation_off_sends_the_real_notification(): void
    {
        config(['canals.simulate_ownership_mail' => false]);
        $second = $this->user();

        app(CanalMembership::class)->attach($this->canal, $second, CanalRole::Owner);

        Notification::assertSentTo($second, CanalOwnershipChanged::class, fn ($n) => $n->change === CanalOwnershipChanged::OWNER_ADDED);
        Notification::assertSentTo($this->owner, CanalOwnershipChanged::class);
        $this->assertFalse(SystemLog::where('event', 'mail.simulated')->exists());
    }

    private function canalLog(Canal $canal)
    {
        return SystemLog::query()
            ->where(fn ($q) => $q->where('subject_type', $canal->getMorphClass())->where('subject_id', $canal->id))
            ->orderBy('id')
            ->get();
    }

    /** Pevné časy prihlásenia — factory ich inak losuje a môže trafiť hodinu, ktorú preskočil prechod na letný čas. */
    private function user(array $attributes = []): User
    {
        return User::factory()->create($attributes + ['last_login_at' => now(), 'last_activity' => now()]);
    }
}
