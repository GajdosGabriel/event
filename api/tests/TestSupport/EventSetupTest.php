<?php

namespace Tests\TestSupport;

use App\Models\Canal;
use App\Models\Event;
use App\Models\Venue;
use App\Repositories\Contracts\EventRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestSupport\CanalSetupTest;


abstract class EventSetupTest extends CanalSetupTest
{
    use RefreshDatabase;

    protected EventRepository $eventRepository;
    protected $futureEvent;
    protected $pastEvent;
    protected $cudziEvent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user->givePermissionTo([
            'event.create',
            'event.update',
            'event.delete',
            'venue.create',
        ]);

        $this->eventRepository = app(EventRepository::class);

        $canals = $this->user->canals->pluck('id')->all();

        $primaryCanalId = (int) $canals[0];
        $primaryVenue = Venue::query()
            ->whereHas('canals', fn ($query) => $query->where('canals.id', $primaryCanalId))
            ->first()
            ?? Venue::factory()->create([
                'canal_id' => $primaryCanalId,
                'village_id' => (int) $this->canalPrimary->municipality_id,
            ]);

        // 1. Vytvorte testovacie eventy
        $this->futureEvent = Event::factory()->future()->create([
            'canal_id' => $primaryCanalId,
            'venue_id' => $primaryVenue->id,
            'user_id' => $this->user->id,
        ]);

        $this->pastEvent = Event::factory()->past()->create([
            'canal_id' => $primaryCanalId,
            'venue_id' => $primaryVenue->id,
            'user_id' => $this->user->id,
        ]);

        $foreignCanal = Canal::factory()->create();
        $foreignVenue = Venue::factory()->create([
            'canal_id' => $foreignCanal->id,
            'village_id' => (int) $foreignCanal->municipality_id,
        ]);

        $this->cudziEvent = Event::factory()->create([
            'canal_id' => $foreignCanal->id,
            'venue_id' => $foreignVenue->id,
            'user_id' => $foreignCanal->users()->value('users.id') ?? $this->user->id,
        ]);
    }
}
