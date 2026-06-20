<?php

namespace Tests\Feature\Events;

use App\Enums\FileType;
use App\Models\Event;
use App\Models\File;
use App\Models\Venue;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

class DashboardEventFileUploadTest extends EventSetupTest
{
    #[Test]
    public function dashboard_store_event_saves_uploaded_files(): void
    {
        Storage::fake('public');

        $venue = Venue::query()
            ->whereHas('canals', fn ($query) => $query->where('canals.id', $this->canalPrimary->id))
            ->first()
            ?? Venue::factory()->create([
                'canal_id' => $this->canalPrimary->id,
                'village_id' => (int) $this->canalPrimary->municipality_id,
            ]);

        $upload = UploadedFile::fake()->create('event-dashboard-store.pdf', 120, 'application/pdf');

        $payload = [
            'name' => 'Dashboard Event File Store ' . uniqid(),
            'body' => 'Dashboard event with uploaded file.',
            'start_at' => now()->addDays(2)->startOfHour()->format('Y-m-d H:i:s'),
            'end_at' => now()->addDays(2)->addHours(2)->startOfHour()->format('Y-m-d H:i:s'),
            'status' => 'published',
            'venue_id' => (int) $venue->id,
            'file_type' => FileType::FILE->value,
            'make_primary_file' => true,
            'files' => [$upload],
        ];

        $response = $this->post('/api/dashboard/events', $payload, ['Accept' => 'application/json']);

        $response->assertStatus(201);

        $event = Event::query()->where('name', $payload['name'])->firstOrFail();

        $file = File::query()
            ->where('fileable_type', Event::class)
            ->where('fileable_id', $event->id)
            ->where('type', FileType::FILE->value)
            ->first();

        $this->assertNotNull($file);
        $this->assertSame($event->name, $file->name);
        $this->assertTrue(Storage::disk('public')->exists($file->path));
    }

    #[Test]
    public function dashboard_update_event_saves_uploaded_files(): void
    {
        Storage::fake('public');

        $event = $this->futureEvent;
        $upload = UploadedFile::fake()->create('event-dashboard-update.pdf', 120, 'application/pdf');

        $payload = [
            '_method' => 'PUT',
            'name' => $event->name . ' Dashboard Updated ' . uniqid(),
            'body' => 'Dashboard updated event with uploaded file.',
            'start_at' => now()->addDays(3)->startOfHour()->format('Y-m-d H:i:s'),
            'end_at' => now()->addDays(3)->addHours(2)->startOfHour()->format('Y-m-d H:i:s'),
            'status' => 'published',
            'venue_id' => $event->venue_id,
            'file_type' => FileType::FILE->value,
            'make_primary_file' => true,
            'files' => [$upload],
        ];

        $response = $this->post('/api/dashboard/events/' . $event->id, $payload, ['Accept' => 'application/json']);

        $response->assertStatus(200);

        $file = File::query()
            ->where('fileable_type', Event::class)
            ->where('fileable_id', $event->id)
            ->where('type', FileType::FILE->value)
            ->latest('id')
            ->first();

        $this->assertNotNull($file);
        $this->assertSame($payload['name'], $file->name);
        $this->assertTrue(Storage::disk('public')->exists($file->path));
    }

    #[Test]
    public function dashboard_store_event_marks_first_uploaded_image_as_primary_when_canal_has_no_primary_image(): void
    {
        Storage::fake('public');

        $venue = Venue::query()
            ->whereHas('canals', fn ($query) => $query->where('canals.id', $this->canalPrimary->id))
            ->first()
            ?? Venue::factory()->create([
                'canal_id' => $this->canalPrimary->id,
                'village_id' => (int) $this->canalPrimary->municipality_id,
            ]);

        $upload = UploadedFile::fake()->image('event-dashboard-store.jpg');

        $payload = [
            'name' => 'Dashboard Event Image Store ' . uniqid(),
            'body' => 'Dashboard event with uploaded image.',
            'start_at' => now()->addDays(2)->startOfHour()->format('Y-m-d H:i:s'),
            'end_at' => now()->addDays(2)->addHours(2)->startOfHour()->format('Y-m-d H:i:s'),
            'status' => 'published',
            'venue_id' => (int) $venue->id,
            'file_type' => FileType::IMAGE->value,
            'files' => [$upload],
        ];

        $response = $this->post('/api/dashboard/events', $payload, ['Accept' => 'application/json']);

        $response->assertStatus(201);

        $event = Event::query()->where('name', $payload['name'])->firstOrFail();

        $file = File::query()
            ->where('fileable_type', Event::class)
            ->where('fileable_id', $event->id)
            ->where('type', FileType::IMAGE->value)
            ->first();

        $this->assertNotNull($file);
        $this->assertTrue($file->is_primary);
    }

