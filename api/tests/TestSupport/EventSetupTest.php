<?php

namespace Tests\TestSupport;

use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\Event;
use App\Models\Venue;
use App\Repositories\Contracts\EventRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
        // VenueFactory losuje status naprieč všetkými prípadmi ModelStatus.
        // Podujatia v tomto setupe sa ale publikujú, a publikované podujatie
        // musí mať publikované miesto (EventDependencyPublisher) — inak by
        // testy padali podľa toho, čo faker práve vylosoval.
        $primaryVenue = Venue::query()
            ->whereHas('canals', fn ($query) => $query->where('canals.id', $primaryCanalId))
            ->first()
            ?? Venue::factory()->create([
                'canal_id' => $primaryCanalId,
                'village_id' => (int) $this->canalPrimary->municipality_id,
            ]);
        $primaryVenue->forceFill(['status' => ModelStatus::Published->value])->save();

        // 1. Vytvorte testovacie eventy
        // EventFactory losuje `status` naprieč všetkými ModelStatus. Fixture
        // ale slúži testom dashboardu, a archivované (a publikované) podujatie
        // má iné práva — update archivovaného padá na 403, delete
        // publikovaného tiež. Bez pevného stavu je preto každý taký test
        // lotéria 1:7. Kto potrebuje iný stav, prepíše si ho u seba;
        // koncept je najnižší spoločný menovateľ.
        $this->futureEvent = Event::factory()->future()->create([
            'canal_id' => $primaryCanalId,
            'venue_id' => $primaryVenue->id,
            'user_id' => $this->user->id,
            'status' => ModelStatus::Draft->value,
        ]);

        $this->pastEvent = Event::factory()->past()->create([
            'canal_id' => $primaryCanalId,
            'venue_id' => $primaryVenue->id,
            'user_id' => $this->user->id,
            'status' => ModelStatus::Draft->value,
        ]);

        $foreignCanal = Canal::factory()->active()->create();
        $foreignVenue = Venue::factory()->create([
            'canal_id' => $foreignCanal->id,
            'village_id' => (int) $foreignCanal->municipality_id,
            'status' => ModelStatus::Published->value,
        ]);

        $this->cudziEvent = Event::factory()->create([
            'canal_id' => $foreignCanal->id,
            'venue_id' => $foreignVenue->id,
            'user_id' => $foreignCanal->users()->value('users.id') ?? $this->user->id,
            'status' => ModelStatus::Draft->value,
        ]);
    }
}
