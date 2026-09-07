<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Services\Events\DuplicateEventMerger;
use App\Services\Imports\CollectionCanal;
use Illuminate\Console\Command;

/**
 * Vypíše (a na požiadanie zlúči) tú istú akciu naimportovanú dvakrát.
 *
 * Zámerne nie je v pláne (`routes/console.php`). Zlúčenie je nevratné mazanie
 * dvojice, ktorú import z opatrnosti nechal tak — spúšťa ho človek, buď týmto
 * príkazom, alebo tlačidlom v /admin/nastroje. Bez `--apply` sa len vypíše, čo
 * by sa stalo.
 */
class MergeDuplicateEvents extends Command
{
    /**
     * @var string
     */
    protected $signature = 'app:events-merge-duplicates {--apply : Naozaj zlúčiť, nielen vypísať} {--limit=50 : Koľko skupín preveriť}';

    /**
     * @var string
     */
    protected $description = 'Nájde podujatia naimportované dvakrát (rovnaký názov, čas a miesto) a zlúči ich do jedného';

    public function handle(DuplicateEventMerger $merger): int
    {
        $apply = (bool) $this->option('apply');
        $limit = max(1, (int) $this->option('limit'));

        $candidates = $merger->candidates($limit);

        if ($candidates === []) {
            $this->info('Žiadne duplicity.');

            return self::SUCCESS;
        }

        $merged = 0;
        $skipped = 0;

        foreach ($candidates as $group) {
            /** @var Event $keep */
            $keep = $group['keep'];
            $drop = $group['drop'];

            $this->line($keep->name . ' — ' . $keep->start_at?->format('j. n. Y H:i'));
            $this->line('  ostáva:  ' . $this->describe($keep));

            foreach ($drop as $event) {
                $this->line('  zahodiť: ' . $this->describe($event));
            }

            if ($group['reason'] !== null) {
                $this->warn('  preskočené — ' . $group['reason']);
                $skipped++;

                continue;
            }

            if (! $apply) {
                continue;
            }

            $merger->mergeGroup($keep, $drop);
            $merged++;
        }

        $this->newLine();

        if ($apply) {
            $this->info('Zlúčené skupiny: ' . $merged . ', preskočené: ' . $skipped . '.');

            return self::SUCCESS;
        }

        $this->info('Nájdené skupiny: ' . count($candidates) . ' (z toho ' . $skipped . ' sa zlúčiť nesmie). Zlúčenie spustíte s --apply.');

        return self::SUCCESS;
    }

    private function describe(Event $event): string
    {
        $canal = $event->canal;
        $label = $canal?->name ?? 'bez kanála';

        if (CollectionCanal::is($canal)) {
            $label .= ' [zberný]';
        }

        return '#' . $event->id . ' · ' . $label . ' · ' . ($event->orginal_source ?? '—');
    }
}
