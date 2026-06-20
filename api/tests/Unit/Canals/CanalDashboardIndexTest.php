<?php

namespace Tests\Unit\Canals;

use Tests\TestCase; // <-- Dôležité: Použite Laravel TestCase namiesto PHPUnit TestCase
use App\Models\Canal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Repositories\Contracts\CanalRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions; // <-- Používame DatabaseTransactions pre testy, ktoré potrebujú transakcie


class CanalDashboardIndexTest extends TestCase // <-- Zmena základnej triedy
{

    use DatabaseTransactions;

    protected CanalRepository $canalRepository;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp(); // <-- Musí byť prvé

        // Inicializácia repository cez Laravel container
        $this->canalRepository = app(CanalRepository::class);

        $this->user = User::factory()->create([
            'first_name' => 'ja'
        ]);

        $this->actingAs($this->user, 'sanctum');
    }

    public function test_active_dashboardindex_canals()
    {
        // 1. Vytvorte testovacie eventy
        $active = Canal::factory()->active()->make(['name' => 'Aktívny kanál']);
        $inactive = Canal::factory()->inactive()->make();
        $deleted = Canal::factory()->make([
            'deleted_at' => now()
        ]);

        $activeCanal = $this->canalRepository->create($active->toArray());
        $inactiveCanal = $this->canalRepository->create($inactive->toArray());
        $deletedCanal = $this->canalRepository->create($deleted->toArray());

        // dump(Canal::withTrashed()->get());

        // dump(Canal::all()->toArray());

        // 2. Získajte výsledky
        $response = $this->canalRepository->dashboardIndexQuery()->get();

        // 3. Debug výpis
        // dump($response->toArray());

        // dump($response); // <-- Pre debugovanie, odstráňte v produkcii

        // 3. Overte výsledky
        $this->assertTrue(
            $response->contains('id', $activeCanal->id),
            'Aktívny canal sa mal objaviť vo výsledkoch'
        );

        $this->assertTrue(
            $response->contains('id', $deletedCanal->id),
            'Vymazaný canal sa mal objaviť vo výsledkoch'
        );

        $this->assertTrue(
            $response->contains('id', $inactiveCanal->id),
            'Inactive kanál by sa mal objaviť'
        );

        // $this->assertFalse(
        //     $response->contains('id', $inactiveItem->id),
        //     'Kanál nepatriaci userovi by sa nemal objaviť'
        // );

        //  $this->assertEquals(1, $response->total());


        /* Four items should be returned: 1 active, 1 inactive, and 1 deleted, and 1 user created automatically 
         by creating new user, which is personal canal. **/
        $this->assertCount(4, $response);

        // $this->assertEquals($activeCanal->id, $response->items()[0]->id);
    }
}
