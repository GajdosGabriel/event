<?php

use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\Event;
use App\Models\Venue;
use App\Services\Canals\CanalEmails;
use App\Services\Events\DuplicateEventMerger;
use App\Services\Resources\ResourceMerger;
use App\Services\SystemLog\Recorder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Duplicity z verejných zdrojov (kontrola 26. 11. 2026) a chyby, na ktoré sa
 * pri nej prišlo.
 *
 * Kanály: dvojice s rovnakým webom alebo e-mailom, ktoré sú ten istý
 * organizátor pod iným zápisom (Spišské biskupstvo = Spišská diecéza, GKA
 * Prešov = Prešovská archieparchia, NOS ECAV = Novohradský seniorát ECAV…).
 * Nadriadená a podriadená organizácia (fakulta – katedra, PMD – Misijné dielo
 * detí) sa nezlučuje. Marana Tha: ostáva starší kanál #70, dostane sídlo
 * v Prešove z #1892 (mal obec Košice, web maranathapo.sk).
 *
 * Weby: import dával kanálom web zdroja či spoluorganizátora — hlascirkvi.sk
 * (archív), www.ecav.sk organizáciám mimo ECAV, abu.sk Trnavskej arcidiecéze,
 * hanusovedni.sk zboru v Modre, misijnediela.sk Dobrej novine… Chybný web sa
 * zmaže, overený sa doplní. Trnavská arcidiecéza stratí adresu abu@abu.sk
 * (Bratislavská arcidiecéza), Sigord – Centrum pre rodinu súradnice pri Seredi,
 * #1297 preklep „Najsvätejšich" v názve.
 *
 * Miesta: Zrkadlová sála #1062 → Zrkadlová sieň Primaciálneho paláca #723,
 * Katedrála sv. Jána Krstiteľa v Prešove #1045 dostane obec do názvu (je to iná
 * katedrála než trnavská #123).
 *
 * Podujatia: 34 skupín tej istej akcie z viacerých článkov (rovnaký začiatok,
 * miesto aj obsah, prečítané ručne). Ostáva podujatie pravého organizátora,
 * inak s dlhším popisom; zahodené idú do koša a ich zdroj si pamätá
 * `meta.merged_sources` (DuplicateEventMerger), takže ich import nezaloží znova.
 *
 * Všetko sa robí len vtedy, keď záznamy ešte sedia ID aj názvom; prevzatý
 * kanál a podujatie prevzatého kanála sa nemenia.
 */
