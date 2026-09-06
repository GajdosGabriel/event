<?php

namespace App\Console\Commands;

use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\Event;
use App\Models\Municipality;
use App\Models\Venue;
use App\Services\Imports\HlascirkviLegacyReader;
use App\Services\Imports\HlascirkviSourceUrl;
use App\Services\Imports\ImportedCanalManager;
use App\Services\Imports\ImportedProfileDescriber;
use App\Services\Imports\ImportedVenueManager;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Nahrá archív podujatí zo starého projektu hlascirkvi do nového dátového
 * modelu. Číta buď priamo starú databázu, alebo JSONL z app:hlascirkvi-export.
 *
 * Beh je dávkový a nadväzujúci: `--max-seconds` ho ukončí a kurzor si zapamätá,
 * kde skončil. Bez toho by sa import nedal spustiť na hostingu bez shellu, kde
 * jediným spúšťačom je webcron s niekoľkosekundovým rozpočtom na požiadavku.
 *
 * Obrázky rieši samostatný app:hlascirkvi-import-images — sťahovanie 11 000
 * súborov je dlhá operácia, ktorá sa musí dať opakovať bez toho, aby sa znovu
 * prepisovali podujatia.
 */
class ImportHlascirkviEvents extends Command
{
    private const CURSOR_KEY = 'hlascirkvi_import.last_legacy_id';

    /**
     * Organizácie, ktoré v starej databáze nie sú usporiadateľom, ale zdrojom
     * scrapera. Ich podujatia patria do zberného kanála pomenovaného po
     * hostovi — presne ako to robí nočný app:import-event-sources.
     */
    private const SOURCE_ORGANIZATIONS = [
        101 => ['tkkbs.sk', 'https://www.tkkbs.sk'],
        102 => ['ecav.sk', 'https://www.ecav.sk'],
        271 => ['vyveska.sk', 'https://www.vyveska.sk'],
    ];

    protected $signature = 'app:hlascirkvi-import
        {--source= : "db" (stará databáza) alebo "file" (JSONL); predvolene db, keď je spojenie nastavené}
        {--file= : Cesta k JSONL súboru relatívne k storage/app}
        {--limit=0 : Spracovať najviac N podujatí (0 = všetky)}
        {--max-seconds=0 : Ukončiť beh po N sekundách (0 = bez limitu); pre webcron}
        {--reset-cursor : Začať znovu od najstaršieho podujatia}
        {--dry-run : Prejde dávku, ale zmeny na konci vráti späť}
        {--force : Prepíše aj podujatia, ktoré už z tohto zdroja existujú}';

    protected $description = 'Import archívu podujatí z hlascirkvi do nového modelu';

    /** @var array<int, int> legacy organization_id => canal id */
    private array $canalCache = [];

    /** @var array<string, int> "village_id|slug" => venue id */
    private array $venueCache = [];

