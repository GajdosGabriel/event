<?php

namespace App\Services\Files;

use App\Jobs\GenerateFileVariantsJob;
use App\Enums\FileType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class RemoteAttachmentPersister
{
    public function __construct(
        private readonly \App\Services\Files\FileDisplayNameResolver $fileDisplayNameResolver = new \App\Services\Files\FileDisplayNameResolver(),
        private readonly FileDuplicator $fileDuplicator = new FileDuplicator(),
    ) {}

    /**
     * @param Collection<int, array<string, mixed>> $attachments
     * @param array<string, mixed> $meta
     */
    public function store(
        Model $model,
        Collection $attachments,
        FileType $type,
        string $disk,
        string $storageDirectory,
        bool $makePrimary,
        array $meta
    ): Collection {
        return $attachments->values()->map(function (array $attachment, int $index) use ($model, $type, $disk, $storageDirectory, $makePrimary, $meta) {
            $url = (string) $attachment['url'];

            $response = Http::timeout(60)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; Event API Bot)'])
                ->get($url);

            if (!$response->successful()) {
                throw new InvalidArgumentException('Failed to download remote file: ' . $url);
            }

            $content = $response->body();
            if ($content === '') {
                throw new InvalidArgumentException('Remote file is empty: ' . $url);
            }

            $originalName = $this->resolveOriginalName($attachment);
            $safeOriginalName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName) ?: ('remote_file_' . time());
            $extension = strtolower((string) pathinfo($safeOriginalName, PATHINFO_EXTENSION));

            $mimeType = (string) $response->header('Content-Type', 'application/octet-stream');
            if ($mimeType !== '' && str_contains($mimeType, ';')) {
                $mimeType = trim((string) Str::before($mimeType, ';'));
            }

            $this->validateRemoteContent($type, $mimeType, $content, $url);

            $checksum = hash('sha256', $content);
            $existingFile = $model->files()
                ->where('type', $type->value)
                ->where('checksum', $checksum)
                ->latest('id')
                ->first();

            if ($existingFile instanceof \App\Models\File) {
                if ($makePrimary && $index === 0 && ! $existingFile->is_primary) {
                    $existingFile->forceFill(['is_primary' => true])->save();
                }

                return $existingFile->fresh();
            }

            if ($extension === '') {
                $extension = $this->extensionFromMimeType($mimeType);
                if ($extension !== '') {
                    $safeOriginalName .= '.' . $extension;
                }
            }

            // Ten istý plagát visí pri viacerých podujatiach — v produkcii bol
            // jeden PNG stiahnutý jedenásťkrát. Keď už rovnaký obsah niekde
            // máme, netreba ho nahrávať znova ani znova prehnať generovaním
            // variantov: objekty sa prekopírujú na disku aj s hotovým thumb
            // a large. Rozhoduje checksum stiahnutého tela, nie odkaz, takže
            // zámena obrázka na zdroji sa nedá prehliadnuť.
            //
            // Kópia, nie zdieľanie cesty — dôvod drží FileDuplicator.
            $reused = $this->reuseStoredCopy($model, $checksum, $type, $safeOriginalName, $extension, $makePrimary && $index === 0, $meta, $attachment, $url);

            if ($reused !== null) {
                return $reused;
            }

            $storedFileName = Str::random(40) . ($extension !== '' ? '.' . $extension : '');
            $path = trim($storageDirectory, '/') . '/' . $storedFileName;

            $stored = Storage::disk($disk)->put($path, $content);
            if (!$stored) {
                throw new InvalidArgumentException('Failed to store remote file: ' . $url);
            }

            $file = $model->files()->create([
                'name' => $this->fileDisplayNameResolver->resolve($model, $originalName),
                'original_name' => $safeOriginalName,
                'extension' => $extension !== '' ? $extension : null,
                'size' => strlen($content),
                'mime_type' => $mimeType !== '' ? $mimeType : null,
                'disk' => $disk,
                'path' => $path,
                'checksum' => $checksum,
                'type' => $type->value,
                'is_primary' => $makePrimary && $index === 0,
                'meta' => array_merge($meta, [
                    'source' => 'remote_attachment',
                    'source_url' => $url,
                    'source_name' => $attachment['name'] ?? null,
                    'source_link_text' => $attachment['link_text'] ?? null,
                    'source_size' => $attachment['size'] ?? null,
                ]),
            ]);

            GenerateFileVariantsJob::dispatch((int) $file->id);

            return $file;
        });
    }

    /**
     * @param array<string, mixed> $attachment
     */
    /**
     * Prekopíruje už uložený súbor s rovnakým obsahom, ak nejaký existuje.
     *
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $attachment
     * @return \App\Models\File|null  null, keď sa nedá použiť a treba ukladať normálne
     */
    private function reuseStoredCopy(
        Model $model,
        string $checksum,
        FileType $type,
        string $safeOriginalName,
        string $extension,
        bool $makePrimary,
        array $meta,
        array $attachment,
        string $url,
    ): ?\App\Models\File {
        $source = \App\Models\File::query()
            ->where('checksum', $checksum)
            ->where('type', $type->value)
            ->whereNotNull('path')
            // Vlastné prílohy tohto záznamu rieši kontrola vyššie; sem patrí
            // len to, čo už visí inde.
            ->where(fn ($query) => $query
                ->where('fileable_type', '!=', $model->getMorphClass())
                ->orWhere('fileable_id', '!=', $model->getKey()))
            ->oldest('id')
            ->first();

        if ($source === null) {
            return null;
        }

        // Vráti null aj vtedy, keď zdrojové objekty na disku medzitým zmizli
        // (napr. po zmazaní originálu po vygenerovaní variantov) — vtedy sa
        // ide normálnou cestou a súbor sa uloží nanovo.
        return $this->fileDuplicator->copyTo($source, $model, [
            'name' => $this->fileDisplayNameResolver->resolve($model, $safeOriginalName),
            'original_name' => $safeOriginalName,
            'extension' => $extension !== '' ? $extension : null,
            'is_primary' => $makePrimary,
            'sort_order' => 0,
            'meta' => array_merge($meta, [
                'source' => 'remote_attachment',
                'source_url' => $url,
                'source_name' => $attachment['name'] ?? null,
                'source_link_text' => $attachment['link_text'] ?? null,
                'source_size' => $attachment['size'] ?? null,
                'reused_from' => $source->id,
            ]),
        ]);
    }
    private function resolveOriginalName(array $attachment): string
    {
        $url = (string) ($attachment['url'] ?? '');
        $linkText = trim((string) ($attachment['link_text'] ?? ''));
        if ($linkText !== '' && str_contains($linkText, '.')) {
            return $linkText;
        }

        $query = [];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        if (isset($query['file']) && is_string($query['file'])) {
            $fromQuery = basename($query['file']);
            if ($fromQuery !== '') {
                return $fromQuery;
            }
        }

        $name = trim((string) ($attachment['name'] ?? ''));
        if ($name !== '' && $name !== 'subor.html') {
            return $name;
        }

        return 'remote_file_' . time() . '.bin';
    }

    private function extensionFromMimeType(string $mimeType): string
    {
        return match (strtolower($mimeType)) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'text/plain' => 'txt',
            default => '',
        };
    }

    private function validateRemoteContent(FileType $type, string $mimeType, string $content, string $url): void
    {
        if ($type !== FileType::IMAGE) {
            return;
        }

        $normalizedMime = strtolower(trim($mimeType));
        if ($normalizedMime === '' || ! str_starts_with($normalizedMime, 'image/')) {
            throw new InvalidArgumentException('Remote file is not an image: ' . $url);
        }

        $allowedMimes = config('services.venue_detection.attach.allowed_mime_types', [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
        ]);

        if (is_string($allowedMimes)) {
            $allowedMimes = array_values(array_filter(array_map('trim', explode(',', $allowedMimes)), fn (string $item) => $item !== ''));
        }

        if (is_array($allowedMimes) && $allowedMimes !== []) {
            $allowedMimes = array_map(fn ($item) => strtolower((string) $item), $allowedMimes);

            if (! in_array($normalizedMime, $allowedMimes, true)) {
                throw new InvalidArgumentException('Remote image mime type is not allowed: ' . $normalizedMime);
            }
        }

        $maxBytes = max(1, (int) config('services.venue_detection.attach.max_bytes', 10485760));
        if (strlen($content) > $maxBytes) {
            throw new InvalidArgumentException('Remote image is too large: ' . $url);
        }
    }
}
