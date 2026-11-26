<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Maže vypršané riadky z databázovej cache (`cache`, `cache_locks`).
 *
 * Databázový store zmaže vypršaný kľúč len vtedy, keď ho niekto znova prečíta.
 * Kľúče, ktoré sa už nikdy neprečítajú (throttle podľa IP, jednorazové zámky),
 * by v tabuľke ostali navždy. Laravel na to vlastný príkaz nemá.
 */
class PruneExpiredCache extends Command
{
    protected $signature = 'app:cache-prune-expired
        {--time-budget=20 : Strop behu v sekundách (0 = bez stropu)}';

    protected $description = 'Delete expired rows from the database cache and cache lock tables';

    /** Po dávkach, aby jeden DELETE nedržal zámok na celej tabuľke. */
    private const CHUNK = 5000;

    public function handle(): int
    {
        $start = microtime(true);
        $timeBudget = max(0, (int) $this->option('time-budget'));
        $connection = config('cache.stores.database.connection');
        $tables = array_unique(array_filter([
            config('cache.stores.database.table', 'cache'),
            config('cache.stores.database.lock_table') ?: 'cache_locks',
        ]));

        $now = now()->getTimestamp();
        $deleted = [];

        foreach ($tables as $table) {
            $deleted[$table] = 0;

            if (! DB::connection($connection)->getSchemaBuilder()->hasTable($table)) {
                continue;
            }

            do {
                $batch = DB::connection($connection)->table($table)
                    ->where('expiration', '<=', $now)
                    ->limit(self::CHUNK)
                    ->delete();

                $deleted[$table] += $batch;

                if ($timeBudget > 0 && microtime(true) - $start > $timeBudget) {
                    break 2;
                }
            } while ($batch > 0);
        }

        $this->info('PruneExpiredCache: zmazané '.collect($deleted)
            ->map(fn ($count, $table) => "{$table}={$count}")
            ->implode(', '));

        return self::SUCCESS;
    }
}
