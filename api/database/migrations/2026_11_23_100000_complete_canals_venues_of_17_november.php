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
 * Kanály a miesta vytvorené 17. 11. 2026 (import TK KBS, vyveska.sk a kalendára ECAV):
 * kanály #1971, #1972 a miesta #1091 – #1095.
 *
 * Kanály zborov dostanú popis v troch odsekoch, telefón, web, e-mail a adresu
 * sídla z adresára ecav.sk, súradnice z Nominatim/OpenStreetMap (import ich mal
 * v strede Slovenska, hoci obec bola správna). Web zboru Banská Bystrica
 * (ecavbb.sk) má neplatný certifikát, preto dostane stránku zboru na ecav.sk;
 * web zboru Krajné funguje len cez http. Osobný kanál #1973 sa nemení.
 *
 * Miesta dostanú popis, skutočný názov a adresu; dve pomenované adresou sa zlúčia
 * do existujúcich: #1092 → #133 (Dom Quo Vadis), #1093 → #795 (Katedrála svätého Martina).
 *
 * Kanál musí sedieť ID aj názvom; prevzatý, zlúčený či zmazaný sa preskočí.
 * Popis sa prepíše len vtedy, keď ešte nemá tri odseky; telefón a web sa
 * dopĺňajú do prázdneho poľa. Archivované miesta sa nemenia.
 */
