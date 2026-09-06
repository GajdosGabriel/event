<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Vyexportuje archív podujatí zo starej databázy hlascirkvi do prenosného
 * JSONL súboru. Beží výhradne lokálne (produkcia na starú DB nevidí), výstup
 * sa nahrá na server a spracujú ho app:hlascirkvi-import a
 * app:hlascirkvi-import-images.
 */
class ExportHlascirkviEvents extends Command
{
    protected $signature = 'app:hlascirkvi-export
        {--out= : Cesta k výstupnému JSONL súboru relatívne k storage/app}
        {--limit=0 : Obmedzenie počtu podujatí (0 = bez obmedzenia)}';

    protected $description = 'Export archívu podujatí zo starej databázy hlascirkvi do JSONL';

    public function handle(): int
    {
        $database = (string) config('database.connections.hlascirkvi.database');
        if ($database === '') {
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

        $query = $this->sourceQuery();
        if ($limit > 0) {
            $query->limit($limit);
        }

        $progress = $this->output->createProgressBar();
        $progress->start();

        // cursor() drží konštantnú pamäť aj pri 12 000 riadkoch s HTML telom.
        foreach ($query->cursor() as $row) {
            $payload = $this->normalizeRow($row);
            fwrite($handle, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");

            $exported++;
            if ($payload['image_path'] !== null) {
                $withImage++;
            }
            $progress->advance();
        }

        $progress->finish();
        $this->newLine(2);
        fclose($handle);

        $manifest = [
            'generated_at' => now()->toIso8601String(),
            'source_database' => $database,
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

    private function sourceQuery(): \Illuminate\Database\Query\Builder
    {
        // Jeden obrázok na podujatie: berie sa `images.url`, čo je veľká
        // varianta (1000 px). Typ 'card' sú strojovo generované plagáty,
        // ktoré si nový projekt vie vyrobiť sám, takže sa neprenášajú.
        $image = <<<'SQL'
            (SELECT i.url FROM images i
              WHERE i.fileable_type LIKE '%Event'
                AND i.fileable_id = e.id
                AND i.type = 'img'
                AND i.deleted_at IS NULL
              ORDER BY i.is_primary DESC, i.id ASC
              LIMIT 1)
        SQL;

        return DB::connection('hlascirkvi')
            ->table('events as e')
            ->join('organizations as o', 'o.id', '=', 'e.organization_id')
            ->leftJoin('villages as m', 'm.id', '=', 'e.village_id')
            ->whereNotNull('e.published')
            ->whereNull('e.deleted_at')
            ->orderBy('e.id')
            ->select([
                'e.id', 'e.title', 'e.slug', 'e.body', 'e.start_at', 'e.end_at',
                'e.published', 'e.created_at', 'e.updated_at', 'e.village_id',
                'e.street', 'e.clientwww', 'e.online_link', 'e.orginal_source',
                'e.registration', 'e.entryFee as entry_fee',
                'o.id as org_id', 'o.title as org_title', 'o.email as org_email',
                'o.url_www as org_www', 'o.village_id as org_village_id',
                'm.fullname as village_name',
                DB::raw($image.' as image_path'),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeRow(object $row): array
    {
        return [
            'legacy_id' => (int) $row->id,
            'title' => $this->text($row->title),
            'slug' => $this->text($row->slug),
            'body' => (string) ($row->body ?? ''),
            'start_at' => $this->text($row->start_at),
            'end_at' => $this->text($row->end_at),
            'published' => $this->text($row->published),
            'created_at' => $this->text($row->created_at),
            'updated_at' => $this->text($row->updated_at),
            'village_id' => $row->village_id !== null ? (int) $row->village_id : null,
            'village_name' => $this->text($row->village_name),
            'street' => $this->text($row->street),
            'clientwww' => $this->text($row->clientwww),
            'online_link' => $this->text($row->online_link),
            'orginal_source' => $this->text($row->orginal_source),
            'registration' => $this->text($row->registration),
            'entry_fee' => $this->text($row->entry_fee),
            'org_id' => (int) $row->org_id,
            'org_title' => $this->text($row->org_title),
            'org_email' => $this->text($row->org_email),
            'org_www' => $this->text($row->org_www),
            'org_village_id' => $row->org_village_id !== null ? (int) $row->org_village_id : null,
            'image_path' => $this->text($row->image_path),
        ];
    }

    private function text(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
