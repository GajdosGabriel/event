<?php

namespace Tests\Feature\Auth;

use App\Models\PendingRegistration;
use App\Notifications\PendingRegistrationVerification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Ochrana formulárov, ktoré posielajú e-mail na zadanú adresu, pred botmi:
 * honeypot, Cloudflare Turnstile a kontrola domény e-mailu v DNS.
 */
class RegistrationBotGuardTest extends TestCase
{
    use DatabaseTransactions;

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'display_name' => 'Človek',
            'registered_via' => 'local',
            'terms_accepted' => true,
            'email' => 'clovek@example.test',
            'password' => 'Heslo-2026-dlhe',
            'password_confirmation' => 'Heslo-2026-dlhe',
        ];
    }

    #[Test]
    public function honeypot_fakes_success_without_sending_anything(): void
    {
        Notification::fake();

        $this->postJson('/api/register', $this->payload(['website' => 'http://spam.example']))
            ->assertStatus(202);

        $this->assertDatabaseMissing('pending_registrations', ['email' => 'clovek@example.test']);
        Notification::assertNothingSent();
    }

    #[Test]
    public function without_secret_key_turnstile_is_not_required(): void
    {
        Notification::fake();
        Http::fake();

        $this->postJson('/api/register', $this->payload())->assertCreated();

        Http::assertNothingSent();
        Notification::assertSentOnDemand(PendingRegistrationVerification::class);
    }

    #[Test]
    public function missing_turnstile_token_is_rejected_when_enabled(): void
    {
        Config::set('services.turnstile.secret_key', 'secret');
        Notification::fake();
        Http::fake();

        $this->postJson('/api/register', $this->payload())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['turnstile_token']);

        Http::assertNothingSent();
        $this->assertDatabaseMissing('pending_registrations', ['email' => 'clovek@example.test']);
    }

    #[Test]
    public function token_rejected_by_cloudflare_blocks_registration(): void
    {
        Config::set('services.turnstile.secret_key', 'secret');
        Notification::fake();
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']])]);

        $this->postJson('/api/register', $this->payload(['turnstile_token' => 'zly']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['turnstile_token']);

        Notification::assertNothingSent();
    }

    #[Test]
    public function valid_token_lets_registration_through(): void
    {
        Config::set('services.turnstile.secret_key', 'secret');
        Notification::fake();
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

        $this->postJson('/api/register', $this->payload(['turnstile_token' => 'dobry']))->assertCreated();

        Http::assertSent(fn (HttpRequest $r) => $r['secret'] === 'secret' && $r['response'] === 'dobry');
        $this->assertTrue(PendingRegistration::where('email', 'clovek@example.test')->exists());
    }

    #[Test]
    public function cloudflare_outage_does_not_block_people(): void
    {
        Config::set('services.turnstile.secret_key', 'secret');
        Notification::fake();
        Http::fake(['challenges.cloudflare.com/*' => Http::response('', 503)]);

        $this->postJson('/api/register', $this->payload(['turnstile_token' => 'dobry']))->assertCreated();
    }

    #[Test]
    public function password_reset_and_resend_are_guarded_too(): void
    {
        Config::set('services.turnstile.secret_key', 'secret');
        Http::fake();

        $this->postJson('/api/password/forgot', ['email' => 'clovek@example.test'])
            ->assertStatus(422)->assertJsonValidationErrors(['turnstile_token']);
        $this->postJson('/api/register/resend', ['email' => 'clovek@example.test'])
            ->assertStatus(422)->assertJsonValidationErrors(['turnstile_token']);
    }

    #[Test]
    public function email_with_nonexistent_domain_is_rejected(): void
    {
        Config::set('registration.check_email_dns', true);
        Notification::fake();

        // TLD .invalid je rezervovaný (RFC 2606) — v DNS nikdy neexistuje.
        $this->postJson('/api/register', $this->payload(['email' => 'nikto@neexistuje.invalid']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        Notification::assertNothingSent();
    }
}
