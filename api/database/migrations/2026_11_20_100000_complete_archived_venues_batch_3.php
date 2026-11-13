<?php

use App\Models\Venue;
use App\Services\Resources\ResourceMerger;
use App\Services\SystemLog\Recorder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Archivované miesta, 3. dávka (november 2026): ďalších 60 konkrétnych
 * archivovaných miest podľa počtu podujatí. Miesta dostanú skutočný názov,
 * popis, opravenú obec a súradnice; ostávajú archivované. Nezmyselné záznamy
 * a miesta s cudzími podujatiami (Samária pri studni, Trojka…) sa nemenia.
 *
 * Opravy: Puskás Aréna bola v Košiciach, Blatnohrad v Zalabe, Rajka
 * v Bratislave, Markowa v Markovciach, katedrála v Astane v Novosade,
 * kostol v Poprade v Petržalke, Koloseum a Aula Pavla VI. mali slovenské PSČ,
 * kaplnka v Krásne nad Kysucou a kostol v Černovej mali súradnice inde.
 *
 * Duplikáty sa zlúčia: #869 → #88 (Lukov dvor), #884 → #60 (Kláštor
 * sv. Augustína), #882 → #41 (Amfiteáter Námestovo), #876 → #16 (Krivá).
 *
 * Popis sa prepíše len vtedy, keď ešte nemá tri odseky; ulica, PSČ a web sa
 * dopĺňajú do prázdneho poľa (okrem `fix_*`). Miesto musí sedieť ID aj názvom.
 */
