<?php

namespace Tests\Unit\Users;

use App\Models\User;
use App\Repositories\Contracts\UserRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use LogicException;
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

        // canal_id sa musí prepísať až po vytvorení: UserObserver po overenom
        // e-maile založí osobný kanál cez PersonalCanalProvisioner a canal_id
        // si pri tom nastaví sám, takže hodnota podaná factory zanikne.
        $visibleUser = User::factory()->create();
        $visibleUser->update(['canal_id' => $ownedCanalId]);

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

    /**
     * Používateľ nie je verejný obsah — zoznam nesmie existovať. Metódu drží
     * len InterfaceRepository, takže je zámerne nepoužiteľná; tento test drží
     * ten zámer, aby ju niekto omylom nedoplnil späť.
     */
    public function test_public_index_query_is_not_available(): void
    {
        $this->expectException(LogicException::class);

        $this->userRepository->publicIndexQuery();
    }

    public function test_public_show_is_not_available(): void
    {
        $this->expectException(LogicException::class);

        $this->userRepository->publicShow($this->authUser->id);
    }
}
