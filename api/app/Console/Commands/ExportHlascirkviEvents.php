<?php

namespace App\Console\Commands;

use App\Services\Imports\HlascirkviLegacyReader;
use Illuminate\Console\Command;

/**
 * Vyexportuje archív podujatí zo starej databázy hlascirkvi do prenosného
 * JSONL súboru.
 *
 * Potrebné je to len vtedy, keď produkcia na starú databázu nevidí — inak
 * číta app:hlascirkvi-import rovnaké dáta priamo cez spojenie `hlascirkvi`.
 */
class ExportHlascirkviEvents extends Command
{
    protected $signature = 'app:hlascirkvi-export
        {--out= : Cesta k výstupnému JSONL súboru relatívne k storage/app}
        {--limit=0 : Obmedzenie počtu podujatí (0 = bez obmedzenia)}';

    protected $description = 'Export archívu podujatí zo starej databázy hlascirkvi do JSONL';

    public function handle(HlascirkviLegacyReader $reader): int
    {
        if (! $reader->isDatabaseAvailable()) {
            $this->error('HLASCIRKVI_DB_DATABASE nie je nastavené — export sa dá spustiť len tam, kde je stará databáza dostupná.');

            return self::FAILURE;
        }

        $relativePath = (string) ($this->option('out') ?: config('services.imports.legacy.file'));
        $limit = (int) $this->option('limit');

        $absolutePath = storage_path('app/'.ltrim($relativePath, '/'));
        $directory = dirname($absolutePath);
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $handle = fopen($absolutePath, 'wb');
        if ($handle === false) {
            $this->error("Nepodarilo sa otvoriť {$absolutePath} na zápis.");

            return self::FAILURE;
        }

        $exported = 0;
        $withImage = 0;

        $progress = $this->output->createProgressBar();
        $progress->start();

        foreach ($reader->fromDatabase(limit: $limit > 0 ? $limit : null) as $row) {
            fwrite($handle, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");

            $exported++;
            if ($row['image_path'] !== null) {
                $withImage++;
            }
            $progress->advance();
        }

        $progress->finish();
        $this->newLine(2);
        fclose($handle);

        $manifest = [
            'generated_at' => now()->toIso8601String(),
            'source_database' => config('database.connections.'.HlascirkviLegacyReader::CONNECTION.'.database'),
            'events' => $exported,
            'events_with_image' => $withImage,
            'sha256' => hash_file('sha256', $absolutePath),
            'bytes' => filesize($absolutePath),
        ];
        file_put_contents(
            preg_replace('/\.jsonl$/', '', $absolutePath).'.manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        $this->info("Vyexportované podujatia: {$exported} (z toho s obrázkom: {$withImage})");
        $this->line("Súbor: {$absolutePath}");

        return self::SUCCESS;
    }
}
