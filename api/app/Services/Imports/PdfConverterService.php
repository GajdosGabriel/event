<?php

namespace App\Services\Imports;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PdfConverterService
{
    private const FALLBACK_URL = 'http://78.47.38.184';

    public function convertFromUrl(string $pdfUrl): ?PdfConvertResult
    {
        $apiUrl = rtrim((string) config('services.pdf_converter.url', self::FALLBACK_URL), '/');
        $token  = (string) config('services.pdf_converter.token', '');

        if ($token === '') {
            Log::debug('PdfConverterService: PDF_CONVERTER_TOKEN not set, skipping', ['url' => $pdfUrl]);
            return null;
        }

        try {
            $pdfResponse = Http::timeout(30)->get($pdfUrl);
            if (!$pdfResponse->successful()) {
                Log::debug('PdfConverterService: PDF download failed', ['url' => $pdfUrl, 'status' => $pdfResponse->status()]);
                return null;
            }
            $contentType = strtolower((string) $pdfResponse->header('Content-Type'));
            if (!str_contains($contentType, 'pdf') && !str_contains($contentType, 'octet-stream')) {
                Log::debug('PdfConverterService: URL did not return a PDF', ['url' => $pdfUrl, 'content_type' => $contentType]);
                return null;
            }
            $pdfContent = $pdfResponse->body();
        } catch (\Throwable $e) {
            Log::warning('PdfConverterService: failed to download PDF', ['url' => $pdfUrl, 'error' => $e->getMessage()]);
            return null;
        }

        $filename = basename((string) parse_url($pdfUrl, PHP_URL_PATH)) ?: 'document.pdf';

        return $this->convert($apiUrl, $token, $pdfContent, $filename, ['url' => $pdfUrl]);
    }

    /**
     * Convert a PDF already held in memory (e.g. a just-uploaded file) without
     * needing a publicly reachable URL to download it from first.
     */
    public function convertFromBinary(string $pdfContent, string $filename): ?PdfConvertResult
    {
        $apiUrl = rtrim((string) config('services.pdf_converter.url', self::FALLBACK_URL), '/');
        $token  = (string) config('services.pdf_converter.token', '');

        if ($token === '') {
            Log::debug('PdfConverterService: PDF_CONVERTER_TOKEN not set, skipping', ['filename' => $filename]);
            return null;
        }

        if ($pdfContent === '') {
            return null;
        }

        return $this->convert($apiUrl, $token, $pdfContent, $filename, ['filename' => $filename]);
    }

    /**
     * @param array<string, string> $logContext
     */
    private function convert(string $apiUrl, string $token, string $pdfContent, string $filename, array $logContext): ?PdfConvertResult
    {
        if (!$this->looksLikePdf($pdfContent)) {
            Log::debug('PdfConverterService: not a PDF, skipping converter', $logContext);
            return null;
        }

        try {
            $response = Http::timeout(120)
                ->acceptJson()
                ->withToken($token)
                ->attach('file', $pdfContent, $filename)
                ->post("{$apiUrl}/api/pdf-convert", [
                    'include_text' => '1',
                    'dpi'          => '150',
                ]);

            if (!$response->successful()) {
                Log::warning('PdfConverterService: converter returned error', [
                    ...$logContext,
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            $data  = $response->json();
            $pages = $data['pages'] ?? [];

            if (!is_array($pages) || $pages === []) {
                return null;
            }

            return new PdfConvertResult(
                pageCount: (int) ($data['page_count'] ?? count($pages)),
                pages: $pages,
            );
        } catch (\Throwable $e) {
            Log::warning('PdfConverterService: conversion failed', [...$logContext, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * The converter endpoint accepts PDFs only and rejects anything else with a 422,
     * so non-PDFs (e.g. a .docx upload looking for a preview) are dropped before the
     * request rather than after a failed round-trip. The %PDF- marker is allowed to
     * sit behind some leading bytes, matching how PDF readers tolerate such files.
     */
    private function looksLikePdf(string $content): bool
    {
        return str_contains(substr($content, 0, 1024), '%PDF-');
    }

    /**
     * Decode a page's base64 "image" field (as returned by the converter) into raw binary.
     */
    public function decodePageImage(array $page): ?string
    {
        $imageData = (string) ($page['image'] ?? '');
        if ($imageData === '') {
            return null;
        }

        $base64 = (string) preg_replace('/^data:[^;]+;base64,/', '', $imageData);
        $binary = base64_decode($base64, true);

        return ($binary === false || $binary === '') ? null : $binary;
    }

    public function pageToUploadedFile(array $page, string $baseName, int $pageNumber): ?UploadedFile
    {
        $binary = $this->decodePageImage($page);
        if ($binary === null) {
            return null;
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'pdf_page_');
        if ($tmpPath === false) {
            return null;
        }

        file_put_contents($tmpPath, $binary);

        $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($baseName, PATHINFO_FILENAME));
        $fileName = ($safeName ?: 'page') . "_page{$pageNumber}.png";

        return new UploadedFile($tmpPath, $fileName, 'image/png', null, true);
    }
}