    public function handle(
        ImportedCanalManager $canalManager,
        ImportedVenueManager $venueManager,
        ImportedProfileDescriber $describer,
        HlascirkviLegacyReader $reader,
    ): int {
        // Pri 12 000 podujatiach by detekcia cez AI a geokóder znamenala
        // desaťtisíce volaní navyše — a nemá čo pridať, obec aj organizátor
        // sú v exporte známe. Miesta bez súradníc dorieši app:venues-backfill.
        config([
            'services.imports.detect_canal_with_ai' => false,
            'services.imports.describe_with_ai' => false,
        ]);

        // Výslovne zadaný súbor je sám o sebe voľbou zdroja — inak by ho pri
        // nastavenom spojení na starú databázu ticho prebilo `db`.
        $source = (string) ($this->option('source') ?: match (true) {
            (bool) $this->option('file') => 'file',
            $reader->isDatabaseAvailable() => 'db',
            default => 'file',
        });
        $path = storage_path('app/'.ltrim((string) ($this->option('file') ?: config('services.imports.legacy.file')), '/'));

        if ($source === 'db' && ! $reader->isDatabaseAvailable()) {
            $this->error('HLASCIRKVI_DB_DATABASE nie je nastavené — buď doplň spojenie na starú databázu, alebo použi --source=file.');

            return self::FAILURE;
        }

        if ($source === 'file' && ! is_file($path)) {
            $this->error("Súbor {$path} neexistuje — najprv spusti app:hlascirkvi-export.");

            return self::FAILURE;
        }

        try {
            $owner = $canalManager->systemOwner();
        } catch (Throwable $e) {
            $this->error("Import potrebuje používateľa s rolou super-admin: {$e->getMessage()}");

            return self::FAILURE;
        }

        $limit = (int) $this->option('limit');
        $maxSeconds = (int) $this->option('max-seconds');
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if ($this->option('reset-cursor')) {
            $this->forgetCursor();
        }

        // Dry-run a --force musia vidieť aj to, čo je už nahraté, inak by
        // kontrolný beh prešiel naprázdno a --force by nemal čo prepísať.
        $cursor = ($dryRun || $force) ? 0 : $this->cursor();
        $startedAt = microtime(true);

        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'no_date' => 0, 'failed' => 0, 'year_corrected' => 0];
        $canalsBefore = Canal::query()->count();
        $venuesBefore = Venue::query()->count();

        // Transakcia je len nástroj dry-runu. Ostrý beh ju zámerne nepoužíva:
        // pri 12 000 podujatiach by jedna dlhá transakcia držala zámky hodiny
        // a zlyhanie na konci by zahodilo celý import. Bez nej sa dá príkaz
        // kedykoľvek prerušiť a spustiť znovu — už nahraté sa preskočí.
        if ($dryRun) {
            DB::beginTransaction();
        }

        $rows = $source === 'db'
            ? $reader->fromDatabase($cursor, $limit > 0 ? $limit : null)
            : $reader->fromFile($path, $cursor, $limit > 0 ? $limit : null);

        $processed = 0;
        $lastId = $cursor;

        foreach ($rows as $row) {
            try {
                $result = $this->importRow($row, $canalManager, $venueManager, $describer, $owner->id, $force);
                $stats[$result['status']]++;
                if ($result['year_corrected']) {
                    $stats['year_corrected']++;
                }
            } catch (Throwable $e) {
                $stats['failed']++;
                Log::error('hlascirkvi import: riadok zlyhal', [
                    'legacy_id' => $row['legacy_id'] ?? null,
                    'message' => $e->getMessage(),
                ]);
                $this->warn("Podujatie {$row['legacy_id']}: {$e->getMessage()}");
            }

            // Kurzor sa posúva aj po chybnom riadku. Inak by jediné podujatie,
            // ktoré sa nedá uložiť, zablokovalo každú ďalšiu dávku webcronu na
            // tom istom mieste — chyba je v logu a beh musí ísť ďalej.
            $lastId = (int) ($row['legacy_id'] ?? $lastId);

            $processed++;
            if ($processed % 500 === 0) {
                $this->line("  … spracovaných {$processed}");
            }
            if ($limit > 0 && $processed >= $limit) {
                break;
            }
            if ($maxSeconds > 0 && (microtime(true) - $startedAt) >= $maxSeconds) {
                $this->line("  … časový limit {$maxSeconds}s, pokračuje sa v ďalšom behu");
                break;
            }
        }

        $newCanals = Canal::query()->count() - $canalsBefore;
        $newVenues = Venue::query()->count() - $venuesBefore;

        if ($dryRun) {
            DB::rollBack();
            $this->warn('DRY RUN — všetky zmeny boli vrátené späť.');
        } elseif (! $force) {
            $this->rememberCursor($lastId);
        }

        if ($processed === 0) {
            $this->info('Nič nové na spracovanie — archív je nahratý celý.');
        }

