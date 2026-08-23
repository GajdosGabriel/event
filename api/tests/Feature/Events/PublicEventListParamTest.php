<?php

namespace Tests\Feature\Events;

use App\Enums\ModelStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PublicEventListParamTest extends TestCase
{
    use RefreshDatabase;

    private Event $ongoingEvent;

    private Event $upcomingEvent;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();

        $this->ongoingEvent = Event::factory()->active()->create([
            'status' => ModelStatus::Published->value,
            'published_at' => now()->subMonth(),
            'user_id' => $user->id,
        ]);

        $this->upcomingEvent = Event::factory()->future()->create([
            'status' => ModelStatus::Published->value,
            'published_at' => now()->subMonth(),
            'user_id' => $user->id,
        ]);
    }

    private function fetchIds(string $query = ''): array
    {
        $response = $this->getJson('/api/events' . $query);
        $response->assertStatus(200);

        return collect($response->json('data'))->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    #[Test]
    public function default_list_excludes_ongoing_events(): void
    {
        $ids = $this->fetchIds();

        $this->assertContains($this->upcomingEvent->id, $ids);
        $this->assertNotContains($this->ongoingEvent->id, $ids);
    }

    #[Test]
    public function ongoing_list_returns_only_already_started_events(): void
    {
        $ids = $this->fetchIds('?list=ongoing');

        $this->assertContains($this->ongoingEvent->id, $ids);
        $this->assertNotContains($this->upcomingEvent->id, $ids);
    }

    #[Test]
    public function all_list_returns_both(): void
    {
        $ids = $this->fetchIds('?list=all');

        $this->assertContains($this->ongoingEvent->id, $ids);
        $this->assertContains($this->upcomingEvent->id, $ids);
    }

    /**
     * Archív. Stav je širší než pri ostatných výpisoch — skončené podujatie
     * preklopí `app:events-archive-finished` na `archived`, takže filter len
     * na `published` by vrátil prázdny zoznam.
     */
    #[Test]
    public function past_list_returns_finished_events_newest_first(): void
    {
        $older = Event::factory()->past()->create([
            'start_at' => now()->subMonths(6),
            'end_at' => now()->subMonths(6)->addHours(3),
            'status' => ModelStatus::Archived->value,
            'published_at' => now()->subYear(),
        ]);

        $newer = Event::factory()->past()->create([
            'start_at' => now()->subWeek(),
            'end_at' => now()->subWeek()->addHours(3),
            'status' => ModelStatus::Archived->value,
            'published_at' => now()->subMonths(2),
        ]);

        $ids = $this->fetchIds('?list=past');

        $this->assertSame([$newer->id, $older->id], array_slice($ids, 0, 2));
        $this->assertNotContains($this->upcomingEvent->id, $ids);
    }

    #[Test]
    public function default_list_excludes_finished_events(): void
    {
        $past = Event::factory()->past()->create([
            'status' => ModelStatus::Archived->value,
            'published_at' => now()->subYear(),
        ]);

        $this->assertNotContains($past->id, $this->fetchIds());
    }
}
