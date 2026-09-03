<?php

namespace Tests\Feature\Files;

use App\Enums\FileType;
use App\Jobs\ImportSocialAvatarJob;
use App\Models\Canal;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImportSocialAvatarJobTest extends TestCase
{
    use DatabaseTransactions;

    private function fakeAvatarResponse(): void
    {
        Http::fake([
            'https://lh3.googleusercontent.com/*' => Http::response(
                (string) file_get_contents(__DIR__ . '/../../../public/images/default.svg'),
                200,
                ['Content-Type' => 'image/png'],
            ),
        ]);
    }

    #[Test]
    public function it_stores_the_downloaded_avatar_as_the_primary_canal_image(): void
    {
        Storage::fake('public');
        $this->fakeAvatarResponse();

        $canal = Canal::factory()->create();

        (new ImportSocialAvatarJob((int) $canal->id, 'https://lh3.googleusercontent.com/a/abc=s512-c'))
            ->handle(app(\App\Services\Files\FileManager::class));

        $file = $canal->files()->where('type', FileType::IMAGE->value)->latest('id')->first();

        $this->assertNotNull($file);
        $this->assertTrue($file->is_primary);
        $this->assertTrue($file->meta['social_avatar'] ?? false);
        Storage::disk('public')->assertExists($file->path);
    }

    #[Test]
    public function it_keeps_an_image_the_canal_already_has(): void
    {
        Storage::fake('public');
        $this->fakeAvatarResponse();

        $canal = Canal::factory()->create();
        $canal->files()->create([
            'name' => 'vlastny.jpg',
            'original_name' => 'vlastny.jpg',
            'type' => FileType::IMAGE->value,
            'disk' => 'public',
            'path' => 'canals/vlastny.jpg',
            'size' => 1024,
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'is_primary' => true,
        ]);

        (new ImportSocialAvatarJob((int) $canal->id, 'https://lh3.googleusercontent.com/a/abc=s512-c'))
            ->handle(app(\App\Services\Files\FileManager::class));

        $this->assertSame(1, $canal->files()->where('type', FileType::IMAGE->value)->count());
    }

    #[Test]
    public function a_failed_download_does_not_throw(): void
    {
        Storage::fake('public');
        Http::fake(['https://lh3.googleusercontent.com/*' => Http::response('nope', 404)]);

        $canal = Canal::factory()->create();

        (new ImportSocialAvatarJob((int) $canal->id, 'https://lh3.googleusercontent.com/a/abc=s512-c'))
            ->handle(app(\App\Services\Files\FileManager::class));

        $this->assertSame(0, $canal->files()->where('type', FileType::IMAGE->value)->count());
    }
}
