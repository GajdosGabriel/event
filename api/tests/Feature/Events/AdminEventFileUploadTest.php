<?php

namespace Tests\Feature\Events;

use App\Enums\FileType;
use App\Models\Canal;
use App\Models\Event;
use App\Models\File;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

class AdminEventFileUploadTest extends EventSetupTest
{
    use RefreshDatabase;

    #[Test]
    public function admin_store_event_saves_uploaded_files(): void
    {
        Storage::fake('public');
        $this->actingAs($this->userSuperAdmin, 'sanctum');

        $canal = Canal::factory()->create([
            'municipality_id' => (int) DB::table('municipalities')->value('id'),
        ]);
        $venue = Venue::factory()->create([
            'canal_id' => $canal->id,
            'village_id' => (int) $canal->municipality_id,
        ]);

        $upload = UploadedFile::fake()->create('event-admin-store.pdf', 120, 'application/pdf');

        $payload = [
            'name' => 'Admin Event File Store ' . uniqid(),
            'body' => 'Admin event with uploaded file.',
            'start_at' => now()->addDays(2)->startOfHour()->format('Y-m-d H:i:s'),
            'end_at' => now()->addDays(2)->addHours(2)->startOfHour()->format('Y-m-d H:i:s'),
            'status' => 'published',
            'canal_id' => $canal->id,
            'user_id' => $this->userSuperAdmin->id,
            'venue_id' => $venue->id,
            'file_type' => FileType::FILE->value,
            'make_primary_file' => true,
            'files' => [$upload],
        ];

        $response = $this->post('/api/admin/events', $payload, ['Accept' => 'application/json']);

        $response->assertStatus(201);

        $event = Event::query()->where('name', $payload['name'])->firstOrFail();
        $file = File::query()
            ->where('fileable_type', Event::class)
            ->where('fileable_id', $event->id)
            ->where('type', FileType::FILE->value)
            ->first();

        $this->assertNotNull($file);
        $this->assertTrue(Storage::disk('public')->exists($file->path));
    }

    #[Test]
    public function admin_update_event_saves_uploaded_files(): void
    {
        Storage::fake('public');
        $this->actingAs($this->userSuperAdmin, 'sanctum');

        $event = Event::factory()->future()->create([
            'canal_id' => $this->canalPrimary->id,
            'user_id' => $this->user->id,
        ]);

        $upload = UploadedFile::fake()->create('event-admin-update.pdf', 120, 'application/pdf');

        $payload = [
            '_method' => 'PUT',
            'name' => $event->name . ' Admin Updated ' . uniqid(),
            'body' => 'Admin updated event with uploaded file.',
            'start_at' => now()->addDays(3)->startOfHour()->format('Y-m-d H:i:s'),
            'end_at' => now()->addDays(3)->addHours(2)->startOfHour()->format('Y-m-d H:i:s'),
            'status' => 'published',
            'canal_id' => $event->canal_id,
            'venue_id' => $event->venue_id,
            'file_type' => FileType::FILE->value,
            'make_primary_file' => true,
            'files' => [$upload],
        ];

        $response = $this->post('/api/admin/events/' . $event->id, $payload, ['Accept' => 'application/json']);

        $response->assertStatus(200);

        $file = File::query()
            ->where('fileable_type', Event::class)
            ->where('fileable_id', $event->id)
            ->where('type', FileType::FILE->value)
            ->latest('id')
            ->first();

        $this->assertNotNull($file);
        $this->assertTrue(Storage::disk('public')->exists($file->path));
    }

    #[Test]
    public function admin_store_event_marks_first_uploaded_image_as_primary_when_canal_has_no_primary_image(): void
    {
        Storage::fake('public');
        $this->actingAs($this->userSuperAdmin, 'sanctum');

        $canal = Canal::factory()->create([
            'municipality_id' => (int) DB::table('municipalities')->value('id'),
        ]);
        $venue = Venue::factory()->create([
            'canal_id' => $canal->id,
            'village_id' => (int) $canal->municipality_id,
        ]);

        $upload = UploadedFile::fake()->image('event-admin-store.jpg');

        $payload = [
            'name' => 'Admin Event Image Store ' . uniqid(),
            'body' => 'Admin event with uploaded image.',
            'start_at' => now()->addDays(2)->startOfHour()->format('Y-m-d H:i:s'),
            'end_at' => now()->addDays(2)->addHours(2)->startOfHour()->format('Y-m-d H:i:s'),
            'status' => 'published',
            'canal_id' => $canal->id,
            'user_id' => $this->userSuperAdmin->id,
            'venue_id' => $venue->id,
            'file_type' => FileType::IMAGE->value,
            'files' => [$upload],
        ];

        $response = $this->post('/api/admin/events', $payload, ['Accept' => 'application/json']);

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
    public function admin_store_event_marks_first_uploaded_image_as_primary_even_without_file_type_image(): void
    {
        Storage::fake('public');
        $this->actingAs($this->userSuperAdmin, 'sanctum');

        $canal = Canal::factory()->create([
            'municipality_id' => (int) DB::table('municipalities')->value('id'),
        ]);
        $venue = Venue::factory()->create([
            'canal_id' => $canal->id,
            'village_id' => (int) $canal->municipality_id,
        ]);

        $upload = UploadedFile::fake()->image('event-admin-store-no-type.jpg');

        $payload = [
            'name' => 'Admin Event Image Store No Type ' . uniqid(),
            'body' => 'Admin event with uploaded image and default file type.',
            'start_at' => now()->addDays(2)->startOfHour()->format('Y-m-d H:i:s'),
            'end_at' => now()->addDays(2)->addHours(2)->startOfHour()->format('Y-m-d H:i:s'),
            'status' => 'published',
            'canal_id' => $canal->id,
            'user_id' => $this->userSuperAdmin->id,
            'venue_id' => $venue->id,
            'files' => [$upload],
        ];

        $response = $this->post('/api/admin/events', $payload, ['Accept' => 'application/json']);

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
    public function admin_update_event_does_not_mark_uploaded_image_as_primary_when_event_already_has_primary_image(): void
    {
        Storage::fake('public');
        $this->actingAs($this->userSuperAdmin, 'sanctum');

        $event = Event::factory()->future()->create([
            'canal_id' => $this->canalPrimary->id,
            'user_id' => $this->user->id,
        ]);

        $event->files()->create([
            'name' => 'existing-event-primary',
            'original_name' => 'existing-event-primary.jpg',
            'extension' => 'jpg',
            'size' => 1024,
            'mime_type' => 'image/jpeg',
            'disk' => 'public',
            'path' => 'event/' . $event->id . '/image/existing-event-primary.jpg',
            'checksum' => 'existing-event-primary-checksum',
            'type' => FileType::IMAGE->value,
            'is_primary' => true,
        ]);

        $upload = UploadedFile::fake()->image('event-admin-secondary.jpg');

        $payload = [
            '_method' => 'PUT',
            'name' => $event->name . ' Admin Secondary Image ' . uniqid(),
            'body' => 'Admin updated event with uploaded secondary image.',
            'start_at' => now()->addDays(3)->startOfHour()->format('Y-m-d H:i:s'),
            'end_at' => now()->addDays(3)->addHours(2)->startOfHour()->format('Y-m-d H:i:s'),
            'status' => 'published',
            'canal_id' => $event->canal_id,
            'venue_id' => $event->venue_id,
            'file_type' => FileType::IMAGE->value,
            'files' => [$upload],
        ];

        $response = $this->post('/api/admin/events/' . $event->id, $payload, ['Accept' => 'application/json']);

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
