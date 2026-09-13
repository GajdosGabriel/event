<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Súradnice pre každé miesto a kanál, ktorým ešte chýbajú.
 *
 * Predchádzajúca migrácia (2026_09_13_100000_backfill_venue_and_canal_coordinates)
 * doplnila súradnice podľa auditu, no na produkcii medzitým pribudli stovky
 * kanálov bez polohy a pár miest ostalo prázdnych — zámerne tam, kde boli
 * súradnice zjavne cudzie. Mapa a filter „v mojom okolí" však potrebujú bod pre
 * každý záznam, takže poradie je:
 *
 *   1. stred obce záznamu, keď ho poznáme (159 obcí, dohľadané cez
 *      Nominatim/Photon 13. 9. 2026),
 *   2. inak stred Slovenska — rovnaký bod, aký používa zberné „Celé Slovensko".
 *
 * Päť miest má zlú už samotnú obec (štyri kostoly pripnuté na Pečenice,
 * „Taliansko" v Bratislave), preto idú rovno na stred Slovenska — stred
 * Pečeníc by tvrdil polohu, ktorá nie je pravda.
 *
 * Zdroj je `municipality`: „Celé Slovensko" je v číselníku tiež obec (4209).
 * Nikdy neprepíše existujúce súradnice.
 */
return new class extends Migration
{
    /** Stred Slovenska — bod, ktorý Nominatim vracia pre „Slovensko". */
    private const SLOVAKIA = [48.7411522, 19.4528646];

    /** Miesta so zlou obcou: [id, názov]. */
    private const MISPLACED_VENUES = [
        [4, 'kostol sv. Františka Minoriti'],
        [32, 'františkánsky kostol'],
        [83, 'ruiny kostola a kláštora sv. Kataríny'],
        [102, 'Univerzitný kostol Svätej Rodiny'],
        [737, 'Taliansko'],
    ];

    /** municipality_id => [šírka, dĺžka] */
    private const MUNICIPALITIES = [
        47 => [48.6650207, 19.1269549], // Badín
        67 => [48.0487885, 18.1915830], // Bánov
        69 => [48.7216671, 18.2598263], // Bánovce nad Bebravou
        73 => [48.7354290, 19.1457338], // Banská Bystrica
        75 => [48.4580926, 18.8988414], // Banská Štiavnica
        119 => [49.0655320, 18.3279679], // Beluša
        206 => [48.5756486, 18.0568631], // Bojná
        215 => [48.9854933, 18.1565106], // Bolešov
        226 => [48.6287180, 17.2058154], // Borský Mikuláš
        232 => [48.5802963, 18.2488386], // Bošany
        237 => [48.6310107, 20.7635859], // Bôrka
        240 => [48.2110635, 18.1470603], // Branč
        242 => [48.1516988, 17.1093063], // Bratislava
        251 => [48.1109720, 17.1112897], // Bratislava - Petržalka
        255 => [48.1493142, 17.1645049], // Bratislava - Ružinov
        257 => [48.2049103, 17.2065787], // Bratislava - Vajnory
        259 => [48.2392876, 17.0383357], // Bratislava - Záhorská Bystrica
        263 => [48.4869802, 21.8181852], // Brehov
        284 => [48.8053349, 19.6409612], // Brezno
        308 => [48.7744129, 18.7261855], // Brusno
        383 => [49.2162796, 18.7664079], // Celulózka
        392 => [48.3151877, 17.4902571], // Cífer
        405 => [49.4375146, 18.7896041], // Čadca
        463 => [49.0919324, 19.2550957], // Černová
        542 => [48.5595662, 19.4205586], // Detva
        569 => [49.3046706, 18.6306974], // Dlhé Pole
        575 => [48.4119472, 22.0280602], // Dobrá
        680 => [49.0450147, 18.5523078], // Domaniža
        697 => [49.1296936, 21.1095429], // Drienica
        721 => [49.3615699, 21.4354492], // Dubová
        725 => [48.7731078, 17.2467708], // Dubovce
        751 => [48.0821940, 17.2636139], // Dunajská Lužná
        788 => [48.2701641, 19.8222938], // Fiľakovo
        801 => [49.1848023, 21.2408563], // Fričkovce
        802 => [49.0164350, 20.9655448], // Fričovce
        806 => [49.3653547, 21.1419506], // Gaboltov
        861 => [49.2778588, 19.6043454], // Habovka
        959 => [48.8078921, 17.1621430], // Holíč
        1016 => [48.4663657, 17.4402984], // Horné Orešany
        1122 => [48.3456007, 18.5592604], // Hronský Beňadik
        1191 => [48.7760794, 18.7240889], // Chrenovec - Brusno
        1231 => [48.1885931, 17.2578039], // Ivanka pri Dunaji
        1421 => [49.1362088, 20.4308701], // Kežmarok
        1460 => [48.9482118, 18.0913353], // Kľúčové
        1462 => [48.9221000, 20.9384978], // Kluknava
        1468 => [48.7443353, 20.3717265], // Kobeliarovo
        1487 => [49.2625789, 20.6287093], // Kolačkov
        1505 => [47.7574079, 18.1298249], // Komárno
        1546 => [48.4146308, 18.2420957], // Kostoľany pod Tribečom
        1550 => [48.8774720, 17.9694367], // Kostolná - Záriečie
        1565 => [48.7172272, 21.2496774], // Košice
        1604 => [48.6391972, 20.6984604], // Kováčová
        1623 => [48.7005763, 17.6871599], // Krajné
        1635 => [48.1966946, 17.4515157], // Kráľová pri Senci
        1672 => [49.2825832, 19.4791415], // Krivá
        1737 => [48.6585651, 17.0209933], // Kúty
        1759 => [49.2999184, 18.7865084], // Kysucké Nové Mesto
        1760 => [49.3380438, 18.8119478], // Kysucký Lieskovec
        1764 => [49.3073591, 20.5938104], // Lacková
        1794 => [49.2258085, 18.2132187], // Lazy pod Makytou
        1798 => [49.0689257, 18.2882484], // Lednické Rovne
        1802 => [48.3150709, 17.9927535], // Lehota
        1818 => [48.4441776, 17.7654426], // Leopoldov
        1829 => [48.2163194, 18.6000869], // Levice
        1831 => [49.0250870, 20.5887098], // Levoča
        1859 => [49.1525429, 20.9640764], // Lipany
        1890 => [49.0494179, 19.6802212], // Liptovský Ján
        1898 => [48.2990075, 19.1789573], // Litava
        1946 => [48.3303886, 19.6634149], // Lučenec
        1958 => [48.2107641, 18.2837974], // Lúčnica nad Žitavou
        1981 => [49.1666204, 21.0493222], // Ľutina
        2114 => [48.2477922, 17.0577765], // Marianka
        2119 => [49.0724921, 18.9291930], // Martin
        2160 => [49.2711323, 21.9039645], // Medzilaborce
        2182 => [48.7514383, 21.9211949], // Michalovce
        2223 => [48.2296737, 17.9279917], // Močenok
        2229 => [48.3357255, 17.3087863], // Modra
        2235 => [48.2406025, 19.3340853], // Modrý Kameň
        2252 => [48.8185887, 17.7928036], // Moravské Lieskové
        2291 => [48.6201970, 18.2518187], // Nadlice
        2295 => [49.4095122, 19.4803756], // Námestovo
        2308 => [48.8176094, 18.6446246], // Nedožery - Brezany
        2333 => [48.3129500, 18.0894593], // Nitra
        2336 => [48.5537377, 17.9669061], // Nitrianska Blatnica
        2378 => [48.8078794, 21.7746197], // Nižný Hrušov
        2419 => [48.2920307, 18.3243456], // Nová Ves nad Žitavou
        2431 => [47.9861843, 18.1631415], // Nové Zámky
        2469 => [49.0748428, 19.6505311], // Okoličné
        2520 => [49.2610972, 19.3570525], // Oravský Podzámok
        2539 => [48.6315679, 18.4686013], // Oslany
        2605 => [48.6128015, 22.0691252], // Pavlovce nad Uhom
        2636 => [48.2854539, 17.2701940], // Pezinok
        2643 => [48.5895247, 17.8213848], // Piešťany
        2763 => [49.0541521, 20.2976401], // Poprad
        2770 => [48.8313714, 18.5926928], // Poruba
        2790 => [49.1159316, 18.4469936], // Považská Bystrica
        2797 => [48.1156937, 18.3868352], // Pozba
        2798 => [48.7257943, 21.8543840], // Pozdišovce
        2801 => [48.3629587, 19.5081950], // Praha
        2822 => [49.0000074, 21.2392122], // Prešov
        2839 => [48.7718361, 18.6234916], // Prievidza
        2883 => [48.4620099, 21.8583845], // Rad
        2885 => [48.0937152, 18.3021805], // Radava
        2893 => [48.7725533, 17.2803098], // Radošovce
        2904 => [49.0449528, 18.6350654], // Rajecká Lesná
        3014 => [48.6620675, 20.5328607], // Rožňava
        3053 => [49.0815718, 19.3034168], // Ružomberok
        3066 => [49.1026740, 21.0973337], // Sabinov
        3075 => [48.6296353, 19.2006986], // Sampor
        3092 => [48.7837273, 21.6800460], // Sečovská Polianka
        3096 => [48.9053599, 21.7278892], // Sedliská
        3097 => [49.0134048, 18.1742166], // Sedmerovec
        3113 => [48.6787557, 17.3661529], // Senica
        3118 => [48.2889032, 17.7308808], // Sereď
        3143 => [48.9279734, 18.0720083], // Skalka nad Váhom
        3190 => [49.2256446, 20.4257705], // Slovenská Ves
        3201 => [48.9571845, 20.5220340], // Smižany
        3206 => [48.7293119, 20.7390108], // Smolník
        3240 => [48.9435344, 20.5629640], // Spišská Nová Ves
        3244 => [48.9921115, 20.2438466], // Spišské Bystré
        3246 => [49.0000350, 20.7510716], // Spišské Podhradie
        3272 => [48.7782198, 17.6948774], // Stará Turá
        3275 => [48.8385543, 19.1147881], // Staré Hory
        3279 => [49.1414836, 20.2221042], // Starý Smokovec
        3329 => [49.0990866, 18.9937678], // Sučany
        3355 => [48.2541879, 17.2127253], // Svätý Jur
        3366 => [49.0121298, 21.1235677], // Svinia
        3370 => [49.0582357, 20.1965291], // Svit
        3379 => [48.1513790, 17.8748587], // Šaľa
        3411 => [48.6376732, 17.1463323], // Šaštín
        3412 => [48.6380414, 17.1439377], // Šaštín - Stráže
        3418 => [48.3000477, 17.3475365], // Šenkvice
        3444 => [48.8074695, 19.1340120], // Špania Dolina
        3449 => [48.6775328, 17.1998548], // Štefanov
        3451 => [48.3840639, 17.3978209], // Štefanová
        3485 => [48.0861055, 18.1868344], // Šurany
        3531 => [49.2580251, 19.0304784], // Terchová
        3537 => [48.1014360, 17.8512447], // Tešedíkovo
        3560 => [48.4202711, 18.4101132], // Topoľčianky
        3580 => [48.6286222, 21.7201711], // Trebišov
        3592 => [48.8922719, 18.0387465], // Trenčín
        3596 => [48.3767652, 17.5858175], // Trnava
        3613 => [49.3592089, 19.6086088], // Trstená
        3619 => [48.5269603, 17.4646045], // Trstín
        3698 => [48.7371605, 17.7229103], // Vaďovce
        3758 => [49.1163282, 20.3586547], // Veľká Lomnica
        3771 => [48.6348865, 18.3553484], // Veľké Bielice
        3777 => [48.7354958, 18.1738722], // Veľké Držkovce
        3783 => [48.5538814, 22.0773719], // Veľké Kapušany
        3933 => [48.8891513, 21.6822318], // Vranov nad Topľou
        3974 => [49.3804047, 18.5511963], // Vysoká nad Kysucou
        3975 => [48.6259789, 22.0921952], // Vysoká nad Uhom
        4016 => [48.7444795, 21.1244396], // Vyšný Klátov
        4048 => [49.0895160, 21.2683874], // Záhradné
        4095 => [49.3679102, 21.3080014], // Zborov
        4130 => [48.9494832, 21.4204059], // Zlatá Baňa
        4153 => [48.5782517, 19.1247135], // Zvolen
        4188 => [48.5910183, 18.8500892], // Žiar nad Hronom
        4194 => [49.2234674, 18.7393139], // Žilina
    ];

    public function up(): void
    {
        foreach (self::MISPLACED_VENUES as [$id, $name]) {
            DB::table('venues')->where('id', $id)->where('name', $name)->whereNull('latitude')
                ->update($this->point(self::SLOVAKIA));
        }

        foreach (self::MUNICIPALITIES as $municipalityId => $center) {
            DB::table('venues')->where('village_id', $municipalityId)->whereNull('latitude')
                ->update($this->point($center));
            DB::table('canals')->where('municipality_id', $municipalityId)->whereNull('latitude')
                ->update($this->point($center));
        }

        // Všetko ostatné vrátane „Celé Slovensko" (4209) a obcí, ktoré geokóder nepoznal.
        foreach (['venues', 'canals'] as $table) {
            DB::table($table)->where(fn ($q) => $q->whereNull('latitude')->orWhereNull('longitude'))
                ->update($this->point(self::SLOVAKIA));
        }
    }

    /**
     * Zámerne prázdne: rovnaké stredy obcí so zdrojom `municipality` zapísala
     * aj predchádzajúca migrácia, takže vrátiť len tieto riadky sa nedá bez
     * rizika, že sa zmažú aj tie staršie.
     */
    public function down(): void {}

    /**
     * @param  array{0: float, 1: float}  $point
     * @return array<string, mixed>
     */
    private function point(array $point): array
    {
        return [
            'latitude' => $point[0],
            'longitude' => $point[1],
            'coordinates_source' => 'municipality',
            'updated_at' => now(),
        ];
    }
};
