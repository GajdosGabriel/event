<?php

namespace App\Console\Commands;

use App\Enums\CanalIdentityMode;
use App\Enums\FileType;
use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Services\Canals\CanalLogoFinder;
use App\Services\Canals\CanalPhotoFinder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Doplní kanálom organizácií typické fotky z ich webu do galérie
 * (CanalPhotoFinder) — obdoba app:venues-find-images pre miesta.
 *
 * Berie len kanály, ktoré okrem loga z webu (app:canals-find-logos) žiadny
 * obrázok nemajú: vlastné fotky tímu ani zmazanie nič neprepíše. Kanál bez
 * hlavného obrázka dostane prvú fotku ako hlavnú. Pri webe bez fotky si
 * príkaz na 30 dní zapamätá, že ho skúšal (--force to obíde).
 */
class FindCanalPhotos extends Command
{
    protected $signature = 'app:canals-find-photos
        {--canal=* : ID konkrétnych kanálov}
        {--limit=50 : Maximum kanálov v jednom behu}
        {--max=3 : Maximum fotiek na kanál (1–6)}
        {--dry-run : Len vypíše, čo by nahral}
        {--force : Skúsi aj kanály, pri ktorých posledný beh nič nenašiel}
        {--sleep=300 : Pauza medzi kanálmi v ms}';

    protected $description = 'Nájde typické fotky kanálov organizácií na ich weboch a nahrá ich do galérie';

    private const MISS_TTL_DAYS = 30;

    public function handle(CanalPhotoFinder $finder, CanalLogoFinder $logos): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $max = max(1, min(6, (int) $this->option('max')));
        $ids = array_filter(array_map('intval', (array) $this->option('canal')));
        $limit = max(1, (int) $this->option('limit'));

        // Jediný povolený obrázok je logo z webu — všetko ostatné je vlastná
        // voľba tímu alebo už hotový beh tohto príkazu.
        $query = Canal::query()
            ->where('identity_mode', CanalIdentityMode::Organization->value)
            ->whereIn('status', [ModelStatus::Published->value, ModelStatus::Draft->value])
            ->whereNotNull('website')->where('website', '<>', '')
            ->whereDoesntHave('files', fn ($files) => $files
                ->where('type', FileType::IMAGE->value)
                ->where(fn ($q) => $q->whereNull('meta->origin')->orWhere('meta->origin', '<>', CanalLogoFinder::ORIGIN)))
            ->orderBy('id');

        if ($ids !== []) {
            $query->whereIn('id', $ids);
        }

        $processed = $found = $stored = $skipped = 0;

        foreach ($query->lazy() as $canal) {
            if ($processed >= $limit) {
                break;
            }

            $missKey = 'canal-photos:miss:'.$canal->id;

            if (! $this->option('force') && $ids === [] && Cache::has($missKey)) {
                continue;
            }

            $reason = $logos->skipReason($canal);

            if ($reason !== null && $reason !== 'has_image') {
                $skipped++;
                $this->line(sprintf(' · #%d %s → preskočený (%s)', $canal->id, $canal->name, $reason), verbosity: $ids === [] ? 'v' : null);

                continue;
            }

            if ($processed > 0 && (int) $this->option('sleep') > 0) {
                usleep((int) $this->option('sleep') * 1000);
            }

            $processed++;
            $photos = $finder->find($canal, $max);

            if ($photos === []) {
                $this->line(sprintf(' - #%d %s (%s) → nenájdené', $canal->id, $canal->name, $canal->website));

                if (! $dryRun) {
                    Cache::put($missKey, true, now()->addDays(self::MISS_TTL_DAYS));
                }

                continue;
            }

            $found++;
            $this->line(sprintf(' + #%d %s (%d)', $canal->id, $canal->name, count($photos)));

            foreach ($photos as $photo) {
                $this->line(sprintf('     [%s %dx%d] %s', $photo['source'], $photo['width'], $photo['height'], $photo['url']));

                if ($dryRun) {
                    continue;
                }

                try {
                    $finder->store($canal, $photo);
                    $stored++;
                } catch (\Throwable $e) {
                    $this->warn('     nepodarilo sa uložiť: '.$e->getMessage());
                }
            }
        }

        $this->info(sprintf('Kanály: %d, s fotkou: %d, nahraných fotiek: %d, preskočených: %d%s', $processed, $found, $stored, $skipped, $dryRun ? ' (dry-run)' : ''));

        return self::SUCCESS;
    }
}
