<?php

namespace App\Console\Commands;

use App\Enums\MailingTrigger;
use App\Services\Canals\CanalOutreachSender;
use Illuminate\Console\Command;

/**
 * Ponuka prevzatia importovaných kanálov (docs/canal-ownership.md, fázy 4 a 5).
 *
 * `--dry-run` len vypíše, koho by oslovil — nevytvorí pozvánku ani záznam.
 * Bez neho: pri ACCOUNT_MAILING sa udalosti hlásia do Account Emailingu,
 * inak e-maily idú cez MailSimulator (kým platí `canals.simulate_outreach_mail`,
 * len do denníka).
 */
class CanalOutreach extends Command
{
    protected $signature = 'app:canal-outreach
        {--dry-run : Iba vypísať, koho by oslovil}
        {--limit= : Najviac oslovení v tomto behu (predvolene canals.outreach.limit)}
        {--trigger= : Len jeden trigger (canal_event_upcoming, canal_views_milestone, canal_event_finished)}';

    protected $description = 'Ponúkne organizátorovi importovaného kanála jeho prevzatie (pred akciou, pri zobrazeniach, po akcii).';

    public function handle(CanalOutreachSender $sender): int
    {
        $only = null;

        if ($this->option('trigger') !== null) {
            $only = MailingTrigger::tryFrom((string) $this->option('trigger'));

            if (! $only?->isCanal()) {
                $this->error('Neznámy trigger: '.$this->option('trigger'));

                return self::INVALID;
            }
        }

        $limit = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null;
        $candidates = $sender->candidates($limit, $only);
        $dryRun = (bool) $this->option('dry-run');
        $mode = $sender->viaAccount() ? ' (Account)' : (config('canals.simulate_outreach_mail', true) ? ' (simulácia)' : '');

        foreach ($candidates as $c) {
            $this->line(sprintf('%s%s #%d %s → %s | akcia #%d %s | zobrazenia %d, ľudia %d, prihlásení %d',
                $dryRun ? '[dry-run] ' : '',
                $c['trigger']->value,
                $c['canal']->id, $c['canal']->name, $c['email'],
                $c['event']->id, $c['event']->name,
                $c['stats']['event_views'], $c['stats']['event_visitors'], $c['stats']['signups'],
            ));

            if (! $dryRun) {
                $sender->send($c);
            }
        }

        $this->info(sprintf('Oslovených: %d%s', $candidates->count(), $dryRun ? ' (dry-run)' : $mode));

        return self::SUCCESS;
    }
}
