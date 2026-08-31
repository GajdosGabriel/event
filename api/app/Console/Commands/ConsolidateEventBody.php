<?php

namespace App\Console\Commands;

use App\Services\Events\EventBodyConsolidator;
use Illuminate\Console\Command;

/**
 * Presunie AI prepis popisu z `body_ai` do `body` (viď EventBodyConsolidator).
 *
 * Na produkcii spustiť s `--dry-run` pred `php artisan migrate`, skontrolovať
 * zoznam dotknutých podujatí, potom migrovať. Migrácia
 * `consolidate_event_body_drop_body_ai` spustí to isté ešte raz ako poistku a
 * až potom stĺpec `body_ai` zahodí.
 */
class ConsolidateEventBody extends Command
{
    protected $signature = 'app:consolidate-event-body {--dry-run : Iba vypísať, čo by sa zmenilo}';

    protected $description = 'Zjednotí popis podujatia — body_ai presunie do body';

    public function handle(EventBodyConsolidator $consolidator): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $summary = $consolidator->run($dryRun);

        foreach ($summary['rows'] as $row) {
            $this->line(sprintf(' - #%d %s', $row['id'], $row['name']));
        }

        $this->info(sprintf(
            '%s importované: %d, ručné (ponechané): %d, preskočené: %d',
            $dryRun ? '[dry-run] ' : '',
            $summary['processed'],
            $summary['manual'],
            $summary['skipped'],
        ));

        return self::SUCCESS;
    }
}
