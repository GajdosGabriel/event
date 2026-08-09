<?php

namespace Tests\Feature\Contacts;

use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\ContactEmailVerification;
use App\Models\Event;
use App\Models\User;
use App\Notifications\ContactEmailVerificationRequest;
use App\Services\Contacts\ContactEmailVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Overovanie kontaktných e-mailov naprieč modelmi.
 *
 * Kanál tu zastupuje všetky typy — proces je pre miesto, podujatie aj firmu
 * ten istý kód (HasVerifiableEmail + ContactEmailVerifier), líši sa len model.
 */
class ContactEmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->admin->assignRole('super-admin');
    }

    private function canal(array $attributes = []): Canal
    {
        return Canal::factory()->create(array_merge([
            'status' => ModelStatus::Published->value,
            'municipality_id' => 1,
        ], $attributes));
    }

    /** Platný payload formulára kanála — validácia pýta meno aj obec. */
    private function formPayload(Canal $canal, array $overrides = []): array
    {
        return array_merge([
            'name' => $canal->name,
            'municipality_id' => $canal->municipality_id,
        ], $overrides);
    }

    #[Test]
    public function saving_a_new_address_from_the_form_sends_a_verification_request(): void
    {
        Notification::fake();

        $canal = $this->canal(['email' => null, 'email_verified_at' => null]);

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/admin/canals/{$canal->id}", $this->formPayload($canal, [
                'email' => 'kontakt@divadlo.sk',
            ]))
            ->assertOk()
            ->assertJsonPath('email_verification.verified', false);

        Notification::assertSentOnDemand(
            ContactEmailVerificationRequest::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'kontakt@divadlo.sk',
        );

        $this->assertDatabaseHas('contact_email_verifications', [
            'verifiable_type' => Canal::class,
            'verifiable_id' => $canal->id,
            'email' => 'kontakt@divadlo.sk',
        ]);
    }

    #[Test]
    public function changing_a_verified_address_drops_the_verification(): void
    {
        Notification::fake();

        $canal = $this->canal(['email' => 'stary@divadlo.sk', 'email_verified_at' => now()]);

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/admin/canals/{$canal->id}", $this->formPayload($canal, [
                'email' => 'novy@divadlo.sk',
            ]))
            ->assertOk();

        $this->assertNull($canal->fresh()->email_verified_at);
    }

    #[Test]
    public function the_link_from_the_email_verifies_the_address(): void
    {
        $canal = $this->canal(['email' => 'kontakt@divadlo.sk', 'email_verified_at' => null]);
        $token = $this->issueToken($canal);

        $this->postJson('/api/contact-email/verify', ['token' => $token])
            ->assertOk()
            ->assertJsonPath('data.type', 'canal')
            ->assertJsonPath('data.email', 'kontakt@divadlo.sk');

        $this->assertNotNull($canal->fresh()->email_verified_at);
        // Uplatnený odkaz sa nesmie dať použiť druhýkrát.
        $this->assertDatabaseCount('contact_email_verifications', 0);
    }

    #[Test]
    public function a_link_issued_for_a_replaced_address_does_not_verify_the_new_one(): void
    {
        Notification::fake();

        $canal = $this->canal(['email' => 'stary@divadlo.sk', 'email_verified_at' => null]);
        $token = $this->issueToken($canal);

        // Adresa sa medzitým vo formulári zmenila.
        $canal->forceFill(['email' => 'novy@divadlo.sk'])->save();

        $this->postJson('/api/contact-email/verify', ['token' => $token])
            ->assertStatus(404);

        $this->assertNull($canal->fresh()->email_verified_at);
    }

    #[Test]
    public function an_expired_link_does_not_verify_the_address(): void
    {
        $canal = $this->canal(['email' => 'kontakt@divadlo.sk', 'email_verified_at' => null]);
        $token = $this->issueToken($canal);

        ContactEmailVerification::query()->update(['expires_at' => now()->subDay()]);

        $this->postJson('/api/contact-email/verify', ['token' => $token])
            ->assertStatus(404);

        $this->assertNull($canal->fresh()->email_verified_at);
        $this->assertDatabaseCount('contact_email_verifications', 0);
    }

    #[Test]
    public function an_imported_address_is_never_mailed_but_counts_as_unverified(): void
    {
        Notification::fake();

        // Import píše priamo cez model, nie cez formulár — adresu z cudzej
        // stránky nesmieme obťažovať overovacím e-mailom.
        $event = Event::factory()->create(['email' => 'najdene@web.sk']);

        Notification::assertNothingSent();
        $this->assertNull($event->fresh()->email_verified_at);
        $this->assertDatabaseCount('contact_email_verifications', 0);
    }

    #[Test]
    public function resending_requires_the_right_to_edit_the_model(): void
    {
        Notification::fake();

        $canal = $this->canal(['email' => 'kontakt@divadlo.sk', 'email_verified_at' => null]);
        $stranger = User::factory()->create();

        $this->actingAs($stranger, 'sanctum')
            ->postJson('/api/contact-email/resend', ['type' => 'canal', 'id' => $canal->id])
            ->assertStatus(403);

        Notification::assertNothingSent();
    }

    #[Test]
    public function resending_is_refused_while_the_cooldown_runs(): void
    {
        Notification::fake();

        $canal = $this->canal(['email' => 'kontakt@divadlo.sk', 'email_verified_at' => null]);
        $this->issueToken($canal);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/contact-email/resend', ['type' => 'canal', 'id' => $canal->id])
            ->assertStatus(429)
            ->assertJsonPath('code', 'too_soon');

        Notification::assertNothingSent();
    }

    #[Test]
    public function resending_after_the_cooldown_issues_a_fresh_link(): void
    {
        Notification::fake();

        $canal = $this->canal(['email' => 'kontakt@divadlo.sk', 'email_verified_at' => null]);
        $this->issueToken($canal);

        ContactEmailVerification::query()->update(['sent_at' => now()->subHour()]);
        $oldToken = ContactEmailVerification::query()->value('token');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/contact-email/resend', ['type' => 'canal', 'id' => $canal->id])
            ->assertStatus(202);

        Notification::assertSentOnDemand(ContactEmailVerificationRequest::class);
        // Starý odkaz sa zahodil — platný smie byť vždy len ten posledný.
        $this->assertNotSame($oldToken, ContactEmailVerification::query()->value('token'));
        $this->assertDatabaseCount('contact_email_verifications', 1);
    }

    #[Test]
    public function resending_is_refused_for_an_already_verified_address(): void
    {
        Notification::fake();

        $canal = $this->canal(['email' => 'kontakt@divadlo.sk', 'email_verified_at' => now()]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/contact-email/resend', ['type' => 'canal', 'id' => $canal->id])
            ->assertStatus(409)
            ->assertJsonPath('code', 'already_verified');

        Notification::assertNothingSent();
    }

    #[Test]
    public function the_detail_tells_the_owner_that_the_address_is_waiting_for_confirmation(): void
    {
        $canal = $this->canal(['email' => 'kontakt@divadlo.sk', 'email_verified_at' => null]);
        $this->issueToken($canal);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/admin/canals/{$canal->id}")
            ->assertOk()
            ->assertJsonPath('email_verification.verified', false)
            ->assertJsonPath('email_verification.pending', true)
            ->assertJsonPath('email_verification.can_resend', false);
    }

    /**
     * Rozpracované overenie so známym odkazom. Zapisuje sa priamo, aby test
     * odosielania (to má vlastné testy) nešpinil záznamy notifikácií.
     */
    private function issueToken(Canal $canal, string $raw = 'raw-verification-token'): string
    {
        $canal->emailVerifications()->create([
            'email' => $canal->email,
            'token' => hash('sha256', $raw),
            'sent_at' => now(),
            'expires_at' => now()->addHours(72),
        ]);

        return $raw;
    }
}
