<?php

namespace App\Console\Commands;

use App\Models\DuplicateDecision;
use Illuminate\Console\Command;

class ReviewImportDuplicates extends Command
{
    protected $signature = 'imports:duplicates
        {--entity= : Obmedziť na canal alebo venue}
        {--all : Zobraziť aj už skontrolované}
        {--reviewed=* : Označiť rozhodnutie (id) ako skontrolované}
        {--distinct=* : Označiť rozhodnutie (id) ako „rôzne subjekty“ a skontrolované}';

    protected $description = 'Zoznam nejasných duplicít kanálov a miest z importu, ktoré čakajú na kontrolu.';

    public function handle(): int
    {
        foreach ((array) $this->option('reviewed') as $id) {
            DuplicateDecision::query()->whereKey((int) $id)->update(['reviewed_at' => now()]);
        }

        foreach ((array) $this->option('distinct') as $id) {
            // Rovnaký vstup sa už nebude pýtať znova — rozhodnutie je cache.
            DuplicateDecision::query()->whereKey((int) $id)->update([
                'decision' => DuplicateDecision::DISTINCT,
                'reviewed_at' => now(),
            ]);
        }

        $rows = DuplicateDecision::query()
            ->where('decision', DuplicateDecision::UNCERTAIN)
            ->when(! $this->option('all'), fn ($q) => $q->whereNull('reviewed_at'))
            ->when($this->option('entity'), fn ($q, $entity) => $q->where('entity', $entity))
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty()) {
            $this->info('Žiadne nejasné duplicity na kontrolu.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Typ', 'Nový záznam', 'Možná duplicita', 'Istota', 'Zdroj', 'Dôvod'],
            $rows->map(fn (DuplicateDecision $row): array => [
                $row->id,
                $row->entity,
                '#'.($row->created_id ?? '?').' '.$row->input_name,
                '#'.$row->candidate_id.' '.$row->candidate_name,
                $row->confidence !== null ? number_format($row->confidence, 2) : '—',
                $row->source,
                (string) $row->reason,
            ])->all(),
        );

        return self::SUCCESS;
    }
}
