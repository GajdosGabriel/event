<?php

namespace Tests\Feature\Users;

use App\Models\Canal;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\UserSetupTest;

/**
 * Celý e-mail cudzieho používateľa vidí len admin; on sám svoj vlastný.
 * Ostatní (členovia spoločného kanála) dostanú len maskovaný tvar.
 */
class UserEmailPrivacyTest extends UserSetupTest
{
    private User $colleague;

    protected function setUp(): void
    {
        parent::setUp();

        $canal = Canal::factory()->create();
        $this->user->canals()->attach($canal->id, ['is_owner' => true]);
        $this->user->forceFill(['canal_id' => $canal->id])->save();

        // canal_id až po založení — overený účet si pri vzniku dostane
        // vlastný osobný kanál (PersonalCanalProvisioner) a ten by ho prepísal.
        $this->colleague = User::factory()->create(['email' => 'kolega.tajny@firma.test']);
        $this->colleague->forceFill(['canal_id' => $canal->id])->save();
    }

    #[Test]
    public function colleague_email_is_masked_in_dashboard_listing_and_detail(): void
    {
        $this->getJson('/api/dashboard/users')
            ->assertOk()
            ->assertDontSee('kolega.tajny@firma.test')
            ->assertJsonFragment(['email_masked' => 'k•••y@firma.test']);

        $this->getJson('/api/dashboard/users/' . $this->colleague->id)
            ->assertOk()
            ->assertDontSee('kolega.tajny@firma.test')
            ->assertJsonMissingPath('email')
            ->assertJsonPath('email_masked', 'k•••y@firma.test');
    }

    #[Test]
    public function user_without_canal_has_no_email_in_display_name(): void
    {
        $this->colleague->canal()->first()->delete();

        $this->getJson('/api/dashboard/users')
            ->assertOk()
            ->assertDontSee('kolega.tajny@firma.test');
    }

    #[Test]
    public function user_sees_own_full_email(): void
    {
        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.email', $this->user->email);
    }

    #[Test]
    public function admin_sees_full_email(): void
    {
        $this->actingAs($this->userSuperAdmin, 'sanctum')
            ->getJson('/api/admin/users/' . $this->colleague->id)
            ->assertOk()
            ->assertJsonPath('email', 'kolega.tajny@firma.test');
    }
}
