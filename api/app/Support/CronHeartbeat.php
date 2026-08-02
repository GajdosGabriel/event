<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Ping externému watchdogu po úspešnom behu webcronu.
 *
 * Scheduler tu nespúšťa systémový cron, ale externá služba, ktorá volá
 * /cron/schedule-run cez HTTP. Keď tá služba vypadne (alebo si zmení plán,
 * alebo jej vyprší free tier), aplikácia sa nedozvie nič — jednoducho prestanú
 * chodiť importy a expirácie a nikto si to týždeň nevšimne.
 *
 * Watchdog (healthchecks.io, Better Stack, cron-job.org monitor) funguje
 * opačne: čaká ping v dohodnutom intervale a keď nepríde, zaalarmuje. Preto sa
 * pinguje až po úspešnom `schedule:run` — nedoručený ping je práve tá správa.
 */
class CronHeartbeat
{
    /**
     * Zlyhanie pingu nesmie zhodiť cron: watchdog je pomocná infraštruktúra,
     * nie podmienka behu scheduleru. Chyba sa zaloguje a beh pokračuje.
     */
    public static function ping(): void
    {
        $url = trim((string) config('services.cron_heartbeat.url'));

        if ($url === '') {
            return;
        }

        try {
            Http::timeout((int) config('services.cron_heartbeat.timeout', 5))
                ->get($url)
                ->throw();
        } catch (Throwable $e) {
            Log::warning('Cron heartbeat ping zlyhal.', ['exception' => $e->getMessage()]);
        }
    }
}
