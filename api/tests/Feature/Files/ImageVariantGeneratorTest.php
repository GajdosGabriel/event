<?php

namespace Tests\Feature\Files;

use App\Services\Files\ImageVariantGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImageVariantGeneratorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_keeps_medium_image_original_when_only_thumb_is_generated(): void
    {
        Storage::fake('public');

        $path = 'event/1/image/medium.jpg';
        Storage::disk('public')->put($path, $this->jpegBinary(640, 453));

        $variants = app(ImageVariantGenerator::class)->generate('public', $path);

        $this->assertSame('event/1/image/medium_thumb.jpg', $variants['thumb']);
        $this->assertNull($variants['large']);
        $this->assertFalse($variants['delete_original']);
        $this->assertTrue(Storage::disk('public')->exists('event/1/image/medium_thumb.jpg'));
        $this->assertTrue(Storage::disk('public')->exists($path));
    }

    #[Test]
    public function it_marks_large_image_original_for_deletion_after_generating_both_variants(): void
    {
        Storage::fake('public');

        $path = 'event/1/image/large.jpg';
        Storage::disk('public')->put($path, $this->jpegBinary(2000, 1413));

        $variants = app(ImageVariantGenerator::class)->generate('public', $path);

        $this->assertSame('event/1/image/large_thumb.jpg', $variants['thumb']);
        $this->assertSame('event/1/image/large_large.jpg', $variants['large']);
        $this->assertTrue($variants['delete_original']);
        $this->assertTrue(Storage::disk('public')->exists('event/1/image/large_thumb.jpg'));
        $this->assertTrue(Storage::disk('public')->exists('event/1/image/large_large.jpg'));
    }

    private function jpegBinary(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        $background = imagecolorallocate($image, 220, 220, 220);
        imagefill($image, 0, 0, $background);

        ob_start();
        imagejpeg($image, null, 90);
        $binary = ob_get_clean();

        imagedestroy($image);

        return is_string($binary) ? $binary : '';
    }
}
