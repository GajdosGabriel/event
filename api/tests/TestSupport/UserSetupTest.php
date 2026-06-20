<?php

namespace Tests\TestSupport;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;


abstract class UserSetupTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $userSuperAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        // superAdmin user
        $this->userSuperAdmin = User::factory()->create();
        $this->userSuperAdmin->assignRole('super-admin');

        // Common user
        $this->user = User::factory()->create();
        $this->user->assignRole('canal-editor');
        $this->user->givePermissionTo([
            'canal.view',
            'canal.update',
            'event.view',
            'venue.view',
        ]);
        $this->actingAs($this->user, 'sanctum');
    }
}
