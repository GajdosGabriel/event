<?php

namespace Tests\Feature\Events;

use App\Enums\ModelStatus;
use App\Enums\RegistrationSource;
use App\Models\Canal;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Venue;
use App\Services\Imports\EventSourceLookup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MergeDuplicateEventsCommandTest extends TestCase
{
    use RefreshDatabase;

    private const START = '2026-10-09 07:00:00';

    #[Test]
    public function it_merges_the_same_article_imported_from_two_sources(): void
    {
        [$collection, $organizer, $venue] = $this->fixtures();

        $dropped = $this->importedEvent($collection, $venue, 'https://www.vyveska.sk/trening-2', 'Krátky popis.');
        $kept = $this->importedEvent($organizer, $venue, 'https://www.vyveska.sk/trening', 'Oveľa dlhší popis toho istého tréningu.');

        $this->artisan('app:events-merge-duplicates', ['--apply' => true])->assertSuccessful();

        // Ostáva záznam na kanáli organizátora, zberný ide do koša.
        $this->assertNull(Event::query()->find($dropped->id));
        $this->assertNotNull(Event::withTrashed()->find($dropped->id)?->deleted_at);
        $this->assertNotNull(Event::query()->find($kept->id));

        $kept->refresh();
        $this->assertSame(['https://www.vyveska.sk/trening-2'], $kept->meta['merged_sources'] ?? []);
        $this->assertSame($kept->id, Event::withTrashed()->find($dropped->id)?->meta['merged_into'] ?? null);

        // Bez tejto pamäte by najbližší import zahodený článok nenašiel
        // (mazanie je mäkké) a duplicitu by vyrobil znova.
        $this->assertSame($kept->id, EventSourceLookup::find('https://www.vyveska.sk/trening-2')?->id);
    }

    #[Test]
    public function it_only_reports_without_apply(): void
    {
        [$collection, $organizer, $venue] = $this->fixtures();

        $this->importedEvent($collection, $venue, 'https://www.vyveska.sk/trening-2');
        $this->importedEvent($organizer, $venue, 'https://www.vyveska.sk/trening');

        $this->artisan('app:events-merge-duplicates')->assertSuccessful();

        $this->assertSame(2, Event::query()->count());
    }

    /**
     * Presne tá istá pozvánka o 16:00 môže v ten istý deň prebiehať v dvoch
     * farnostiach. Rovnaké miesto konania je jediné, čo dvojicu odlíši od
     * náhody — bez neho sa nezlučuje.
     */
    #[Test]
    public function it_skips_two_events_that_only_share_a_name_and_a_time(): void
    {
        [$collection, $organizer] = $this->fixtures();

        $bardejov = Venue::factory()->create(['canal_id' => $collection->id]);
        $kosice = Venue::factory()->create(['canal_id' => $organizer->id]);

        $this->importedEvent($collection, $bardejov, 'https://www.ecav.sk/obnova-bardejov');
        $this->importedEvent($organizer, $kosice, 'https://www.ecav.sk/obnova-kosice');

        $this->artisan('app:events-merge-duplicates', ['--apply' => true])->assertSuccessful();

        $this->assertSame(2, Event::query()->count());
    }

    /**
     * Keď na duplicite už niekto stavia (lístky, odbery, nástenka otázok),
     * automatika sa jej nesmie dotknúť — inak by väzby ostali visieť v prázdne.
     */
    #[Test]
    public function it_skips_a_duplicate_that_already_has_tickets(): void
    {
        [$collection, $organizer, $venue] = $this->fixtures();

        $dropped = $this->importedEvent($collection, $venue, 'https://www.vyveska.sk/trening-2');
        $this->importedEvent($organizer, $venue, 'https://www.vyveska.sk/trening');

        TicketType::query()->create([
            'event_id' => $dropped->id,
            'name' => 'Vstupné',
        ]);

        $this->artisan('app:events-merge-duplicates', ['--apply' => true])->assertSuccessful();

        $this->assertSame(2, Event::query()->count());
    }

    /**
     * @return array{0: Canal, 1: Canal, 2: Venue}
     */
    private function fixtures(): array
    {
        $collection = Canal::factory()->create([
            'name' => 'vyveska.sk',
            'slug' => 'vyveska-sk',
            'website' => 'https://www.vyveska.sk',
            'registration_source' => RegistrationSource::IMPORT->value,
        ]);

        $organizer = Canal::factory()->create([
            'name' => 'Komunitné centrum Košice',
            'registration_source' => RegistrationSource::IMPORT->value,
        ]);

        return [$collection, $organizer, Venue::factory()->create(['canal_id' => $collection->id])];
    }

    private function importedEvent(Canal $canal, Venue $venue, string $source, string $body = 'Popis.'): Event
    {
        return Event::factory()->create([
            'name' => 'Tréning zručností tvorby komunity',
            // Factory si slug odvádza z vlastného náhodného mena, nie z toho,
            // čo mu podstrčíme — a zhoda slugov je celý predmet tohto testu.
            'slug' => 'trening-zrucnosti-tvorby-komunity',
            'canal_id' => $canal->id,
            'user_id' => User::factory()->create(['canal_id' => $canal->id])->id,
            'venue_id' => $venue->id,
            'status' => ModelStatus::Published->value,
            'published_at' => now(),
            'start_at' => self::START,
            'end_at' => '2026-10-09 12:00:00',
            'body' => $body,
            'orginal_source' => $source,
        ]);
    }
}
