<?php

namespace Tests\Feature\Auth;

use App\Models\PendingRegistration;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PendingAuthenticationTest extends TestCase
{
    use DatabaseTransactions;

    private function registerPending(array $overrides = []): PendingRegistration
    {
        Notification::fake();
        $this->postJson('/api/register', $overrides + [
            'display_name' => 'Original', 'registered_via' => 'local', 'terms_accepted' => true,
            'email' => 'pending-flow@example.test',
            'password' => 'Original-pass-2026', 'password_confirmation' => 'Original-pass-2026',
        ])->assertCreated();

        return PendingRegistration::where('email', 'pending-flow@example.test')->firstOrFail();
    }

    public function test_repeat_registration_preserves_original_credentials_and_link(): void
    {
        $pending = $this->registerPending();
        $token = $pending->verification_token;
        $this->registerPending(['password' => 'Different-pass-2026', 'password_confirmation' => 'Different-pass-2026']);
        $pending->refresh();
        $this->assertTrue(Hash::check('Original-pass-2026', $pending->password));
        $this->assertSame($token, $pending->verification_token);
        Notification::assertNothingSent();
    }

    public function test_pending_state_requires_the_correct_password_and_never_issues_a_token(): void
    {
        $pending = $this->registerPending();
        Notification::fake();
        $this->postJson('/api/login', ['email' => $pending->email, 'password' => 'Wrong-pass-2026'])
            ->assertStatus(401)->assertJsonMissingPath('code');
        $this->postJson('/api/login', ['email' => strtoupper($pending->email), 'password' => 'Original-pass-2026'])
            ->assertStatus(409)->assertJsonPath('code', 'email_not_verified')
            ->assertJsonMissingPath('token')->assertJsonMissingPath('access_token');
        Notification::assertNothingSent();
        $this->assertGuest();
    }

    public function test_expired_login_renews_verification_without_authentication(): void
    {
        $pending = $this->registerPending();
        $token = $pending->verification_token;
        $this->travel(8)->days();
        Notification::fake();
        $this->postJson('/api/login', ['email' => $pending->email, 'password' => 'Original-pass-2026'])
            ->assertStatus(409)->assertJsonPath('code', 'email_not_verified');
        $this->assertNotSame($token, $pending->fresh()->verification_token);
        Notification::assertCount(1);
        $this->assertGuest();
    }

    public function test_resend_has_cooldown_and_a_per_address_limit(): void
    {
        $pending = $this->registerPending();
        Notification::fake();
        $this->postJson('/api/register/resend', ['email' => $pending->email])->assertOk();
        Notification::assertNothingSent();
        for ($i = 0; $i < 6; $i++) {
            $this->travel(121)->seconds();
            $this->postJson('/api/register/resend', ['email' => $pending->email])->assertOk();
        }
        Notification::assertCount(4);
        $known = $this->postJson('/api/register/resend', ['email' => $pending->email])->json();
        $unknown = $this->postJson('/api/register/resend', ['email' => 'unknown@example.test'])->json();
        $this->assertSame($known, $unknown);
    }
}
