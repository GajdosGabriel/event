<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class LoginThrottleTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // TestCase throttling globálne vypína — tu ho práve testujeme.
        $this->withMiddleware(ThrottleRequests::class);
        RateLimiter::clear('auth');
    }

    public function test_repeated_failed_logins_are_rate_limited(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('password'),
        ]);

        // Limiter je 5 pokusov za minútu.
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'invalid-password',
            ])->assertUnauthorized();
        }

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'invalid-password',
        ]);

        $response->assertStatus(429);
        $response->assertJsonStructure(['message']);
        $this->assertGuest();
    }

    public function test_throttle_does_not_block_a_different_account(): void
    {
        $victim = User::factory()->create(['password' => bcrypt('password')]);
        $other = User::factory()->create(['password' => bcrypt($plain = 'i-love-laravel')]);

        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/login', [
                'email' => $victim->email,
                'password' => 'invalid-password',
            ]);
        }

        // Kľúč limitera obsahuje e-mail, takže útok na jeden účet nezablokuje iný.
        $this->postJson('/api/login', [
            'email' => $other->email,
            'password' => $plain,
        ])->assertStatus(200);
    }
}