return new class extends Migration
{
    private const OTHERS = 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.';

    /** Zahraničné mestá (už existujú; vytvoria sa len ak chýbajú). */
    private const CITIES = [
        'krakov' => ['name' => 'Krakov', 'zip' => '30-001'],
        'brno' => ['name' => 'Brno', 'zip' => '603 00'],
        'brusel' => ['name' => 'Brusel', 'zip' => '1000'],
        'selce-chorvatsko' => ['name' => 'Selce (Chorvátsko)', 'zip' => '51266'],
        'markowa' => ['name' => 'Markowa', 'zip' => '37-120'],
        'astana' => ['name' => 'Astana', 'zip' => '010000'],
        'assisi' => ['name' => 'Assisi', 'zip' => '06081'],
        'budapest' => ['name' => 'Budapešť', 'zip' => '1011'],
        'zalavar' => ['name' => 'Zalavár', 'zip' => '8392'],
        'rajka' => ['name' => 'Rajka', 'zip' => '9224'],
    ];

    /** @var array<int, array<string, mixed>> */
    private const VENUES = [
        782 => [
            'name' => 'Ignatianum Univerzita',
            'fix_name' => 'Akadémia Ignatianum (Krakov)',
            'city' => 'krakov',
            'country' => 'Poľsko',
            'fix_street' => 'ul. Mikołaja Kopernika 26',
            'fix_postcode' => '31-501',
            'latitude' => 50.0631,
            'longitude' => 19.9504,
            'coordinates_source' => 'address',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Akadémia Ignatianum je jezuitská vysoká škola v Krakove. Sídli na ulici Kopernika v centre mesta.',
                1 => 'Konajú sa tu štúdiá a kurzy, napríklad postgraduálne štúdium prevencie sexuálneho násilia voči deťom a mládeži, na ktoré pozýva Centrum ochrany dieťaťa.',
                2 => self::OTHERS,
            ],
        ],
        799 => [
            'name' => 'Športová hala',
            'fix_name' => 'Športová hala Levice',
            'body' => [
                0 => 'Športová hala v Leviciach stojí na ulici Turecký rad. Slúži športovým klubom aj väčším spoločenským podujatiam.',
                1 => 'Konajú sa tu aj cirkevné stretnutia, napríklad Dištriktuálny deň Západného dištriktu ECAV.',
                2 => self::OTHERS,
            ],
        ],
        800 => [
            'name' => 'kostol Ducha Svätého – Špitáliku',
            'fix_name' => 'Kostol Ducha Svätého – Špitálik (Hlohovec)',
            'body' => [
                0 => 'Kostol Ducha Svätého, nazývaný Špitálik, je malý historický kostol v Hlohovci na ulici Pod Beranom. Pôvodne patril k mestskému špitálu.',
                1 => 'Konajú sa tu sväté omše a pobožnosti, napríklad deň úcty k sv. Šarbelovi, ktorý prenášalo Rádio Mária.',
                2 => self::OTHERS,
            ],
        ],
        801 => [
            'name' => 'Penzion Klek***',
            'fix_name' => 'Penzión Klek (Selce, Chorvátsko)',
            'city' => 'selce-chorvatsko',
            'country' => 'Chorvátsko',
            'body' => [
                0 => 'Penzión Klek je ubytovanie v prímorskom mestečku Selce pri Crikvenici v Chorvátsku.',
                1 => 'Konajú sa tu letné pobyty slovenských spoločenstiev, napríklad Chorvátske leto občianskeho združenia Slobodní 28+.',
                2 => self::OTHERS,
            ],
        ],
        808 => [
            'name' => 'evanjelický kostol',
            'fix_name' => 'Evanjelický kostol (Stará Turá)',
            'body' => [
                0 => 'Evanjelický a. v. kostol stojí na Hurbanovej ulici v Starej Turej. Patrí miestnemu cirkevnému zboru ECAV.',
                1 => 'Konajú sa tu služby Božie a stretnutia, napríklad večer Chcemviac pre mladých a dospelých.',
                2 => self::OTHERS,
            ],
        ],
        809 => [
            'name' => 'Marková',
            'fix_name' => 'Markowa (Poľsko)',
            'city' => 'markowa',
            'country' => 'Poľsko',
            'fix_postcode' => '37-120',
            'latitude' => 50.0245067,
            'longitude' => 22.3112211,
            'coordinates_source' => 'municipality',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Markowa je obec v juhovýchodnom Poľsku pri Łańcute. Je známa rodinou Ulmovcov, ktorú nacisti zavraždili v roku 1944 za ukrývanie Židov. V obci je múzeum Poliakov zachraňujúcich Židov.',
                1 => 'V septembri 2023 tu blahorečili celú rodinu Ulmovcov vrátane detí.',
                2 => self::OTHERS,
            ],
        ],
        813 => [
            'name' => 'Kostol svätej Kataríny Alexandrijskej',
            'fix_name' => 'Kostol sv. Kataríny (Kremnica)',
            'body' => [
                0 => 'Gotický kostol sv. Kataríny Alexandrijskej stojí v areáli mestského hradu nad Zámockým námestím v Kremnici. Je známy koncertmi na jeho organe.',
                1 => 'Konajú sa tu sväté omše a slávnosti, napríklad omša ku cti sv. Cyrila a Metoda v rámci Cyrilometodiády.',
                2 => self::OTHERS,
            ],
        ],
        815 => [
            'name' => 'Dom kultúry',
            'fix_name' => 'Dom kultúry Liptovský Mikuláš',
            'body' => [
                0 => 'Dom kultúry stojí na Štefánikovej ulici v Liptovskom Mikuláši. Má divadelnú sálu a priestory na koncerty a spoločenské podujatia.',
                1 => 'Konajú sa tu aj duchovné podujatia, napríklad evanjelizácia PRE TEBA s hudbou, slovom a príbehmi.',
                2 => self::OTHERS,
            ],
        ],
        816 => [
            'name' => 'Evanjelický kostol Svätej Trojice (Poprad)',
            'fix_village_id' => 2763,
            'body' => [
                0 => 'Evanjelický kostol Svätej Trojice stojí na Námestí sv. Egídia v Poprade. Je hlavným kostolom miestneho cirkevného zboru ECAV.',
                1 => 'Konajú sa tu služby Božie, koncerty a slávnosti zboru.',
                2 => self::OTHERS,
            ],
        ],
        820 => [
            'name' => 'Nitriansky hrad',
            'body' => [
                0 => 'Nitriansky hrad stojí na Hradnom kopci nad starým mestom. V areáli je Katedrála sv. Emeráma, biskupský palác a Diecézne múzeum. Je sídlom nitrianskeho biskupa.',
                1 => 'Konajú sa tu bohoslužby, prehliadky a výstavy, napríklad adventné prehliadky hradu.',
                2 => self::OTHERS,
            ],
        ],
        821 => [
            'name' => 'Ranč Kráľová Lehota',
            'fix_village_id' => 1633,
            'fix_postcode' => '032 33',
            'body' => [
                0 => 'Ranč v Kráľovej Lehote je ubytovacie a stretávacie miesto na Liptove pod Nízkymi Tatrami.',
                1 => 'Konajú sa tu pobyty a školy, napríklad škola chvál kapely Timothy.',
                2 => self::OTHERS,
            ],
        ],
        822 => [
            'name' => 'kostol Nanebovzatia Panny Márie (u jezuitov)',
            'fix_name' => 'Kostol Nanebovzatia Panny Márie u jezuitov (Brno)',
            'city' => 'brno',
            'country' => 'Česko',
            'fix_street' => 'Mezírka 1',
            'fix_postcode' => '602 00',
            'latitude' => 49.1969215,
            'longitude' => 16.6100691,
            'coordinates_source' => 'address',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Jezuitský kostol Nanebovzatia Panny Márie stojí v centre Brna na rohu ulíc Jezuitská a Mezírka. Je to baroková stavba bývalého jezuitského kolégia.',
                1 => 'Konajú sa tu aj sväté omše pre Slovákov v Brne, napríklad slávnosť Krista Kráľa s brnianskym biskupom Pavlom Konzbulom.',
                2 => self::OTHERS,
            ],
        ],
        824 => [
            'name' => 'penzión Zornička',
            'fix_name' => 'Penzión Zornička (Donovaly)',
            'body' => [
                0 => 'Penzión Zornička je ubytovanie na Donovaloch medzi Veľkou Fatrou a Nízkymi Tatrami.',
                1 => 'Konajú sa tu duchovné obnovy, napríklad obnova Rádia LUMEN s biskupom Františkom Rábekom.',
                2 => self::OTHERS,
            ],
        ],
        825 => [
            'name' => 'Koloseum',
            'fix_name' => 'Koloseum (Rím)',
            'country' => 'Taliansko',
            'fix_street' => 'Piazza del Colosseo',
            'fix_postcode' => '00184',
            'latitude' => 41.8902572,
            'longitude' => 12.4923841,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Koloseum je antický amfiteáter v centre Ríma. Je symbolom mesta a pamätníkom prvých kresťanských mučeníkov.',
                1 => 'Konajú sa tu modlitby a pobožnosti, napríklad Krížová cesta s pápežom na Veľký piatok a modlitba za mier Komunity sv. Egídia.',
                2 => self::OTHERS,
            ],
        ],
        829 => [
            'name' => 'mariánska kaplnka',
            'fix_name' => 'Mariánska kaplnka (Krásno nad Kysucou)',
            'fix_street' => null,
            'fix_postcode' => '023 02',
            'latitude' => 49.3896875,
            'longitude' => 18.8322043,
            'coordinates_source' => 'municipality',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Mariánska kaplnka je pútnické miesto v Krásne nad Kysucou.',
                1 => 'Konajú sa tu sväté omše pod holým nebom, napríklad Lumentúra, ktorou Rádio LUMEN oslávilo sviatok svojho patróna sv. Michala.',
                2 => self::OTHERS,
            ],
        ],
        830 => [
            'name' => 'Kino Lumière',
            'fix_name' => 'Kino Lumière (Bratislava)',
            'website' => 'https://www.kino-lumiere.sk',
            'body' => [
                0 => 'Kino Lumière je artové kino na Špitálskej ulici v Bratislave. Prevádzkuje ho Slovenský filmový ústav.',
                1 => 'Konajú sa tu premiéry, festivaly a besedy, napríklad predpremiéra filmu Slobodní o Silvestrovi Krčmérym a Vladimírovi Jukl.',
                2 => self::OTHERS,
            ],
        ],
        833 => [
            'name' => 'Katedrála Matky Božej ustavičnej pomoci',
            'fix_name' => 'Katedrála Matky ustavičnej pomoci (Astana)',
            'city' => 'astana',
            'country' => 'Kazachstan',
            'fix_street' => null,
            'fix_postcode' => null,
            'latitude' => 51.1282804,
            'longitude' => 71.4304708,
            'coordinates_source' => 'municipality',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Katedrála Matky ustavičnej pomoci je katolícka katedrála v Astane, hlavnom meste Kazachstanu.',
                1 => 'V septembri 2022 sa tu pápež František stretol s biskupmi, kňazmi a rehoľníkmi. Stretnutie prenášala TV LUX.',
                2 => self::OTHERS,
            ],
        ],
        835 => [
            'name' => 'Centrum celého človeka Enoia',
            'fix_name' => 'Centrum celého človeka Enoia (Považská Bystrica)',
            'body' => [
                0 => 'Centrum celého človeka Enoia v Považskej Bystrici spája psychológiu a duchovný život. Spolupracuje s Duchovným centrom Lukov dvor.',
                1 => 'Konajú sa tu kurzy a vzdelávanie, napríklad semestrálne vzdelávanie Psychológia a viera.',
                2 => self::OTHERS,
            ],
        ],
        836 => [
            'name' => 'Trojmedzie',
            'fix_name' => 'Trojmedzie (Čierne)',
            'body' => [
                0 => 'Trojmedzie je miesto pri Čiernom, kde sa stretávajú hranice Slovenska, Česka a Poľska.',
                1 => 'Každoročne sa tu koná svätá omša veriacich troch krajín s ich biskupmi.',
                2 => self::OTHERS,
            ],
        ],
        838 => [
            'name' => 'Svätyňa odhalenia',
            'fix_name' => 'Svätyňa Zvlečenia (Assisi)',
            'city' => 'assisi',
            'country' => 'Taliansko',
            'fix_street' => 'Piazza del Vescovado',
            'fix_postcode' => '06081',
            'latitude' => 43.0694663,
            'longitude' => 12.614266,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Svätyňa Zvlečenia (Santuario della Spogliazione) v Assisi pripomína miesto, kde sa sv. František pred biskupom vzdal majetku aj šiat. Patrí k nej kostol Santa Maria Maggiore a biskupský palác.',
                1 => 'V svätyni je pochovaný bl. Carlo Acutis. Prichádzajú sem pútnici z celého sveta.',
                2 => self::OTHERS,
            ],
        ],
        839 => [
            'name' => 'Puskás Aréna',
            'fix_name' => 'Puskás Aréna (Budapešť)',
            'city' => 'budapest',
            'country' => 'Maďarsko',
            'fix_street' => 'Istvánmezei út 3-5',
            'fix_postcode' => '1146',
            'latitude' => 47.503109,
            'longitude' => 19.0980509,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Puskás Aréna je národný futbalový štadión v Budapešti. Patrí k najväčším štadiónom v strednej Európe.',
                1 => 'Konajú sa tu aj veľké náboženské podujatia, napríklad modlitby chvál Csillagpont alebo záverečná omša Eucharistického kongresu 2021.',
                2 => self::OTHERS,
            ],
        ],
        840 => [
            'name' => 'Múzeum Červený Kláštor',
            'body' => [
                0 => 'Múzeum Červený Kláštor sídli v bývalom kartuziánskom kláštore v Pieninách. Expozícia predstavuje históriu kláštora a fráterov Cypriána.',
                1 => 'Konajú sa tu prehliadky, koncerty a duchovné podujatia, napríklad stretnutie s pátrom Rafaelom.',
                2 => self::OTHERS,
            ],
        ],
        841 => [
            'name' => 'kaštieli',
            'fix_name' => 'Kaštieľ (Ivanka pri Dunaji)',
            'body' => [
                0 => 'Kaštieľ v Ivanke pri Dunaji je historické šľachtické sídlo v obci pri Bratislave.',
                1 => 'Konajú sa tu vzdelávacie pobyty, napríklad pobyty o umení, teológii a bioetike Kolégia Antona Neuwirtha.',
                2 => self::OTHERS,
            ],
        ],
        843 => [
            'name' => 'Blatnohrad',
            'fix_name' => 'Blatnohrad (Zalavár)',
            'city' => 'zalavar',
            'country' => 'Maďarsko',
            'fix_postcode' => '8392',
            'latitude' => 46.6699863,
            'longitude' => 17.1565488,
            'coordinates_source' => 'municipality',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Blatnohrad (dnes Zalavár) bol sídlom panonského kniežaťa Koceľa. Pôsobili tu sv. Cyril a Metod. Na mieste hradiska je pamätník s archeologickým areálom.',
                1 => 'Konajú sa tu púte k svätým Cyrilovi a Metodovi, napríklad slovensko-maďarská púť vojenských ordinariátov.',
                2 => self::OTHERS,
            ],
        ],
        849 => [
            'name' => 'Zichyho palác',
            'fix_name' => 'Zichyho palác (Bratislava)',
            'body' => [
                0 => 'Zichyho palác je klasicistický palác na Ventúrskej ulici v Starom Meste Bratislavy. Má sálu na koncerty, konferencie a sobáše.',
                1 => 'Konajú sa tu semináre a kultúrne podujatia, napríklad vedecký seminár o pápežovi Piovi XII. a strednej Európe.',
                2 => self::OTHERS,
            ],
        ],
        851 => [
            'name' => 'Aula Pavla VI.',
            'fix_name' => 'Aula Pavla VI. (Vatikán)',
            'country' => 'Vatikán',
            'fix_street' => null,
            'fix_postcode' => '00120',
            'latitude' => 41.9008,
            'longitude' => 12.4547,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Aula Pavla VI. je audienčná sála vo Vatikáne vedľa Baziliky sv. Petra. Pojme vyše šesťtisíc ľudí.',
                1 => 'Konajú sa tu generálne audiencie a stretnutia so Svätým Otcom, napríklad audiencia ďakovnej púte Slovákov.',
                2 => self::OTHERS,
            ],
        ],
        853 => [
            'name' => 'Rajka',
            'fix_name' => 'Evanjelický kostol (Rajka)',
            'city' => 'rajka',
            'country' => 'Maďarsko',
            'fix_postcode' => '9224',
            'latitude' => 47.997068,
            'longitude' => 17.1960871,
            'coordinates_source' => 'municipality',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Rajka je maďarská obec pri hranici s Bratislavou. Žije v nej mnoho Slovákov.',
                1 => 'Konajú sa tu maďarsko-slovenské evanjelické bohoslužby.',
                2 => self::OTHERS,
            ],
        ],
        855 => [
            'name' => 'kultúrny dom',
            'fix_name' => 'Kultúrny dom Zákamenné',
            'body' => [
                0 => 'Kultúrny dom stojí na Námestí J. Vojtaššáka v Zákamennom. Biskup Ján Vojtaššák je rodákom z obce.',
                1 => 'Konajú sa tu kultúrne podujatia, napríklad predpremiéra filmu o monsterprocese s biskupom Vojtaššákom.',
                2 => self::OTHERS,
            ],
        ],
        857 => [
            'name' => 'Štúrova 56/6, Liptovský Mikuláš',
            'fix_name' => 'Liptovská knižnica G. F. Belopotockého (Liptovský Mikuláš)',
            'body' => [
                0 => 'Liptovská knižnica Gašpara Fejérpataky-Belopotockého sídli na Štúrovej ulici v Liptovskom Mikuláši.',
                1 => 'Konajú sa tu besedy, kurzy a workshopy, napríklad kurz pre hudobníkov v cirkevných zboroch.',
                2 => self::OTHERS,
            ],
        ],
        863 => [
            'name' => 'chrám Boží',
            'fix_name' => 'Evanjelický kostol (Trnava)',
            'fix_street' => 'Námestie SNP 7',
            'fix_postcode' => '917 01',
            'latitude' => 48.3733111,
            'longitude' => 17.5861432,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Evanjelický a. v. kostol stojí na Námestí SNP v Trnave. Patrí Cirkevnému zboru ECAV Trnava.',
                1 => 'Konajú sa tu služby Božie, niektoré prenášal Slovenský rozhlas.',
                2 => self::OTHERS,
            ],
        ],
        864 => [
            'name' => 'Nový evanjelický kostol (Bratislava)',
            'fix_street' => 'Legionárska',
            'fix_postcode' => '811 07',
            'body' => [
                0 => 'Nový evanjelický kostol stojí na Legionárskej ulici v Bratislave. Patrí Cirkevnému zboru ECAV Bratislava.',
                1 => 'Konajú sa tu služby Božie a ekumenické bohoslužby, napríklad v týždni modlitieb za jednotu kresťanov.',
                2 => self::OTHERS,
            ],
        ],
        867 => [
            'name' => 'Pútnický kostol',
            'fix_village_id' => 80,
        ],
        868 => [
            'name' => 'Kostol sv. Štefana Kráľa',
            'fix_name' => 'Kostol sv. Štefana Kráľa (Trenčín)',
            'body' => [
                0 => 'Kostol sv. Štefana Kráľa je moderný farský kostol na sídlisku Juh v Trenčíne.',
                1 => 'Konajú sa tu sväté omše, niektoré prenášalo Rádio LUMEN.',
                2 => self::OTHERS,
            ],
        ],
        871 => [
            'name' => 'Brusel',
            'city' => 'brusel',
            'country' => 'Belgicko',
            'latitude' => 50.8467,
            'longitude' => 4.3525,
            'coordinates_source' => 'municipality',
            'fix_coordinates' => true,
        ],
        872 => [
            'name' => 'hotel SOREA Hutník I.',
            'fix_name' => 'Hotel SOREA Hutník I (Tatranské Matliare)',
            'body' => [
                0 => 'Hotel SOREA Hutník I stojí v Tatranských Matliaroch vo Vysokých Tatrách. Ponúka ubytovanie a sály na konferencie.',
                1 => 'Konajú sa tu konferencie a pobyty, napríklad rodinná konferencia Rodinného spoločenstva ECAV.',
                2 => self::OTHERS,
            ],
        ],
        875 => [
            'name' => 'evanjelický cintorín',
            'fix_name' => 'Evanjelický cintorín (Košice)',
            'fix_street' => null,
            'fix_postcode' => null,
            'latitude' => 48.7164,
            'longitude' => 21.2611,
            'coordinates_source' => 'municipality',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Evanjelický cintorín v Košiciach je historický cintorín s hrobmi významných košických rodín.',
                1 => 'Konajú sa tu prehliadky a spomienky, napríklad prezentácia príbehov cintorína v rámci Dní európskeho kultúrneho dedičstva.',
                2 => self::OTHERS,
            ],
        ],
        878 => [
            'name' => 'Divadlo Jonáša Záborského',
            'fix_name' => 'Divadlo Jonáša Záborského (Prešov)',
            'body' => [
                0 => 'Divadlo Jonáša Záborského je profesionálne divadlo na Námestí legionárov v Prešove. Hrá činohru, muzikály a balet.',
                1 => 'Konajú sa tu predstavenia a veľké stretnutia, napríklad Dištriktuálny deň Východného dištriktu ECAV.',
                2 => self::OTHERS,
            ],
        ],
        879 => [
            'name' => 'pole za Šaštínom',
            'fix_name' => 'Areál pri Národnej svätyni (Šaštín)',
            'fix_postcode' => '908 41',
            'latitude' => 48.6389247,
            'longitude' => 17.1429925,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Areál pri Bazilike Sedembolestnej Panny Márie v Šaštíne je miestom veľkých púti k patrónke Slovenska.',
                1 => 'V septembri 2021 tu slávil svätú omšu pápež František. Púte sa tu konajú každý rok okolo 15. septembra.',
                2 => self::OTHERS,
            ],
        ],
        880 => [
            'name' => 'jedáleň Strednej odbornej školy vinársko-ovocinárskej',
            'fix_name' => 'Stredná vinársko-ovocinárska škola (Modra)',
            'fix_street' => 'Kostolná 162/3',
            'fix_postcode' => '900 01',
            'latitude' => 48.333597,
            'longitude' => 17.3140995,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Stredná vinársko-ovocinárska škola stojí na Kostolnej ulici v Modre. Je najstaršou vinárskou školou na Slovensku.',
                1 => 'V jej jedálni sa konajú stretnutia, napríklad seminár pre neordinovaných Západného dištriktu ECAV.',
                2 => self::OTHERS,
            ],
        ],
        883 => [
            'name' => 'Hotel Priehrada',
            'fix_name' => 'Hotel Priehrada (Dedinky)',
            'body' => [
                0 => 'Hotel Priehrada stojí pri vodnej nádrži Palcmanská Maša v Dedinkách v Slovenskom raji.',
                1 => 'Konajú sa tu stretnutia a pobyty, napríklad Deň farárskych rodín Východného dištriktu ECAV.',
                2 => self::OTHERS,
            ],
        ],
        886 => [
            'name' => 'bazilika sv. Apolinára',
            'fix_name' => 'Bazilika sv. Apolinára (Rím)',
            'country' => 'Taliansko',
            'fix_street' => 'Piazza di Sant\'Apollinare',
            'fix_postcode' => '00186',
            'latitude' => 41.9009818,
            'longitude' => 12.4736816,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Bazilika sv. Apolinára stojí pri Piazza Navona v Ríme. Patrí k Pápežskej univerzite Svätého Kríža.',
                1 => 'Konajú sa tu sväté omše a kňazské vysviacky, napríklad vysviacka slovenského diakona Branislava Borovského.',
                2 => self::OTHERS,
            ],
        ],
        887 => [
            'name' => 'Arcidiecézne centrum pre rodinu',
            'fix_name' => 'Arcidiecézne centrum pre rodinu (Košice)',
            'body' => [
                0 => 'Arcidiecézne centrum pre rodinu je pastoračné centrum Košickej arcidiecézy na Podjavorinskej ulici v Košiciach.',
                1 => 'Pripravuje kurzy a stretnutia pre manželov a rodiny.',
                2 => self::OTHERS,
            ],
        ],
        891 => [
            'name' => 'Kostol Panny Márie Ružencovej',
            'fix_name' => 'Kostol Panny Márie Ružencovej (Černová)',
            'fix_street' => 'Černovských martýrov 31',
            'fix_postcode' => '034 01',
            'latitude' => 49.0912525,
            'longitude' => 19.2573126,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Kostol Panny Márie Ružencovej v Černovej pri Ružomberku dal postaviť Andrej Hlinka. Pri jeho vysviacke v roku 1907 zahynulo pätnásť ľudí, čo vošlo do dejín ako černovská tragédia.',
                1 => 'Konajú sa tu sväté omše a pietne spomienky, napríklad spomienka na pátra Vendelína Javorku.',
                2 => self::OTHERS,
            ],
        ],
        892 => [
            'name' => 'CZ ECAV Modra-Kráľová',
            'fix_name' => 'Evanjelický kostol (Modra-Kráľová)',
            'body' => [
                0 => 'Evanjelický kostol v Modre-Kráľovej patrí Cirkevnému zboru ECAV Modra-Kráľová.',
                1 => 'Konajú sa tu služby Božie, niektoré prenášala televízia.',
                2 => self::OTHERS,
            ],
        ],
        893 => [
            'name' => 'kaplnka Arcibiskupského úradu',
            'fix_name' => 'Kaplnka Arcibiskupského úradu (Bratislava)',
            'fix_street' => 'Kapitulská 9',
            'fix_postcode' => '811 01',
            'latitude' => 48.1435534,
            'longitude' => 17.10509,
            'coordinates_source' => 'address',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Kaplnka Arcibiskupského úradu je v budove Bratislavskej arcidiecézy na Kapitulskej ulici pri Dóme sv. Martina.',
                1 => 'Slúžia sa tu sväté omše bratislavského arcibiskupa, napríklad omša za obete pandémie.',
                2 => self::OTHERS,
            ],
        ],
    ];

    /** Zdroj → cieľ: ten istý priestor pod inými názvami. */
    private const MERGES = [
        869 => [
            0 => 88,
            1 => 'Lukov dvor',
            2 => 'Duchovné centrum Lukov dvor (Nitra)',
        ],
        884 => [
            0 => 60,
            1 => 'augustiniánsky kláštor',
            2 => 'Kláštor sv. Augustína (Košice)',
        ],
        882 => [
            0 => 41,
            1 => 'Nábrežie Oravskej priehrady',
            2 => 'Amfiteáter Námestovo',
        ],
        876 => [
            0 => 16,
            1 => 'pútnické miesto KRIVÁ',
            2 => 'Krivá (rodisko bl. Zdenky Schelingovej)',
        ],
    ];

    public function up(): void
    {
        foreach (self::VENUES as $id => $data) {
            $venue = Venue::query()->find($id);

            if (! $venue || ($venue->name !== $data['name'] && $venue->name !== ($data['fix_name'] ?? null))) {
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