return new class extends Migration
{
    /** Zdrojový kanál => [cieľ, názov zdroja, názov cieľa]. */
    private const CANAL_MERGES = [
        1902 => [36, 'Spišské biskupstvo', 'Spišská diecéza'],
        1664 => [48, 'Gréckokatolícke arcibiskupstvo Prešov', 'Prešovská archieparchia'],
        1953 => [48, 'Gréckokatolícke arcibiskupstvo Prešov a Združenie Sakrálne spevy Východu o.z', 'Prešovská archieparchia'],
        115 => [1974, 'GKMK, s.r.o.', 'Škola Kresťanský kouč'],
        299 => [1961, 'KOSTOL ECAV PEZINOK', 'CZ ECAV Pezinok'],
        1980 => [1990, 'NOS ECAV', 'Novohradský seniorát ECAV'],
        1177 => [1882, 'novaJAR', 'Vydavateľstvo novaJAR'],
        380 => [339, 'Gréckokatolícka teologická fakulta v Prešove', 'Gréckokatolícka teologická fakulta Prešovskej univerzity'],
        915 => [373, 'Kolégium ONLINE', 'Univerzitné kolégium Antona Neuwirtha'],
        367 => [1096, 'Tím RADOSTNÁ', 'Projekt Radostná'],
        1033 => [953, 'Tím zasvätených laikov Miles Jesu', 'Miles Jesu'],
        1931 => [1881, 'Spoločenstvo Vykroč', 'Občianske združenie Vykročiť'],
        235 => [347, 'Nitrianske biskupstvo s farnosťou sv. Urbana', 'Nitrianske biskupstvo'],
        357 => [1197, 'Pavlíni', 'Rehoľa Pavlínov'],
        1965 => [1374, 'ZD a VD ECAV', 'Biskupské úrady VD a ZD ECAV na Slovensku'],
        47 => [196, 'Centrum pre rodinu', 'Sigord - Centrum pre rodinu'],
        961 => [688, 'Bratislavské Hanusove dni', 'Spoločenstvo Ladislava Hanusa'],
        1169 => [42, 'Vinárstvo MEA GRATI', 'MEA GRATI'],
        1892 => [70, 'M-Aréna a spoločenstvo MaranaTha', 'Katolícke spoločenstvo Marana Tha'],
        475 => [153, 'saleziánska rodina', 'Saleziáni don Bosca'],
        1801 => [153, 'saleziánska komunita', 'Saleziáni don Bosca'],
        1877 => [1208, 'tím SingleKatolici.sk', 'Nádej - spoločenstvo slobodných'],
        740 => [93, 'Ekumenické spoločenstvo v Košiciach', 'Ekumenické spoločenstvo cirkví a náboženských spoločností na území mesta Košice'],
        1914 => [345, 'Katolícka cirkev', 'Katolícka cirkev na Slovensku'],
        65 => [1906, 'Spoločnosť Božieho Slova', 'Slovenská a česká provincia Spoločnosti Božieho Slova'],
    ];

    /**
     * Kanál => [názov, chybný web (host) alebo null = ľubovoľný, správny web alebo null = zmazať].
     *
     * @var array<int, array{0: string, 1: string|null, 2: string|null}>
     */
    private const CANAL_WEBSITES = [
        186 => ['Koinonia Ján Krstiteľ', 'hlascirkvi.sk', null],
        189 => ['UPeCe Žilina', 'hlascirkvi.sk', null],
        190 => ['Gabriel Gajdoš', 'hlascirkvi.sk', null],
        192 => ['Neuvedený', 'hlascirkvi.sk', null],
        199 => ['Loan Magma', 'hlascirkvi.sk', null],
        203 => ['Služobnice Ducha Svätého ustavičnej poklony', 'hlascirkvi.sk', null],
        18 => ['SNM – Múzeum Ľudovíta Štúra', 'ecav.sk', 'https://www.snm.sk'],
        22 => ['Cirkevný odbor Ministerstva kultúry SR', 'ecav.sk', null],
        24 => ['Ars ante portas', 'ecav.sk', null],
        50 => ['Občianske združenie Poľné ľalie', 'ecav.sk', null],
        808 => ['SLUHA o. z', 'ecav.sk', null],
        951 => ['Evanjelické kolegiálne gymnázium v Prešove', 'ecav.sk', null],
        995 => ['Úrad Ekumenickej pastoračnej služby Ministerstva vnútra SR', 'ecav.sk', null],
        1052 => ['Vydavateľstvo Tranoscius, a. s.', 'ecav.sk', 'https://www.tranoscius.sk'],
        1302 => ['CZ ECAV Modra', 'hanusovedni.sk', null],
        1178 => ['Mariánski ctitelia', 'sprevadzajuci.sk', null],
        1562 => ['Bratia Dominikáni – farnosť Bratislava Kalvária', 'sprevadzajuci.sk', null],
        429 => ['Úsmev ako dar', 'podelmesa.org', null],
        1625 => ['Kolednícka akcia Dobrá novina', 'misijnediela.sk', null],
        782 => ['Gréckokatolícka cirkev na Slovensku', 'narodnestretnutiemladeze.sk', null],
        568 => ['Trnavská arcidiecéza', 'abu.sk', null],
        1297 => ['Misionári Najsvätejšich Sŕdc Pána Ježiša a Panny Márie', 'misionari.sk', 'https://www.msscc.sk'],
        559 => ['Slovenská komora sestier a pôrodných asistentiek', 'charita-ke.sk', 'https://www.sksapa.sk'],
        485 => ['Rada KBS pre rodinu', 'laityfamilylife.va', 'https://rodina.kbs.sk'],
        216 => ['Aliancia za rodinu', 'zajazerom.sk', 'https://www.alianciazarodinu.sk'],
        153 => ['Saleziáni don Bosca', 'sdb.org', 'https://saleziani.sk'],
    ];

    /** Kanál => [názov s preklepom, opravený názov] — slug ostáva rovnaký. */
    private const CANAL_RENAMES = [
        1297 => ['Misionári Najsvätejšich Sŕdc Pána Ježiša a Panny Márie', 'Misionári Najsvätejších Sŕdc Pána Ježiša a Panny Márie'],
    ];

    /** Kanál => [názov, adresa, ktorá patrí inej inštitúcii]. */
    private const CANAL_FOREIGN_EMAILS = [
        568 => ['Trnavská arcidiecéza', 'abu@abu.sk'],
    ];

    /** Kanál => [názov, chybná zemepisná šírka, [lat, lng, zdroj]]. */
    private const CANAL_COORDINATES = [
        196 => ['Sigord - Centrum pre rodinu', 48.1014360, [48.9559394, 21.3913847, 'address']],
    ];

    /** Zdroj => [cieľ, názov zdroja, názov cieľa]. */
    private const VENUE_MERGES = [
        1062 => [723, 'Zrkadlová sála Primaciálneho paláca', 'Zrkadlová sieň Primaciálneho paláca'],
    ];

    /** Miesto => [obec, starý názov, nový názov]. */
    private const VENUE_RENAMES = [
        1045 => [2822, 'Katedrála svätého Jána Krstiteľa', 'Katedrála svätého Jána Krstiteľa (Prešov)'],
    ];

    /**
     * Ostávajúce podujatie => [zahodené, prípadne správne miesto].
     *
     * @var array<int, array{0: array<int, int>, 1?: int}>
     */
    private const EVENT_GROUPS = [
        148 => [[279]],
        114 => [[316]],
        10897 => [[10911]],
        199 => [[318, 10925]],
        287 => [[282]],
        21 => [[93, 294]],
        273 => [[206, 10946, 10252, 180]],
        11018 => [[11042]],
        11013 => [[11043], 967],
        10999 => [[63]],
        11055 => [[11093]],
        11275 => [[11290]],
        11134 => [[10967, 11108]],
        11123 => [[11162]],
        11168 => [[11164]],
        11133 => [[11167]],
        11271 => [[11274]],
        11276 => [[11186]],
        11320 => [[11286]],
        11359 => [[11346]],
        11295 => [[11380]],
        11232 => [[11400]],
        11424 => [[11425]],
        11326 => [[11402]],
        11291 => [[11450]],
        11473 => [[11449]],
        11467 => [[11391]],
        11272 => [[11364]],
        11 => [[68]],
        11231 => [[11344]],
        11410 => [[11317]],
        10927 => [[10476]],
        11160 => [[11292]],
        10912 => [[10973]],
    ];

    public function up(): void
    {
        $this->mergeCanals();
        $this->settleMaranaTha();
        $this->fixCanals();
        $this->mergeVenues();
        $this->renameVenues();
        $this->mergeEvents();
    }

    private function mergeCanals(): void
    {
        foreach (self::CANAL_MERGES as $sourceId => [$targetId, $sourceName, $targetName]) {
            $source = Canal::query()->find($sourceId);
            $target = Canal::query()->find($targetId);
            if (! $source || ! $target || $source->name !== $sourceName || $target->name !== $targetName
                || $source->claimed_at !== null || $source->merged_into_id !== null || $target->merged_into_id !== null) {
                continue;
            }

            // Archivovaný či koncept bez podujatí sa zlúči ako cieľ — inak ho merger odmietne.
            if ($source->status !== $target->status && $source->status !== ModelStatus::Archived
                && ! Event::query()->where('canal_id', $sourceId)->exists()) {
                $source->forceFill(['status' => $target->status])->save();
            }

            $this->dropWeakerLinks($sourceId, $targetId);

            try {
                app(ResourceMerger::class)->merge('canals', $sourceId, $targetId, null);
            } catch (Throwable $e) {
                Recorder::warning('admin', 'resource.merge_skipped', "Zlúčenie kanála #{$sourceId} → #{$targetId} preskočené",
                    subject: $target, context: ['error' => mb_substr($e->getMessage(), 0, 250)]);
            }
        }
    }

    /**
     * Väzba zdroja na miesto, ktoré má cieľ silnejšie (vlastník, zverejnené),
     * nič nepridáva — merger by ju ale vyhodnotil ako konflikt (Spišské
     * biskupstvo a Spišská Kapitula).
     */
    private function dropWeakerLinks(int $sourceId, int $targetId): void
    {
        $target = DB::table('canal_venue')->where('canal_id', $targetId)->get()->keyBy('venue_id');

        foreach (DB::table('canal_venue')->where('canal_id', $sourceId)->get() as $link) {
            $own = $target->get($link->venue_id);
            if ($own && (int) $own->is_owner >= (int) $link->is_owner
                && ($own->status === $link->status || $own->status === ModelStatus::Published->value)) {
                DB::table('canal_venue')->where('canal_id', $sourceId)->where('venue_id', $link->venue_id)->delete();
            }
        }
    }

    /** #70 Marana Tha mal obec Košice; sídlo z #1892 je Švábska 22A v Prešove. */
    private function settleMaranaTha(): void
    {
        $canal = Canal::query()->find(70);
        $merged = Canal::withTrashed()->find(1892);

        if (! $canal || $canal->claimed_at !== null || ! $merged || (int) $merged->merged_into_id !== 70
            || (int) $canal->municipality_id !== 1565 || $canal->coordinates_source !== 'municipality') {
            return;
        }

        $canal->forceFill([
            'municipality_id' => 2822, 'street' => 'Švábska 22A', 'postcode' => '080 05',
            'latitude' => 48.9752312, 'longitude' => 21.2623996, 'coordinates_source' => 'address',
        ])->save();
        Recorder::info('canals', 'profile_described', "Kanál {$canal->name}: sídlo v Prešove",
            status: 'ok', subject: $canal, context: ['fields' => ['municipality_id', 'street', 'postcode', 'latitude', 'longitude']]);
    }

    private function fixCanals(): void
    {
        foreach (self::CANAL_WEBSITES as $id => [$name, $wrongHost, $website]) {
            $canal = $this->openCanal($id, $name);
            if (! $canal || self::host($canal->website) !== $wrongHost) {
                continue;
            }

            $canal->forceFill(['website' => $website])->save();
            Recorder::info('canals', 'profile_described', "Kanál {$canal->name}: ".($website === null ? 'zmazaný cudzí web' : 'opravený web'),
                status: 'ok', subject: $canal, context: ['fields' => ['website'], 'was' => $wrongHost]);
        }

        if (Schema::hasTable('canal_emails')) {
            $emails = app(CanalEmails::class);

            foreach (self::CANAL_FOREIGN_EMAILS as $id => [$name, $foreign]) {
                $canal = $this->openCanal($id, $name);
                $all = $canal ? $emails->all($canal)->pluck('email')->all() : [];
                if (! $canal || ! in_array($foreign, $all, true)) {
                    continue;
                }

                $rest = array_values(array_diff($all, [$foreign]));
                $primary = $emails->primary($canal)?->email;
                $emails->sync($canal, $primary === $foreign ? ($rest[0] ?? null) : $primary, $rest);
                $emails->refresh($canal->fresh());
            }
        }

        foreach (self::CANAL_RENAMES as $id => [$old, $new]) {
            $canal = $this->openCanal($id, $old);
            if ($canal && Str::slug($old) === Str::slug($new)) {
                $canal->forceFill(['name' => $new, 'slug' => $canal->slug])->save();
            }
        }

        foreach (self::CANAL_COORDINATES as $id => [$name, $wrongLat, [$lat, $lng, $source]]) {
            $canal = $this->openCanal($id, $name);
            if ($canal && abs((float) $canal->latitude - $wrongLat) < 0.0001) {
                $canal->forceFill(['latitude' => $lat, 'longitude' => $lng, 'coordinates_source' => $source])->save();
            }
        }
    }

    private function mergeVenues(): void
    {
        foreach (self::VENUE_MERGES as $sourceId => [$targetId, $sourceName, $targetName]) {
            $source = Venue::query()->find($sourceId);
            $target = Venue::query()->find($targetId);
            if (! $source || ! $target || $source->name !== $sourceName || $target->name !== $targetName
                || $target->status === ModelStatus::Archived) {
                continue;
            }

            try {
                app(ResourceMerger::class)->merge('venues', $sourceId, $targetId, null);
            } catch (Throwable $e) {
                Recorder::warning('admin', 'resource.merge_skipped', "Zlúčenie miesta #{$sourceId} → #{$targetId} preskočené",
                    subject: $target, context: ['error' => mb_substr($e->getMessage(), 0, 250)]);
            }
        }
    }

    private function renameVenues(): void
    {
        foreach (self::VENUE_RENAMES as $id => [$village, $old, $new]) {
            $venue = Venue::query()->find($id);
            if ($venue && $venue->name === $old && (int) $venue->village_id === $village) {
                $venue->forceFill(['name' => $new])->save();
            }
        }
    }

    private function mergeEvents(): void
    {
        $merger = app(DuplicateEventMerger::class);

        foreach (self::EVENT_GROUPS as $keepId => $group) {
            [$dropIds, $venueId] = [$group[0], $group[1] ?? null];
            $keep = Event::query()->find($keepId);
            $drop = Event::query()->whereIn('id', $dropIds)->get();

            if (! $keep || $drop->isEmpty() || $drop->contains(fn (Event $event) => ! $event->start_at?->eq($keep->start_at))) {
                continue;
            }

            $all = $drop->concat([$keep]);
            $claimed = Canal::withTrashed()->whereIn('id', $all->pluck('canal_id')->filter())->whereNotNull('claimed_at')->exists();
            $blocker = $drop->map(fn (Event $event) => $merger->referenceCount($event))->filter()->first();

            if ($claimed || $blocker !== null) {
                Recorder::warning('events', 'merge_skipped', "Zlúčenie podujatia #{$keepId} preskočené",
                    subject: $keep, context: ['drop' => $dropIds, 'reason' => $claimed ? 'prevzatý kanál' : $blocker]);

                continue;
            }

            if ($venueId !== null && Venue::query()->whereKey($venueId)->exists()) {
                $keep->forceFill(['venue_id' => $venueId])->save();
            }

            $merger->mergeGroup($keep, $drop->all());
            Recorder::info('events', 'duplicates_merged', "Podujatie {$keep->name}: zlúčené duplicity",
                status: 'ok', subject: $keep, context: ['dropped' => $drop->pluck('id')->all()]);
        }
    }

    private function openCanal(int $id, string $name): ?Canal
    {
        $canal = Canal::query()->find($id);

        return $canal && $canal->name === $name && $canal->claimed_at === null && $canal->merged_into_id === null ? $canal : null;
    }

    private static function host(mixed $website): ?string
    {
        $host = is_string($website) ? parse_url(trim($website), PHP_URL_HOST) : null;

        return is_string($host) && $host !== '' ? preg_replace('/^www\./', '', strtolower($host)) : null;
    }

    public function down(): void
    {
        // Zlúčenia a opravy sa nevracajú — zlúčené záznamy ostávajú v koši.
    }
};
