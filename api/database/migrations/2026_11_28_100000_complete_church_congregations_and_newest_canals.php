<?php

use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\Venue;
use App\Services\Resources\ResourceMerger;
use App\Services\SystemLog\Recorder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Kanály Cirkvi bratskej ako miestne zbory a posledné kanály a miesta
 * k 24. 11. 2026 (#1981 – #1983, #1113 – #1116).
 *
 * Cirkev bratská: #1172 „Cirkvi bratskej" (genitív z vety „v modlitebni Cirkvi
 * bratskej") a #1852 „Cirkev bratská" neboli celá cirkev, ale zbory, v ktorých
 * modlitebni sa podujatie konalo — Cukrová 4 v Bratislave (Zbor Cirkvi bratskej
 * v Bratislave, register MK SR) a Slovenská 34 v Prešove (text podujatia).
 * Dostanú názov v tvare, aký odteraz tvorí import (App\Services\Imports\ChurchName):
 * „Cirkev bratská Bratislava", „Cirkev bratská Prešov". #1852 sedel na Bratislave,
 * hoci jeho jediné podujatie je v Prešove.
 *
 * Posledné kanály: #1982 sestry SDR dostanú adresu kláštora (Zimná 66, register
 * reholí MK SR), #1981 GOLD Orchestra popis z textu pozvánky. #1983 „Tranoscius
 * a VD ECAV" je zlepenie organizátora so spoluorganizátorom — zlúči sa do
 * #1052 Vydavateľstvo Tranoscius.
 *
 * Miesta: #1115 „Zimná 66" je kláštor sestier SDR, #1114 Blumentálsky kostol
 * dostane celý popis. Duplicity: #1116 → #121 Coworking Poprad (tá istá adresa),
 * koncept #1113 → #850 svätyňa v Smižanoch (rovnako ako skôr zlúčené #1068, #1104).
 *
 * Kanál musí sedieť ID aj názvom; prevzatý, zlúčený či zmazaný sa preskočí.
 * Popis sa prepíše len vtedy, keď ešte nemá tri odseky; ulica sa dopĺňa do prázdnej
 * (#1852 mal z importu adresu ústredia cirkvi, Cukrová 14 — tá sa nahradí).
 */
return new class extends Migration
{
    private const CLOSING = 'Podrobnosti nájdete vždy v detaile podujatia.';

    private const OTHERS = 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.';

    /** @var array<int, array<string, mixed>> */
    private const CANALS = [
        1172 => [
            'name' => 'Cirkvi bratskej',
            'rename' => 'Cirkev bratská Bratislava',
            'municipality' => 242,
            'address' => ['street' => 'Cukrová 4', 'postcode' => '811 08'],
            'body' => [
                'Zbor Cirkvi bratskej v Bratislave sa schádza v modlitebni na Cukrovej ulici 4. Cirkev bratská je evanjelikálna protestantská cirkev, registrovaná na Slovensku, s ústredím tiež na Cukrovej ulici.',
                'V modlitebni sa konajú nedeľné bohoslužby a ekumenické stretnutia, napríklad bratislavské podujatie Svetového dňa modlitieb žien.',
                'Kanál je určený členom a priateľom Cirkvi bratskej v Bratislave. Sledovaním budete vedieť o podujatiach zboru. '.self::CLOSING,
            ],
        ],
        1852 => [
            'name' => 'Cirkev bratská',
            'rename' => 'Cirkev bratská Prešov',
            'municipality' => 2822,
            'fix_municipality_from' => 242,
            'address' => ['street' => 'Slovenská 34', 'postcode' => '080 01'],
            // Adresa ústredia cirkvi v Bratislave, nie prešovského zboru.
            'replace_street' => 'Cukrová 14',
            'body' => [
                'Zbor Cirkvi bratskej v Prešove sa schádza v modlitebni na Slovenskej ulici 34. Cirkev bratská je evanjelikálna protestantská cirkev, ktorá na Slovensku pôsobí od začiatku 20. storočia.',
                'V modlitebni sa konajú bohoslužby a podujatia pre verejnosť, napríklad výstava Biblií „Stretnutie s Bibliou", ktorú zbor pripravil so Slovenskou biblickou spoločnosťou.',
                'Kanál je určený členom a priateľom Cirkvi bratskej v Prešove. Sledovaním budete vedieť o podujatiach zboru. '.self::CLOSING,
            ],
        ],
        1982 => [
            'name' => 'Kongregácia sestier Božského Vykupiteľa',
            'municipality' => 3240,
            'address' => ['street' => 'Zimná 66', 'postcode' => '052 01'],
            'body' => [
                'Kongregácia sestier Božského Vykupiteľa (SDR) je ženská katolícka rehoľa. Slovenská provincia sídli v kláštore na Zimnej ulici 66 v Spišskej Novej Vsi.',
                'Sestry sa venujú starostlivosti o chorých a seniorov — v Spišskej Novej Vsi založili neziskovú organizáciu Alžbetka. Pre dievčatá a mladé ženy pripravujú duchovné obnovy v kláštore.',
                'Kanál je určený mladým ženám a všetkým, ktorí hľadajú duchovné povzbudenie. Sledovaním budete vedieť o obnovách a stretnutiach sestier. '.self::CLOSING,
            ],
        ],
        1981 => [
            'name' => 'GOLD Orchestra Detva',
            'municipality' => 542,
            'body' => [
                'GOLD Orchestra je hudobná skupina z Detvy. Hrá najmä piesne z Podpoľania v úprave pre orchester, s fujarou či heligónkou.',
                'Pripravuje vianočné koncerty v detvianskom Kostole sv. Františka z Assisi s hosťami z Detvy a okolia, napríklad so ZUŠ S. Stračinu, zborom Rechtorovci či FS Dubkáčik.',
                'Kanál je určený milovníkom ľudovej a vianočnej hudby. Sledovaním budete vedieť o koncertoch orchestra. '.self::CLOSING,
            ],
        ],
    ];

    /** Zdrojový kanál => [cieľ, názov zdroja, názov cieľa]. */
    private const CANAL_MERGES = [
        1983 => [1052, 'Tranoscius a VD ECAV', 'Vydavateľstvo Tranoscius, a. s.'],
    ];

    /** @var array<int, array<string, mixed>> */
    private const VENUES = [
        1115 => ['name' => 'Zimná 66', 'fix_name' => 'Kláštor sestier Božského Vykupiteľa (Spišská Nová Ves)', 'village' => 3240,
            'postcode' => '052 01', 'body' => [
                'Kláštor Kongregácie sestier Božského Vykupiteľa stojí na Zimnej ulici 66 v Spišskej Novej Vsi. Je sídlom slovenskej provincie sestier SDR.',
                'Konajú sa tu duchovné obnovy, napríklad víkendové obnovy pre dievčatá a ženy od 17 do 30 rokov pred adventom.',
                self::OTHERS,
            ]],
        1114 => ['name' => 'Kostol Nanebovzatia Panny Márie (Blumentálsky)', 'village' => 242, 'body' => [
            'Kostol Nanebovzatia Panny Márie, známy ako Blumentálsky kostol, stojí v bratislavskom Starom Meste na rohu Floriánskeho námestia a Blumentálskej ulice. Postavili ho v rokoch 1885 – 1888 v eklektickom slohu.',
            'Je farským kostolom farnosti Bratislava-Blumentál. Okrem svätých omší sa tu konajú pobožnosti a modlitbové stretnutia, napríklad krížová cesta za kňazov so sestrou Zdenkou.',
            self::OTHERS,
        ]],
    ];

    /** Zdroj => [cieľ, názov zdroja, názov cieľa]. */
    private const VENUE_MERGES = [
        1116 => [121, 'Námestie svätého Egídia 11/25', 'Coworking Poprad'],
        1113 => [850, 'Centrum Božieho milosrdenstva', 'Diecézna svätyňa Božieho milosrdenstva'],
    ];

    public function up(): void
    {
        $this->completeCanals();
        $this->mergeCanals();
        $this->completeVenues();
        $this->mergeVenues();
    }

    private function completeCanals(): void
    {
        foreach (self::CANALS as $id => $profile) {
            $canal = Canal::withTrashed()->find($id);

            if (! $canal || $canal->trashed() || $canal->merged_into_id !== null || $canal->claimed_at !== null
                || ($canal->name !== $profile['name'] && $canal->name !== ($profile['rename'] ?? null))) {
                continue;
            }

            $municipality = (int) $canal->municipality_id;
            if ($municipality !== $profile['municipality'] && $municipality !== ($profile['fix_municipality_from'] ?? null)) {
                continue;
            }

            $changes = [];

            if (isset($profile['rename']) && $canal->name !== $profile['rename']) {
                $changes['name'] = $profile['rename'];
                $changes['slug'] = $this->freeSlug($profile['rename'], $canal->id);
            }

            if ($municipality !== $profile['municipality']) {
                $changes['municipality_id'] = $profile['municipality'];
            }

            if (substr_count((string) $canal->body, '<p') !== 3) {
                $changes['body'] = self::paragraphs($profile['body']);
            }

            $street = trim((string) $canal->street);
            if (isset($profile['address']) && ($street === '' || $street === ($profile['replace_street'] ?? null))) {
                $changes['street'] = $profile['address']['street'];
                $changes['postcode'] = $profile['address']['postcode'];
            }

            if ($changes !== []) {
                $canal->forceFill($changes)->save();
                Recorder::info('canals', 'profile_described', "Kanál {$canal->name}: doplnené ".implode(', ', array_keys($changes)),
                    status: 'ok', subject: $canal, context: ['fields' => array_keys($changes)]);
            }
        }
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
            try {
                app(ResourceMerger::class)->merge('canals', $sourceId, $targetId, null);
            } catch (Throwable $e) {
                Recorder::warning('admin', 'resource.merge_skipped', "Zlúčenie kanála #{$sourceId} → #{$targetId} preskočené",
                    subject: $target, context: ['error' => mb_substr($e->getMessage(), 0, 250)]);
            }
        }
    }

    private function completeVenues(): void
    {
        foreach (self::VENUES as $id => $data) {
            $venue = Venue::query()->find($id);

            if (! $venue || $venue->status === ModelStatus::Archived || (int) $venue->village_id !== $data['village']
                || ($venue->name !== $data['name'] && $venue->name !== ($data['fix_name'] ?? null))) {
                continue;
            }

            $changes = [];

            if (isset($data['fix_name']) && $venue->name === $data['name']) {
                $changes['name'] = $data['fix_name'];
            }

            if (substr_count((string) $venue->body, '<p') !== 3) {
                $changes['body'] = self::paragraphs($data['body']);
            }

            if (isset($data['postcode']) && trim((string) $venue->postcode) === '') {
                $changes['postcode'] = $data['postcode'];
            }

            if ($changes !== []) {
                $venue->forceFill($changes)->save();
                Recorder::info('venues', 'profile_described', "Miesto {$venue->name}: doplnené ".implode(', ', array_keys($changes)),
                    status: 'ok', subject: $venue, context: ['fields' => array_keys($changes)]);
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

            // Koncept z importu bez podujatí sa zlučuje ako publikovaný — inak ho merger odmietne.
            if ($source->status === ModelStatus::Draft && ! DB::table('events')->where('venue_id', $sourceId)->exists()) {
                $source->forceFill(['status' => $target->status])->save();
            }

            try {
                app(ResourceMerger::class)->merge('venues', $sourceId, $targetId, null);
            } catch (Throwable $e) {
                Recorder::warning('admin', 'resource.merge_skipped', "Zlúčenie miesta #{$sourceId} → #{$targetId} preskočené",
                    subject: $target, context: ['error' => mb_substr($e->getMessage(), 0, 250)]);
            }
        }
    }

    /** Slug nového názvu; keď ho už má iný kanál (aj zmazaný), dostane číslo. */
    private function freeSlug(string $name, int $canalId): string
    {
        $base = Str::slug($name);
        $slug = $base;

        for ($i = 2; DB::table('canals')->where('slug', $slug)->where('id', '!=', $canalId)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    /** @param  array<int, string>  $paragraphs */
    private static function paragraphs(array $paragraphs): string
    {
        return implode('', array_map(fn (string $p) => '<p>'.e($p).'</p>', $paragraphs));
    }

    public function down(): void
    {
        // Doplnené údaje, premenovania a zlúčenia sa nevracajú.
    }
};
