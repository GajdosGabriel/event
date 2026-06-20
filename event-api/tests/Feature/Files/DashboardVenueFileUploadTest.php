<?php

namespace Tests\Feature\Files;

use App\Enums\FileType;
use App\Models\File;
use App\Models\Venue;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

class DashboardVenueFileUploadTest extends EventSetupTest
{
    #[Test]
    public function dashboard_file_store_marks_uploaded_venue_image_as_primary_when_canal_has_no_primary_image(): void
    {
        Storage::fake('public');

        $venue = Venue::factory()->forCanal($this->canalPrimary->id)->create();
        $upload = UploadedFile::fake()->image('venue-dashboard-store.jpg');

        $payload = [
            'fileable_type' => 'venue',
            'fileable_id' => $venue->id,
            'type' => FileType::IMAGE->value,
            'files' => [$upload],
        ];

        $response = $this->post('/api/dashboard/files', $payload, ['Accept' => 'application/json']);

        $response->assertStatus(201);

        $file = File::query()
            ->where('fileable_type', Venue::class)
            ->where('fileable_id', $venue->id)
            ->where('type', FileType::IMAGE->value)
            ->latest('id')
            ->first();

        $this->assertNotNull($file);
        $this->assertSame($venue->name, $file->name);
        $this->assertTrue($file->is_primary);
    }

    #[Test]
    public function dashboard_file_store_marks_uploaded_venue_image_as_primary_even_without_type(): void
    {
        Storage::fake('public');

        $venue = Venue::factory()->forCanal($this->canalPrimary->id)->create();
        $upload = UploadedFile::fake()->image('venue-dashboard-store-no-type.jpg');

        $payload = [
            'fileable_type' => 'venue',
            'fileable_id' => $venue->id,
            'files' => [$upload],
        ];

        $response = $this->post('/api/dashboard/files', $payload, ['Accept' => 'application/json']);

        $response->assertStatus(201);

        $file = File::query()
            ->where('fileable_type', Venue::class)
            ->where('fileable_id', $venue->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($file);
        $this->assertStringStartsWith('image/', (string) $file->mime_type);
        $this->assertTrue($file->is_primary);
    }

    #[Test]
    public function dashboard_file_store_does_not_mark_uploaded_venue_image_as_primary_when_venue_already_has_primary_image(): void
    {
        Storage::fake('public');

        $venue = Venue::factory()->forCanal($this->canalPrimary->id)->create();

        $venue->files()->create([
            'name' => 'existing-venue-primary',
            'original_name' => 'existing-venue-primary.jpg',
            'extension' => 'jpg',
            'size' => 1024,
            'mime_type' => 'image/jpeg',
            'disk' => 'public',
            'path' => 'venue/' . $venue->id . '/image/existing-venue-primary.jpg',
            'checksum' => 'existing-venue-primary-checksum',
            'type' => FileType::IMAGE->value,
            'is_primary' => true,
        ]);

        $upload = UploadedFile::fake()->image('venue-dashboard-secondary.jpg');

        $payload = [
            'fileable_type' => 'venue',
            'fileable_id' => $venue->id,
            'type' => FileType::IMAGE->value,
            'files' => [$upload],
        ];

        $response = $this->post('/api/dashboard/files', $payload, ['Accept' => 'application/json']);

        $response->assertStatus(201);

        $file = File::query()
            ->where('fileable_type', Venue::class)
            ->where('fileable_id', $venue->id)
            ->where('type', FileType::IMAGE->value)
            ->latest('id')
            ->first();

        $this->assertNotNull($file);
        $this->assertFalse($file->is_primary);
    }
}
