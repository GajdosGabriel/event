<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Maže staré riadky z tabuľky `views`.
 *
 * Tabuľka slúži len na rozpoznanie opakovaného zobrazenia v rámci dňa a na
 * prípadné časové štatistiky — trvalý počet žije v `views_count` na samotnom
 * zázname, takže mazanie o číslo nepripraví.
 */
class PruneViews extends Command
{
    protected $signature = 'app:views-prune
        {--days=90 : Koľko dní ponechať}
        {--time-budget=20 : Strop behu v sekundách (0 = bez stropu)}';

    protected $description = 'Prune old rows from the views table';

    /** Po dávkach, aby jeden DELETE nedržal zámok na celej tabuľke. */
    private const CHUNK = 5000;

    public function handle(): int
    {
        $start = microtime(true);
        $days = max(1, (int) $this->option('days'));
        $timeBudget = max(0, (int) $this->option('time-budget'));
        $cutoff = now()->subDays($days)->toDateString();

        $deleted = 0;

        do {
            $batch = DB::table('views')
                ->where('viewed_on', '<', $cutoff)
                ->limit(self::CHUNK)
                ->delete();

            $deleted += $batch;

            if ($timeBudget > 0 && microtime(true) - $start > $timeBudget) {
                break;
            }
        } while ($batch > 0);

        $this->info(sprintf('PruneViews: zmazaných %d riadkov starších ako %s', $deleted, $cutoff));

        return self::SUCCESS;
    }
}
