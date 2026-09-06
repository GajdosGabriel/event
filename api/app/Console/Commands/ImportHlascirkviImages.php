<?php

namespace App\Console\Commands;

use App\Enums\FileType;
use App\Models\Event;
use App\Services\Files\FileManager;
use App\Services\Imports\HlascirkviSourceUrl;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use SplFileObject;
use Throwable;

/**
 * Druhý priebeh prenosu archívu: ku každému importovanému podujatiu stiahne
 * jeden obrázok z produkcie hlascirkvi.sk a uloží ho cez FileManager (teda aj
 * na S3, aj s dogenerovaním variantov). Beží nezávisle od app:hlascirkvi-import
 * a dá sa opakovať — podujatia, ktoré obrázok už majú, preskočí.
 */
class ImportHlascirkviImages extends Command
{
    protected $signature = 'app:hlascirkvi-import-images
        {--file= : Cesta k JSONL súboru relatívne k storage/app}
        {--limit=0 : Stiahnuť najviac N obrázkov (0 = všetky)}
        {--disk= : Cieľový disk (predvolene FILESYSTEM_DISK); na skúšku sa hodí "public"}
        {--redownload : Stiahne obrázok aj pre podujatia, ktoré už nejaký majú}';

    protected $description = 'Stiahne obrázky archívnych podujatí z hlascirkvi do úložiska';

    public function handle(FileManager $fileManager): int
    {
        $relative = (string) ($this->option('file') ?: config('services.imports.legacy.file'));
        $path = storage_path('app/'.ltrim($relative, '/'));
        if (! is_file($path)) {
            $this->error("Súbor {$path} neexistuje — najprv spusti app:hlascirkvi-export.");

            return self::FAILURE;
        }

        $baseUrl = (string) config('services.imports.legacy.hlascirkvi_base_url');
        $limit = (int) $this->option('limit');
        $redownload = (bool) $this->option('redownload');
        $disk = $this->option('disk') ? (string) $this->option('disk') : null;

        $stats = ['stored' => 0, 'skipped' => 0, 'missing_event' => 0, 'failed' => 0];
        $handle = new SplFileObject($path, 'r');

        while (! $handle->eof()) {
            $line = trim((string) $handle->fgets());
            if ($line === '') {
                continue;
            }

            $row = json_decode($line, true);
            if (! is_array($row) || blank($row['image_path'] ?? null)) {
                continue;
            }

            // Podujatie sa hľadá cez ten istý kľúč, akým ho založil import —
            // `meta` nie je indexované a legacy id sa inde neukladá.
            $event = Event::query()
                ->where('orginal_source', HlascirkviSourceUrl::for($row, $baseUrl))
                ->first();

            if (! $event instanceof Event) {
                $stats['missing_event']++;

                continue;
            }

            if (! $redownload && $event->files()->where('type', FileType::IMAGE->value)->exists()) {
                $stats['skipped']++;

                continue;
            }

            $imagePath = ltrim((string) $row['image_path'], '/');

            try {
                // storeRemoteForEvent() zabezpečí stiahnutie, kontrolný súčet,
                // uloženie na disk z FILESYSTEM_DISK (S3 s prefixom AWS_ROOT)
                // aj naplánovanie GenerateFileVariantsJob pre thumb/large.
                $stored = $fileManager->storeRemoteForEvent(
                    event: $event,
                    attachments: [[
                        'url' => "{$baseUrl}/storage/{$imagePath}",
                        'name' => basename($imagePath),
                    ]],
                    type: FileType::IMAGE,
                    disk: $disk,
                    makePrimary: true,
                    meta: [
                        'source' => 'hlascirkvi_legacy',
                        'legacy_image_path' => $imagePath,
                    ],
                );

                if ($stored->isEmpty()) {
                    $stats['failed']++;

                    continue;
                }

                $stats['stored']++;
            } catch (Throwable $e) {
                $stats['failed']++;
                Log::error('hlascirkvi import: obrázok zlyhal', [
                    'event_id' => $event->id,
                    'image_path' => $imagePath,
                    'message' => $e->getMessage(),
                ]);
            }

            if ($stats['stored'] % 100 === 0 && $stats['stored'] > 0) {
                $this->line("  … stiahnutých {$stats['stored']}");
            }

            if ($limit > 0 && $stats['stored'] >= $limit) {
                break;
            }
        }

        $this->table(
            ['stiahnuté', 'preskočené', 'bez podujatia', 'chybné'],
            [[$stats['stored'], $stats['skipped'], $stats['missing_event'], $stats['failed']]],
        );

        return self::SUCCESS;
    }
}
