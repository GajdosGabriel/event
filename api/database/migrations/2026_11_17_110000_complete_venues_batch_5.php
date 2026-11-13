<?php

use App\Enums\ModelStatus;
use App\Models\Venue;
use App\Services\Resources\ResourceMerger;
use App\Services\SystemLog\Recorder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Piata, posledná dávka miest konania (november 2026): zvyšných 24
 * nearchivovaných miest (#2 – #54). Zástupné miesta celých obcí („Poprad",
 * „Galanta", „Celé Slovensko") a nejasné záznamy („Kapitulská 1", „Komenského
 * ulica") sa nepopisujú. Konkrétne miesta dostanú popis, skutočný názov,
 * adresu a súradnice z Nominatim/OpenStreetMap.
 *
 * Opravy:
 *  - #19 brdársky kostol je evanjelický (jediný kostol v Brdárke), nie rímskokatolícky,
 *  - #54 a #9 sú ten istý Hotel Javorná v Drienici — #9 sa zlúči do #54,
 *  - #49 Svoradova 13 je S klub v Študentskom domove Svoradov.
 *
 * Archivované miesta sa nemenia. Popis sa prepíše len vtedy, keď ešte nemá tri
 * odseky; ulica, PSČ a web sa dopĺňajú do prázdneho poľa (okrem `fix_*`).
 * Miesto musí sedieť ID aj názvom.
 */
return new class extends Migration
{
    private const OTHERS = 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.';

    private const CITIES = [];

    /** @var array<int, array<string, mixed>> */
    private const VENUES = [
        54 => ['name' => 'Drienica 501, Drienica', 'fix_name' => 'Hotel Javorná (Drienica)', 'fix_street' => 'Drienica 501', 'fix_postcode' => '083 01',
            'latitude' => 49.1422372, 'longitude' => 21.1129099, 'coordinates_source' => 'venue', 'fix_coordinates' => true, 'body' => [
                'Hotel Javorná leží v lyžiarskom stredisku Drienica pri Sabinove. Ponúka ubytovanie, stravovanie a sály na stretnutia.',
                'Konajú sa tu víkendové kurzy a obnovy, napríklad kurzy Charizmy Sv. Ducha či Spoločenstvo a dary služby, ktoré pripravuje spoločenstvo Joel.',
                self::OTHERS,
            ]],
        53 => ['name' => 'Kostolná - Záriečie', 'fix_name' => 'Bývalý kláštor redemptoristov (Kostolná-Záriečie)', 'fix_street' => 'Kostolná-Záriečie 8', 'fix_postcode' => '913 04',
            'latitude' => 48.8775527, 'longitude' => 17.9732393, 'coordinates_source' => 'address', 'fix_coordinates' => true, 'body' => [
                'Bývalý kláštor redemptoristov stojí v obci Kostolná-Záriečie pri Trenčíne. Dnes slúži na duchovné obnovy. Stará sa oň komunita redemptoristov a laikov Koral.',
                'Konajú sa tu víkendové duchovné obnovy, napríklad obnovy so sv. Šarbelom. Obnovy trvajú od piatka do nedele.',
                self::OTHERS,
            ]],
        49 => ['name' => 'Svoradova 13, Bratislava', 'fix_name' => 'S klub – Študentský domov Svoradov (Bratislava)', 'fix_street' => 'Svoradova 13', 'fix_postcode' => '811 03',
            'latitude' => 48.1453803, 'longitude' => 17.1005793, 'coordinates_source' => 'venue', 'fix_coordinates' => true, 'body' => [
                'S klub je komunitný priestor v Študentskom domove Svoradov na Svoradovej ulici v Bratislave. Spája kultúru a duchovný rozmer. Slúži študentom aj verejnosti.',
                'Konajú sa tu komorné koncerty S klub Sessions, premietania a diskusie, napríklad premietanie filmu Slobodní o Juklovi a Krčmérym.',
                self::OTHERS,
            ]],
        23 => ['name' => 'Tipsport Aréna', 'fix_name' => 'Tipsport Aréna (Banská Bystrica)', 'latitude' => 48.7338929, 'longitude' => 19.1558658, 'coordinates_source' => 'venue', 'fix_coordinates' => true, 'body' => [
            'Tipsport Aréna je multifunkčná hala v Banskej Bystrici. Hostí športové zápasy, koncerty a veľké podujatia.',
            'Konali sa tu aj veľké stretnutia mladých, napríklad ekumenické stretnutie TAM s komunitou Taizé.',
            self::OTHERS,
        ]],
        19 => ['name' => 'brdársky kostol', 'fix_name' => 'Evanjelický kostol (Brdárka)', 'fix_postcode' => '049 34', 'body' => [
            'Evanjelický kostol v Brdárke stojí v malej obci v okrese Rožňava. Patrí evanjelikom a. v. Obec leží v doline pod Stolickými vrchmi.',
            'Konajú sa tu služby Božie a slávnosti zboru. Kostol je otvorený pri bohoslužbách a podujatiach.',
            self::OTHERS,
        ]],
        15 => ['name' => 'Štítnická 13, Rožňava', 'fix_name' => 'Športová hala (Rožňava)', 'fix_street' => 'Štítnická 13', 'fix_postcode' => '048 01', 'body' => [
            'Multifunkčná športová hala stojí na Štítnickej ulici v Rožňave. Slúži na športové aj kultúrne podujatia.',
            'Konajú sa tu aj veľké duchovné stretnutia, napríklad duchovná obnova s pátrom Jamesom Manjackalom.',
            self::OTHERS,
        ]],
        8 => ['name' => 'pútnické miesto Drieň', 'fix_name' => 'Pútnické miesto Drieň (Vysoká)', 'fix_postcode' => '966 61', 'latitude' => 48.4139243, 'longitude' => 18.7774681,
            'coordinates_source' => 'venue', 'fix_coordinates' => true, 'body' => [
                'Drieň je vrch a pútnické miesto medzi obcami Vysoká a Uhliská pri Banskej Štiavnici. Na vrchole je kaplnka. Z vrchu je výhľad na Štiavnické vrchy.',
                'Konajú sa tu púte, napríklad Eucharistická púť na Drieň, ktorú pripravuje farnosť Štiavnické Bane.',
                self::OTHERS,
            ]],
        2 => ['name' => 'Ružová 4, Trnava', 'fix_name' => 'Kaplnka Provinciálneho domu (Trnava-Tulipán)', 'fix_street' => 'Ružová 4', 'fix_postcode' => '917 02',
            'latitude' => 48.3666127, 'longitude' => 17.5842626, 'coordinates_source' => 'address', 'fix_coordinates' => true, 'body' => [
                'Kaplnka Provinciálneho domu sa nachádza na Ružovej ulici na sídlisku Tulipán v Trnave. Slúži na bohoslužby a modlitby.',
                'Konajú sa tu sväté omše a pútnické slávnosti, napríklad pútnická svätá omša na sviatok blahoslavenej Zdenky Schelingovej.',
                self::OTHERS,
            ]],
    ];

    /** Zdroj → cieľ: ten istý priestor pod inými názvami. */
    private const MERGES = [
        9 => [54, 'Drienica', 'Hotel Javorná (Drienica)'],
    ];

    public function up(): void
    {
        foreach (self::VENUES as $id => $data) {
            $venue = Venue::query()->find($id);

            if (! $venue || $venue->status === ModelStatus::Archived || ($venue->name !== $data['name'] && $venue->name !== ($data['fix_name'] ?? null))) {
                continue;
            }

            $changes = [];

            if (isset($data['fix_name']) && $venue->name === $data['name']) {
                $changes['name'] = $data['fix_name'];
            }

            if (isset($data['body']) && substr_count((string) $venue->body, '<p') !== 3) {
                $changes['body'] = implode('', array_map(fn (string $p) => '<p>'.e($p).'</p>', $data['body']));
            }

            foreach (['street', 'postcode', 'website', 'phone', 'email'] as $field) {
                if (array_key_exists('fix_'.$field, $data)) {
                    if ($data['fix_'.$field] !== $venue->$field) {
                        $changes[$field] = $data['fix_'.$field];
                    }
                } elseif (isset($data[$field]) && trim((string) $venue->$field) === '') {
                    $changes[$field] = $data[$field];
                }
            }

            if (isset($data['country']) && $venue->country !== $data['country']) {
                $changes['country'] = $data['country'];
            }

            $villageId = isset($data['city']) ? $this->city($data['city']) : ($data['fix_village_id'] ?? null);
            if ($villageId !== null && (int) $venue->village_id !== $villageId) {
                $changes['village_id'] = $villageId;
            }

            $precise = in_array($venue->coordinates_source, ['venue', 'address', 'manual'], true);
            if (isset($data['latitude']) && (! $precise || ! empty($data['fix_coordinates']))) {
                $changes += ['latitude' => $data['latitude'], 'longitude' => $data['longitude'], 'coordinates_source' => $data['coordinates_source']];
            }

            if ($changes !== []) {
                $venue->forceFill($changes)->save();
                Recorder::info('venues', 'profile_described', "Miesto {$venue->name}: doplnené ".implode(', ', array_keys($changes)),
                    status: 'ok', subject: $venue, context: ['fields' => array_keys($changes)]);
            }
        }

        foreach (self::MERGES as $sourceId => [$targetId, $sourceName, $targetName]) {
            $source = Venue::query()->find($sourceId);
            $target = Venue::query()->find($targetId);
            if (! $source || ! $target || $source->name !== $sourceName || $target->name !== $targetName) {
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

    /** Zahraničné mesto v číselníku obcí (bez okresu a kraja), vytvorí sa raz. */
    private function city(string $slug): int
    {
        $city = DB::table('municipalities')->where('slug', $slug)->first();
        if ($city !== null) {
            return (int) $city->id;
        }

        return (int) DB::table('municipalities')->insertGetId([
            'fullname' => self::CITIES[$slug]['name'], 'shortname' => self::CITIES[$slug]['name'], 'slug' => $slug,
            'zip' => self::CITIES[$slug]['zip'], 'district_id' => 0, 'region_id' => 0, 'use' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Doplnené údaje sa nevracajú.
    }
};
