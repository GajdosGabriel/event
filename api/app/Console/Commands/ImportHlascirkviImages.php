<?php

namespace App\Console\Commands;

use App\Enums\FileType;
use App\Models\Event;
use App\Services\Files\FileManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Druhý priebeh prenosu archívu: ku každému importovanému podujatiu stiahne
 * jeden obrázok z hlascirkvi.sk a uloží ho cez FileManager (teda aj na S3, aj
 * s dogenerovaním variantov).
 *
 * Zdrojom nie je JSONL ani stará databáza — cestu k obrázku si uložil import
 * podujatí do `meta.import.image_path`, takže tento príkaz vystačí s vlastnou
 * databázou a dá sa spustiť kedykoľvek neskôr.
 *
 * Beh je dávkový a nadväzujúci (`--max-seconds`, kurzor), aby prešiel aj cez
 * webcron, ktorý má na jednu požiadavku len sekundy.
 */
class ImportHlascirkviImages extends Command
{
    private const CURSOR_KEY = 'hlascirkvi_import.last_image_event_id';

    protected $signature = 'app:hlascirkvi-import-images
        {--limit=0 : Stiahnuť najviac N obrázkov (0 = všetky)}
        {--max-seconds=0 : Ukončiť beh po N sekundách (0 = bez limitu); pre webcron}
        {--disk= : Cieľový disk (predvolene FILESYSTEM_DISK); na skúšku sa hodí "public"}
        {--reset-cursor : Začať znovu od najstaršieho podujatia}
        {--redownload : Stiahne obrázok aj pre podujatia, ktoré už nejaký majú}';

    protected $description = 'Stiahne obrázky archívnych podujatí z hlascirkvi do úložiska';

    public function handle(FileManager $fileManager): int
    {
        $baseUrl = rtrim((string) config('services.imports.legacy.hlascirkvi_base_url'), '/');
        $limit = (int) $this->option('limit');
        $maxSeconds = (int) $this->option('max-seconds');
        $redownload = (bool) $this->option('redownload');
        $disk = $this->option('disk') ? (string) $this->option('disk') : null;

        if ($this->option('reset-cursor')) {
            Cache::forget(self::CURSOR_KEY);
        }

        $cursor = $redownload ? 0 : (int) Cache::get(self::CURSOR_KEY, 0);
        $startedAt = microtime(true);
        $stats = ['stored' => 0, 'skipped' => 0, 'failed' => 0];
        $lastId = $cursor;

        $query = Event::query()
            ->where('meta->import->source', 'hlascirkvi_legacy')
            ->where('id', '>', $cursor)
            ->orderBy('id');

        foreach ($query->lazyById(100) as $event) {
            $lastId = $event->id;
            $imagePath = ltrim((string) ($event->meta['import']['image_path'] ?? ''), '/');

            if ($imagePath === '') {
                continue;
            }

            if (! $redownload && $event->files()->where('type', FileType::IMAGE->value)->exists()) {
                $stats['skipped']++;

                continue;
            }

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

                $stored->isEmpty() ? $stats['failed']++ : $stats['stored']++;
            } catch (Throwable $e) {
                $stats['failed']++;
                Log::error('hlascirkvi import: obrázok zlyhal', [
                    'event_id' => $event->id,
                    'image_path' => $imagePath,
                    'message' => $e->getMessage(),
                ]);
            }

            if ($stats['stored'] > 0 && $stats['stored'] % 100 === 0) {
                $this->line("  … stiahnutých {$stats['stored']}");
            }

            if ($limit > 0 && $stats['stored'] >= $limit) {
                break;
            }
            if ($maxSeconds > 0 && (microtime(true) - $startedAt) >= $maxSeconds) {
                $this->line("  … časový limit {$maxSeconds}s, pokračuje sa v ďalšom behu");
                break;
            }
        }

        if (! $redownload) {
            Cache::forever(self::CURSOR_KEY, $lastId);
        }

        $this->table(
            ['stiahnuté', 'preskočené', 'chybné'],
            [[$stats['stored'], $stats['skipped'], $stats['failed']]],
        );

        return self::SUCCESS;
    }
}
