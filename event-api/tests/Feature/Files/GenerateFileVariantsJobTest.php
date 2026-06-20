<?php

namespace Tests\Feature\Files;

use App\Enums\FileType;
use App\Jobs\GenerateFileVariantsJob;
use App\Models\File;
use App\Models\User;
use App\Services\Files\ImageVariantGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GenerateFileVariantsJobTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_deletes_original_file_when_generator_marks_it_as_redundant(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put('user/1/file/source.pdf', 'pdf');
        Storage::disk('public')->put('user/1/file/source_thumb.jpg', 'thumb');
        Storage::disk('public')->put('user/1/file/source_large.jpg', 'large');

        $user = User::factory()->create();
        $file = $this->createFile($user, [
            'original_name' => 'source.pdf',
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'path' => 'user/1/file/source.pdf',
        ]);

        $generator = Mockery::mock(ImageVariantGenerator::class);
        $generator->shouldReceive('generate')
            ->once()
            ->with('public', 'user/1/file/source.pdf')
            ->andReturn([
                'thumb' => 'user/1/file/source_thumb.jpg',
                'large' => 'user/1/file/source_large.jpg',
                'delete_original' => true,
            ]);

        (new GenerateFileVariantsJob((int) $file->id))->handle($generator);

        $this->assertFalse(Storage::disk('public')->exists('user/1/file/source.pdf'));
        $this->assertTrue(Storage::disk('public')->exists('user/1/file/source_thumb.jpg'));
        $this->assertTrue(Storage::disk('public')->exists('user/1/file/source_large.jpg'));

        $file->refresh();

        $this->assertSame('generated', $file->meta['variant_generation']['status'] ?? null);
        $this->assertTrue((bool) ($file->meta['variant_generation']['original_deleted'] ?? false));
        $this->assertStringEndsWith('/storage/user/1/file/source_large.jpg', (string) $file->original_file_url);
        $this->assertStringEndsWith('/storage/user/1/file/source_thumb.jpg', (string) $file->thumb_image_url);
    }

    #[Test]
    public function it_keeps_original_image_when_only_thumb_was_generated(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put('user/1/image/tiny.jpg', 'tiny-original');
        Storage::disk('public')->put('user/1/image/tiny_thumb.jpg', 'tiny-thumb');

        $user = User::factory()->create();
        $file = $this->createFile($user, [
            'original_name' => 'tiny.jpg',
            'extension' => 'jpg',
            'mime_type' => 'image/jpeg',
            'type' => FileType::IMAGE->value,
            'path' => 'user/1/image/tiny.jpg',
        ]);

        $generator = Mockery::mock(ImageVariantGenerator::class);
        $generator->shouldReceive('generate')
            ->once()
            ->with('public', 'user/1/image/tiny.jpg')
            ->andReturn([
                'thumb' => 'user/1/image/tiny_thumb.jpg',
                'large' => null,
                'delete_original' => false,
            ]);

        (new GenerateFileVariantsJob((int) $file->id))->handle($generator);

        $this->assertTrue(Storage::disk('public')->exists('user/1/image/tiny.jpg'));
        $this->assertTrue(Storage::disk('public')->exists('user/1/image/tiny_thumb.jpg'));

        $file->refresh();

        $this->assertSame('generated', $file->meta['variant_generation']['status'] ?? null);
        $this->assertArrayNotHasKey('original_deleted', $file->meta['variant_generation'] ?? []);
        $this->assertStringEndsWith('/storage/user/1/image/tiny.jpg', (string) $file->original_file_url);
        $this->assertStringEndsWith('/storage/user/1/image/tiny_thumb.jpg', (string) $file->thumb_image_url);
        $this->assertStringEndsWith('/storage/user/1/image/tiny.jpg', (string) $file->large_image_url);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createFile(User $user, array $overrides = []): File
    {
        return File::create(array_merge([
            'fileable_id' => $user->id,
            'fileable_type' => User::class,
            'name' => 'test-file',
            'original_name' => 'test-file.jpg',
            'extension' => 'jpg',
            'size' => 1234,
            'mime_type' => 'image/jpeg',
            'disk' => 'public',
            'path' => 'uploads/test-file.jpg',
            'thumb' => null,
            'large' => null,
            'checksum' => 'checksum',
            'type' => FileType::FILE->value,
            'is_primary' => false,
            'meta' => null,
        ], $overrides));
    }
}