    #[Test]
    public function dashboard_store_event_marks_first_uploaded_image_as_primary_even_without_file_type_image(): void
    {
        Storage::fake('public');

        $venue = Venue::query()
            ->whereHas('canals', fn ($query) => $query->where('canals.id', $this->canalPrimary->id))
            ->first()
            ?? Venue::factory()->create([
                'canal_id' => $this->canalPrimary->id,
                'village_id' => (int) $this->canalPrimary->municipality_id,
            ]);

        $upload = UploadedFile::fake()->image('event-dashboard-store-no-type.jpg');

        $payload = [
            'name' => 'Dashboard Event Image Store No Type ' . uniqid(),
            'body' => 'Dashboard event with uploaded image and default file type.',
            'start_at' => now()->addDays(2)->startOfHour()->format('Y-m-d H:i:s'),
            'end_at' => now()->addDays(2)->addHours(2)->startOfHour()->format('Y-m-d H:i:s'),
            'status' => 'published',
            'venue_id' => (int) $venue->id,
            'files' => [$upload],
        ];

        $response = $this->post('/api/dashboard/events', $payload, ['Accept' => 'application/json']);

        $response->assertStatus(201);

        $event = Event::query()->where('name', $payload['name'])->firstOrFail();

        $file = File::query()
            ->where('fileable_type', Event::class)
            ->where('fileable_id', $event->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($file);
        $this->assertStringStartsWith('image/', (string) $file->mime_type);
        $this->assertTrue($file->is_primary);
    }

    #[Test]
    public function dashboard_update_event_marks_first_uploaded_image_as_primary_when_canal_has_no_primary_image(): void
    {
        Storage::fake('public');

        $event = $this->futureEvent;
        $upload = UploadedFile::fake()->image('event-dashboard-update.jpg');

        $payload = [
            '_method' => 'PUT',
            'name' => $event->name . ' Dashboard Image Updated ' . uniqid(),
            'body' => 'Dashboard updated event with uploaded image.',
            'start_at' => now()->addDays(3)->startOfHour()->format('Y-m-d H:i:s'),
            'end_at' => now()->addDays(3)->addHours(2)->startOfHour()->format('Y-m-d H:i:s'),
            'status' => 'published',
            'venue_id' => $event->venue_id,
            'file_type' => FileType::IMAGE->value,
            'files' => [$upload],
        ];

        $response = $this->post('/api/dashboard/events/' . $event->id, $payload, ['Accept' => 'application/json']);

        $response->assertStatus(200);

        $file = File::query()
            ->where('fileable_type', Event::class)
            ->where('fileable_id', $event->id)
            ->where('type', FileType::IMAGE->value)
            ->latest('id')
            ->first();

        $this->assertNotNull($file);
        $this->assertTrue($file->is_primary);
    }

    #[Test]
    public function dashboard_update_event_does_not_mark_uploaded_image_as_primary_when_canal_already_has_primary_image(): void
    {
        Storage::fake('public');

        $event = $this->futureEvent;
        $event->files()->create([
            'name' => 'existing-primary',
            'original_name' => 'existing-primary.jpg',
            'extension' => 'jpg',
            'size' => 1024,
            'mime_type' => 'image/jpeg',
            'disk' => 'public',
            'path' => 'event/' . $event->id . '/image/existing-primary.jpg',
            'checksum' => 'existing-primary-checksum',
            'type' => FileType::IMAGE->value,
            'is_primary' => true,
        ]);

        $upload = UploadedFile::fake()->image('event-dashboard-secondary.jpg');

        $payload = [
            '_method' => 'PUT',
            'name' => $event->name . ' Dashboard Secondary Image ' . uniqid(),
            'body' => 'Dashboard updated event with secondary uploaded image.',
            'start_at' => now()->addDays(3)->startOfHour()->format('Y-m-d H:i:s'),
            'end_at' => now()->addDays(3)->addHours(2)->startOfHour()->format('Y-m-d H:i:s'),
            'status' => 'published',
            'venue_id' => $event->venue_id,
            'file_type' => FileType::IMAGE->value,
            'files' => [$upload],
        ];

        $response = $this->post('/api/dashboard/events/' . $event->id, $payload, ['Accept' => 'application/json']);

        $response->assertStatus(200);

        $file = File::query()
            ->where('fileable_type', Event::class)
            ->where('fileable_id', $event->id)
            ->where('type', FileType::IMAGE->value)
            ->latest('id')
            ->first();

        $this->assertNotNull($file);
        $this->assertFalse($file->is_primary);
    }
}
