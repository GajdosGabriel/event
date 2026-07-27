<?php

namespace Tests\Feature\Events;

use App\Enums\ModelStatus;
use App\Models\Event;
use App\Models\Tag;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

class PublicEventTagFilterTest extends EventSetupTest
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

        // Verejný výpis ukazuje len publikované a ešte neskončené podujatia.
        // EventFactory losuje stav aj dátumy (a cudziEvent nemá ani modifikátor
        // future()), takže sa oba musia pripnúť — inak test závisí od hodu kockou.
        Event::query()->whereIn('id', [$this->futureEvent->id, $this->cudziEvent->id])->update([
            'status' => ModelStatus::Published->value,
            'start_at' => now()->addWeek(),
            'end_at' => now()->addWeek()->addHours(3),
        ]);

        $this->attach($this->futureEvent, [$this->koncert, $this->folklor]);
        $this->attach($this->cudziEvent, [$this->koncert]);
    }

    private function attach(Event $event, array $tags): void
    {
        $event->tags()->attach(collect($tags)->mapWithKeys(fn (Tag $tag) => [
            $tag->id => ['confidence' => 100, 'source' => 'ai', 'created_at' => now(), 'updated_at' => now()],
        ])->all());
    }

    private function ids(array $query): array
    {
        return collect($this->getJson('/api/events?' . http_build_query($query + ['list' => 'all', 'per_page' => 100]))
            ->assertOk()
            ->json('data'))
            ->pluck('id')
            ->all();
    }

    #[Test]
    public function it_filters_by_a_single_tag(): void
    {
        $ids = $this->ids(['tags' => 'koncert']);

        $this->assertContains($this->futureEvent->id, $ids);
        $this->assertContains($this->cudziEvent->id, $ids);
        $this->assertNotContains($this->pastEvent->id, $ids);
    }

    #[Test]
    public function multiple_tags_narrow_the_result(): void
    {
        // Viac štítkov znamená AND — používateľ čaká zúženie, nie rozšírenie.
        $ids = $this->ids(['tags' => 'koncert,folklor']);

        $this->assertSame([$this->futureEvent->id], $ids);
    }

    #[Test]
    public function unknown_slug_returns_empty_not_an_error(): void
    {
        // Zastaralý odkaz nemá skončiť chybou.
        $this->getJson('/api/events?tags=neexistujuci-slug&list=all')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function filtering_only_narrows_the_unfiltered_result(): void
    {
        // Verejný výpis nikdy nevracia skončené podujatia, nech je filter
        // akýkoľvek — porovnáva sa preto voči nefiltrovanému výsledku, nie
        // voči celkovému počtu v databáze.
        $all = $this->ids([]);
        $filtered = $this->ids(['tags' => 'koncert']);

        $this->assertNotEmpty($all);
        $this->assertEmpty(array_diff($filtered, $all));
        $this->assertLessThanOrEqual(count($all), count($filtered));
    }

    #[Test]
    public function tags_are_present_in_the_index_payload(): void
    {
        $row = collect($this->getJson('/api/events?list=all&per_page=100')->assertOk()->json('data'))
            ->firstWhere('id', $this->futureEvent->id);

        $this->assertSame(
            ['folklor', 'koncert'],
            collect($row['tags'])->pluck('slug')->sort()->values()->all(),
        );
        $this->assertSame('ai', collect($row['tags'])->firstWhere('slug', 'koncert')['source']);
    }

    #[Test]
    public function tags_are_present_on_the_public_detail(): void
    {
        // Public\EventController::show() obchádza EventResource a serializuje
        // model priamo, takže štítky tam závisia od eager loadu v publicShow().
        $tags = $this->getJson('/api/events/' . $this->futureEvent->id)
            ->assertOk()
            ->json('tags');

        $this->assertSame(
            ['folklor', 'koncert'],
            collect($tags)->pluck('slug')->sort()->values()->all(),
        );
    }
}
