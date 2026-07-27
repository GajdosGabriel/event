<?php

namespace Tests\Feature\Events;

use App\Enums\ModelStatus;
use App\Models\Tag;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

class EventTagAssignmentTest extends EventSetupTest
{
    private Tag $koncert;
    private Tag $folklor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->koncert = Tag::query()->create([
            'group' => 'format', 'slug' => 'koncert', 'name' => 'Koncert', 'sort_order' => 0, 'is_active' => true,
        ]);
        $this->folklor = Tag::query()->create([
            'group' => 'topic', 'slug' => 'folklor', 'name' => 'Folklór', 'sort_order' => 0, 'is_active' => true,
        ]);

        // EventFactory losuje stav zo VŠETKÝCH ModelStatus vrátane 'archived',
        // ktorý EventPolicy::update() odmieta — bez pripnutia by test padal
        // zhruba v jednom behu zo siedmich.
        $this->futureEvent->update(['status' => ModelStatus::Draft->value]);
    }

    private function pivot(int $eventId): array
    {
        return DB::table('event_tag')
            ->join('tags', 'tags.id', '=', 'event_tag.tag_id')
            ->where('event_tag.event_id', $eventId)
            ->orderBy('tags.slug')
            ->pluck('event_tag.source', 'tags.slug')
            ->all();
    }

    #[Test]
    public function user_can_assign_tags_when_updating_an_event(): void
    {
        $this->putJson('/api/dashboard/events/' . $this->futureEvent->id, [
            'name' => $this->futureEvent->name,
            'tag_ids' => [$this->koncert->id, $this->folklor->id],
        ])->assertOk();

        $this->assertSame(
            ['folklor' => 'manual', 'koncert' => 'manual'],
            $this->pivot($this->futureEvent->id),
        );
    }

    #[Test]
    public function omitting_tag_ids_leaves_tags_untouched(): void
    {
        $this->futureEvent->tags()->attach([
            $this->koncert->id => ['confidence' => 100, 'source' => 'manual', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Rýchla zmena iného poľa nesmie zmazať štítky.
        $this->putJson('/api/dashboard/events/' . $this->futureEvent->id, [
            'name' => 'Nový názov',
        ])->assertOk();

        $this->assertSame(['koncert' => 'manual'], $this->pivot($this->futureEvent->id));
    }

    #[Test]
    public function empty_tag_ids_detaches_manual_tags(): void
    {
        $this->futureEvent->tags()->attach([
            $this->koncert->id => ['confidence' => 100, 'source' => 'manual', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->putJson('/api/dashboard/events/' . $this->futureEvent->id, [
            'name' => $this->futureEvent->name,
            'tag_ids' => [],
        ])->assertOk();

        $this->assertSame([], $this->pivot($this->futureEvent->id));
    }

    #[Test]
    public function manual_sync_does_not_remove_ai_tags(): void
    {
        $this->futureEvent->tags()->attach([
            $this->folklor->id => ['confidence' => 90, 'source' => 'ai', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->putJson('/api/dashboard/events/' . $this->futureEvent->id, [
            'name' => $this->futureEvent->name,
            'tag_ids' => [$this->koncert->id],
        ])->assertOk();

        // AI a odvodené priradenia vlastnia príslušné služby — ručný výber
        // ich neprepisuje, inak by ich najbližší beh aj tak vrátil.
        $this->assertSame(
            ['folklor' => 'ai', 'koncert' => 'manual'],
            $this->pivot($this->futureEvent->id),
        );
    }

    #[Test]
    public function unknown_tag_id_is_rejected(): void
    {
        $this->putJson('/api/dashboard/events/' . $this->futureEvent->id, [
            'name' => $this->futureEvent->name,
            'tag_ids' => [999999],
        ])->assertStatus(422)->assertJsonValidationErrors('tag_ids.0');
    }

    #[Test]
    public function derived_tags_are_recalculated_on_every_save(): void
    {
        $viacdnove = Tag::query()->create([
            'group' => 'attribute', 'slug' => 'viacdnove', 'name' => 'Viacdňové', 'sort_order' => 0, 'is_active' => true,
        ]);

        $this->futureEvent->update([
            'start_at' => now()->addWeek(),
            'end_at' => now()->addWeek()->addHours(2),
        ]);

        $this->putJson('/api/dashboard/events/' . $this->futureEvent->id, [
            'name' => $this->futureEvent->name,
            'start_at' => now()->addWeek()->format('Y-m-d H:i:s'),
            'end_at' => now()->addWeek()->addHours(2)->format('Y-m-d H:i:s'),
        ])->assertOk();

        $this->assertArrayNotHasKey('viacdnove', $this->pivot($this->futureEvent->id));

        // Predĺženie termínu nemení text, takže AI beh sa nespustí — odvodenie
        // preto musí bežať pri každom zápise, inak by štítok chýbal navždy.
        $this->putJson('/api/dashboard/events/' . $this->futureEvent->id, [
            'name' => $this->futureEvent->name,
            'start_at' => now()->addWeek()->format('Y-m-d H:i:s'),
            'end_at' => now()->addWeek()->addDays(3)->format('Y-m-d H:i:s'),
        ])->assertOk();

        $this->assertSame(['viacdnove' => 'derived'], $this->pivot($this->futureEvent->id));

        // A skrátenie termínu ho musí zase odobrať.
        $this->putJson('/api/dashboard/events/' . $this->futureEvent->id, [
            'name' => $this->futureEvent->name,
            'start_at' => now()->addWeek()->format('Y-m-d H:i:s'),
            'end_at' => now()->addWeek()->addHours(2)->format('Y-m-d H:i:s'),
        ])->assertOk();

        $this->assertArrayNotHasKey('viacdnove', $this->pivot($this->futureEvent->id));
        $this->assertNotNull($viacdnove->id);
    }

    #[Test]
    public function tags_can_be_assigned_when_creating_an_event(): void
    {
        $id = $this->postJson('/api/dashboard/events', [
            'name' => 'Nové podujatie',
            'status' => ModelStatus::Draft->value,
            'tag_ids' => [$this->koncert->id],
        ])->assertCreated()->json('id');

        $this->assertSame(['koncert' => 'manual'], $this->pivot((int) $id));
    }

    #[Test]
    public function duplicating_an_event_copies_its_tags(): void
    {
        $this->futureEvent->tags()->attach([
            $this->koncert->id => ['confidence' => 100, 'source' => 'manual', 'created_at' => now(), 'updated_at' => now()],
            $this->folklor->id => ['confidence' => 80, 'source' => 'ai', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // replicate() pivot riadky neprenáša — kópia by inak prišla o štítky.
        $copyId = $this->postJson('/api/dashboard/events/' . $this->futureEvent->id . '/duplicate')
            ->assertSuccessful()
            ->json('id');

        $this->assertSame(
            ['folklor' => 'ai', 'koncert' => 'manual'],
            $this->pivot((int) $copyId),
        );
    }
}