return new class extends Migration
{
    private const CLOSING = 'Podrobnosti nájdete vždy v detaile podujatia.';

    private const OTHERS = 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.';

    /** @var array<int, array<string, mixed>> */
    private const CANALS = [
        1971 => [
            'name' => 'CZ ECAV Banská Bystrica',
            'municipality' => 73,
            'phone' => '048/415 51 16',
            'website' => 'https://www.ecav.sk/zapadny-distrikt/zvolensky-seniorat/banska-bystrica',
            'emails' => [['email' => 'banskabystrica@ecav.sk', 'found_on' => 'https://www.ecav.sk/zapadny-distrikt/zvolensky-seniorat/banska-bystrica']],
            'address' => ['street' => 'Lazovná 42', 'postcode' => '974 01', 'latitude' => 48.7394093, 'longitude' => 19.1429919],
            'body' => [
                'Cirkevný zbor ECAV Banská Bystrica patrí do Zvolenského seniorátu Evanjelickej cirkvi augsburského vyznania. Sídli na Lazovnej ulici. Predsedajúcim farárom je Mgr. Daniel Koštial, zborovou farárkou Mgr. Slávka Koštialová.',
                'Služby Božie sú každú nedeľu o 9.00 v Banskej Bystrici a raz do mesiaca aj v Senici, Nemciach, Kynceľovej, Podlaviciach a Riečke. Zbor slúži aj v Centre zborovej diakonie Kanaán a pripravuje koncerty, napríklad organový koncert na pamiatku profesora Bela Riečana.',
                'Kanál je určený evanjelikom z Banskej Bystrice a okolia. Sledovaním budete vedieť o koncertoch a podujatiach zboru. '.self::CLOSING,
            ],
        ],
        1972 => [
            'name' => 'CZ ECAV Krajné',
            'municipality' => 1623,
            'phone' => '032/778 61 91',
            'website' => 'http://www.ecavkrajne.sk',
            'emails' => [['email' => 'krajne@ecav.sk', 'found_on' => 'https://www.ecav.sk/zapadny-distrikt/myjavsky-seniorat/krajne']],
            'address' => ['street' => 'Krajné 161', 'postcode' => '916 16', 'latitude' => 48.6969596, 'longitude' => 17.6879384],
            'body' => [
                'Cirkevný zbor ECAV Krajné patrí do Myjavského seniorátu Evanjelickej cirkvi augsburského vyznania. K zboru patria aj veriaci z Korytnianskych Kopaníc, Žadovice, Matejovca a Hrachovišťa.',
                'Služby Božie sú každú nedeľu o 8.45 v tolerančnom kostole v Krajnom. Zbor má spevokol a mládežnícku kapelu Eleos, pred Vianocami pripravuje benefičný koncert pre Komunitné centrum Drahuškovo.',
                'Kanál je určený evanjelikom z Krajného a okolia. Sledovaním budete vedieť o koncertoch a podujatiach zboru. '.self::CLOSING,
            ],
        ],
    ];

    /** @var array<int, array<string, mixed>> */
    private const VENUES = [
        1091 => ['name' => 'Bazilika sv. Benedikta opáta', 'fix_name' => 'Bazilika sv. Benedikta (Hronský Beňadik)', 'village' => 1122,
            'street' => 'Pod kláštorom 447/1', 'website' => 'https://klastorbenadik.nrb.sk',
            'latitude' => 48.3449162, 'longitude' => 18.5579219, 'coordinates_source' => 'address', 'body' => [
                'Bazilika sv. Benedikta stojí v areáli kláštora v Hronskom Beňadiku, ktorý existoval už pred rokom 1075. Pápež František ju v roku 2019 povýšil na baziliku minor. Uchováva sa v nej relikvia Kristovej krvi.',
                'Sväté omše sú v pondelok, utorok, štvrtok a piatok o 18.00 a v nedeľu o 11.00. Konajú sa tu aj púte, napríklad na slávnosť Krista Kráľa s prijatím nových členov do Bratstva Kristovej krvi.',
                self::OTHERS,
            ]],
        1094 => ['name' => 'Evanjelický chrám Boží', 'fix_name' => 'Evanjelický chrám Boží (Banská Bystrica)', 'village' => 73,
            'street' => 'Lazovná 268/46', 'postcode' => '974 01',
            'latitude' => 48.7398914, 'longitude' => 19.1426036, 'coordinates_source' => 'address', 'body' => [
                'Evanjelický chrám Boží stojí na Lazovnej ulici v Banskej Bystrici. Patrí cirkevnému zboru ECAV Banská Bystrica, ktorý tu slávi Služby Božie každú nedeľu o 9.00.',
                'Konajú sa tu aj koncerty, napríklad organový koncert na pamiatku profesora Bela Riečana.',
                self::OTHERS,
            ]],
        1095 => ['name' => 'Evanjelický kostol', 'fix_name' => 'Evanjelický kostol (Krajné)', 'village' => 1623, 'body' => [
            'Evanjelický kostol v Krajnom v okrese Myjava je tolerančný kostol z roku 1784, obnovený v rokoch 1806 a 1867. Patrí cirkevnému zboru ECAV Krajné.',
            'Služby Božie sú tu každú nedeľu o 8.45. Konajú sa tu aj koncerty, napríklad benefičný koncert pre Komunitné centrum Drahuškovo.',
            self::OTHERS,
        ]],
    ];

    /** Zdroj => [cieľ, názov zdroja, názov cieľa]. */
    private const MERGES = [
        1092 => [133, 'Veterná 1, Bratislava', 'Dom Quo Vadis'],
        1093 => [795, 'Rudnayovo námestie 1, Bratislava', 'Katedrála svätého Martina (Bratislava)'],
    ];

    public function up(): void
    {
        $this->completeCanals();
        $this->completeVenues();
        $this->mergeVenues();
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

            $changes = [];

            // Prepíše sa len popis, ktorý ešte nemá tri odseky — upravený text ostane.
            if (substr_count((string) $canal->body, '<p') !== 3) {
                $changes['body'] = self::paragraphs($profile['body']);
            }

            foreach (['website', 'phone'] as $field) {
                if (trim((string) $canal->$field) === '') {
                    $changes[$field] = $profile[$field];
                }
            }

            $looseCoordinates = in_array($canal->coordinates_source, ['municipality', 'nationwide', 'ai', null], true);

            if ($looseCoordinates && (int) $canal->municipality_id === $profile['municipality']) {
                $a = $profile['address'];
                if (trim((string) $canal->street) === '') {
                    $changes['street'] = $a['street'];
                    $changes['postcode'] = $a['postcode'];
                }
                $changes += ['latitude' => $a['latitude'], 'longitude' => $a['longitude'], 'coordinates_source' => 'address'];
            }

            if ($changes !== []) {
                $canal->forceFill($changes)->save();
                Recorder::info('canals', 'profile_described', "Kanál {$canal->name}: doplnené ".implode(', ', array_keys($changes)),
                    status: 'ok', subject: $canal, context: ['fields' => array_keys($changes)]);
            }

            if ($emails) {
                $emails->discover($canal->fresh(), $profile['emails'], 'manual');
                $emails->refresh($canal->fresh());
            }
        }
    }

    private function completeVenues(): void
    {
        foreach (self::VENUES as $id => $data) {
            $venue = Venue::query()->find($id);

            if (! $venue || $venue->status === ModelStatus::Archived
                || ($venue->name !== $data['name'] && $venue->name !== $data['fix_name'])
                || (int) $venue->village_id !== $data['village']) {
                continue;
            }

            $changes = [];

            if ($venue->name === $data['name']) {
                $changes['name'] = $data['fix_name'];
            }

            if (substr_count((string) $venue->body, '<p') !== 3) {
                $changes['body'] = self::paragraphs($data['body']);
            }

            foreach (['street', 'postcode', 'website'] as $field) {
                if (isset($data[$field]) && trim((string) $venue->$field) === '') {
                    $changes[$field] = $data[$field];
                }
            }

            $precise = in_array($venue->coordinates_source, ['venue', 'address', 'manual'], true);
            if (isset($data['latitude']) && ! $precise) {
                $changes += ['latitude' => $data['latitude'], 'longitude' => $data['longitude'], 'coordinates_source' => $data['coordinates_source']];
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
