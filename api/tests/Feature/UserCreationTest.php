<?php

namespace Tests\Feature;

use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\User;
use App\Notifications\PendingRegistrationVerification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Vytváranie používateľov — cez factory a cez verejnú registráciu.
 *
 * Úprava používateľa cez dashboard je pokrytá v
 * tests/Feature/Users/DashboardUserUpdateTest.php, sociálne registrácie
 * v tests/Feature/Auth/{GoogleAuthTest,FacebookAuthTest}.php.
 */
class UserCreationTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;

    protected Canal $canal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->canal = Canal::factory()->create();
    }

    #[Test]
    public function a_user_can_be_created_with_specified_attributes_via_factory()
    {
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'email' => $this->user->email,
            'canal_id' => $this->user->canal_id,
            'status' => $this->user->status,
        ]);
    }

    #[Test]
    public function a_user_can_be_created_with_specific_status()
    {
        $user = User::factory()->create(['status' => ModelStatus::Published]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'status' => ModelStatus::Published->value,
        ]);
    }

    #[Test]
    public function a_user_can_be_created_with_unverified_email()
    {
        $user = User::factory()->unverified()->create();

        $this->assertDatabaseHas('users', [
            'email' => $user->email,
            'email_verified_at' => null,
        ]);
        $this->assertNull($user->email_verified_at);
    }

    #[Test]
    public function local_registration_creates_a_pending_registration_and_sends_verification()
    {
        Notification::fake();

        $response = $this->postJson('/api/register', [
            'email' => 'novy@example.sk',
            'password' => 'Password123!',
            'display_name' => 'Nový Používateľ',
            'registered_via' => 'local',
        ]);

        $response->assertStatus(201);

        // Lokálna registrácia zámerne ešte nezakladá používateľa — ten vznikne
        // až po overení e-mailu cez /api/register/verify.
        $this->assertDatabaseMissing('users', ['email' => 'novy@example.sk']);
        $this->assertDatabaseHas('pending_registrations', [
            'email' => 'novy@example.sk',
            'display_name' => 'Nový Používateľ',
            'registered_via' => 'local',
        ]);

        Notification::assertSentOnDemand(PendingRegistrationVerification::class);
    }

    #[Test]
    public function local_registration_requires_email_password_and_display_name()
    {
        $this->postJson('/api/register', ['registered_via' => 'local'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password', 'display_name']);
    }
}
