<?php

namespace App\Services\Files;

use Illuminate\Support\Facades\Storage;

class ImageVariantGenerator
{
    /**
     * @return array{thumb: ?string, large: ?string, delete_original: bool}
     */
    public function generate(string $disk, string $originalPath, int $thumbLongEdge = 320, int $largeLongEdge = 1280): array
    {
        if (!Storage::disk($disk)->exists($originalPath)) {
            return ['thumb' => null, 'large' => null, 'delete_original' => false];
        }

        $binary = Storage::disk($disk)->get($originalPath);
        if ($binary === '') {
            return ['thumb' => null, 'large' => null, 'delete_original' => false];
        }

        $source = @imagecreatefromstring($binary);
        if (!$source) {
            $source = $this->createImageFromDocumentPreview($binary);
        }

        if (!$source) {
            return ['thumb' => null, 'large' => null, 'delete_original' => false];
        }

        $width = imagesx($source);
        $height = imagesy($source);

        $thumb = $this->storeVariant($disk, $originalPath, $source, $width, $height, $thumbLongEdge, 'thumb', true);
        $large = $this->storeVariant($disk, $originalPath, $source, $width, $height, $largeLongEdge, 'large', false);

        imagedestroy($source);

        return [
            'thumb' => $thumb,
            'large' => $large,
            'delete_original' => $this->shouldDeleteOriginal($thumb, $large),
        ];
    }

    private function shouldDeleteOriginal(?string $thumb, ?string $large): bool
    {
        return $large !== null;
    }

    private function createImageFromDocumentPreview(string $binary): \GdImage|false
    {
        $imagickClass = 'Imagick';
        if (!class_exists($imagickClass)) {
            return false;
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'event_file_preview_');
        if ($tmpPath === false) {
            return false;
        }

        file_put_contents($tmpPath, $binary);

        try {
            /** @var object $imagick */
            $imagick = new $imagickClass();
            $imagick->setResolution(150, 150);
            $imagick->readImage($tmpPath . '[0]');
            $imagick->setImageBackgroundColor('white');
            $imagick->setImageFormat('jpeg');
            $blob = $imagick->getImageBlob();
            $imagick->clear();
            $imagick->destroy();

            return @imagecreatefromstring($blob);
        } catch (\Throwable) {
            return false;
        } finally {
            @unlink($tmpPath);
        }
    }

    private function storeVariant(
        string $disk,
        string $originalPath,
        \GdImage $source,
        int $width,
        int $height,
        int $maxLongEdge,
        string $suffix,
        bool $alwaysGenerate
    ): ?string {
        $longEdge = max($width, $height);
        if (!$alwaysGenerate && $longEdge <= $maxLongEdge) {
            return null;
        }

        $ratio = min(1, $maxLongEdge / max(1, $longEdge));
        $newWidth = max(1, (int) round($width * $ratio));
        $newHeight = max(1, (int) round($height * $ratio));

        $target = imagecreatetruecolor($newWidth, $newHeight);
        if (!$target) {
            return null;
        }

        imagealphablending($target, false);
        imagesavealpha($target, true);

        imagecopyresampled($target, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        $variantPath = $this->variantPath($originalPath, $suffix);
        $payload = $this->toBinary($target, $variantPath);

        imagedestroy($target);

        if ($payload === null) {
            return null;
        }

        Storage::disk($disk)->put($variantPath, $payload);

        return $variantPath;
    }

    private function variantPath(string $originalPath, string $suffix): string
    {
        $info = pathinfo($originalPath);
        $dirname = ($info['dirname'] ?? '.') !== '.' ? $info['dirname'] : '';
        $filename = $info['filename'] ?? 'file';
        $extension = strtolower((string) ($info['extension'] ?? 'jpg'));

        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $extension = 'jpg';
        }

        $name = $filename . '_' . $suffix . '.' . $extension;

        return $dirname !== '' ? ($dirname . '/' . $name) : $name;
    }

    private function toBinary(\GdImage $image, string $variantPath): ?string
    {
        $extension = strtolower((string) pathinfo($variantPath, PATHINFO_EXTENSION));

        ob_start();
        $saved = match ($extension) {
            'png' => imagepng($image, null, 8),
            'webp' => function_exists('imagewebp') ? imagewebp($image, null, 80) : imagejpeg($image, null, 82),
            default => imagejpeg($image, null, 82),
        };
        $binary = ob_get_clean();

        if (!$saved || !is_string($binary) || $binary === '') {
            return null;
        }

        return $binary;
    }
}
