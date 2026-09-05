<?php

namespace Tests\Feature\Events;

use App\Enums\ModelStatus;
use App\Models\Event;
use App\Models\EventSeries;
use App\Services\Events\EventSeriesManager;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

/**
 * Séria opakovaných termínov: každý termín je samostatné podujatie, spoločný
 * je len obsah.
 */
class EventSeriesTest extends EventSetupTest
{
    #[Test]
    public function adding_an_occurrence_creates_the_series_and_puts_both_events_in_it(): void
    {
        $response = $this->postJson("/api/dashboard/events/{$this->futureEvent->id}/occurrences", []);

        $response->assertStatus(201);

        $this->futureEvent->refresh();
        $this->assertNotNull($this->futureEvent->series_id, 'Zdrojové podujatie musí do série pribudnúť tiež.');

        $occurrence = Event::query()->findOrFail($response->json('id') ?? $response->json('data.id'));

        $this->assertSame($this->futureEvent->series_id, $occurrence->series_id);
        $this->assertSame($this->futureEvent->name, $occurrence->name);
    }

    /** Nový termín je koncept bez dátumu — publikovať ho musí človek. */
    #[Test]
    public function new_occurrence_starts_as_a_draft_without_a_date(): void
    {
        $this->futureEvent->update(['status' => ModelStatus::Published->value]);

        $response = $this->postJson("/api/dashboard/events/{$this->futureEvent->id}/occurrences", []);

        $occurrence = Event::query()->findOrFail($response->json('id') ?? $response->json('data.id'));

        $this->assertSame(ModelStatus::Draft, $occurrence->status);
        $this->assertNull($occurrence->start_at);
        $this->assertNull($occurrence->published_at);
    }

    #[Test]
    public function occurrence_can_be_created_with_a_date(): void
    {
        $start = Carbon::parse('2026-11-20 19:00:00');

        $response = $this->postJson("/api/dashboard/events/{$this->futureEvent->id}/occurrences", [
            'start_at' => $start->toDateTimeString(),
            'end_at' => $start->copy()->addHours(2)->toDateTimeString(),
        ]);

        $occurrence = Event::query()->findOrFail($response->json('id') ?? $response->json('data.id'));

        $this->assertTrue($start->equalTo($occurrence->start_at));
    }

    /**
     * Jadro série: spoločný popis sa píše raz. Prepisuje sa len to, čo sa
     * v požiadavke naozaj zmenilo — termín ostatných termínov sa nesmie hnúť.
     */
    #[Test]
    public function shared_fields_are_written_into_the_other_occurrences(): void
    {
        $occurrence = $this->addOccurrence();
        $occurrence->forceFill(['start_at' => Carbon::parse('2026-12-01 18:00:00')])->save();

        $this->putJson("/api/dashboard/events/{$this->futureEvent->id}", [
            'name' => $this->futureEvent->name,
            'body' => '<p>Nový spoločný popis</p>',
            'canal_id' => $this->futureEvent->canal_id,
        ])->assertStatus(200);

        $occurrence->refresh();

        $this->assertStringContainsString('Nový spoločný popis', (string) $occurrence->body);
        $this->assertTrue(
            Carbon::parse('2026-12-01 18:00:00')->equalTo($occurrence->start_at),
            'Termín súrodenca sa prepisovať nesmie.',
        );
    }

    /** Termín, stav ani kapacita sa nikdy neprepisujú. */
    #[Test]
    public function date_and_status_are_never_propagated(): void
    {
        $occurrence = $this->addOccurrence();

        $this->putJson("/api/dashboard/events/{$this->futureEvent->id}", [
            'name' => $this->futureEvent->name,
            'canal_id' => $this->futureEvent->canal_id,
            'start_at' => Carbon::parse('2027-01-05 20:00:00')->toDateTimeString(),
            'status' => ModelStatus::Published->value,
        ])->assertStatus(200);

        $occurrence->refresh();

        $this->assertNull($occurrence->start_at);
        $this->assertSame(ModelStatus::Draft, $occurrence->status);
    }

    #[Test]
    public function detaching_the_last_pair_dissolves_the_series(): void
    {
        $occurrence = $this->addOccurrence();
        $seriesId = $occurrence->series_id;

        $this->deleteJson("/api/dashboard/events/{$occurrence->id}/series")->assertStatus(200);

        $this->assertNull($occurrence->fresh()->series_id);
        // V sérii by zostal jediný termín — taká séria nemá čo hlásiť „a ďalšie
        // termíny", preto zaniká celá.
        $this->assertNull($this->futureEvent->fresh()->series_id);
        $this->assertNull(EventSeries::query()->find($seriesId));
    }

    /**
     * Verejný výpis ukáže zo série len najbližší termín — inak by reprízny
     * program vytlačil z agendy všetko ostatné.
     */
    #[Test]
    public function public_list_shows_only_the_nearest_occurrence_of_a_series(): void
    {
        $occurrence = $this->addOccurrence();

        $near = Carbon::now()->addDays(3)->setTime(19, 0);
        $far = Carbon::now()->addDays(10)->setTime(19, 0);

        $this->futureEvent->forceFill([
            'status' => ModelStatus::Published->value,
            'published_at' => now(),
            'start_at' => $near,
        ])->save();

        $occurrence->forceFill([
            'status' => ModelStatus::Published->value,
            'published_at' => now(),
            'start_at' => $far,
        ])->save();

        $this->app['auth']->forgetGuards();

        $response = $this->getJson('/api/events?per_page=100')->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($this->futureEvent->id, $ids);
        $this->assertNotContains($occurrence->id, $ids, 'Vzdialenejší termín série do výpisu nepatrí.');
    }

    /** Na detaile najbližšieho termínu musia byť ostatné termíny vidieť. */
    #[Test]
    public function public_detail_lists_the_other_upcoming_occurrences(): void
    {
        $occurrence = $this->addOccurrence();

        $this->futureEvent->forceFill([
            'status' => ModelStatus::Published->value,
            'published_at' => now(),
            'start_at' => Carbon::now()->addDays(3)->setTime(19, 0),
        ])->save();

        $occurrence->forceFill([
            'status' => ModelStatus::Published->value,
            'published_at' => now(),
            'start_at' => Carbon::now()->addDays(10)->setTime(19, 0),
        ])->save();

        $this->app['auth']->forgetGuards();

        $response = $this->getJson("/api/events/{$this->futureEvent->id}")->assertOk();

        $this->assertSame([$occurrence->id], collect($response->json('series_occurrences'))->pluck('id')->all());
    }

    /** Duplikát je nové podujatie, nie ďalší termín. */
    #[Test]
    public function duplicating_an_event_does_not_join_its_series(): void
    {
        $this->addOccurrence();

        $response = $this->postJson("/api/dashboard/events/{$this->futureEvent->id}/duplicate")->assertStatus(201);

        $copy = Event::query()->findOrFail($response->json('id') ?? $response->json('data.id'));

        $this->assertNull($copy->series_id);
    }

    private function addOccurrence(): Event
    {
        return app(EventSeriesManager::class)->addOccurrence($this->user, $this->futureEvent->fresh());
    }
}
