<?php

namespace Tests\Unit\Users;

use App\Enums\ModelStatus;
use App\Models\User;
use App\Repositories\Contracts\UserRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class UserRepositoryQueriesTest extends TestCase
{
    use DatabaseTransactions;

    private UserRepository $userRepository;
    private User $authUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userRepository = app(UserRepository::class);
        $this->authUser = User::factory()->create();
        $this->actingAs($this->authUser, 'sanctum');
    }

    public function test_dashboard_index_query_returns_only_accessible_users(): void
    {
        $ownedCanalId = $this->authUser->canals()->value('canals.id');

        $visibleUser = User::factory()->create([
            'canal_id' => $ownedCanalId,
        ]);

        $hiddenUser = User::factory()->create();

        $results = $this->userRepository->dashboardIndexQuery()->get();

        $this->assertTrue($results->contains('id', $this->authUser->id));
        $this->assertTrue($results->contains('id', $visibleUser->id));
        $this->assertFalse($results->contains('id', $hiddenUser->id));
    }

    public function test_admin_index_query_includes_soft_deleted_users(): void
    {
        $activeUser = User::factory()->create();
        $deletedUser = User::factory()->create();
        $deletedUser->delete();

        $results = $this->userRepository->adminIndexQuery()->get();

        $this->assertTrue($results->contains('id', $activeUser->id));
        $this->assertTrue($results->contains('id', $deletedUser->id));
    }

    public function test_public_index_query_returns_only_verified_published_users(): void
    {
        $publicUser = User::factory()->create([
            'status' => ModelStatus::Published->value,
            'email_verified_at' => now(),
        ]);

        $draftUser = User::factory()->create([
            'status' => ModelStatus::Draft->value,
            'email_verified_at' => now(),
        ]);

        $unverifiedUser = User::factory()->create([
            'status' => ModelStatus::Published->value,
            'email_verified_at' => null,
        ]);

        $results = $this->userRepository->publicIndexQuery()->get();

        $this->assertTrue($results->contains('id', $publicUser->id));
        $this->assertFalse($results->contains('id', $draftUser->id));
        $this->assertFalse($results->contains('id', $unverifiedUser->id));
    }
}
