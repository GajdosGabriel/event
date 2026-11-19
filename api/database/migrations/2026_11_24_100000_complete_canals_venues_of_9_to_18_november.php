<?php

use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\Venue;
use App\Services\Canals\CanalEmails;
use App\Services\Resources\ResourceMerger;
use App\Services\SystemLog\Recorder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Kanály a miesta vytvorené 9. – 18. 11. 2026, ktoré predchádzajúce dávky ešte
 * nedoplnili. Nové organizácie v tom čase nepribudli.
 *
 * Kanály: #1950 Emanuel bol priradený ku Klokočovu v okrese Čadca, hoci
 * spoločenstvo sa stretáva v Klokočove pri Michalovciach (#1456); #1952, #1954,
 * #1958 a #1974 mali súradnice stredu Slovenska. Adresy sú z webov organizácií
 * (opatstvojasov.sk, krestanskykouc.sk) a z Centrálneho registra zmlúv
 * (SCENE, o. z., IČO 31812252), súradnice z Nominatim/OpenStreetMap.
 * Osobné kanály (#1955, #1956, #1973) sa nemenia.
 *
 * Miesta: #11 dostane skutočný názov, adresu a popis (Kláštor kapucínov v Pezinku),
 * #82 správnu obec Klokočov, #1070, #1080, #1089 adresu a súradnice, #1098 názov
 * a adresu z článku TK KBS. Import vyrobil miesta z úryvkov textu alebo duplicity:
 * #1096 → #109 (Dóm sv. Alžbety, kde je svätá omša), #1097 → #982 (Námestie sv. Petra),
 * #1099 → #53, #1100 → #987, #1101 → #11, #1076 → #190 (Martin),
 * #1068 → #850 (Diecézna svätyňa Božieho milosrdenstva v Smižanoch na Jahodovej ulici).
 *
 * Kanál musí sedieť ID aj názvom; prevzatý, zlúčený či zmazaný sa preskočí.
 * Popis sa prepíše len vtedy, keď ešte nemá tri odseky; telefón, web a ulica
 * sa dopĺňajú do prázdneho poľa. Archivované miesta sa nemenia.
 */
return new class extends Migration
{
    private const CLOSING = 'Podrobnosti nájdete vždy v detaile podujatia.';

    private const OTHERS = 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.';

    /** Súradnice, ktoré import dáva kanálom bez adresy (stred Slovenska). */
    private const NATIONWIDE = [48.7411522, 19.4528646];

    /** @var array<int, array<string, mixed>> */
    private const CANALS = [
        1950 => [
            'name' => 'Emanuel',
            'municipality' => 1455,
            'fix_municipality' => 1456,
            'coordinates' => [48.8143498, 22.0296342, 'municipality'],
        ],
        1952 => [
            'name' => 'Premonštráti',
            'municipality' => 1565,
            'address' => ['street' => 'Kováčska 28', 'postcode' => '040 01'],
            'coordinates' => [48.7225449, 21.2587116, 'address'],
        ],
        1954 => [
            'name' => 'Iniciatíva na podporu cností vo verejnom živote',
            'municipality' => 242,
            'coordinates' => [48.1435026, 17.1082867, 'municipality'],
        ],
        1958 => [
            'name' => 'SCENE OZ Bratislava',
            'municipality' => 242,
            'address' => ['street' => 'Kúpeľná 7', 'postcode' => '811 02'],
            'coordinates' => [48.1412572, 17.1119165, 'address'],
        ],
        1974 => [
            'name' => 'Škola Kresťanský kouč',
            'municipality' => 2636,
            'website' => 'https://www.krestanskykouc.sk',
            'emails' => [['email' => 'info@krestanskykouc.sk', 'found_on' => 'https://www.krestanskykouc.sk/kontakt/']],
            'coordinates' => [48.2854539, 17.2701940, 'municipality'],
            'body' => [
                'Škola Kresťanský kouč pripravuje kresťanov na koučovanie. Jej hlavným programom je 92-hodinový kurz Kresťanský kouč – základy životného koučingu, akreditovaný medzinárodnou sieťou kresťanských koučov CCNI. Odborným garantom je PhDr. Marián Kubeš, CSc., duchovným garantom ThLic. Mgr. Ing. Maroš Lovič.',
                'Kurz prebieha kombinovane v Kláštore kapucínov v Pezinku a online. Škola má vyše 400 absolventov a ponúka aj nadväzujúce programy a certifikáciu CCNI.',
                'Kanál je určený tým, ktorí chcú koučovať v duchu kresťanských hodnôt. Sledovaním budete vedieť o nových bežiach kurzov. '.self::CLOSING,
            ],
        ],
    ];

    /** @var array<int, array<string, mixed>> */
    private const VENUES = [
        11 => ['name' => 'kombinovane online a Kláštor kapucínov v Pezinku', 'fix_name' => 'Kláštor kapucínov (Pezinok)', 'village' => 2636,
            'street' => 'Holubyho 91', 'postcode' => '902 01',
            'latitude' => 48.2888396, 'longitude' => 17.2661475, 'coordinates_source' => 'address', 'body' => [
                'Kláštor kapucínov stojí na Holubyho ulici v Pezinku. Barokový kláštorný Kostol Najsvätejšej Trojice pochádza z 18. storočia, zvonica z roku 1807. Kláštor patrí Rádu menších bratov kapucínov.',
                'Konajú sa tu sväté omše, duchovné obnovy a kurzy, napríklad Škola Kresťanský kouč. Časť programov prebieha kombinovane, na mieste aj online.',
                self::OTHERS,
            ]],
        82 => ['name' => 'Chrám Zosnutia Presvätej Bohorodičky (Klokočov)', 'village' => 1455, 'fix_village' => 1456, 'postcode' => '072 36'],
        1070 => ['name' => 'Salónik reštaurácie U Kormidla', 'fix_name' => 'Salónik reštaurácie U Kormidla (Brno)', 'village' => 4213,
            'street' => 'Kounicova 43', 'postcode' => '602 00',
            'latitude' => 49.2018818, 'longitude' => 16.6013913, 'coordinates_source' => 'venue'],
        1080 => ['name' => 'Evanjelický chrám Boží (Turá Lúka)', 'village' => 3628,
            'street' => 'Turá Lúka 420', 'postcode' => '907 03',
            'latitude' => 48.7433231, 'longitude' => 17.5389543, 'coordinates_source' => 'venue'],
        1089 => ['name' => 'Sála obecného úradu (Mníšek nad Hnilcom)', 'village' => 2218,
            'street' => 'Mníšek nad Hnilcom 292', 'postcode' => '055 64',
            'latitude' => 48.8061846, 'longitude' => 20.8065475, 'coordinates_source' => 'address'],
        1098 => ['name' => 'Trebišove. Požehná ho Mons. Bernard Bober', 'fix_name' => 'Dom blahoslavenej Emílie (Trebišov)', 'village' => 3580,
            'street' => 'M. R. Štefánika 150', 'replace_street' => 'M. R. Štefánika', 'postcode' => '075 01', 'body' => [
                'Dom blahoslavenej Emílie je nový misijno-pastoračný dom na Ulici M. R. Štefánika v Trebišove. Slúžiť má misijnej a pastoračnej práci.',
                'Dom slávnostne požehnal Mons. Bernard Bober, apoštolský administrátor Košickej arcidiecézy, 19. novembra 2026.',
                self::OTHERS,
            ]],
    ];

    /** Zdroj => [cieľ, názov zdroja, názov cieľa]. */
    private const MERGES = [
        1096 => [109, 'Košiciach. Zároveň si účastníci pripomenú aj 70. výročie súdnych procesov so sestrami vincentkami na Krajskom súde', 'Dóm svätej Alžbety'],
        1097 => [982, 'Ríme Patrik Mitura. Organizátori odporúčajú účastníkom p', 'Námestie sv. Petra'],
        1099 => [53, 'Kostolná - Záriečie 8, Kostolná - Záriečie', 'Bývalý kláštor redemptoristov (Kostolná-Záriečie)'],
        1100 => [987, 'kláštor redemptoristiek, Kežmarok', 'Kláštor sestier redemptoristiek (Kežmarok)'],
        1101 => [11, 'Kláštor kapucínov a online', 'Kláštor kapucínov (Pezinok)'],
        1076 => [190, 'Martin a ONLINE', 'Martin'],
        1068 => [850, 'Centrum Božieho milosrdenstva', 'Diecézna svätyňa Božieho milosrdenstva'],
    ];

    public function up(): void
    {
        $this->completeCanals();
        $this->completeVenues();
        $this->mergeVenues();
        $this->completeShrine();
    }

    private function completeCanals(): void
    {
        $emails = Schema::hasTable('canal_emails') ? app(CanalEmails::class) : null;

        foreach (self::CANALS as $id => $profile) {
            $canal = Canal::withTrashed()->find($id);

            if (! $canal || $canal->trashed() || $canal->merged_into_id !== null || $canal->claimed_at !== null
                || $canal->name !== $profile['name']) {
                continue;
            }

            $municipality = (int) $canal->municipality_id;
            if ($municipality !== $profile['municipality'] && $municipality !== ($profile['fix_municipality'] ?? null)) {
                continue;
            }

            $changes = [];

            if (isset($profile['fix_municipality']) && $municipality === $profile['municipality']) {
                $changes['municipality_id'] = $profile['fix_municipality'];
            }

            if (isset($profile['body']) && substr_count((string) $canal->body, '<p') !== 3) {
                $changes['body'] = self::paragraphs($profile['body']);
            }

            foreach (['website', 'phone'] as $field) {
                if (isset($profile[$field]) && trim((string) $canal->$field) === '') {
                    $changes[$field] = $profile[$field];
                }
            }

            // Kanál z importu mal nezabezpečený odkaz na ten istý web.
            if (isset($profile['website']) && $canal->website === str_replace('https://', 'http://', $profile['website'])) {
                $changes['website'] = $profile['website'];
            }

            if (isset($profile['address']) && trim((string) $canal->street) === '') {
                $changes['street'] = $profile['address']['street'];
                $changes['postcode'] = $profile['address']['postcode'];
            }

            $looseCoordinates = in_array($canal->coordinates_source, ['municipality', 'nationwide', 'ai', null], true);
            $atCenter = abs((float) $canal->latitude - self::NATIONWIDE[0]) < 0.0001
                && abs((float) $canal->longitude - self::NATIONWIDE[1]) < 0.0001;

            if ($looseCoordinates && ($atCenter || isset($changes['street']) || isset($changes['municipality_id']))) {
                [$lat, $lng, $source] = $profile['coordinates'];
                $changes += ['latitude' => $lat, 'longitude' => $lng, 'coordinates_source' => $source];
            }

            if ($changes !== []) {
                $canal->forceFill($changes)->save();
                Recorder::info('canals', 'profile_described', "Kanál {$canal->name}: doplnené ".implode(', ', array_keys($changes)),
                    status: 'ok', subject: $canal, context: ['fields' => array_keys($changes)]);
            }

            if ($emails && isset($profile['emails'])) {
                $emails->discover($canal->fresh(), $profile['emails'], 'manual');
                $emails->refresh($canal->fresh());
            }
        }
    }

    private function completeVenues(): void
    {
        foreach (self::VENUES as $id => $data) {
            $venue = Venue::query()->find($id);
            $village = (int) $venue?->village_id;

            if (! $venue || $venue->status === ModelStatus::Archived
                || ($venue->name !== $data['name'] && $venue->name !== ($data['fix_name'] ?? null))
                || ($village !== $data['village'] && $village !== ($data['fix_village'] ?? null))) {
                continue;
            }

            $changes = [];

            if (isset($data['fix_name']) && $venue->name === $data['name']) {
                $changes['name'] = $data['fix_name'];
            }

            if (isset($data['fix_village']) && $village === $data['village']) {
                $changes['village_id'] = $data['fix_village'];
            }

            if (isset($data['body']) && substr_count((string) $venue->body, '<p') !== 3) {
                $changes['body'] = self::paragraphs($data['body']);
            }

            $street = trim((string) $venue->street);
            if (isset($data['street']) && ($street === '' || $street === ($data['replace_street'] ?? null))) {
                $changes['street'] = $data['street'];
            }

            // Poštové smerovacie číslo sa opraví aj vtedy, keď ho import vzal z inej obce.
            if (isset($data['postcode']) && (trim((string) $venue->postcode) === '' || isset($changes['street']) || isset($changes['village_id']))) {
                $changes['postcode'] = $data['postcode'];
            }

            foreach (['website'] as $field) {
                if (isset($data[$field]) && trim((string) $venue->$field) === '') {
                    $changes[$field] = $data[$field];
                }
            }

            $precise = in_array($venue->coordinates_source, ['venue', 'address', 'manual'], true);
            if (isset($data['latitude']) && ! $precise) {
                $changes += ['latitude' => $data['latitude'], 'longitude' => $data['longitude'], 'coordinates_source' => $data['coordinates_source']];
            }

            $changes = array_filter($changes, fn ($value, $field) => $value != $venue->$field, ARRAY_FILTER_USE_BOTH);

            if ($changes !== []) {
                $venue->forceFill($changes)->save();
                Recorder::info('venues', 'profile_described', "Miesto {$venue->name}: doplnené ".implode(', ', array_keys($changes)),
                    status: 'ok', subject: $venue, context: ['fields' => array_keys($changes)]);
            }
        }
    }

    private function mergeVenues(): void
    {
        foreach (self::MERGES as $sourceId => [$targetId, $sourceName, $targetName]) {
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

    /** Svätyňa v Smižanoch dostane ulicu a súradnice zlúčeného miesta #1068. */
    private function completeShrine(): void
    {
        $venue = Venue::query()->find(850);

        if (! $venue || $venue->name !== 'Diecézna svätyňa Božieho milosrdenstva' || (int) $venue->village_id !== 3201
            || $venue->status === ModelStatus::Archived) {
            return;
        }

        $changes = [];
        if (trim((string) $venue->street) === '') {
            $changes += ['street' => 'Jahodová', 'postcode' => '053 11'];
        }
        if (! in_array($venue->coordinates_source, ['venue', 'address', 'manual'], true)) {
            $changes += ['latitude' => 48.954591, 'longitude' => 20.5160425, 'coordinates_source' => 'address'];
        }

        if ($changes !== []) {
            $venue->forceFill($changes)->save();
            Recorder::info('venues', 'profile_described', "Miesto {$venue->name}: doplnené ".implode(', ', array_keys($changes)),
                status: 'ok', subject: $venue, context: ['fields' => array_keys($changes)]);
        }
    }

    /** @param  array<int, string>  $paragraphs */
    private static function paragraphs(array $paragraphs): string
    {
        return implode('', array_map(fn (string $p) => '<p>'.e($p).'</p>', $paragraphs));
    }

    public function down(): void
    {
        // Doplnené údaje sa nevracajú.
    }
};
