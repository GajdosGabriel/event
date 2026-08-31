<?php

namespace Tests\Feature\Auth;

use App\Models\PendingRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use DatabaseTransactions;

    public function test_google_login_creates_new_user_and_returns_token(): void
    {
        Config::set('services.google.client_id', 'google-client-id');

        Http::fake([
            'https://oauth2.googleapis.com/tokeninfo*' => Http::response([
                'aud' => 'google-client-id',
                'email' => 'new-google-user@example.test',
                'sub' => 'google-provider-123',
                'email_verified' => true,
                'name' => 'Google Tester',
            ], 200),
        ]);

        $response = $this->postJson('/api/login/google', [
            'id_token' => 'google-id-token',
            'terms_accepted' => true,
        ]);

        $response->assertOk();
        $response->assertJsonPath('token_type', 'Bearer');
        $response->assertJsonPath('is_new_user', true);
        // Odpoveď má tvar UserResource (rovnako ako pri /api/login).
        $response->assertJsonPath('user.email', 'new-google-user@example.test');
        $response->assertJsonStructure(['user' => ['id', 'display_name', 'canal_context']]);

        $this->assertDatabaseHas('users', [
            'email' => 'new-google-user@example.test',
            'registered_via' => 'google',
            'provider_id' => 'google:google-provider-123',
        ]);

        $this->assertNotNull(User::where('email', 'new-google-user@example.test')->firstOrFail()->terms_accepted_at);
    }

    public function test_google_login_refuses_to_create_an_account_without_the_terms_consent(): void
    {
        Config::set('services.google.client_id', 'google-client-id');

        Http::fake([
            'https://oauth2.googleapis.com/tokeninfo*' => Http::response([
                'aud' => 'google-client-id',
                'email' => 'no-consent@example.test',
                'sub' => 'google-provider-999',
                'email_verified' => true,
                'name' => 'No Consent',
            ], 200),
        ]);

        $this->postJson('/api/login/google', ['id_token' => 'google-id-token'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'terms_required');

        $this->assertDatabaseMissing('users', ['email' => 'no-consent@example.test']);
    }

    public function test_google_login_of_an_existing_account_does_not_ask_for_the_terms_again(): void
    {
        Config::set('services.google.client_id', 'google-client-id');

        User::factory()->create([
            'email' => 'known@example.test',
            'registered_via' => 'google',
            'provider_id' => 'google:google-provider-known',
        ]);

        Http::fake([
            'https://oauth2.googleapis.com/tokeninfo*' => Http::response([
                'aud' => 'google-client-id',
                'email' => 'known@example.test',
                'sub' => 'google-provider-known',
                'email_verified' => true,
                'name' => 'Known User',
            ], 200),
        ]);

        $this->postJson('/api/login/google', ['id_token' => 'google-id-token'])
            ->assertOk()
            ->assertJsonPath('is_new_user', false);
    }

    public function test_google_login_reuses_existing_user_and_updates_provider_data(): void
    {
        Config::set('services.google.client_id', 'google-client-id');

        $user = User::factory()->create([
            'email' => 'existing-user@example.test',
            'password' => Hash::make('password1234'),
            'registered_via' => 'local',
            'provider_id' => null,
            'email_verified_at' => null,
            'last_login_at' => null,
        ]);

        Http::fake([
            'https://oauth2.googleapis.com/tokeninfo*' => Http::response([
                'aud' => 'google-client-id',
                'email' => 'existing-user@example.test',
                'sub' => 'google-provider-xyz',
                'email_verified' => true,
                'name' => 'Existing User',
            ], 200),
        ]);

        $response = $this->postJson('/api/register/google', [
            'id_token' => 'google-id-token',
        ]);

        $response->assertOk();
        $response->assertJsonPath('is_new_user', false);

        $user->refresh();

        $this->assertSame('google:google-provider-xyz', $user->provider_id);
        $this->assertSame('google', $user->registered_via);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($user->last_login_at);
    }

    public function test_google_login_bypasses_pending_local_registration_for_verified_social_account(): void
    {
        Config::set('services.google.client_id', 'google-client-id');

        PendingRegistration::create([
            'email' => 'pending-user@example.test',
            'password' => Hash::make('password1234'),
            'display_name' => 'Pending User',
            'registered_via' => 'local',
            'verification_token' => hash('sha256', 'pending-token'),
            'expires_at' => now()->addDay(),
        ]);

        Http::fake([
            'https://oauth2.googleapis.com/tokeninfo*' => Http::response([
                'aud' => 'google-client-id',
                'email' => 'pending-user@example.test',
                'sub' => 'google-provider-777',
                'email_verified' => true,
                'name' => 'Pending User',
            ], 200),
        ]);

        $response = $this->postJson('/api/login/google', [
            'id_token' => 'google-id-token',
            'terms_accepted' => true,
        ]);

        $response->assertOk();
        $response->assertJsonPath('is_new_user', true);

        $this->assertDatabaseHas('users', [
            'email' => 'pending-user@example.test',
            'registered_via' => 'google',
            'provider_id' => 'google:google-provider-777',
        ]);

        $this->assertDatabaseMissing('pending_registrations', [
            'email' => 'pending-user@example.test',
        ]);
    }
}
