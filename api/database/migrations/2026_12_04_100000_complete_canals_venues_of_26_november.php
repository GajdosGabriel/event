<?php

use App\Enums\FileType;
use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\Event;
use App\Models\File;
use App\Models\Venue;
use App\Services\Resources\ResourceMerger;
use App\Services\SystemLog\Recorder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kanály a miesta z importu 26. – 27. 11. 2026 (kanály #1992 – #1996, miesta
 * #1125 – #1136) a prázdne či chybné údaje starších miest (OpenStreetMap, weby
 * správcov, ecav.sk).
 *
 * Duplicity: kanály #1925 a #1994 organizujú tú istú sériu „Mládežka a Chvály ZM"
 * ako #1886 — zlúčia sa doň a #1886 dostane názov z posledného zdroja. Miesta
 * #1131 a #1132 sú košický Dóm sv. Alžbety (#109), #1129 je DKC Veritas na
 * Dominikánskom námestí 8 (#1107) a prázdne #1128 je evanjelický kostol na
 * Mlynskej (#1083). Podujatie #11502 je v kostole Bratov tešiteľov (#1023),
 * nie vo všeobecnom mieste „Zlaté Moravce".
 *
 * Údaj sa dopĺňa len do prázdneho poľa; neprázdny sa prepíše iba vtedy, keď má
 * presne uvedenú chybnú hodnotu. Súradnice sa menia len z uvedenej pôvodnej
 * zemepisnej šírky. Prevzatý, zlúčený či zmazaný kanál a archivované miesto sa
 * nemenia.
 *
 * Fotky: zmenšeniny z Commons v assets/canal-venue-photos, autor a licencia v meta.
 * Fotku dostane len kanál či miesto bez obrázka.
 */
return new class extends Migration
{
    private const SOURCE = 'migration:2026_12_04_canal_venue_photo';

    private const CLOSING = 'Podrobnosti nájdete vždy v detaile podujatia.';

    private const OTHERS = 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.';

    /**
     * name = očakávaný názov, fill = doplniť do prázdneho, fix = [chybná, správna],
     * at = [pôvodná šírka, šírka, dĺžka, zdroj súradníc].
     *
     * @var array<int, array<string, mixed>>
     */
    private const CANALS = [
        1886 => [
            'name' => 'Rímskokatolícke spoločenstvá',
            'fix' => ['name' => ['Rímskokatolícke spoločenstvá', 'Katolícke spoločenstvá Zlatomoraveckého dekanátu']],
            'fill' => ['street' => 'Hviezdoslavova 104/77', 'postcode' => '953 01'],
            'at' => [48.7411522, 48.3863965, 18.3857328, 'venue'],
            'body' => [
                'Katolícke spoločenstvá Zlatomoraveckého dekanátu spájajú mladých veriacich zo Zlatých Moraviec a okolia. Stretávajú sa pri svätej omši, chválach a slove v kostole Bratov tešiteľov na Hviezdoslavovej ulici.',
                'Pripravujú Mládežku a chvály, večerné stretnutia v prvý piatok mesiaca. Po mládežníckej svätej omši nasleduje modlitba chvál s možnosťou prijať individuálne eucharistické požehnanie.',
                'Kanál je určený mladým zo Zlatých Moraviec a okolia. Sledovaním budete vedieť o ďalších stretnutiach. '.self::CLOSING,
            ],
        ],
        1992 => [
            'name' => 'Synodálny tím Nitrianskej diecézy',
            'fix' => ['municipality_id' => [4209, 2333]],
            'fill' => ['street' => 'Námestie Jána Pavla II. 7', 'postcode' => '950 50'],
            'at' => [48.7411522, 48.3184475, 18.0863993, 'address'],
            'body' => [
                'Synodálny tím Nitrianskej diecézy sa venuje synodálnemu procesu v diecéze a pomáha farnostiam, komunitám a spoločenstvám rásť vo vzájomnom počúvaní, spolupráci a spoluzodpovednosti kňazov a laikov. Diecézu spravuje Biskupský úrad na Námestí Jána Pavla II. 7 v Nitre.',
                'Pripravuje stretnutia a vzdelávanie o synodalite, napríklad webinár o budovaní spoločenstva rozlišujúceho v Duchu s teoretickou aj praktickou časťou rozhovoru v Duchu Svätom.',
                'Kanál je určený všetkým, ktorých zaujíma synodálna cesta Cirkvi. Sledovaním budete vedieť o ďalších stretnutiach. '.self::CLOSING,
            ],
        ],
        1993 => [
            'name' => 'CZ ECAV Kladzany',
            'at' => [48.7411522, 48.8822383, 21.7489894, 'address'],
            'body' => [
                'Cirkevný zbor Evanjelickej cirkvi augsburského vyznania na Slovensku v Kladzanoch patrí do Šarišsko-zemplínskeho seniorátu. Farský úrad sídli v Kladzanoch 108, námestným farárom je Mgr. Slavomír Gallo.',
                'Služby Božie bývajú každú nedeľu o 9.00 hod. v evanjelickom kostole v Kladzanoch a pravidelne aj v Hencovciach, Humennom, Snine, Tovarnianskej Polianke a Tovarnom.',
                'Kanál je určený evanjelikom z Kladzian a okolia aj hosťom zborových podujatí. Sledovaním budete vedieť o koncertoch a stretnutiach zboru. '.self::CLOSING,
            ],
        ],
        1995 => [
            'name' => 'CZ ECAV Pribylina',
            'fill' => [
                'street' => 'Mlynská 364/2', 'postcode' => '032 42', 'email' => 'pribylina@ecav.sk', 'phone' => '+421 918 828 347',
                'website' => 'https://www.ecav.sk/vychodny-distrikt/liptovsko-oravsky-seniorat/pribylina',
            ],
            'at' => [48.7411522, 49.0955676, 19.7976155, 'address'],
            'body' => [
                'Cirkevný zbor Evanjelickej cirkvi augsburského vyznania na Slovensku v Pribyline patrí do Liptovsko-oravského seniorátu. Farský úrad sídli na Mlynskej 364/2, zborovou farárkou je Mgr. Erika Pospíšilová.',
                'Zbor sa schádza v neorománskom evanjelickom kostole z rokov 1901 – 1902. Okrem služieb Božích pripravuje aj kultúrne podujatia, napríklad adventný organový koncert.',
                'Kanál je určený evanjelikom z Pribyliny a okolia aj návštevníkom podujatí zboru. Sledovaním budete vedieť o ďalších termínoch. '.self::CLOSING,
            ],
        ],
        1996 => [
            'name' => 'CZ ECAV Vrbové',
            'fill' => [
                'street' => 'Gen. M. R. Štefánika 117/90', 'postcode' => '922 03', 'email' => 'vrbove@ecav.sk', 'phone' => '+421 907 960 484',
                'website' => 'https://www.ecav.sk/zapadny-distrikt/bratislavsky-seniorat/vrbove',
            ],
            'at' => [48.7411522, 48.6283927, 17.7190639, 'address'],
            'body' => [
                'Cirkevný zbor Evanjelickej cirkvi augsburského vyznania na Slovensku vo Vrbovom má farský úrad na ulici Gen. M. R. Štefánika 117/90. Zborovým farárom je Mgr. Štefan Panuška.',
                'Služby Božie bývajú každú nedeľu o 10.00 hod. vo Vrbovom a tretiu nedeľu v mesiaci popoludní v domove dôchodcov. Zbor pripravuje aj koncerty v evanjelickom kostole.',
                'Kanál je určený evanjelikom z Vrbového a okolia aj hosťom zborových podujatí. Sledovaním budete vedieť o koncertoch a stretnutiach zboru. '.self::CLOSING,
            ],
        ],
    ];

    /** @var array<int, array<string, mixed>> */
    private const VENUES = [
        // Najnovšie miesta.
        1125 => ['name' => 'Primaciálne námestie',
            'fix' => ['website' => ['https://www.pscpsc.sk/psc-Bratislava-Primacialne_namestie', null]],
            'body' => [
                'Primaciálne námestie leží v bratislavskom Starom Meste hneď za Starou radnicou. Dominuje mu klasicistický Primaciálny palác z konca 18. storočia, dnes sídlo primátora so známou Zrkadlovou sieňou.',
                'Námestie patrí k miestam mestských slávností, trhov a stretnutí. V advente sa tu konajú aj dialógy bratislavského pomocného biskupa Jozefa Haľka s okoloidúcimi.',
                self::OTHERS,
            ]],
        1126 => ['name' => 'Kostol Povýšenia Svätého Kríža',
            'fix' => [
                'name' => ['Kostol Povýšenia Svätého Kríža', 'Kostol Povýšenia Svätého Kríža (Podunajské Biskupice)'],
                'street' => ['13284', 'Biskupická 64'],
                'postcode' => ['974 01', '821 06'],
            ],
            'at' => [48.1290319, 48.1260343, 17.2085450, 'venue'],
            'body' => [
                'Kostol Povýšenia Svätého Kríža je rímskokatolícky kostol na Biskupickej ulici v bratislavskej mestskej časti Podunajské Biskupice. Spája sa s Kongregáciou milosrdných sestier Svätého kríža.',
                'Sestry sem pozývajú veriacich na pútnické sväté omše k úcte blahoslavenej Zdenky Schelingovej, rehoľníčky a mučenice komunistického režimu, ktorá patrila do ich spoločenstva.',
                self::OTHERS,
            ]],
        1127 => ['name' => 'Kostol',
            'fix' => [
                'name' => ['Kostol', 'Kostol sv. Michala archanjela (Nitrica)'],
                'street' => ['1771', 'Nitrica 1771'],
            ],
            'body' => [
                'Kostol svätého Michala archanjela je rímskokatolícky kostol v obci Nitrica v okrese Prievidza. Patrí do farnosti Horné Vestenice v Nitrianskej diecéze, ktorej farský úrad sídli v Horných Vesteniciach 236.',
                'Slúži na bohoslužby a farské slávnosti. V decembri 2026 si tu pripomenú sté výročie narodenia misionára Jozefa Bereca SVD.',
                self::OTHERS,
            ]],
        1130 => ['name' => 'Evanjelický kostol',
            'fix' => [
                'name' => ['Evanjelický kostol', 'Evanjelický kostol (Kladzany)'],
                'street' => ['71', 'Kladzany 71'],
            ],
            'body' => [
                'Evanjelický kostol v Kladzanoch je jednoloďová klasicistická stavba s predstavanou vežou z rokov 1808 – 1812. Obnovili ho v rokoch 1910 a 1950.',
                'V interiéri je klasicistický oltár s obrazom Žehnajúceho Krista a organ z roku 1901. Kostol slúži miestnemu cirkevnému zboru ECAV na služby Božie a koncerty.',
                self::OTHERS,
            ]],
        1133 => ['name' => 'Diecézna svätyňa sv. Andreja-Svorada a Beňadika',
            'fill' => [
                'street' => 'Skalka 507', 'website' => 'https://putnickemiestoskalka.sk',
                'email' => 'knaz@putnickemiestoskalka.sk', 'phone' => '+421 32 658 4240',
            ],
            'at' => [48.9064469, 48.9060104, 18.0739329, 'venue'],
            'body' => [
                'Diecézna svätyňa svätého Andreja-Svorada a Beňadika stojí na Malej Skalke pri Trenčíne. Skalka je najstaršie pútnické miesto na Slovensku, spojené s pustovníkmi z 11. storočia a patrónmi Nitrianskej diecézy.',
                'K areálu patrí aj Veľká Skalka s kláštorom a jaskyňou. Pútnické miesto spravuje farnosť Skalka nad Váhom, konajú sa tu púte a diecézne bohoslužby.',
                self::OTHERS,
            ]],
        1134 => ['name' => 'Evanjelický kostol (Pribylina)',
            'fix' => ['street' => ['12', 'Emila Janotku 52/47'], 'postcode' => ['026 01', '032 42']],
            'at' => [49.0987736, 49.0952569, 19.7973602, 'venue'],
            'body' => [
                'Evanjelický kostol v Pribyline je neorománska jednoloďová stavba s predstavanou vežou z rokov 1901 – 1902 podľa návrhu architekta Milana Michala Harminca. Stojí na mieste staršieho dreveného kostola z roku 1812.',
                'Interiér zdobí výmaľba podľa návrhu Jozefa Hálu z roku 1925. Kostol slúži miestnemu cirkevnému zboru ECAV na služby Božie a koncerty.',
                self::OTHERS,
            ]],
        1135 => ['name' => 'Evanjelický kostol',
            'fix' => ['name' => ['Evanjelický kostol', 'Evanjelický kostol (Vrbové)']],
            'fill' => ['website' => 'https://www.ecav.sk/zapadny-distrikt/bratislavsky-seniorat/vrbove'],
            'body' => [
                'Evanjelický kostol vo Vrbovom je stavba v štýle umiernenej moderny na pôdoryse gréckeho kríža z rokov 1928 – 1929. Stojí na mieste tolerančného kostola z roku 1784, súčasná veža pochádza z roku 1896.',
                'Slúži miestnemu cirkevnému zboru ECAV na nedeľné služby Božie. Konajú sa v ňom aj hudobné podujatia, napríklad vianočný organový koncert.',
                self::OTHERS,
            ]],
        1136 => ['name' => 'Dom odborov',
            'fix' => ['name' => ['Dom odborov', 'Dom odborov (Žilina)']],
            'fill' => ['website' => 'https://www.domodborov.sk', 'phone' => '+421 41 723 2064'],
            'body' => [
                'Dom odborov je kultúrny a spoločenský dom na Ulici Antona Bernoláka v Žiline, v blízkosti obchodného centra Aupark a neďaleko centra mesta.',
                'Konajú sa v ňom koncerty, divadelné predstavenia, plesy a konferencie, napríklad celoslovenská konferencia pre mužov iMuž. Informácie k podujatiam poskytuje recepcia domu.',
                self::OTHERS,
            ]],
        // Staršie miesta: prázdne či chybné adresy, súradnice a weby.
        4 => ['name' => 'kostol sv. Františka Minoriti', 'fill' => ['postcode' => '841 04'], 'at' => [48.1516988, 48.1594631, 17.0519665, 'venue']],
        6 => ['name' => 'Kapitulská 1, Bratislava', 'fill' => ['postcode' => '811 01'], 'fix' => ['street' => ['Kapitulská', 'Kapitulská 1']]],
        10 => ['name' => 'Prešov, Konštantínova 2', 'fill' => ['postcode' => '080 01'], 'fix' => ['street' => ['Konštantínova', 'Konštantínova 2']]],
        26 => ['name' => 'hotel SOREA MÁJ', 'fill' => ['website' => 'https://www.sorea.sk/nizke-tatry/hotel-sorea-maj/', 'phone' => '+421 44 520 8100']],
        37 => ['name' => 'Kalvária (Nitra)', 'fill' => ['postcode' => '949 01']],
        66 => ['name' => 'Námestie slobody (Bratislava)', 'fill' => ['postcode' => '811 06']],
        71 => ['name' => 'Bazilika Zoslania Ducha Svätého (Michalovce)', 'fill' => ['website' => 'https://grkatmichalovce.fara.sk']],
        72 => ['name' => 'Kostol sv. Mikuláša (Kovarce)', 'fill' => ['website' => 'https://kovarce.nrb.sk']],
        75 => ['name' => 'Dóm sv. Štefana (Viedeň)', 'fill' => ['street' => 'Stephansplatz 3', 'website' => 'https://www.stephanskirche.at']],
        81 => ['name' => 'TIPOS Aréna (Bratislava)', 'fill' => ['street' => 'Odbojárov 9', 'postcode' => '831 04']],
        90 => ['name' => 'HC MONACObet Banská Bystrica', 'fill' => ['street' => 'Hronské predmestie 1452/4', 'postcode' => '974 01'],
            'at' => [48.7354290, 48.7339522, 19.1552397, 'venue']],
        107 => ['name' => 'Seminárny kostol na Hlavnej 89', 'fill' => ['postcode' => '040 01'], 'fix' => ['street' => ['Hlavná', 'Hlavná 89']]],
        112 => ['name' => 'Arcidiecézne centrum pre mládež ACM Vinica', 'at' => [48.2117887, 48.2128793, 17.1490479, 'venue']],
        116 => ['name' => 'Kláštor Hronský Beňadik', 'fill' => ['postcode' => '966 53']],
        131 => ['name' => 'Jesenákov palác (Michalská)', 'fill' => ['postcode' => '811 01'], 'fix' => ['street' => ['Michalská', 'Michalská 3']]],
        152 => ['name' => 'Bazilika Mariazell (Rakúsko)', 'fill' => ['website' => 'https://www.basilika-mariazell.at']],
        609 => ['name' => 'Herman', 'at' => [35.8580559, 48.5005710, 19.5386511, 'municipality']],
        719 => ['name' => 'Aula sv. Jána Pavla II.', 'fill' => ['postcode' => '034 01']],
        918 => ['name' => 'Skalné sanktuárium Božieho milosrdenstva', 'fill' => ['website' => 'https://www.krizbutkov.sk'],
            'at' => [49.0174221, 49.0220569, 18.3205318, 'venue']],
        979 => ['name' => 'Diecézne sanktuárium Ružencovej Panny Márie (Obišovce)', 'fill' => ['website' => 'https://www.farnostobisovce.sk']],
        1005 => ['name' => 'Rodinkovo, Beluša', 'fill' => ['website' => 'https://www.rodinkovo.sk', 'phone' => '+421 911 911 780']],
        1031 => ['name' => 'Kultúrne centrum kresťanov FUGA', 'at' => [48.7172272, 48.7217041, 21.2521098, 'address']],
        1055 => ['name' => 'Pútnický dom Zdenky Schelingovej', 'fill' => ['street' => 'Biskupická', 'postcode' => '821 06']],
        1057 => ['name' => 'Pútnický dom', 'fix' => ['postcode' => ['911 05', '900 33']], 'at' => [48.2477922, 48.2482028, 17.0650863, 'address']],
        1119 => ['name' => 'Evanjelická fara (Hybe)', 'at' => [49.0444964, 49.0452774, 19.8322264, 'address']],
    ];

    /** Miesta, ktoré zastupujú len obec: ID => [názov, úvodná veta]. */
    private const TOWN_VENUES = [
        12 => ['Celé Slovensko', 'Záznam zastupuje celoslovenské a online podujatia, ktoré sa neviažu na jedno konkrétne miesto.'],
        947 => ['celý svet', 'Záznam zastupuje medzinárodné a online podujatia, ktoré sa neviažu na jedno konkrétne miesto.'],
        166 => ['Ábelová', 'Ábelová je obec v okrese Lučenec v Banskobystrickom kraji.'],
        167 => ['Bratislava', 'Bratislava je hlavné a najväčšie mesto Slovenska na brehoch Dunaja.'],
        168 => ['Košice', 'Košice sú druhé najväčšie mesto Slovenska a metropola jeho východnej časti.'],
        173 => ['Banská Bystrica', 'Banská Bystrica je krajské mesto na strednom Slovensku.'],
        175 => ['Prešov', 'Prešov je krajské mesto a prirodzené centrum regiónu Šariš.'],
        176 => ['Nitra', 'Nitra je krajské mesto a sídlo najstaršieho biskupstva na Slovensku.'],
        181 => ['Ľubietová', 'Ľubietová je obec v okrese Banská Bystrica a niekdajšie banské mesto.'],
        186 => ['Spišská Nová Ves', 'Spišská Nová Ves je okresné mesto na Spiši a vstupná brána do Slovenského raja.'],
        190 => ['Martin', 'Martin je okresné mesto v Turci a sídlo Matice slovenskej.'],
        194 => ['Vysoká nad Uhom', 'Vysoká nad Uhom je obec v okrese Michalovce.'],
        226 => ['Gorazdov', 'Gorazdov je miestna časť obce Močenok v okrese Šaľa.'],
        241 => ['Staré Hory', 'Staré Hory sú obec v okrese Banská Bystrica a známe mariánske pútnické miesto.'],
        260 => ['Nové Mesto nad Váhom', 'Nové Mesto nad Váhom je okresné mesto v Trenčianskom kraji.'],
        265 => ['Šaštín - Stráže', 'Šaštín-Stráže je mesto v okrese Senica s národnou svätyňou Sedembolestnej Panny Márie.'],
        269 => ['Badín', 'Badín je obec v okrese Banská Bystrica, kde sídli Kňazský seminár sv. Františka Xaverského.'],
        356 => ['Michalovce', 'Michalovce sú okresné mesto v Košickom kraji.'],
        402 => ['Zlaté Moravce', 'Zlaté Moravce sú okresné mesto v Nitrianskom kraji.'],
        406 => ['Gelnica', 'Gelnica je okresné mesto v Košickom kraji.'],
        413 => ['Poltár', 'Poltár je okresné mesto v Banskobystrickom kraji.'],
        441 => ['Senec', 'Senec je okresné mesto v Bratislavskom kraji.'],
        538 => ['Malinovo', 'Malinovo je obec v okrese Senec pri Malom Dunaji.'],
        568 => ['Hrabovec', 'Hrabovec je obec v okrese Bardejov.'],
        609 => ['Herman', 'Herman je osada, ktorá patrí k obci Dobroč v okrese Lučenec.'],
        683 => ['Mníšek nad Hnilcom', 'Mníšek nad Hnilcom je obec v okrese Gelnica.'],
        702 => ['Biel', 'Biel je obec v okrese Trebišov.'],
    ];

    /** Zdroj => [cieľ, názov zdroja, názov cieľa]. */
    private const CANAL_MERGES = [
        1925 => [1886, 'Kresťanské spoločenstvá', 'Katolícke spoločenstvá Zlatomoraveckého dekanátu'],
        1994 => [1886, 'Katalícke spoločenstvá Zlatomoraveckého dekanátu', 'Katolícke spoločenstvá Zlatomoraveckého dekanátu'],
    ];

    private const VENUE_MERGES = [
        1128 => [1083, 'Mlynská 23', 'Evanjelický kostol (Košice)'],
        1129 => [1107, 'Dominikánske námestie 8', 'Dominikánske kultúrne centrum Veritas'],
        1131 => [109, 'Dóme svätej Alžbety', 'Dóm svätej Alžbety'],
        1132 => [109, 'Dóme sv. Alžbety', 'Dóm svätej Alžbety'],
    ];

    /** Podujatie => [názov, z miesta, na miesto, názov cieľa]. */
    private const EVENT_MOVES = [
        11502 => ['Mládežka a Chvály ZM december', 402, 1023, 'Kostol Božského Srdca Ježišovho (Zlaté Moravce)'],
    ];

    /** Kľúč (c = kanál, v = miesto) => [názov, súbor, názov na Commons, autor, licencia, odkaz na licenciu]. */
    private const PHOTOS = [
        'c1928' => ['Filozofická fakulta Katolíckej univerzity v Ružomberku', 'ruzomberok-filozoficka-fakulta-ku.jpg', 'Filozofická fakuta KU v Ružomberku.jpg', 'Eugen.zelenak', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        'c1992' => ['Synodálny tím Nitrianskej diecézy', 'nitra-biskupsky-palac.jpg', 'Nitra - Castle 02.jpg', 'Lestat (Jan Mehlich)', 'CC BY-SA 3.0', 'https://creativecommons.org/licenses/by-sa/3.0'],
        'c1995' => ['CZ ECAV Pribylina', 'pribylina-evanjelicky-kostol-bocny-pohlad.jpg', 'Evangelical Church Pribylina1.JPG', 'Slav Sepo', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        'c1996' => ['CZ ECAV Vrbové', 'vrbove-evanjelicky-kostol.jpg', 'Evanjelický kostol, Vrbové (2020).jpg', 'Akul59', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        'v11' => ['Kláštor kapucínov (Pezinok)', 'pezinok-klastor-kapucinov.jpg', 'Kláštor kapucínov, Holubyho ul. 91, Pezinok, okres Pezinok 01.JPG', 'Horakvlado (talk)Horakvlado', 'CC BY-SA 3.0', 'https://creativecommons.org/licenses/by-sa/3.0'],
        'v17' => ['Poprad', 'poprad-namestie.jpg', 'Poprad námestie.jpg', 'Lucas', 'CC BY-SA 2.0', 'https://creativecommons.org/licenses/by-sa/2.0'],
        'v21' => ['evanjelický kostol ap. Petra a Pavla', 'modra-slovensky-evanjelicky-kostol.jpg', 'Modra evanjelický slovenský 03.jpg', 'Ľuboš Repta', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        'v26' => ['hotel SOREA MÁJ', 'liptovsky-jan-hotel-sorea-maj.jpg', 'Hotel Maj - panoramio.jpg', 'pepanos', 'CC BY 3.0', 'https://creativecommons.org/licenses/by/3.0'],
        'v402' => ['Zlaté Moravce', 'zlate-moravce-fontana.jpg', 'Zlate Moravce (7).jpg', 'János Korom Dr.', 'CC BY-SA 2.0', 'https://creativecommons.org/licenses/by-sa/2.0'],
        'v1078' => ['Evanjelické lýceum (Kežmarok)', 'kezmarok-evanjelicke-lyceum.jpg', 'Evanjelické lýceum (Kežmarok) 2019-01-06.jpg', 'Govisity / Tip na Trip / kosice.guide', 'CC BY 2.0', 'https://creativecommons.org/licenses/by/2.0'],
        'v1084' => ['Evanjelický kostol (Sobotište)', 'sobotiste-evanjelicky-kostol.jpg', 'Sobotište evkostol 04.jpg', 'Akul59', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        'v1086' => ['Vrbovce', 'vrbovce-centrum.jpg', 'Vrbovce 05.jpg', 'Stanislav Doronenko', 'CC BY 3.0', 'https://creativecommons.org/licenses/by/3.0'],
        'v1125' => ['Primaciálne námestie', 'bratislava-primacialne-namestie.jpg', 'Primaciálne námestie, Bratislava.jpg', 'Radosław Botev & Sylwia Botev', 'CC BY 3.0 pl', 'https://creativecommons.org/licenses/by/3.0/pl/deed.en'],
        'v1126' => ['Kostol Povýšenia Svätého Kríža (Podunajské Biskupice)', 'podunajske-biskupice-kostol-sv-kriza.jpg', 'Podunajské Biskupice 03 02.jpg', 'Lure', 'CC BY-SA 3.0', 'https://creativecommons.org/licenses/by-sa/3.0'],
        'v1133' => ['Diecézna svätyňa sv. Andreja-Svorada a Beňadika', 'skalka-kostol-sv-svorada-a-benadika.jpg', 'Malá skalka kostol sv. Svorada a Beňadika.jpg', 'Pistal', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        'v1134' => ['Evanjelický kostol (Pribylina)', 'pribylina-evanjelicky-kostol.jpg', 'Pribylina - evanjelický kostol.jpg', 'Patrik Kunec', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        'v1135' => ['Evanjelický kostol (Vrbové)', 'vrbove-evanjelicky-kostol.jpg', 'Evanjelický kostol, Vrbové (2020).jpg', 'Akul59', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
    ];

    public function up(): void
    {
        $this->completeCanals();
        $this->completeVenues();
        $this->merge('canals', self::CANAL_MERGES);
        $this->merge('venues', self::VENUE_MERGES);
        $this->moveEvents();
        $this->attachPhotos();
    }

    private function completeCanals(): void
    {
        foreach (self::CANALS as $id => $data) {
            $canal = Canal::query()->find($id);

            if (! $canal || $canal->claimed_at !== null || $canal->merged_into_id !== null || $canal->name !== $data['name']) {
                continue;
            }

            $this->apply($canal, $data, 'canals', 'Kanál');
        }
    }

    private function completeVenues(): void
    {
        foreach (self::VENUES as $id => $data) {
            $venue = Venue::query()->find($id);

            if (! $venue || $venue->status === ModelStatus::Archived || $venue->name !== $data['name']) {
                continue;
            }

            $this->apply($venue, $data, 'venues', 'Miesto');
        }

        foreach (self::TOWN_VENUES as $id => [$name, $intro]) {
            $venue = Venue::query()->find($id);

            if (! $venue || $venue->status === ModelStatus::Archived || $venue->name !== $name
                || trim((string) $venue->body) !== '') {
                continue;
            }

            $this->apply($venue, ['body' => [
                $intro,
                'Miesto sa používa pri podujatiach, pri ktorých zdroj neuviedol presnejšiu adresu ani konkrétnu budovu.',
                'Presné miesto, čas a vstup si overte vždy v detaile podujatia.',
            ]], 'venues', 'Miesto');
        }
    }

    /** @param  array<string, mixed>  $data */
    private function apply(Model $model, array $data, string $channel, string $label): void
    {
        $changes = [];

        foreach ($data['fill'] ?? [] as $field => $value) {
            if (trim((string) $model->$field) === '') {
                $changes[$field] = $value;
            }
        }

        foreach ($data['fix'] ?? [] as $field => [$wrong, $right]) {
            if ((string) $model->$field === (string) $wrong) {
                $changes[$field] = $right;
            }
        }

        if (isset($data['at'])) {
            [$from, $lat, $lng, $source] = $data['at'];

            if ($model->coordinates_source !== 'manual' && abs((float) $model->latitude - $from) < 0.0001) {
                $changes += ['latitude' => $lat, 'longitude' => $lng, 'coordinates_source' => $source];
            }
        }

        // Popisy najnovších záznamov z generátora majú jeden odsek, nahradia sa overenými.
        if (isset($data['body']) && substr_count((string) $model->body, '<p') !== 3) {
            $changes['body'] = implode('', array_map(fn (string $p) => '<p>'.e($p).'</p>', $data['body']));
        }

        $changes = array_filter($changes, fn ($value, $field) => $value != $model->$field, ARRAY_FILTER_USE_BOTH);

        if ($changes === []) {
            return;
        }

        $model->forceFill($changes)->save();
        Recorder::info($channel, 'profile_described', "{$label} {$model->name}: doplnené ".implode(', ', array_keys($changes)),
            status: 'ok', subject: $model, context: ['fields' => array_keys($changes)]);
    }

    /** @param  array<int, array{int, string, string}>  $merges */
    private function merge(string $kind, array $merges): void
    {
        $class = $kind === 'canals' ? Canal::class : Venue::class;

        foreach ($merges as $sourceId => [$targetId, $sourceName, $targetName]) {
            $source = $class::query()->find($sourceId);
            $target = $class::query()->find($targetId);

            if (! $source || ! $target || $source->name !== $sourceName || $target->name !== $targetName
                || $target->status === ModelStatus::Archived
                || ($source instanceof Canal && ($source->claimed_at !== null || $target->claimed_at !== null))) {
                continue;
            }

            try {
                DB::transaction(function () use ($kind, $source, $sourceId, $targetId) {
                    // Rozpracované miesto bez podujatí zlúčenie do zverejneného pustí len archivované.
                    if ($source instanceof Venue && $source->status === ModelStatus::Draft
                        && ! Event::withTrashed()->where('venue_id', $sourceId)->exists()) {
                        $source->forceFill(['status' => ModelStatus::Archived])->save();
                    }

                    app(ResourceMerger::class)->merge($kind, $sourceId, $targetId, null);
                });
            } catch (Throwable $e) {
                Recorder::warning('admin', 'resource.merge_skipped', "Zlúčenie {$kind} #{$sourceId} → #{$targetId} preskočené",
                    subject: $target, context: ['error' => mb_substr($e->getMessage(), 0, 250)]);
            }
        }
    }

    private function moveEvents(): void
    {
        foreach (self::EVENT_MOVES as $eventId => [$name, $fromId, $toId, $venueName]) {
            $event = Event::query()->find($eventId);
            $venue = Venue::query()->find($toId);

            if (! $event || $event->name !== $name || (int) $event->venue_id !== $fromId || $venue?->name !== $venueName) {
                continue;
            }

            $event->forceFill(['venue_id' => $toId])->save();

            if ($event->canal_id !== null) {
                DB::table('canal_venue')->insertOrIgnore([
                    'canal_id' => $event->canal_id, 'venue_id' => $toId, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            Recorder::info('venues', 'events_moved', "Podujatie #{$eventId} presunuté z miesta #{$fromId} na #{$toId}",
                status: 'ok', subject: $venue, context: ['events' => [$eventId], 'moved' => 1]);
        }
    }

    private function attachPhotos(): void
    {
        if (! Schema::hasTable('files')) {
            return;
        }

        foreach (self::PHOTOS as $key => [$name, $file, $title, $author, $license, $licenseUrl]) {
            $class = $key[0] === 'c' ? Canal::class : Venue::class;
            $model = $class::query()->find((int) substr($key, 1));

            if (! $model || $model->name !== $name || $this->hasImage($model)
                || ($model instanceof Canal && ($model->claimed_at !== null || $model->merged_into_id !== null))
                || ($model instanceof Venue && $model->status === ModelStatus::Archived)) {
                continue;
            }

            $path = __DIR__.'/assets/canal-venue-photos/'.$file;
            if (! is_file($path)) {
                continue;
            }

            try {
                $model->addFiles(
                    files: new UploadedFile($path, $file, 'image/jpeg', null, true),
                    type: FileType::IMAGE,
                    makePrimary: true,
                    meta: [
                        'source' => self::SOURCE,
                        'origin' => 'wikimedia_commons',
                        'commons_title' => 'File:'.$title,
                        'commons_page' => 'https://commons.wikimedia.org/wiki/File:'.rawurlencode(str_replace(' ', '_', $title)),
                        'author' => $author,
                        'license' => $license,
                        'license_url' => $licenseUrl,
                    ],
                );
            } catch (Throwable $e) {
                Recorder::warning('files', 'photo_skipped', "Fotka {$file} pre {$key} sa nenahrala",
                    subject: $model, context: ['error' => mb_substr($e->getMessage(), 0, 250)]);
            }
        }
    }

    private function hasImage(Model $model): bool
    {
        return File::query()
            ->where('fileable_type', $model->getMorphClass())
            ->where('fileable_id', $model->getKey())
            ->where('type', FileType::IMAGE->value)
            ->exists();
    }

    public function down(): void
    {
        // Doplnené údaje, zlúčenia a presun sa nevracajú; odstránia sa len nahraté fotky.
        if (! Schema::hasTable('files')) {
            return;
        }

        File::query()
            ->where('meta->source', self::SOURCE)
            ->get()
            ->each(fn (File $file) => $file->delete());
    }
};
