<?php

namespace App\Console\Commands;

use App\Services\Attributes\AttributeCheckService;
use Illuminate\Console\Command;

/**
 * Overí dávku hodnôt, ktorým dobehol odstup (viď AttributeCheckService).
 *
 * Dávka je malá a beh má časový strop, lebo hosting nemá shell — schedule:run
 * volá webcron v HTTP requeste a všetky naplánované príkazy v ňom bežia za
 * sebou. Jeden pomalý cudzí server tak nesmie zjesť celé okno; radšej sa
 * zvyšok dávky presunie do ďalšieho behu o pár minút.
 */
class RunAttributeChecks extends Command
{
    protected $signature = 'app:attribute-checks-run
        {--limit= : Koľko hodnôt overiť (predvolene z configu)}
        {--time-budget=25 : Strop behu v sekundách (0 = bez stropu)}';

    protected $description = 'Check whether stored values (websites…) still work';

    public function handle(AttributeCheckService $service): int
    {
        if (! config('attribute_checks.enabled', true)) {
            $this->info('AttributeChecks: vypnuté konfiguráciou.');

            return self::SUCCESS;
        }

        $start = microtime(true);
        $limit = max(1, (int) ($this->option('limit') ?: config('attribute_checks.batch', 20)));
        $timeBudget = max(0, (int) $this->option('time-budget'));

        $checked = 0;
        $failed = 0;

        foreach ($service->due($limit) as $check) {
            if (! $service->run($check)) {
                continue;
            }

            $checked++;

            if ($check->status->isFailed()) {
                $failed++;
            }

            if ($timeBudget > 0 && microtime(true) - $start > $timeBudget) {
                break;
            }
        }

        $this->info(sprintf('AttributeChecks: overených %d, z toho nefunkčných %d', $checked, $failed));

        return self::SUCCESS;
    }
}
