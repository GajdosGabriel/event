<?php

namespace App\Console\Commands;

use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\Event;
use App\Models\Municipality;
use App\Models\Venue;
use App\Services\Imports\HlascirkviSourceUrl;
use App\Services\Imports\ImportedCanalManager;
use App\Services\Imports\ImportedProfileDescriber;
use App\Services\Imports\ImportedVenueManager;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use SplFileObject;
use Throwable;

/**
 * Nahrá archív podujatí zo starého projektu hlascirkvi (JSONL z
 * app:hlascirkvi-export) do nového dátového modelu. Obrázky rieši samostatný
 * app:hlascirkvi-import-images — sťahovanie 11 000 súborov je dlhá operácia,
 * ktorá sa musí dať opakovať bez toho, aby sa znovu prepisovali podujatia.
 */
class ImportHlascirkviEvents extends Command
{
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
        {--file= : Cesta k JSONL súboru relatívne k storage/app}
        {--limit=0 : Spracovať najviac N riadkov (0 = všetky)}
        {--dry-run : Prejde celý súbor, ale zmeny na konci vráti späť}
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
    ): int {
        // Pri 12 000 podujatiach by detekcia cez AI a geokóder znamenala
        // desaťtisíce volaní navyše — a nemá čo pridať, obec aj organizátor
        // sú v exporte známe. Miesta bez súradníc dorieši app:venues-backfill.
        config([
            'services.imports.detect_canal_with_ai' => false,
            'services.imports.describe_with_ai' => false,
        ]);

        $relative = (string) ($this->option('file') ?: config('services.imports.legacy.file'));
        $path = storage_path('app/'.ltrim($relative, '/'));
        if (! is_file($path)) {
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
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0, 'date_unreliable' => 0];
        $canalsBefore = Canal::query()->count();
        $venuesBefore = Venue::query()->count();

        // Transakcia je len nástroj dry-runu. Ostrý beh ju zámerne nepoužíva:
        // pri 12 000 podujatiach by jedna dlhá transakcia držala zámky hodiny
        // a zlyhanie na konci by zahodilo celý import. Bez nej sa dá príkaz
        // kedykoľvek prerušiť a spustiť znovu — už nahraté sa preskočí.
        if ($dryRun) {
            DB::beginTransaction();
        }

        $handle = new SplFileObject($path, 'r');
        $processed = 0;

        while (! $handle->eof()) {
            $line = trim((string) $handle->fgets());
            if ($line === '') {
                continue;
            }

            $row = json_decode($line, true);
            if (! is_array($row)) {
                $stats['failed']++;

                continue;
            }

            try {
                $result = $this->importRow($row, $canalManager, $venueManager, $describer, $owner->id, $force);
                $stats[$result['status']]++;
                if ($result['date_unreliable']) {
                    $stats['date_unreliable']++;
                }
            } catch (Throwable $e) {
                $stats['failed']++;
                Log::error('hlascirkvi import: riadok zlyhal', [
                    'legacy_id' => $row['legacy_id'] ?? null,
                    'message' => $e->getMessage(),
                ]);
                $this->warn("Podujatie {$row['legacy_id']}: {$e->getMessage()}");
            }

            $processed++;
            if ($processed % 500 === 0) {
                $this->line("  … spracovaných {$processed}");
            }
            if ($limit > 0 && $processed >= $limit) {
                break;
            }
        }

        $newCanals = Canal::query()->count() - $canalsBefore;
        $newVenues = Venue::query()->count() - $venuesBefore;

        if ($dryRun) {
            DB::rollBack();
            $this->warn('DRY RUN — všetky zmeny boli vrátené späť.');
        }

        $this->table(
            ['vytvorené', 'aktualizované', 'preskočené', 'chybné', 'nespoľahlivý dátum', 'nové kanály', 'nové miesta'],
            [[
                $stats['created'], $stats['updated'], $stats['skipped'], $stats['failed'],
                $stats['date_unreliable'], $newCanals, $newVenues,
            ]],
        );

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{status: string, date_unreliable: bool}
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
            return ['status' => 'skipped', 'date_unreliable' => false];
        }

        $canal = $this->resolveCanal($row, $canalManager);
        $venue = $this->resolveVenue($row, $canal, $venueManager, $describer);

        $createdAt = $this->toDate($row['created_at'] ?? null) ?? CarbonImmutable::now();
        [$startAt, $endAt, $unreliable] = $this->resolveDates($row, $createdAt);

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
                    'date_unreliable' => $unreliable,
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
            'date_unreliable' => $unreliable,
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
     * Scraper starého webu občas zachytil rok zo znenia článku — podujatie z
     * roku 2022 tak má `start_at` v roku 1452. Dátum, ktorý sa výrazne
     * rozchádza s dňom vzniku záznamu, sa preto nahradí dňom vzniku a označí
     * v meta, aby sa dal neskôr dohľadať.
     *
     * @param  array<string, mixed>  $row
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: bool}
     */
    private function resolveDates(array $row, CarbonImmutable $createdAt): array
    {
        $startAt = $this->toDate($row['start_at'] ?? null);
        $endAt = $this->toDate($row['end_at'] ?? null);
        $unreliable = false;

        if ($startAt === null || $startAt->year < $createdAt->year - 1 || $startAt->year > $createdAt->year + 3) {
            $startAt = $createdAt;
            $unreliable = true;
        }

        // Rovnaká chyba scrapera zasiahla aj koniec podujatia, tam však vyšla
        // roky dopredu (8330, 7119…) — nad strop MySQL TIMESTAMP-u (2038), na
        // ktorom insert padol. Koniec vzdialenejší než rok od začiatku je vždy
        // chyba čítania, nie podujatie; skutočné viacdňové akcie sú v rámci dní.
        if ($endAt === null || $endAt->lessThan($startAt) || $endAt->greaterThan($startAt->addYear())) {
            $endAt = $this->avoidDstGap($startAt->addHours(2));
            $unreliable = true;
        }

        return [$startAt, $endAt, $unreliable];
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
