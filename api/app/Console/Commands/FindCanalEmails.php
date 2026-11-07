<?php

namespace App\Console\Commands;

use App\Models\Canal;
use App\Models\CanalEmailSearch;
use App\Services\Canals\CanalEmailFinder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Hľadanie náhradnej adresy kanálov bez kontaktu (CanalEmailFinder).
 * Krátke dávky pre webcron — kanály po vrátenom e-maile idú prvé.
 */
class FindCanalEmails extends Command
{
    protected $signature = 'app:canal-emails-find
        {--canal= : Len tento kanál (ID), aj mimo poradia}
        {--limit= : Najviac kanálov v dávke}';

    protected $description = 'Nájde náhradnú e-mailovú adresu kanálom, ktoré žiadnu použiteľnú nemajú';

    public function handle(CanalEmailFinder $finder): int
    {
        if (! Schema::hasTable('canal_email_searches')) {
            return self::SUCCESS;
        }

        if ($id = $this->option('canal')) {
            $canal = Canal::withTrashed()->find((int) $id);

            if (! $canal) {
                $this->error("Kanál #{$id} neexistuje.");

                return self::FAILURE;
            }

            $result = $finder->run($canal);
            $this->line("#{$canal->id} {$canal->name}: {$result}".($result === CanalEmailSearch::SKIPPED ? ' ('.$finder->skipReason($canal->fresh()).')' : ''));

            return self::SUCCESS;
        }

        if (! config('canals.emails.discovery.enabled', true)) {
            return self::SUCCESS;
        }

        $lock = Cache::lock('canal-emails-find:batch', 120);

        if (! $lock->get()) {
            return self::SUCCESS;
        }

        try {
            $start = microtime(true);
            $limit = max(1, (int) ($this->option('limit') ?? config('canals.emails.discovery.batch', 3)));
            $seconds = max(1, (int) config('canals.emails.discovery.max_seconds', 40));

            foreach ($finder->dueQuery()->limit($limit)->get() as $canal) {
                $this->line("#{$canal->id} {$canal->name}: ".$finder->run($canal));

                if (microtime(true) - $start >= $seconds) {
                    break;
                }
            }

            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
