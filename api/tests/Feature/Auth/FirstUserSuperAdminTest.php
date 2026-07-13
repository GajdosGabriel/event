<?php

namespace Tests\Feature\Auth;

use App\Models\PendingRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FirstUserSuperAdminTest extends TestCase
{
    use RefreshDatabase;

    private function verifyPendingUser(string $email, string $rawToken): void
    {
        PendingRegistration::create([
            'email' => $email,
            'password' => Hash::make('Password123!'),
            'display_name' => 'Test',
            'registered_via' => 'local',
            'verification_token' => hash('sha256', $rawToken),
            'expires_at' => now()->addHours(48),
        ]);

        $this->postJson('/api/register/verify', ['token' => $rawToken])
            ->assertOk();
    }

    #[Test]
    public function first_verified_user_becomes_super_admin(): void
    {
        $this->verifyPendingUser('first@example.com', 'token-first');

        $user = User::where('email', 'first@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole('super-admin'));
    }

    #[Test]
    public function subsequent_verified_users_do_not_become_super_admin(): void
    {
        $this->verifyPendingUser('first@example.com', 'token-first');
        $this->verifyPendingUser('second@example.com', 'token-second');

        $first = User::where('email', 'first@example.com')->firstOrFail();
        $second = User::where('email', 'second@example.com')->firstOrFail();

        $this->assertTrue($first->hasRole('super-admin'));
        $this->assertFalse($second->hasRole('super-admin'));
    }
}
