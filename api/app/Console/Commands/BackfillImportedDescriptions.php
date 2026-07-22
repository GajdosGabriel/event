<?php

namespace App\Console\Commands;

use App\Enums\RegistrationSource;
use App\Models\Canal;
use App\Models\Venue;
use App\Services\Imports\ImportedProfileDescriber;
use Illuminate\Console\Command;

/**
 * Doplní popisy kanálom a miestam, ktoré vznikli importom ešte predtým, než
 * import začal písať popis sám (všetky mali rovnaký systémový text / prázdno).
 */
class BackfillImportedDescriptions extends Command
{
    protected $signature = 'app:backfill-imported-descriptions
        {--canals : Spracovať iba kanály}
        {--venues : Spracovať iba miesta}
        {--limit=0 : Maximálny počet záznamov na typ (0 = bez limitu)}
        {--sleep=300 : Pauza v milisekundách medzi volaniami AI}
        {--dry-run : Iba vypísať, čo by sa zmenilo}';

    protected $description = 'Vygeneruje popisy pre importované kanály a miesta, ktorým chýbajú';

    private const LEGACY_CANAL_BODY = 'Systémom vytvorený canal pre importované eventy.';

    public function handle(ImportedProfileDescriber $describer): int
    {
        if (! (bool) config('services.imports.describe_with_ai', false)) {
            $this->warn('services.imports.describe_with_ai je vypnuté — kanály dostanú len neutrálny fallback, miesta nič.');
        }

        $onlyCanals = (bool) $this->option('canals');
        $onlyVenues = (bool) $this->option('venues');
        $doCanals = $onlyCanals || ! $onlyVenues;
        $doVenues = $onlyVenues || ! $onlyCanals;

        if ($doCanals) {
            $this->backfillCanals($describer);
        }

        if ($doVenues) {
            $this->backfillVenues($describer);
        }

        return self::SUCCESS;
    }

    private function backfillCanals(ImportedProfileDescriber $describer): void
    {
        $query = Canal::query()
            ->where('registration_source', RegistrationSource::IMPORT->value)
            ->where(function ($q) {
                $q->whereNull('body')
                    ->orWhere('body', '')
                    ->orWhere('body', self::LEGACY_CANAL_BODY);
            })
            ->orderBy('id');

        $this->applyLimit($query);

        $updated = 0;

        foreach ($query->get() as $canal) {
            $body = $describer->forCanal($canal->name, $canal->website);

            $this->line(sprintf(' - canal #%d %s -> %s', $canal->id, $canal->name, $body));

            if (! $this->option('dry-run')) {
                $canal->update(['body' => $body]);
                $updated++;
            }

            $this->pause();
        }

        $this->info(sprintf('Kanály: aktualizovaných %d', $updated));
    }

    private function backfillVenues(ImportedProfileDescriber $describer): void
    {
        // Miesta nemajú vlastný registration_source — importované sú tie, ktoré
        // sú stále v drafte bez popisu a nie sú zberným "Celé Slovensko".
        $query = Venue::query()
            ->where(function ($q) {
                $q->whereNull('body')->orWhere('body', '');
            })
            ->where(function ($q) {
                $q->whereNull('category')->orWhere('category', '!=', 'fallback');
            })
            ->orderBy('id');

        $this->applyLimit($query);

        $updated = 0;

        foreach ($query->get() as $venue) {
            $body = $describer->forVenue($venue->name, $venue->municipality?->fullname);

            if ($body === null) {
                $this->line(sprintf(' - venue #%d %s -> preskočené', $venue->id, $venue->name));
                $this->pause();
                continue;
            }

            $this->line(sprintf(' - venue #%d %s -> %s', $venue->id, $venue->name, $body));

            if (! $this->option('dry-run')) {
                $venue->update(['body' => $body]);
                $updated++;
            }

            $this->pause();
        }

        $this->info(sprintf('Miesta: aktualizovaných %d', $updated));
    }

    private function applyLimit(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $limit = max(0, (int) $this->option('limit'));

        if ($limit > 0) {
            $query->limit($limit);
        }
    }

    private function pause(): void
    {
        $sleepMs = max(0, (int) $this->option('sleep'));

        if ($sleepMs > 0) {
            usleep($sleepMs * 1000);
        }
    }
}
