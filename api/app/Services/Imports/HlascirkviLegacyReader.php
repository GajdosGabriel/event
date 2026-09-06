<?php

namespace App\Services\Imports;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use SplFileObject;

/**
 * Zdroj archívnych podujatí z hlascirkvi — buď priamo stará databáza, alebo
 * JSONL súbor z app:hlascirkvi-export.
 *
 * Dve cesty existujú preto, že produkcia na starú databázu vidieť môže aj
 * nemusí. Keď vidí, netreba prenášať 25 MB súbor; keď nevidí, export ho vyrobí
 * lokálne. Obe vracajú rovnaký tvar riadku, takže import medzi nimi nerozlišuje.
 */
class HlascirkviLegacyReader
{
    public const CONNECTION = 'hlascirkvi';

    public function isDatabaseAvailable(): bool
    {
        return (string) config('database.connections.'.self::CONNECTION.'.database') !== '';
    }

    /**
     * Podujatia zo starej databázy, zoradené podľa id.
     *
     * `$afterId` umožňuje pokračovať tam, kde predchádzajúca dávka skončila —
     * webcron má na jeden beh len sekundy, takže import musí vedieť postupovať
     * po kúskoch bez toho, aby zakaždým znovu čítal už spracovaný začiatok.
     *
     * @return iterable<array<string, mixed>>
     */
    public function fromDatabase(int $afterId = 0, ?int $limit = null): iterable
    {
        $query = $this->query()->where('e.id', '>', $afterId);

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        foreach ($query->cursor() as $row) {
            yield $this->normalize($row);
        }
    }

    /**
     * @return iterable<array<string, mixed>>
     */
    public function fromFile(string $path, int $afterId = 0, ?int $limit = null): iterable
    {
        $handle = new SplFileObject($path, 'r');
        $yielded = 0;

        while (! $handle->eof()) {
            $line = trim((string) $handle->fgets());
            if ($line === '') {
                continue;
            }

            $row = json_decode($line, true);
            if (! is_array($row) || (int) ($row['legacy_id'] ?? 0) <= $afterId) {
                continue;
            }

            yield $row;

            $yielded++;
            if ($limit !== null && $limit > 0 && $yielded >= $limit) {
                return;
            }
        }
    }

    public function query(): Builder
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

        return DB::connection(self::CONNECTION)
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
    public function normalize(object $row): array
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
