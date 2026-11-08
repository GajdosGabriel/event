<?php

namespace App\Console\Commands;

use App\Enums\CanalIdentityMode;
use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Services\Canals\CanalLogoFinder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Doplní kanálom organizácií bez obrázka logo z ich webu (CanalLogoFinder).
 *
 * Kanál, ktorý už nejaký obrázok má, sa nedotkne — ani keď ho niekto
 * zmazal zámerne. Pri webe bez použiteľného loga si príkaz na 30 dní
 * zapamätá, že ho skúšal (--force to obíde).
 */
class FindCanalLogos extends Command
{
    protected $signature = 'app:canals-find-logos
        {--canal=* : ID konkrétnych kanálov}
        {--limit=50 : Maximum kanálov v jednom behu}
        {--dry-run : Len vypíše, čo by nahral}
        {--force : Skúsi aj kanály, pri ktorých posledný beh nič nenašiel}
        {--sleep=300 : Pauza medzi kanálmi v ms}';

    protected $description = 'Nájde logá kanálov organizácií na ich weboch a nahrá ich ako hlavný obrázok';

    private const MISS_TTL_DAYS = 30;

    public function handle(CanalLogoFinder $finder): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $ids = array_filter(array_map('intval', (array) $this->option('canal')));
        $limit = max(1, (int) $this->option('limit'));

        $query = Canal::query()
            ->where('identity_mode', CanalIdentityMode::Organization->value)
            ->whereIn('status', [ModelStatus::Published->value, ModelStatus::Draft->value])
            ->whereNotNull('website')->where('website', '<>', '')
            ->whereDoesntHave('images')
            ->orderBy('id');

        if ($ids !== []) {
            $query->whereIn('id', $ids);
        }

        $processed = $found = $stored = $skipped = 0;
        $bySource = [];

        foreach ($query->lazy() as $canal) {
            if ($processed >= $limit) {
                break;
            }

            $missKey = 'canal-logos:miss:'.$canal->id;

            if (! $this->option('force') && $ids === [] && Cache::has($missKey)) {
                continue;
            }

            if ($reason = $finder->skipReason($canal)) {
                $skipped++;
                $this->line(sprintf(' · #%d %s → preskočený (%s)', $canal->id, $canal->name, $reason), verbosity: $ids === [] ? 'v' : null);

                continue;
            }

            if ($processed > 0 && (int) $this->option('sleep') > 0) {
                usleep((int) $this->option('sleep') * 1000);
            }

            $processed++;
            $logo = $finder->find($canal);

            if ($logo === null) {
                $this->line(sprintf(' - #%d %s (%s) → nenájdené', $canal->id, $canal->name, $canal->website));

                if (! $dryRun) {
                    Cache::put($missKey, true, now()->addDays(self::MISS_TTL_DAYS));
                }

                continue;
            }

            $found++;
            $bySource[$logo['source']] = ($bySource[$logo['source']] ?? 0) + 1;
            $this->line(sprintf(' + #%d %s → [%s %dx%d] %s', $canal->id, $canal->name, $logo['source'], $logo['width'], $logo['height'], $logo['url']));

            if ($dryRun) {
                continue;
            }

            try {
                if ($finder->store($canal, $logo)) {
                    $stored++;
                }
            } catch (\Throwable $e) {
                $this->warn('     nepodarilo sa uložiť: '.$e->getMessage());
            }
        }

        ksort($bySource);
        $sources = collect($bySource)->map(fn ($n, $s) => "$s: $n")->implode(', ');

        $this->info(sprintf(
            'Kanály: %d, s logom: %d%s, uložených: %d, preskočených: %d%s',
            $processed, $found, $sources !== '' ? " ($sources)" : '', $stored, $skipped, $dryRun ? ' (dry-run)' : '',
        ));

        return self::SUCCESS;
    }
}
