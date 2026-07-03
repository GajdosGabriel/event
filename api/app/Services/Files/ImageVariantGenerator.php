<?php

namespace App\Services\Files;

use App\Services\Pdf\PdfPreviewRenderer;
use Illuminate\Support\Facades\Storage;

class ImageVariantGenerator
{
    public function __construct(
        private readonly PdfPreviewRenderer $pdfPreviewRenderer,
    ) {}

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
        $isDocumentPreview = false;
        if (!$source) {
            $source = $this->pdfPreviewRenderer->renderFirstPage($binary, basename($originalPath));
            $isDocumentPreview = true;
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
            // The original of a document preview (PDF/DOC) is the source document itself,
            // not a redundant full-res image — it must never be deleted, unlike a plain
            // image original that's been superseded by its own resized variants.
            'delete_original' => $isDocumentPreview ? false : $this->shouldDeleteOriginal($thumb, $large),
        ];
    }

    private function shouldDeleteOriginal(?string $thumb, ?string $large): bool
    {
        return $large !== null;
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
