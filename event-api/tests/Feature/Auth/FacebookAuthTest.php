<?php

namespace Tests\Feature\Auth;

use App\Models\PendingRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FacebookAuthTest extends TestCase
{
    use DatabaseTransactions;

    public function test_facebook_login_creates_new_user_and_returns_token(): void
    {
        Config::set('services.facebook.app_id', 'fb-app-id');
        Config::set('services.facebook.app_secret', 'fb-app-secret');

        Http::fake([
            'https://graph.facebook.com/debug_token*' => Http::response([
                'data' => [
                    'is_valid' => true,
                    'app_id' => 'fb-app-id',
                ],
            ], 200),
            'https://graph.facebook.com/me*' => Http::response([
                'id' => 'facebook-user-123',
                'email' => 'new-facebook-user@example.test',
                'name' => 'Facebook Tester',
            ], 200),
        ]);

        $response = $this->postJson('/api/login/facebook', [
            'access_token' => 'facebook-access-token',
        ]);

        $response->assertOk();
        $response->assertJsonPath('token_type', 'Bearer');
        $response->assertJsonPath('is_new_user', true);
        $response->assertJsonPath('user.registered_via', 'facebook');

        $this->assertDatabaseHas('users', [
            'email' => 'new-facebook-user@example.test',
            'registered_via' => 'facebook',
            'provider_id' => 'facebook:facebook-user-123',
        ]);
    }

    public function test_facebook_login_reuses_existing_user_and_updates_provider_data(): void
    {
        Config::set('services.facebook.app_id', 'fb-app-id');
        Config::set('services.facebook.app_secret', 'fb-app-secret');

        $user = User::factory()->create([
            'email' => 'existing-fb-user@example.test',
            'password' => Hash::make('password1234'),
            'registered_via' => 'local',
            'provider_id' => null,
            'email_verified_at' => null,
            'last_login_at' => null,
        ]);

        Http::fake([
            'https://graph.facebook.com/debug_token*' => Http::response([
                'data' => [
                    'is_valid' => true,
                    'app_id' => 'fb-app-id',
                ],
            ], 200),
            'https://graph.facebook.com/me*' => Http::response([
                'id' => 'facebook-user-xyz',
                'email' => 'existing-fb-user@example.test',
                'name' => 'Existing FB User',
            ], 200),
        ]);

        $response = $this->postJson('/api/register/facebook', [
            'access_token' => 'facebook-access-token',
        ]);

        $response->assertOk();
        $response->assertJsonPath('is_new_user', false);

        $user->refresh();

        $this->assertSame('facebook:facebook-user-xyz', $user->provider_id);
        $this->assertSame('facebook', $user->registered_via);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($user->last_login_at);
    }

    public function test_facebook_login_bypasses_pending_local_registration_for_verified_social_account(): void
    {
        Config::set('services.facebook.app_id', 'fb-app-id');
        Config::set('services.facebook.app_secret', 'fb-app-secret');

        PendingRegistration::create([
            'email' => 'pending-fb-user@example.test',
            'password' => Hash::make('password1234'),
            'display_name' => 'Pending FB User',
            'registered_via' => 'local',
            'verification_token' => hash('sha256', 'pending-fb-token'),
            'expires_at' => now()->addDay(),
        ]);

        Http::fake([
            'https://graph.facebook.com/debug_token*' => Http::response([
                'data' => [
                    'is_valid' => true,
                    'app_id' => 'fb-app-id',
                ],
            ], 200),
            'https://graph.facebook.com/me*' => Http::response([
                'id' => 'facebook-user-777',
                'email' => 'pending-fb-user@example.test',
                'name' => 'Pending FB User',
            ], 200),
        ]);

        $response = $this->postJson('/api/login/facebook', [
            'access_token' => 'facebook-access-token',
        ]);

        $response->assertOk();
        $response->assertJsonPath('is_new_user', true);

        $this->assertDatabaseHas('users', [
            'email' => 'pending-fb-user@example.test',
            'registered_via' => 'facebook',
            'provider_id' => 'facebook:facebook-user-777',
        ]);

        $this->assertDatabaseMissing('pending_registrations', [
            'email' => 'pending-fb-user@example.test',
        ]);
    }
}
