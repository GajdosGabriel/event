<?php

namespace Tests\Unit\Filters;

use App\Enums\ModelStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CommonFilterTest extends TestCase
{
    use DatabaseTransactions;

    public function test_filter_by_published_status(): void
    {
        $user = User::factory()->create();

        $publishedEvent = Event::factory()->create([
            'user_id' => $user->id,
            'status' => ModelStatus::Published->value,
            'published_at' => now(),
        ]);

        $draftEvent = Event::factory()->create([
            'user_id' => $user->id,
            'status' => ModelStatus::Draft->value,
            'published_at' => null,
        ]);

        $results = Event::byPublished(true)->get();

        $this->assertTrue($results->contains('id', $publishedEvent->id));
        $this->assertFalse($results->contains('id', $draftEvent->id));
    }

    public function test_filter_by_unpublished_status(): void
    {
        $user = User::factory()->create();

        $publishedEvent = Event::factory()->create([
            'user_id' => $user->id,
            'status' => ModelStatus::Published->value,
            'published_at' => now(),
        ]);

        $draftEvent = Event::factory()->create([
            'user_id' => $user->id,
            'status' => ModelStatus::Draft->value,
            'published_at' => null,
        ]);

        $results = Event::byPublished(false)->get();

        $this->assertFalse($results->contains('id', $publishedEvent->id));
        $this->assertTrue($results->contains('id', $draftEvent->id));
    }

    public function test_filter_by_status(): void
    {
        $user = User::factory()->create();

        $publishedEvent = Event::factory()->create([
            'user_id' => $user->id,
            'status' => ModelStatus::Published->value,
        ]);

        $draftEvent = Event::factory()->create([
            'user_id' => $user->id,
            'status' => ModelStatus::Draft->value,
        ]);

        $results = Event::byStatus(ModelStatus::Published->value)->get();

        $this->assertTrue($results->contains('id', $publishedEvent->id));
        $this->assertFalse($results->contains('id', $draftEvent->id));
    }

    public function test_filter_by_blocked(): void
    {
        $user = User::factory()->create();

        $blockedEvent = Event::factory()->create([
            'user_id' => $user->id,
            'status' => ModelStatus::Blocked->value,
        ]);

        $activeEvent = Event::factory()->create([
            'user_id' => $user->id,
            'status' => ModelStatus::Published->value,
        ]);

        $results = Event::byBlocked(true)->get();

        $this->assertTrue($results->contains('id', $blockedEvent->id));
        $this->assertFalse($results->contains('id', $activeEvent->id));
    }

    public function test_filter_by_deleted_soft_deletes(): void
    {
        $user = User::factory()->create();

        $activeEvent = Event::factory()->create(['user_id' => $user->id]);
        $deletedEvent = Event::factory()->create(['user_id' => $user->id]);
        $deletedEvent->delete();

        $withoutDeleted = Event::withTrashed()->byDeleted(false)->get();
        $onlyDeleted = Event::withTrashed()->byDeleted(true)->get();

        $this->assertTrue($withoutDeleted->contains('id', $activeEvent->id));
        $this->assertFalse($withoutDeleted->contains('id', $deletedEvent->id));

        $this->assertFalse($onlyDeleted->contains('id', $activeEvent->id));
        $this->assertTrue($onlyDeleted->contains('id', $deletedEvent->id));
    }

    public function test_filter_by_search_matches_name_before_body_but_returns_both(): void
    {
        $user = User::factory()->create();
        $needle = 'Needle ' . uniqid();

        $nameMatch = Event::factory()->create([
            'user_id' => $user->id,
            'name' => 'Event ' . $needle,
            'body' => 'Generic body text',
        ]);

        $bodyMatch = Event::factory()->create([
            'user_id' => $user->id,
            'name' => 'Body match only',
            'body' => 'Contains ' . $needle . ' in description',
        ]);

        $nonMatch = Event::factory()->create([
            'user_id' => $user->id,
            'name' => 'Different event',
            'body' => 'Different description',
        ]);

        $results = Event::query()->applyCommonFilters(['search' => $needle])->get();

        $this->assertSame($nameMatch->id, $results->first()?->id);
        $this->assertTrue($results->contains('id', $nameMatch->id));
        $this->assertTrue($results->contains('id', $bodyMatch->id));
        $this->assertFalse($results->contains('id', $nonMatch->id));
    }

    public function test_apply_common_filters_combined(): void
    {
        $user = User::factory()->create();

        $published = Event::factory()->create([
            'user_id' => $user->id,
            'status' => ModelStatus::Published->value,
            'published_at' => now(),
        ]);

        $draft = Event::factory()->create([
            'user_id' => $user->id,
            'status' => ModelStatus::Draft->value,
            'published_at' => null,
        ]);

        $deleted = Event::factory()->create([
            'user_id' => $user->id,
            'status' => ModelStatus::Draft->value,
        ]);
        $deleted->delete();

        $filters = [
            'status' => null,
            'search' => null,
            'published' => true,
            'blocked' => null,
            'deleted' => false,
        ];

        $results = Event::applyCommonFilters($filters)->get();

        $this->assertTrue($results->contains('id', $published->id));
        $this->assertFalse($results->contains('id', $draft->id));
        $this->assertFalse($results->contains('id', $deleted->id));
    }
}
