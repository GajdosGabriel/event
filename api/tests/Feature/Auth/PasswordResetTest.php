<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\PasswordResetLink;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use DatabaseTransactions;

    public function test_forgot_sends_reset_link_to_known_address(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $response = $this->postJson('/api/password/forgot', ['email' => $user->email]);

        $response->assertOk();
        Notification::assertSentTo($user, PasswordResetLink::class);
    }

    /**
     * Jadro celého endpointu: odpoveď sa nesmie líšiť podľa toho, či adresa
     * v portáli existuje. Inak je formulár nástroj na overovanie, kto tu má
     * účet — a to je presne to, čo chce útočník zistiť pred phishingom.
     */
    public function test_forgot_answers_the_same_for_unknown_address(): void
    {
        Notification::fake();

        $known = User::factory()->create();

        $knownResponse = $this->postJson('/api/password/forgot', ['email' => $known->email]);
        $unknownResponse = $this->postJson('/api/password/forgot', ['email' => 'nikto-tu-nie-je@example.test']);

        $unknownResponse->assertStatus($knownResponse->status());
        $this->assertSame($knownResponse->json('message'), $unknownResponse->json('message'));

        Notification::assertCount(1);
    }

    public function test_reset_sets_new_password_and_revokes_tokens(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('stare-heslo-123'),
        ]);

        // Token z aktívnej relácie: po zmene hesla nesmie prežiť, inak by
        // zmena hesla nevyhodila toho, kto sa dostal k starému.
        $user->createToken('auth_token');
        $token = Password::createToken($user);

        $response = $this->postJson('/api/password/reset', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'nove-heslo-123',
            'password_confirmation' => 'nove-heslo-123',
        ]);

        $response->assertOk();

        $user->refresh();
        $this->assertTrue(Hash::check('nove-heslo-123', $user->password));
        $this->assertSame(0, $user->tokens()->count());

        // A nové heslo naozaj funguje na prihlásenie.
        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'nove-heslo-123',
        ])->assertOk();
    }

    public function test_reset_rejects_invalid_token(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('stare-heslo-123'),
        ]);

        $response = $this->postJson('/api/password/reset', [
            'token' => 'toto-nie-je-platny-token',
            'email' => $user->email,
            'password' => 'nove-heslo-123',
            'password_confirmation' => 'nove-heslo-123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('token');

        $user->refresh();
        $this->assertTrue(Hash::check('stare-heslo-123', $user->password));
    }

    /** Token je jednorazový — druhé použitie toho istého odkazu už neprejde. */
    public function test_reset_token_cannot_be_used_twice(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('stare-heslo-123'),
        ]);

        $token = Password::createToken($user);

        $payload = [
            'token' => $token,
            'email' => $user->email,
            'password' => 'nove-heslo-123',
            'password_confirmation' => 'nove-heslo-123',
        ];

        $this->postJson('/api/password/reset', $payload)->assertOk();
        $this->postJson('/api/password/reset', $payload)->assertStatus(422);
    }

    public function test_reset_requires_password_confirmation_and_minimum_length(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->postJson('/api/password/reset', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'nove-heslo-123',
            'password_confirmation' => 'nieco-uplne-ine',
        ])->assertJsonValidationErrors('password');

        $this->postJson('/api/password/reset', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'kratke',
            'password_confirmation' => 'kratke',
        ])->assertJsonValidationErrors('password');
    }

    /** Odkaz v e-maile musí viesť na stránku SPA, nie na routu API. */
    public function test_reset_link_points_to_the_frontend_page(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->postJson('/api/password/forgot', ['email' => $user->email])->assertOk();

        Notification::assertSentTo($user, PasswordResetLink::class, function (PasswordResetLink $notification) use ($user) {
            $url = $notification->toMail($user)->actionUrl;

            return str_starts_with($url, rtrim((string) config('app.frontend_url'), '/').'/obnova-hesla/')
                && str_contains($url, 'email='.rawurlencode($user->email));
        });
    }
}
