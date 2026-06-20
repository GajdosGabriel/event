<?php

namespace App\Services\OpenAI;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AttachmentDownloader
{
    public function download(array $attachments, int $eventId): array
    {
        $downloaded = [];

        foreach ($attachments as $attachment) {
            try {
                $url = $attachment['url'] ?? null;
                if (!$url) {
                    continue;
                }

                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_TIMEOUT => 60,
                    CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; PHP Bot)',
                ]);

                $content = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
                curl_close($ch);

                if ($httpCode !== 200 || !$content) {
                    Log::warning('Nepodarilo sa stiahnut prilohu: ' . $url);
                    continue;
                }

                $originalName = $attachment['name'] ?? 'priloha.pdf';
                $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
                $fileName = 'event_' . $eventId . '_' . time() . '_' . $safeName;
                $path = 'attachments/' . $fileName;

                Storage::disk('public')->put($path, $content);

                $downloaded[] = [
                    'name' => $originalName,
                    'file_path' => $path,
                    'original_url' => $url,
                    'file_size' => strlen($content),
                    'mime_type' => $contentType,
                ];
            } catch (\Throwable $e) {
                Log::error('Chyba pri stahovani prilohy: ' . $e->getMessage());
            }
        }

        return $downloaded;
    }
}
