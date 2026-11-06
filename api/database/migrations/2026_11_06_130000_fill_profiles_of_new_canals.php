<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Doplní profily nových kanálov (ručná kontrola 6. 11. 2026).
 *
 * Kanály založené od 27. 9. 2026, ktoré automatické dopĺňanie profilov
 * (profile_enrichments) ešte nespracovalo. Zdroj: text ich vlastných podujatí
 * a kontaktná stránka oficiálneho webu (pri každom kanáli v poznámke).
 * Dopĺňa sa to isté ako pri automatickom dopĺňaní — ulica, PSČ, krajina,
 * telefón — a len do prázdneho poľa. Telefón len všeobecný kontakt
 * organizácie, nie číslo konkrétnej osoby.
 *
 * Opravuje sa obec, ktorú import odvodil chybne (z miesta podujatia alebo
 * z textu „Ulica 29. augusta" → Ulič), len ak má kanál stále pôvodnú obec.
 *
 * Súradnice z adresy dohľadal NominatimGeocoder::lookupAddress (zhoda ulice
 * aj obce), pri samotnej oprave obce stred obce. Prepisujú sa len zástupné
 * súradnice (coordinates_source = municipality / prázdne), nie ručné.
 *
 * down() vráti len hodnoty, ktoré sú stále tie, čo sem migrácia zapísala.
 */
return new class extends Migration
{
    private const BRATISLAVA = 242;

    /**
     * id => [
     *   'from' => pôvodná (chybná) obec, 'municipality' => správna obec,
     *   'fill' => [stĺpec => hodnota] do prázdnych polí,
     *   'coords' => [lat, lng, zdroj],
     * ]
     *
     * @var array<int, array<string, mixed>>
     */
    private const PROFILES = [
        // Diecézna charita Rožňava — výročná správa (registeruz.sk); obec bola z miesta stacionára (Kokava n. R.)
        1944 => ['from' => 1484, 'municipality' => 3014,
            'fill' => ['street' => 'Kósu-Schoppera 141/22', 'postcode' => '048 01', 'country' => 'Slovensko'],
            'coords' => [48.6608241, 20.528665, 'address']],
        // Nadácia Slovakia Christiana — slovakiachristiana.sk/kontakt
        1940 => ['fill' => ['street' => 'Kvetinárska 31', 'postcode' => '821 06', 'country' => 'Slovensko'],
            'coords' => [48.1293728, 17.200832, 'address']],
        // Záhorské osvetové stredisko — zoznam okresných organizátorov SÚH 2026
        1937 => ['fill' => ['street' => 'Vajanského 19/5', 'postcode' => '905 01', 'country' => 'Slovensko'],
            'coords' => [48.6769068, 17.3680276, 'address']],
        // OZ Leváda — levada.sk/kontakt
        1934 => ['fill' => ['phone' => '+421 905 642 681']],
        // Bratislava Mozart Festival — obec „Celé Slovensko", festival je v Bratislave
        1933 => ['from' => 4209, 'municipality' => self::BRATISLAVA,
            'coords' => [48.1435026, 17.1082867, 'municipality']],
        // Malé Rodinkovo — malerodinkovo.sk
        1930 => ['fill' => ['street' => 'Bratislavská 1900', 'postcode' => '900 45', 'country' => 'Slovensko', 'phone' => '+421 947 977 903'],
            'coords' => [48.1476526, 17.2903312, 'address']],
        // M & M — text podujatia („Info: mmpodujatia@gmail.com, 0911 322 891")
        1929 => ['fill' => ['phone' => '+421 911 322 891']],
        // Filozofická fakulta KU — portalvs.sk (študijné oddelenie fakulty)
        1928 => ['fill' => ['street' => 'Hrabovská cesta 1B', 'postcode' => '034 01', 'country' => 'Slovensko',
            'website' => 'https://www.ku.sk/fakulty-katolickej-univerzity/filozoficka-fakulta/'],
            'coords' => [49.0825123, 19.280265, 'address']],
        // Bratislavskí františkáni — frantiskani.sk/bratislava (kláštor)
        1927 => ['fill' => ['street' => 'Františkánska 2', 'postcode' => '811 01', 'country' => 'Slovensko', 'phone' => '+421 918 270 374'],
            'coords' => [48.1446478, 17.1087592, 'address']],
        // Slovenský tím SDM — svetovednimladeze.sk (Rada pre mládež a univerzity KBS); obec bola Nové Mesto n. V.
        1923 => ['from' => 2428, 'municipality' => self::BRATISLAVA,
            'fill' => ['street' => 'Kapitulská 11', 'postcode' => '814 99', 'country' => 'Slovensko'],
            'coords' => [48.143312, 17.1052033, 'address']],
        // Biznis raňajky Poprad — biznisranajky.sk (adresa: Coworking Poprad)
        1918 => ['fill' => ['street' => 'Námestie svätého Egídia 11/25', 'postcode' => '058 01', 'country' => 'Slovensko', 'phone' => '+421 903 733 057'],
            'coords' => [49.055997, 20.303851, 'address']],
        // Coworking Poprad — tá istá adresa na biznisranajky.sk
        1896 => ['fill' => ['street' => 'Námestie svätého Egídia 11/25', 'postcode' => '058 01', 'country' => 'Slovensko'],
            'coords' => [49.055997, 20.303851, 'address']],
        // Vydavateľstvo Wojtyla.sk — wojtyla.sk (Framegroup s.r.o.)
        1916 => ['fill' => ['street' => 'Športovcov 1445/6', 'postcode' => '054 01', 'country' => 'Slovensko', 'phone' => '+421 944 128 757'],
            'coords' => [49.0289385, 20.5952952, 'address']],
        // Verbisti — adresa už v profile (Kalvária 3, 949 01), obec bola „Celé Slovensko"
        1906 => ['from' => 4209, 'municipality' => 2333,
            'coords' => [48.2982161, 18.0916092, 'address']],
        // Spišské biskupstvo — kapitula.sk
        1902 => ['fill' => ['street' => 'Spišská Kapitula 661/9', 'postcode' => '053 04', 'country' => 'Slovensko', 'phone' => '+421 53 454 11 36'],
            'coords' => [49.0009254, 20.7404511, 'address']],
        // Kyrillomethodeon — obec „Ulič" z textu „Ulica 29. augusta"; zbor pôsobí v Bratislave
        1899 => ['from' => 3683, 'municipality' => self::BRATISLAVA,
            'coords' => [48.1435026, 17.1082867, 'municipality']],
        // S klub — text podujatia („S klub, Svoradova 13, Bratislava")
        1895 => ['fill' => ['street' => 'Svoradova 13', 'postcode' => '811 03', 'country' => 'Slovensko'],
            'coords' => [48.1453803, 17.1005793, 'address']],
        // M-Aréna a spoločenstvo MaranaTha — maranathapo.sk
        1892 => ['fill' => ['street' => 'Švábska 22A', 'postcode' => '080 05', 'country' => 'Slovensko'],
            'coords' => [48.9752312, 21.2623996, 'address']],
        // ClipTime — cliptime.sk/kontakt
        1891 => ['fill' => ['phone' => '+421 903 982 638']],
        // Donacia.sk — donacia.sk/kontakt (Teo via Slovensko n.f.); obec bola z miesta webinára (Pezinok)
        1884 => ['from' => 2636, 'municipality' => 3596,
            'fill' => ['street' => 'Štefánikova 46', 'postcode' => '917 01', 'country' => 'Slovensko'],
            'coords' => [48.3780604, 17.5850727, 'address']],
    ];

    /** Kanál „Martin" (púte do Turzovky) dostal pri importe web mesta Martin. */
    private const WRONG_WEBSITES = [1894 => 'https://www.martin.sk'];

    public function up(): void
    {
        if (! Schema::hasTable('canals')) {
            return;
        }

        DB::transaction(function () {
            foreach (self::PROFILES as $id => $profile) {
                $this->apply($id, $profile);
            }

            foreach (self::WRONG_WEBSITES as $id => $website) {
                DB::table('canals')->where('id', $id)->where('website', $website)
                    ->update(['website' => null, 'updated_at' => now()]);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('canals')) {
            return;
        }

        DB::transaction(function () {
            foreach (self::PROFILES as $id => $profile) {
                foreach ($profile['fill'] ?? [] as $column => $value) {
                    DB::table('canals')->where('id', $id)->where($column, $value)->update([$column => null]);
                }

                if (isset($profile['municipality'])) {
                    DB::table('canals')->where('id', $id)->where('municipality_id', $profile['municipality'])
                        ->update(['municipality_id' => $profile['from']]);
                }

                // Súradnice sa na stred Slovenska nevracajú — boli zástupné.
            }

            foreach (self::WRONG_WEBSITES as $id => $website) {
                DB::table('canals')->where('id', $id)->whereNull('website')->update(['website' => $website]);
            }
        });
    }

    /** @param  array<string, mixed>  $profile */
    private function apply(int $id, array $profile): void
    {
        $canal = DB::table('canals')->where('id', $id)->whereNull('deleted_at')->lockForUpdate()->first();

        if ($canal === null) {
            return;
        }

        $update = [];

        foreach ($profile['fill'] ?? [] as $column => $value) {
            if (trim((string) $canal->{$column}) === '') {
                $update[$column] = $value;
            }
        }

        if (isset($profile['municipality']) && (int) $canal->municipality_id === $profile['from']) {
            $update['municipality_id'] = $profile['municipality'];
        }

        if (isset($profile['coords']) && $this->hasRoughCoordinates($canal)) {
            [$update['latitude'], $update['longitude'], $update['coordinates_source']] = $profile['coords'];
        }

        if ($update !== []) {
            DB::table('canals')->where('id', $id)->update($update + ['updated_at' => now()]);
        }
    }

    private function hasRoughCoordinates(object $canal): bool
    {
        return $canal->latitude === null || in_array($canal->coordinates_source, [null, 'municipality'], true);
    }
};
