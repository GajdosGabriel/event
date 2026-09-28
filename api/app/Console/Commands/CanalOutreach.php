<?php

namespace App\Console\Commands;

use App\Services\Canals\CanalOutreachSender;
use Illuminate\Console\Command;

/**
 * Oslovenie organizátorov po akcii (docs/canal-ownership.md, fáza 4).
 *
 * `--dry-run` len vypíše, koho by oslovil — nevytvorí pozvánku ani záznam.
 * Bez neho e-maily idú cez MailSimulator: kým platí
 * `canals.simulate_outreach_mail`, len do denníka.
 */
class CanalOutreach extends Command
{
    protected $signature = 'app:canal-outreach
        {--dry-run : Iba vypísať, koho by oslovil}
        {--limit= : Najviac oslovení v tomto behu (predvolene canals.outreach.limit)}';

    protected $description = 'Po skončení akcie ponúkne organizátorovi importovaného kanála jeho prevzatie.';

    public function handle(CanalOutreachSender $sender): int
    {
        $limit = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null;
        $candidates = $sender->candidates($limit);
        $dryRun = (bool) $this->option('dry-run');
        $simulate = (bool) config('canals.simulate_outreach_mail', true);

        foreach ($candidates as $c) {
            $this->line(sprintf('%s#%d %s → %s | akcia #%d %s | zobrazenia %d, ľudia %d, prihlásení %d',
                $dryRun ? '[dry-run] ' : ($simulate ? '[simulácia] ' : ''),
                $c['canal']->id, $c['canal']->name, $c['email'],
                $c['event']->id, $c['event']->name,
                $c['stats']['event_views'], $c['stats']['event_visitors'], $c['stats']['signups'],
            ));

            if (! $dryRun) {
                $sender->send($c['canal'], $c['event'], $c['email'], $c['stats']);
            }
        }

        $this->info(sprintf('Oslovených: %d%s', $candidates->count(), $dryRun ? ' (dry-run)' : ($simulate ? ' (simulácia)' : '')));

        return self::SUCCESS;
    }
}
