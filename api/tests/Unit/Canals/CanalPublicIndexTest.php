<?php

namespace Tests\Unit\Canals;

use App\Models\Canal; // <-- Dôležité: Použite Laravel TestCase namiesto PHPUnit TestCase
use App\Models\User;
use App\Repositories\Contracts\CanalRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase; // <-- Používame DatabaseTransactions pre testy, ktoré potrebujú transakcie

class CanalPublicIndexTest extends TestCase // <-- Zmena základnej triedy
{
    use DatabaseTransactions;

    protected CanalRepository $canalRepository;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp(); // <-- Musí byť prvé

        // Inicializácia repository cez Laravel container
        $this->canalRepository = app(CanalRepository::class);

        $this->user = User::factory()->create();
    }

    public function test_it_returns_only_active_canals_in_public_index()
    {
        // Arrange: Vytvor rôzne typy kanálov
        $activeItem = Canal::factory()->active()->create();
        $inactiveItem = Canal::factory()->inactive()->create();
        $deletedCanal = Canal::factory()->create([
            'deleted_at' => now(),
        ]);

        // 2. Získajte výsledky
        $results = $this->canalRepository->publicIndexQuery()->get();

        // 3. Overte výsledky
        // Assert: Over prítomnosť/absenciu kanálov
        $this->assertTrue(
            $results->contains('id', $activeItem->id),
            'Aktívny kanál sa mal objaviť vo výsledkoch.'
        );

        $this->assertFalse(
            $results->contains('id', $inactiveItem->id),
            'Neaktívny kanál by sa nemal objaviť.'
        );

        $this->assertFalse(
            $results->contains('id', $deletedCanal->id),
            'Zmazaný kanál by sa nemal objaviť.'
        );
    }
}
