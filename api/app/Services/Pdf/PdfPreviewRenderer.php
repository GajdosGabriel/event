<?php

namespace App\Services\Pdf;

use App\Services\Imports\PdfConverterService;

/**
 * Renders the first page of a PDF (or other Imagick-readable document) as a raster
 * image, for use as a preview/thumbnail. Tries the Imagick PHP extension first and
 * falls back to the external PDF_CONVERTER_URL service when Imagick isn't installed.
 *
 * Shared between direct file uploads (ImageVariantGenerator) and event imports
 * (EventImportService) so both keep the original document alongside its preview.
 */
class PdfPreviewRenderer
{
    public function __construct(
        private readonly PdfConverterService $pdfConverter,
    ) {}

    public function renderFirstPage(string $binary, string $filename): \GdImage|false
    {
        $viaImagick = $this->viaImagick($binary);

        return $viaImagick ?: $this->viaConverter($binary, $filename);
    }

    private function viaImagick(string $binary): \GdImage|false
    {
        $imagickClass = 'Imagick';
        if (!class_exists($imagickClass)) {
            return false;
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'pdf_preview_');
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

    private function viaConverter(string $binary, string $filename): \GdImage|false
    {
        $result = $this->pdfConverter->convertFromBinary($binary, $filename);
        if ($result === null || $result->pages === []) {
            return false;
        }

        $firstPage = collect($result->pages)->sortBy('page')->first() ?? $result->pages[0];
        $pageImage = $this->pdfConverter->decodePageImage($firstPage);
        if ($pageImage === null) {
            return false;
        }

        return @imagecreatefromstring($pageImage);
    }
}
