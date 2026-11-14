<?php

namespace App\Services\Files;

/**
 * Stiahnutá a zvalidovaná príloha pripravená na zápis.
 *
 * Vzniká mimo DB transakcie (sťahovanie má timeout 60 s), do transakcie ide
 * už len zápis na disk a do DB.
 */
final readonly class RemoteAttachmentDownload
{
    /**
     * @param  array<string, mixed>  $attachment
     */
    public function __construct(
        public array $attachment,
        public string $url,
        public string $content,
        public string $mimeType,
        public string $checksum,
        public string $originalName,
        public string $safeOriginalName,
        public string $extension,
    ) {}
}
