<?php

namespace Tests\Feature\Events;

use App\Enums\ModelStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ArchiveFinishedEventsCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_archives_only_finished_published_events(): void
    {
        $user = User::factory()->create();

        $finishedPublished = Event::factory()->create([
            'user_id' => $user->id,
            'status' => ModelStatus::Published->value,
            'published_at' => now()->subDays(3),
            'start_at' => now()->subDays(2),
            'end_at' => now()->subHour(),
        ]);

        $runningPublished = Event::factory()->create([
            'user_id' => $user->id,
            'status' => ModelStatus::Published->value,
            'published_at' => now()->subDays(2),
            'start_at' => now()->subDay(),
            'end_at' => now()->addHours(2),
        ]);

        $finishedDraft = Event::factory()->create([
            'user_id' => $user->id,
            'status' => ModelStatus::Draft->value,
            'start_at' => now()->subDays(2),
            'end_at' => now()->subHour(),
        ]);

        $this->artisan('app:events-archive-finished')
            ->expectsOutput('Archived events: 1')
            ->assertSuccessful();

        $this->assertDatabaseHas('events', [
            'id' => $finishedPublished->id,
            'status' => ModelStatus::Archived->value,
        ]);

        $this->assertDatabaseHas('events', [
            'id' => $runningPublished->id,
            'status' => ModelStatus::Published->value,
        ]);

        $this->assertDatabaseHas('events', [
            'id' => $finishedDraft->id,
            'status' => ModelStatus::Draft->value,
        ]);
    }
}
