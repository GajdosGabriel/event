<?php

namespace App\Console\Commands;

use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Services\Canals\CanalEventOffer;
use Illuminate\Console\Command;

/**
 * Jednorazové dobehnutie technického e-mailu s ponukou prevzatia
 * (CanalEventOffer) kanálom, ktoré ho nikdy nedostali — typicky importované
 * kanály, ktorým sa e-mail dohľadal až po zverejnení akcie (pred tým, než
 * ponuku spúšťala aj zmena kontaktu).
 *
 * Píše sa len kanálom bez akejkoľvek doterajšej ponuky, k ich najbližšej
 * zverejnenej akcii, ktorá ešte neskončila. Platia rovnaké pravidlá ako pri
 * zverejnení (spravovaný kanál, prepínač v admine, vylúčené domény,
 * nedoručiteľné adresy, kontakt v tíme). Najprv `--dry-run`.
 */
class CanalEventOfferBackfill extends Command
{
    protected $signature = 'app:canal-event-offer-backfill
        {--dry-run : Iba vypísať, komu by e-mail odišiel}';

    protected $description = 'Pošle ponuku prevzatia kanálom, ktoré ju ešte nedostali a majú budúcu zverejnenú akciu.';

    public function handle(CanalEventOffer $offer): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $count = 0;

        $canals = Canal::query()
            ->whereDoesntHave('events', fn ($q) => $q->whereNotNull('takeover_notified_at'))
            ->whereHas('events', fn ($q) => $q->where('status', ModelStatus::Published->value))
            ->orderBy('id')
            ->get();

        foreach ($canals as $canal) {
            if ($canal->isManaged()) {
                continue;
            }

            foreach ($offer->pendingEvents($canal)->with(['canal', 'venue'])->get() as $event) {
                $email = $offer->recipient($event);

                if ($email === null) {
                    continue;
                }

                if (! $dryRun && ! $offer->notify((int) $event->id)) {
                    break;
                }

                $count++;
                $this->line(sprintf('%s#%d %s → %s | akcia #%d %s (%s)',
                    $dryRun ? '[dry-run] ' : '',
                    $canal->id, $canal->name, $email,
                    $event->id, $event->name, $event->start_at?->format('d.m.Y') ?? '—',
                ));

                break;
            }
        }

        $this->info(sprintf('%s: %d', $dryRun ? 'Odišlo by' : 'Odoslaných', $count));

        return self::SUCCESS;
    }
}
