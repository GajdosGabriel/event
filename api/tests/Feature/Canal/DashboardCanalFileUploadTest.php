<?php

namespace Tests\Feature\Canal;

use App\Enums\FileType;
use App\Models\Canal;
use App\Models\File;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\CanalSetupTest;

class DashboardCanalFileUploadTest extends CanalSetupTest
{
    use RefreshDatabase;

    #[Test]
    public function dashboard_store_canal_saves_uploaded_files(): void
    {
        Storage::fake('public');

        $municipalityId = (int) DB::table('municipalities')->value('id');
        $upload = UploadedFile::fake()->create('canal-dashboard-store.pdf', 120, 'application/pdf');

        $payload = [
            'name' => 'Dashboard Canal File Store ' . uniqid(),
            'municipality_id' => $municipalityId,
            'body' => 'Canal with uploaded dashboard file.',
            'file_type' => FileType::FILE->value,
            'make_primary_file' => true,
            'files' => [$upload],
        ];

        $response = $this->post('/api/dashboard/canals', $payload, ['Accept' => 'application/json']);

        $response->assertStatus(201);

        $canal = Canal::query()->where('name', $payload['name'])->firstOrFail();
        $file = File::query()
            ->where('fileable_type', Canal::class)
            ->where('fileable_id', $canal->id)
            ->where('type', FileType::FILE->value)
            ->first();

        $this->assertNotNull($file);
        $this->assertSame($canal->name, $file->name);
        $this->assertTrue((bool) $file->is_primary);
        $this->assertTrue(Storage::disk('public')->exists($file->path));
    }

    #[Test]
    public function dashboard_update_canal_saves_uploaded_files(): void
    {
        Storage::fake('public');

        $canal = $this->canalPrimary;
        $municipalityId = (int) DB::table('municipalities')->value('id');
        $upload = UploadedFile::fake()->create('canal-dashboard-update.pdf', 120, 'application/pdf');

        $payload = [
            '_method' => 'PUT',
            'name' => $canal->name . ' Updated ' . uniqid(),
            'municipality_id' => $municipalityId,
            'body' => 'Updated canal with new uploaded file.',
            'file_type' => FileType::FILE->value,
            'make_primary_file' => true,
            'files' => [$upload],
        ];

        $response = $this->post('/api/dashboard/canals/' . $canal->id, $payload, ['Accept' => 'application/json']);

        $response->assertStatus(200);

        $file = File::query()
            ->where('fileable_type', Canal::class)
            ->where('fileable_id', $canal->id)
            ->where('type', FileType::FILE->value)
            ->latest('id')
            ->first();

        $this->assertNotNull($file);
        $this->assertTrue(Storage::disk('public')->exists($file->path));
    }

    #[Test]
    public function dashboard_store_canal_marks_first_uploaded_image_as_primary_when_canal_has_no_primary_image(): void
    {
        Storage::fake('public');

        $municipalityId = (int) DB::table('municipalities')->value('id');
        $upload = UploadedFile::fake()->image('canal-dashboard-store.jpg');

        $payload = [
            'name' => 'Dashboard Canal Image Store ' . uniqid(),
            'municipality_id' => $municipalityId,
            'body' => 'Canal with uploaded dashboard image.',
            'file_type' => FileType::IMAGE->value,
            'files' => [$upload],
        ];

        $response = $this->post('/api/dashboard/canals', $payload, ['Accept' => 'application/json']);

        $response->assertStatus(201);

        $canal = Canal::query()->where('name', $payload['name'])->firstOrFail();
        $file = File::query()
            ->where('fileable_type', Canal::class)
            ->where('fileable_id', $canal->id)
            ->where('type', FileType::IMAGE->value)
            ->first();

        $this->assertNotNull($file);
        $this->assertTrue((bool) $file->is_primary);
    }

    #[Test]
    public function dashboard_store_canal_marks_first_uploaded_image_as_primary_even_without_file_type_image(): void
    {
        Storage::fake('public');

        $municipalityId = (int) DB::table('municipalities')->value('id');
        $upload = UploadedFile::fake()->image('canal-dashboard-store-no-type.jpg');

        $payload = [
            'name' => 'Dashboard Canal Image Store No Type ' . uniqid(),
            'municipality_id' => $municipalityId,
            'body' => 'Canal with uploaded dashboard image and default file type.',
            'files' => [$upload],
        ];

        $response = $this->post('/api/dashboard/canals', $payload, ['Accept' => 'application/json']);

        $response->assertStatus(201);

        $canal = Canal::query()->where('name', $payload['name'])->firstOrFail();
        $file = File::query()
            ->where('fileable_type', Canal::class)
            ->where('fileable_id', $canal->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($file);
        $this->assertStringStartsWith('image/', (string) $file->mime_type);
        $this->assertTrue((bool) $file->is_primary);
    }

    #[Test]
    public function dashboard_update_canal_does_not_mark_uploaded_image_as_primary_when_canal_already_has_primary_image(): void
    {
        Storage::fake('public');

        $canal = $this->canalPrimary;
        $canal->files()->create([
            'name' => 'existing-canal-primary',
            'original_name' => 'existing-canal-primary.jpg',
            'extension' => 'jpg',
            'size' => 1024,
            'mime_type' => 'image/jpeg',
            'disk' => 'public',
            'path' => 'canal/' . $canal->id . '/image/existing-canal-primary.jpg',
            'checksum' => 'existing-canal-primary-checksum-dashboard',
            'type' => FileType::IMAGE->value,
            'is_primary' => true,
        ]);

        $payload = [
            '_method' => 'PUT',
            'name' => $canal->name . ' Dashboard Secondary Image ' . uniqid(),
            'municipality_id' => $canal->municipality_id,
            'body' => 'Canal update with secondary image.',
            'file_type' => FileType::IMAGE->value,
            'files' => [UploadedFile::fake()->image('canal-dashboard-secondary.jpg')],
        ];

        $response = $this->post('/api/dashboard/canals/' . $canal->id, $payload, ['Accept' => 'application/json']);

        $response->assertStatus(200);

        $file = File::query()
            ->where('fileable_type', Canal::class)
            ->where('fileable_id', $canal->id)
            ->where('mime_type', 'like', 'image/%')
            ->latest('id')
            ->first();

        $this->assertNotNull($file);
        $this->assertFalse((bool) $file->is_primary);
    }
}
