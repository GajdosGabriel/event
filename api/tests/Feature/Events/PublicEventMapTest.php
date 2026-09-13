<?php

namespace Tests\Feature\Events;

use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\Event;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PublicEventMapTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function map_returns_every_published_upcoming_event_without_pagination(): void
    {
        $user = User::factory()->create();
        $canal = Canal::factory()->create();
        $venue = Venue::factory()->create([
            'canal_id' => $canal->id,
            'name' => 'Kultúrny dom',
            'latitude' => 48.1486,
            'longitude' => 17.1077,
        ]);

        $published = Event::factory()->future()->count(20)->create([
            'canal_id' => $canal->id,
            'venue_id' => $venue->id,
            'status' => ModelStatus::Published->value,
            'published_at' => now()->subHour(),
            'user_id' => $user->id,
        ]);

        $draft = Event::factory()->future()->create([
            'canal_id' => $canal->id,
            'venue_id' => $venue->id,
            'status' => ModelStatus::Draft->value,
            'published_at' => null,
            'user_id' => $user->id,
        ]);

        $response = $this->getJson('/api/events/map');

        $response->assertOk();
        $response->assertJsonPath('meta.total', 20);
        $response->assertJsonCount(20, 'data');
        $response->assertJsonPath('data.0.venue.name', 'Kultúrny dom');

        $ids = collect($response->json('data'))->pluck('id');

        $this->assertEqualsCanonicalizing($published->pluck('id')->all(), $ids->all());
        $this->assertNotContains($draft->id, $ids);
        $this->assertEqualsWithDelta(48.1486, (float) $response->json('data.0.venue.latitude'), 0.0001);
    }

    #[Test]
    public function map_applies_the_same_search_filter_as_the_list(): void
    {
        $user = User::factory()->create();
        $canal = Canal::factory()->create();
        $venue = Venue::factory()->create(['canal_id' => $canal->id]);

        $attributes = [
            'canal_id' => $canal->id,
            'venue_id' => $venue->id,
            'status' => ModelStatus::Published->value,
            'published_at' => now()->subHour(),
            'user_id' => $user->id,
        ];

        $match = Event::factory()->future()->create($attributes + ['name' => 'Jarmok remesiel']);
        Event::factory()->future()->create($attributes + ['name' => 'Organový koncert']);

        $response = $this->getJson('/api/events/map?search=jarmok');

        $response->assertOk();
        $response->assertJsonPath('meta.total', 1);
        $response->assertJsonPath('data.0.id', $match->id);
    }
}
