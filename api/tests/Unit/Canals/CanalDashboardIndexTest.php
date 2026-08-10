<?php

namespace Tests\Unit\Canals;

use App\Models\Canal; // <-- Dôležité: Použite Laravel TestCase namiesto PHPUnit TestCase
use App\Models\User;
use App\Repositories\Contracts\CanalRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase; // <-- Používame DatabaseTransactions pre testy, ktoré potrebujú transakcie

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

        // Bez atribútov: tabuľka `users` stĺpec `first_name` nemá, meno je na
        // kanáli. Test ho aj tak nikde nečíta.
        $this->user = User::factory()->create();

        $this->actingAs($this->user, 'sanctum');
    }

    public function test_active_dashboardindex_canals()
    {
        // 1. Vytvorte testovacie eventy
        // raw() a nie make()->toArray(): toArray() pribalí aj $appends
        // (has_primary_image, primary_image, thumb_image), čo sú accessory,
        // nie stĺpce — pri $guarded = [] by prešli rovno do INSERTu.
        $activeCanal = $this->canalRepository->create(
            Canal::factory()->active()->raw(['name' => 'Aktívny kanál'])
        );
        $inactiveCanal = $this->canalRepository->create(
            Canal::factory()->inactive()->raw()
        );
        $deletedCanal = $this->canalRepository->create(
            Canal::factory()->raw(['deleted_at' => now()])
        );

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
