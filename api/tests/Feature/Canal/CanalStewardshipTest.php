<?php

namespace Tests\Feature\Canal;

use App\Enums\CanalRole;
use App\Enums\ModelStatus;
use App\Enums\RegistrationSource;
use App\Models\Canal;
use App\Models\CanalInvitation;
use App\Models\Event;
use App\Models\SystemLog;
use App\Models\User;
use App\Notifications\EventSignupOrganizerNotice;
use App\Services\Canals\CanalInviter;
use App\Services\Canals\CanalMembership;
use App\Services\Imports\ImportedCanalManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Prevzatie importovaného kanála organizátorom (CanalStewardship): pozvánka
 * vlastníka prenesie správu, technický vlastník z importu odíde a import ani
 * notifikácie kanál odvtedy nepovažujú za nespravovaný.
 */
class CanalStewardshipTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Canal $canal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->user();
        $this->admin->assignRole('super-admin');

        $this->canal = Canal::factory()->create([
            'name' => 'Farnosť Hájske',
            'registration_source' => RegistrationSource::IMPORT->value,
            'email' => 'farnost@example.sk',
            'status' => ModelStatus::Published->value,
            'published_at' => now(),
        ]);
        app(CanalMembership::class)->attach($this->canal, $this->admin, CanalRole::Owner);
    }

    #[Test]
    public function accepting_the_owner_invitation_claims_an_imported_canal(): void
    {
        $invitation = app(CanalInviter::class)->ensureOwnerInvitation($this->canal, 'farnost@example.sk');
        $farar = $this->user(['email' => 'farnost@example.sk']);

        $this->assertNull($this->canal->messageRecipient());

        $this->actingAs($farar, 'sanctum')
            ->postJson('/api/invitations/'.$invitation->token.'/accept')
            ->assertOk();

        $canal = $this->canal->fresh();
        $this->assertTrue($canal->isClaimed());
        $this->assertTrue($canal->isManaged());
        $this->assertSame($farar->id, $canal->claimed_by_user_id);
        $this->assertSame(RegistrationSource::IMPORT, $canal->registration_source);
        $this->assertSame([$farar->id], $canal->owners()->pluck('users.id')->all());
        $this->assertSame($farar->id, $canal->messageRecipient()?->id);
        $this->assertTrue(SystemLog::where('event', 'canals.claimed')->where('subject_id', $canal->id)->exists());
    }

    #[Test]
    public function editor_invitation_does_not_claim_the_canal(): void
    {
        $invitation = $this->canal->invitations()->create([
            'email' => 'editor@example.sk',
            'role' => CanalRole::Editor->value,
            'expires_at' => now()->addDay(),
        ]);
        $editor = $this->user(['email' => 'editor@example.sk']);

        $this->actingAs($editor, 'sanctum')
            ->postJson('/api/invitations/'.$invitation->token.'/accept')
            ->assertOk();

        $canal = $this->canal->fresh();
        $this->assertFalse($canal->isClaimed());
        $this->assertSame([$this->admin->id], $canal->owners()->pluck('users.id')->all());
    }

    #[Test]
    public function owner_invitation_on_a_registered_canal_is_not_a_claim(): void
    {
        $this->canal->forceFill(['registration_source' => RegistrationSource::SELF->value])->save();
        $invitation = $this->canal->invitations()->create([
            'email' => 'druhy@example.sk',
            'role' => CanalRole::Owner->value,
            'expires_at' => now()->addDay(),
        ]);
        $second = $this->user(['email' => 'druhy@example.sk']);

        $this->actingAs($second, 'sanctum')
            ->postJson('/api/invitations/'.$invitation->token.'/accept')
            ->assertOk();

        $canal = $this->canal->fresh();
        $this->assertNull($canal->claimed_at);
        $this->assertEqualsCanonicalizing([$this->admin->id, $second->id], $canal->owners()->pluck('users.id')->all());
    }

    #[Test]
    public function import_leaves_a_claimed_canal_alone(): void
    {
        $this->canal->forceFill(['website' => null])->save();
        $farar = $this->claim();

        $resolved = app(ImportedCanalManager::class)
            ->resolveOrCreate('Farnosť Hájske', 'Farnosť Hájske', 'https://vyveska.sk', 'https://hajske.sk');

        $this->assertSame($this->canal->id, $resolved->id);
        $this->assertNull($resolved->fresh()->website);
        $this->assertSame([$farar->id], $resolved->owners()->pluck('users.id')->all());
    }

    #[Test]
    public function signup_on_a_claimed_canal_notifies_the_owner_even_after_the_contact_changed(): void
    {
        Notification::fake();
        $farar = $this->claim();
        $event = Event::factory()->future()->create([
            'canal_id' => $this->canal->id,
            'status' => ModelStatus::Published->value,
            'published_at' => now()->subDay(),
            'registration_deadline_at' => null,
            'price_amount' => null,
            'email' => 'nova-adresa@example.sk',
        ]);

        $this->actingAs($this->user(), 'sanctum')
            ->postJson("/api/events/{$event->id}/signup")
            ->assertOk();

        Notification::assertSentTo($farar, EventSignupOrganizerNotice::class);
        $this->assertFalse(CanalInvitation::where('email', 'nova-adresa@example.sk')->exists());
    }

    #[Test]
    public function collection_canal_cannot_be_claimed_nor_offered(): void
    {
        Notification::fake();
        $this->canal->forceFill(['name' => 'vyveska.sk'])->save();
        $invitation = app(CanalInviter::class)->ensureOwnerInvitation($this->canal, 'farnost@example.sk');
        $farar = $this->user(['email' => 'farnost@example.sk']);

        $this->actingAs($farar, 'sanctum')
            ->postJson('/api/invitations/'.$invitation->token.'/accept')
            ->assertUnprocessable();

        $this->assertNull($this->canal->fresh()->claimed_at);
        $this->assertSame([$this->admin->id], $this->canal->owners()->pluck('users.id')->all());

        // Prihláška na akciu v zbernom kanáli nesmie ponúknuť jeho prevzatie.
        $invitation->delete();
        $event = Event::factory()->future()->create([
            'canal_id' => $this->canal->id,
            'status' => ModelStatus::Published->value,
            'published_at' => now()->subDay(),
            'registration_deadline_at' => null,
            'price_amount' => null,
            'email' => 'organizator@example.sk',
        ]);
        $this->actingAs($this->user(), 'sanctum')->postJson("/api/events/{$event->id}/signup")->assertOk();

        $this->assertSame(0, CanalInvitation::where('canal_id', $this->canal->id)->count());
        Notification::assertNotSentTo($this->admin, EventSignupOrganizerNotice::class);
    }

    private function claim(): User
    {
        $invitation = app(CanalInviter::class)->ensureOwnerInvitation($this->canal, 'farnost@example.sk');
        $farar = $this->user(['email' => 'farnost@example.sk']);
        app(CanalInviter::class)->accept($invitation, $farar);

        return $farar;
    }

    /** Pevné časy prihlásenia — factory ich inak losuje a môže trafiť hodinu, ktorú preskočil prechod na letný čas. */
    private function user(array $attributes = []): User
    {
        return User::factory()->create($attributes + ['last_login_at' => now(), 'last_activity' => now()]);
    }
}
