<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;


class LoginTest extends TestCase
{
    use DatabaseTransactions;
    protected $user;
    /**
     * Test that the login form can be viewed.
     */
    public function test_can_view_login_form(): void
    {
        $response = $this->get('/api/login-form');

        $response->assertSuccessful();
    }

    public function test_user_can_login_with_valid_credentials()
    {
        $user = User::factory()->create([
            'password' => bcrypt($plainPassword = 'i-love-laravel'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => $plainPassword,
        ]);

        $response->assertStatus(200);
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_cannot_login_with_invalid_password()
    {
        $user = User::factory()->create([
            'password' => bcrypt('password'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'invalid-password',
        ]);

        $response->assertUnauthorized();
        $this->assertGuest();
    }


    //  public function test_user_receives_an_email_with_a_password_reset_link()
    // {
    //     Notification::fake();

    //     $user = User::factory()->create();

    //     $response = $this->post('/password/email', [
    //         'email' => $user->email,
    //     ]);

    //     // assertions go here
    // }

    public function test_user_can_create_token()
    {
        $user = User::factory()->create();

        $token = $user->createToken('MyToken');

        // Token je objekt s prístupom k databázovému modelu tokenu
        $this->assertNotNull($token->accessToken->id);

        // V databáze sa token nachádza
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $token->accessToken->id,
            'tokenable_id' => $user->id,
        ]);
    }

    // public function testLogoutAnAuthenticatedUser()
    // {
    //     $user = User::factory()->create();
    //     $token = $user->createToken('TestToken')->plainTextToken;

    //     // V extrakcii z tokenu dostaneme len token bez názvu a časti "token|..."
    //     $rawToken = explode('|', $token)[1];

    //     // Odhlásenie – pošli token ako Bearer token
    //     $response = $this->withHeader('Authorization', 'Bearer ' . $rawToken)
    //         ->postJson('/api/logout');

    //     $response->assertOk();

    //     // Skontroluj, že token bol zmazaný
    //     $this->assertDatabaseMissing('personal_access_tokens', [
    //         'token' => hash('sha256', $rawToken),
    //     ]);
    // }
}
