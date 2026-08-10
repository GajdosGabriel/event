<?php

namespace Tests\Unit\Events;

use App\Enums\ModelStatus; // <-- Dôležité: Použite Laravel TestCase namiesto PHPUnit TestCase
use App\Models\Event;
use App\Models\User;
use App\Repositories\Contracts\EventRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase; // <-- Používame DatabaseTransactions pre testy, ktoré potrebujú transakcie

class EventPublicIndexTest extends TestCase // <-- Zmena základnej triedy
{
    use DatabaseTransactions;

    protected EventRepository $eventRepository;

    protected $user;

    protected $canal;

    protected function setUp(): void
    {
        parent::setUp(); // <-- Musí byť prvé

        // Inicializácia repository cez Laravel container
        $this->eventRepository = app(EventRepository::class);

        $this->user = User::factory()->create();
    }

    public function test_it_returns_only_active_events_in_public_index()
    {
        // 1. Vytvorte testovacie eventy
        // status explicitne: future() rieši len termín, a publicIndexQuery()
        // filtruje na published.
        $activeItem = Event::factory()->future()->create([
            'status' => ModelStatus::Published->value,
        ]);
        $pasiveItem = Event::factory()->past()->create();
        $inactiveItem = Event::factory()->create([
            'deleted_at' => now(),
            'status' => 'draft',
        ]);

        // 2. Získajte výsledky
        $results = $this->eventRepository->publicIndexQuery()->get();

        // 3. Overte výsledky
        $this->assertTrue(
            $results->contains('id', $activeItem->id),
            'Aktívny event sa mal objaviť vo výsledkoch'
        );

        $this->assertFalse(
            $results->contains('id', $pasiveItem->id),
            'Neaktívny event by sa nemal objaviť'
        );

        $this->assertFalse(
            $results->contains('id', $inactiveItem->id),
            'Zmazaný kanál by sa nemal objaviť.'
        );
    }
}
