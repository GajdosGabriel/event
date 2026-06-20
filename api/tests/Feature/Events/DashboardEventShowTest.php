<?php

namespace Tests\Feature\Events;


use App\Enums\FileType;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;
use App\Models\Canal;
use App\Models\Event;
use App\Models\Venue;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;


class DashboardEventShowTest extends EventSetupTest
{

    #[Test]
    public function user_can_see_one_event()
    {
        $response = $this->getJson('/api/dashboard/events/' . $this->futureEvent->id);

        $response->assertStatus(200);

        // dump($response->getContent());
        $response->assertJsonFragment([
            'id' => $this->futureEvent->id,
            'name' => $this->futureEvent->name,
            'body' => $this->futureEvent->body,
            'canal_id' => $this->futureEvent->canal_id,
            'user_id' => $this->futureEvent->user_id,
            'start_at' => $this->futureEvent->start_at,
            'venue_id' => $this->futureEvent->venue_id,
            'end_at' => $this->futureEvent->end_at
        ]);
    }

    #[Test]
    public function user_can_see_primary_image_payload_with_thumb_and_large_variants(): void
    {
        Storage::fake('public');

        $originalPath = 'event/' . $this->futureEvent->id . '/image/source.jpg';
        $thumbPath = 'event/' . $this->futureEvent->id . '/image/source_thumb.jpg';
        $largePath = 'event/' . $this->futureEvent->id . '/image/source_large.jpg';

        Storage::disk('public')->put($originalPath, 'original');
        Storage::disk('public')->put($thumbPath, 'thumb');
        Storage::disk('public')->put($largePath, 'large');

        $this->futureEvent->files()->create([
            'name' => 'event-primary-image',
            'original_name' => 'event-primary-image.jpg',
            'extension' => 'jpg',
            'size' => 1024,
            'mime_type' => 'image/jpeg',
            'disk' => 'public',
            'path' => $originalPath,
            'thumb' => $thumbPath,
            'large' => $largePath,
            'checksum' => 'event-primary-image-checksum',
            'type' => FileType::IMAGE->value,
            'is_primary' => true,
        ]);

        $response = $this->getJson('/api/dashboard/events/' . $this->futureEvent->id);

        $thumbUrl = Storage::url($thumbPath);
        $largeUrl = Storage::url($largePath);
        $originalUrl = Storage::url($originalPath);

        $response->assertStatus(200)
            ->assertJsonPath('primary_image.thumb', $thumbUrl)
            ->assertJsonPath('primary_image.large', $largeUrl)
            ->assertJsonPath('primary_image.original', $originalUrl)
            ->assertJsonPath('thumb_image', $thumbUrl);
    }

    #[Test]
    public function user_can_see_single_day_date_range_label(): void
    {
        $this->futureEvent->update([
            'start_at' => Carbon::parse('2026-04-28 17:30:00'),
            'end_at' => Carbon::parse('2026-04-28 20:30:00'),
        ]);

        $response = $this->getJson('/api/dashboard/events/' . $this->futureEvent->id);

        $response->assertStatus(200)
            ->assertJsonPath('date_range_label', '28. 04. 2026 17:30 - 20:30')
            ->assertJsonPath('date_range_days.start', 'Utorok')
            ->assertJsonPath('date_range_days.end', null);
    }

    #[Test]
    public function user_can_see_multi_day_date_range_label(): void
    {
        $this->futureEvent->update([
            'start_at' => Carbon::parse('2026-05-01 16:00:00'),
            'end_at' => Carbon::parse('2026-05-08 10:30:00'),
        ]);

        $response = $this->getJson('/api/dashboard/events/' . $this->futureEvent->id);

        $response->assertStatus(200)
            ->assertJsonPath('date_range_label', '01. 05. 2026 16:00 - 08. 05. 2026 10:30')
            ->assertJsonPath('date_range_days.start', 'Piatok')
            ->assertJsonPath('date_range_days.end', 'Piatok');
    }

    #[Test]
    public function user_can_see_end_day_name_when_it_differs_from_start_day(): void
    {
        $this->futureEvent->update([
            'start_at' => Carbon::parse('2026-05-01 16:00:00'),
            'end_at' => Carbon::parse('2026-05-09 10:30:00'),
        ]);

        $response = $this->getJson('/api/dashboard/events/' . $this->futureEvent->id);

        $response->assertStatus(200)
            ->assertJsonPath('date_range_label', '01. 05. 2026 16:00 - 09. 05. 2026 10:30')
            ->assertJsonPath('date_range_days.start', 'Piatok')
            ->assertJsonPath('date_range_days.end', 'Sobota');
    }

    #[Test]
    public function user_can_see_none_yourevent()
    {
        $foreignCanal = Canal::factory()->create();
        $foreignVenue = Venue::factory()->create([
            'canal_id' => $foreignCanal->id,
            'village_id' => (int) $foreignCanal->municipality_id,
        ]);

        $foreignEvent = Event::factory()->create([
            'canal_id' => $foreignCanal->id,
            'venue_id' => $foreignVenue->id,
        ]);

        $response = $this->getJson('/api/dashboard/events/' . $foreignEvent->id);

        $response->assertStatus(404);
    }
}