        $this->table(
            ['vytvorené', 'aktualizované', 'preskočené', 'bez dátumu', 'chybné', 'opravený rok', 'nové kanály', 'nové miesta'],
            [[
                $stats['created'], $stats['updated'], $stats['skipped'], $stats['no_date'],
                $stats['failed'], $stats['year_corrected'], $newCanals, $newVenues,
            ]],
        );

        return self::SUCCESS;
    }

    /**
     * Kde import naposledy skončil. Webcron má na jeden beh len sekundy, takže
     * sa postupuje po dávkach a každá musí vedieť nadviazať.
     *
     * Hodnota žije v cache, ale cache maže aj po-deploy endpoint
     * (`/api/artisan/run` volá `optimize:clear`). Preto sa pri prázdnej cache
     * dopočíta z už nahratých podujatí — inak by import po každom nasadení
     * začínal od nuly a zbytočne prechádzal celý archív odznova.
     */
    private function cursor(): int
    {
        $cached = Cache::get(self::CURSOR_KEY);
        if (is_numeric($cached)) {
            return (int) $cached;
        }

        $highest = Event::query()
            ->where('meta->import->source', 'hlascirkvi_legacy')
            // Nie `meta->>'$...'` — ten operátor MariaDB nepozná.
            ->max(DB::raw("CAST(JSON_UNQUOTE(JSON_EXTRACT(meta, '$.import.legacy_event_id')) AS UNSIGNED)"));

        return is_numeric($highest) ? (int) $highest : 0;
    }

    private function rememberCursor(int $legacyId): void
    {
        Cache::forever(self::CURSOR_KEY, $legacyId);
    }

    private function forgetCursor(): void
    {
        Cache::forget(self::CURSOR_KEY);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{status: string, year_corrected: bool}
     */
    private function importRow(
        array $row,
        ImportedCanalManager $canalManager,
        ImportedVenueManager $venueManager,
        ImportedProfileDescriber $describer,
        int $ownerId,
        bool $force,
    ): array {
        $sourceUrl = HlascirkviSourceUrl::for($row);
        $existing = Event::query()->where('orginal_source', $sourceUrl)->first();

        if ($existing instanceof Event && ! $force) {
            return ['status' => 'skipped', 'year_corrected' => false];
        }

        $canal = $this->resolveCanal($row, $canalManager);
        $venue = $this->resolveVenue($row, $canal, $venueManager, $describer);

        $createdAt = $this->toDate($row['created_at'] ?? null) ?? CarbonImmutable::now();
        [$startAt, $endAt, $yearCorrected, $endEstimated] = $this->resolveDates($row, $createdAt);

        // Podujatie bez začiatku nemá v archíve čo robiť: dátum sa nedá ani
        // opraviť, ani odhadnúť, a vo výpise by viselo na dni vzniku článku.
        if ($startAt === null) {
            return ['status' => 'no_date', 'year_corrected' => false];
        }

        $payload = [
            'name' => Str::limit((string) $row['title'], 250, ''),
            'body' => (string) ($row['body'] ?? ''),
            'start_at' => $startAt,
            'end_at' => $endAt,
            'status' => $endAt->isPast() ? ModelStatus::Archived->value : ModelStatus::Published->value,
            'published_at' => $this->toDate($row['published'] ?? null) ?? $createdAt,
            'website' => $row['clientwww'] ?? null,
            'orginal_source' => $sourceUrl,
            'venue_id' => $venue->id,
            'canal_id' => $canal->id,
            'user_id' => $ownerId,
            'meta' => [
                'import' => [
                    'source' => 'hlascirkvi_legacy',
                    'source_origin' => config('services.imports.legacy.hlascirkvi_base_url'),
                    'legacy_event_id' => (int) $row['legacy_id'],
                    'legacy_organization_id' => (int) $row['org_id'],
                    'legacy_organization_title' => $row['org_title'] ?? null,
                    'legacy_village_id' => $row['village_id'] ?? null,
                    'legacy_street' => $row['street'] ?? null,
                    'online_link' => $row['online_link'] ?? null,
                    'registration' => $row['registration'] ?? null,
                    'entry_fee' => $row['entry_fee'] ?? null,
                    'date_year_corrected' => $yearCorrected,
                    'end_at_estimated' => $endEstimated,
                    'image_path' => $row['image_path'] ?? null,
                    'imported_at' => now()->toIso8601String(),
                ],
            ],
        ];

        $event = $existing instanceof Event ? $existing : new Event;

        if ($existing instanceof Event) {
            // Kanál raz priradený sa neprepisuje — rovnaké pravidlo ako v
            // EventImportService: administrátorova oprava musí prežiť re-import.
            unset($payload['canal_id']);
        }

        // Eloquent by `updated_at` prepísal časom behu a `created_at` by pri
        // novom zázname nastavil na teraz — archív by tým prišiel o svoju os.
        $event->timestamps = false;
        $event->fill($payload);
        $event->created_at = $createdAt;
        $event->updated_at = $this->toDate($row['updated_at'] ?? null) ?? $createdAt;
        $event->save();

        return [
            'status' => $existing instanceof Event ? 'updated' : 'created',
            'year_corrected' => $yearCorrected,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveCanal(array $row, ImportedCanalManager $canalManager): Canal
    {
        $orgId = (int) $row['org_id'];

        if (isset($this->canalCache[$orgId])) {
            return Canal::query()->findOrFail($this->canalCache[$orgId]);
        }

        if (isset(self::SOURCE_ORGANIZATIONS[$orgId])) {
            // Bez detekovaného mena hľadá manager striktne podľa slugu hosta,
            // takže sa trafí na už existujúci zberný kanál scrapera.
            [$hostLabel, $origin] = self::SOURCE_ORGANIZATIONS[$orgId];
            $canal = $canalManager->resolveOrCreate($hostLabel, null, $origin);
        } else {
            $title = (string) ($row['org_title'] ?: 'hlascirkvi.sk');
            $origin = (string) ($row['org_www'] ?: config('services.imports.legacy.hlascirkvi_base_url'));
            $canal = $canalManager->resolveOrCreate($title, $title, $origin);
        }

        $this->canalCache[$orgId] = $canal->id;

        return $canal;
    }

    /**
     * Miesto sa určuje z obce, ktorú stará databáza pozná presne — číselníky
     * oboch projektov sú zhodné, takže `village_id` platí aj tu. Prekladať to
     * cez názov sa nesmie: 211 obcí má rovnaké meno a 93 podujatí by tak
     * skončilo v inej obci.
     *
     * @param  array<string, mixed>  $row
     */
    private function resolveVenue(
        array $row,
        Canal $canal,
        ImportedVenueManager $venueManager,
        ImportedProfileDescriber $describer,
    ): Venue {
        $villageId = (int) ($row['village_id'] ?? 0);

        if ($villageId === 0 || $villageId === Municipality::nationwideId()) {
            return $venueManager->resolveFallbackVenueForCanal($canal);
        }

        $municipality = Municipality::query()->find($villageId);
        if ($municipality === null) {
            return $venueManager->resolveFallbackVenueForCanal($canal);
        }

        $name = (string) ($row['street'] ?: $municipality->fullname);
        $slug = Str::slug($name);
        $cacheKey = $villageId.'|'.$slug;

        if (isset($this->venueCache[$cacheKey])) {
            $venue = Venue::query()->findOrFail($this->venueCache[$cacheKey]);
        } else {
            $venue = Venue::query()
                ->where('village_id', $villageId)
                ->where('slug', $slug)
                ->first();

            if (! $venue instanceof Venue) {
                $venue = Venue::create([
                    'village_id' => $villageId,
                    'name' => Str::limit($name, 250, ''),
                    'street' => $row['street'] ? Str::limit((string) $row['street'], 250, '') : null,
                    'postcode' => $municipality->zip,
                    'body' => $describer->forVenue($name, $municipality->fullname),
                    'status' => ModelStatus::Draft->value,
                    'country' => 'Slovensko',
                ]);
            }

            $this->venueCache[$cacheKey] = $venue->id;
        }

        // Vlastníctvo si importované miesto nenárokuje: tú istú obec prinesú
        // desiatky kanálov a prvý z nich by ju inak dostal do správy.
        $venue->assignCanal($canal, isOwner: false);

        return $venue;
    }

    /**
     * Scraper starého webu občas zachytil rok zo znenia článku: podujatie z
     * roku 2022 tak má `start_at` v roku 1452 a koniec až v roku 8330 — nad
     * stropom MySQL TIMESTAMP-u (2038), na ktorom insert padne.
     *
     * Pokazený je však výlučne rok. Deň, mesiac aj čas sedia — v 133 takých
     * podujatiach zo starej databázy dá prepis roka na rok vzniku záznamu
     * odstup 0 až 160 dní, teda presne to, ako pozvánka vyzerá. Preto sa rok
     * neháda ani sa dátum nezahadzuje: prepíše sa na rok vzniku, a keď by tým
     * podujatie vyšlo pred vznikom článku, na rok nasledujúci (decembrová
     * pozvánka na januárovú akciu — 8 prípadov).
     *
     * Koniec dostane rovnaký posun ako začiatok, takže viacdňovej akcii ostane
     * jej trvanie aj hodiny.
     *
     * @param  array<string, mixed>  $row
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable, 2: bool, 3: bool}
     */
    private function resolveDates(array $row, CarbonImmutable $createdAt): array
    {
        $startAt = $this->toDate($row['start_at'] ?? null);
        $endAt = $this->toDate($row['end_at'] ?? null);

        // Bez začiatku sa nedá zachrániť nič — deň ani mesiac neexistujú.
        // Volajúci taký riadok preskočí.
        if ($startAt === null) {
            return [null, null, false, false];
        }

        $yearCorrected = false;

        if ($startAt->year < $createdAt->year - 1 || $startAt->year > $createdAt->year + 3) {
            $corrected = $startAt->setYear($createdAt->year);

            // Malý záporný odstup je bežný (pozvánka doplnená deň po začiatku),
            // väčší znamená, že akcia patrí až do nasledujúceho roka.
            if ($corrected->lessThan($createdAt->subDays(3))) {
                $corrected = $corrected->addYear();
            }

            $shift = $corrected->year - $startAt->year;
            $startAt = $corrected;
            $endAt = $endAt?->addYears($shift);
            $yearCorrected = true;
        }

        // Koniec skorší než začiatok alebo vzdialenejší než rok je chyba
        // čítania, nie podujatie — skutočné viacdňové akcie trvajú dni.
        $endEstimated = false;
        if ($endAt === null || $endAt->lessThan($startAt) || $endAt->greaterThan($startAt->addYear())) {
            $endAt = $this->avoidDstGap($startAt->addHours(2));
            $endEstimated = true;
        }

        return [$startAt, $endAt, $yearCorrected, $endEstimated];
    }

    /**
     * Aplikácia počíta v UTC, ale MySQL prijíma hodnotu ako lokálny čas. Keď
     * dopočítaný koniec padne do jarnej medzery posunu času (28. 3. 2021 o
     * 02:00 na Slovensku neexistuje), server insert odmietne s „Incorrect
     * datetime value“. Taká hodnota sa preto posunie o hodinu ďalej.
     */
    private function avoidDstGap(CarbonImmutable $value): CarbonImmutable
    {
        $zone = new DateTimeZone((string) config('services.imports.legacy.database_timezone'));
        $wallClock = $value->format('Y-m-d H:i:s');

        try {
            $local = new DateTimeImmutable($wallClock, $zone);
        } catch (Throwable) {
            return $value;
        }

        // PHP neexistujúci čas ticho normalizuje na inú hodinu — rozdiel
        // oproti zadanému reťazcu je teda dôkaz, že hodnota v medzere je.
        return $local->format('Y-m-d H:i:s') === $wallClock ? $value : $value->addHour();
    }

    private function toDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '' || str_starts_with($value, '0000-')) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
