<?php

use App\Enums\ModelStatus;
use App\Models\Venue;
use App\Services\Resources\ResourceMerger;
use App\Services\SystemLog\Recorder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tretia dávka miest konania (november 2026): ďalších 80 nearchivovaných miest
 * v poradí (#145 – #918). Veľkú časť tvoria zástupné miesta celých obcí
 * („Senec", „Nitra", „Gelnica") — tie sa podľa doterajšej zásady nepopisujú.
 * Popis, adresu a súradnice dostanú konkrétne miesta; miesta vzniknuté
 * z adries či viet dostanú skutočný názov.
 *
 * Opravy chybných údajov:
 *  - #731 Bazilika Panny Márie Guadalupskej je v Mexiku, nie v Nitre,
 *  - #152 Mariazell je pútnické miesto v Rakúsku, nie kostol v Nitre,
 *  - #155 Kostol sv. Pier Giorgia Frassatiho je v Turíne, nie v obci Turie,
 *  - #145 Sála kardinála Berana je v Prahe (Česko), nie v obci Praha pri Lučenci,
 *  - #735 Modrý kostol bol v Svätom Jure, #804 Burianova medzierka v Kežmarku,
 *  - #720 farský úrad v Rade mal adresu a súradnice z Nitry,
 *  - #729 „Univerzita Komenského" je v skutočnosti Dom Matice slovenskej v Štúrove,
 *  - #168 Košice mali súradnice v Turecku,
 *  - #807, #726, #724, #734 mali popis mesta z Wikipédie.
 *
 * Duplikáty sa zlúčia: #158 → #104 (Bazilika v Šaštíne), #888 → #959
 * (Online – Zoom), #726 → #806 (Misijný dom na Kalvárii), #805 → #969,
 * #732 → #1009 (pallotíni v Spišskej Novej Vsi).
 *
 * #832 je tiež bazilika v Šaštíne — zlúčenie s #104 blokuje priložený súbor,
 * preto sa len opraví.
 *
 * Archivované miesta sa nemenia. Popis sa prepíše len vtedy, keď ešte nemá tri
 * odseky; ulica, PSČ a web sa dopĺňajú do prázdneho poľa (okrem `fix_*`).
 * Miesto musí sedieť ID aj názvom.
 */
return new class extends Migration
{
    private const OTHERS = 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.';

    /** Zahraničné mestá, ktoré v číselníku obcí ešte nie sú (vzor: Rím). */
    private const CITIES = [
        'mexiko' => ['name' => 'Mexiko', 'zip' => '07050'],
        'mariazell' => ['name' => 'Mariazell', 'zip' => '8630'],
        'turin' => ['name' => 'Turín', 'zip' => '10151'],
        'praha-cesko' => ['name' => 'Praha (Česko)', 'zip' => '110 00'],
    ];

    /** @var array<int, array<string, mixed>> */
    private const VENUES = [
        832 => ['name' => 'Slávnosť Sedembolestnej Panny Márie', 'fix_name' => 'Bazilika Sedembolestnej Panny Márie (Šaštín)', 'fix_street' => 'Kláštorné námestie 1294/9', 'fix_postcode' => '908 41', 'fix_website' => null,
            'latitude' => 48.638, 'longitude' => 17.1439, 'coordinates_source' => 'municipality', 'body' => [
                'Bazilika Sedembolestnej Panny Márie v Šaštíne je národnou svätyňou Slovenska. Sedembolestná Panna Mária je patrónkou Slovenska, jej sviatok sa slávi 15. septembra. Baziliku spravujú saleziáni don Bosca.',
                'Konajú sa tu národné púte, púte ružencových bratstiev, Púť zaľúbených aj púte rozvedených. Priame prenosy prinášajú TV LUX a Rádio Lumen.',
                self::OTHERS,
            ]],
        823 => ['name' => 'Bazilika svätého Mikuláša (Trnava)', 'body' => [
            'Bazilika sv. Mikuláša je gotický farský kostol v Trnave a bazilika minor. Uchováva milostivý obraz Trnavskej Panny Márie. Stojí na Námestí sv. Mikuláša v historickom centre.',
            'Každý rok sa tu koná Trnavská novéna k Trnavskej Panne Márii, ktorá patrí k duchovným vrcholom roka v meste. Konajú sa tu aj koncerty chrámovej hudby.',
            self::OTHERS,
        ]],
        807 => ['name' => 'Farská ulica č. 12, Trenčín', 'fix_name' => 'Centrum pre rodinu (Trenčín)', 'fix_street' => 'Farská 12', 'postcode' => '911 01', 'fix_website' => null,
            'latitude' => 48.8939785, 'longitude' => 18.041562, 'coordinates_source' => 'address', 'fix_coordinates' => true, 'body' => [
                'Centrum pre rodinu v Trenčíne sídli na Farskej ulici v historickom centre mesta. Pripravuje programy pre manželov, rodiny a slobodných. Spolupracuje s farnosťou.',
                'Konajú sa tu stretnutia, kurzy a spoločenské večery, napríklad speed dating katRande pre slobodných katolíkov. Na podujatia sa prihlasuje vopred.',
                self::OTHERS,
            ]],
        806 => ['name' => 'Auly Misijného domu Matky Božej na Kalvárii', 'fix_name' => 'Misijný dom Matky Božej na Kalvárii (Nitra)', 'street' => 'Kalvária 3', 'postcode' => '949 01',
            'latitude' => 48.2982161, 'longitude' => 18.0916092, 'coordinates_source' => 'venue', 'fix_coordinates' => true, 'body' => [
                'Misijný dom Matky Božej stojí na Kalvárii v Nitre. Je centrom slovenskej a českej provincie misionárov verbistov. V dome sú aule a priestory na stretnutia.',
                'Konajú sa tu výstavy, stretnutia a formačné programy, napríklad výstava Tajomstvo zoborského kláštora či speed dating katRande. Verbisti tu pripravujú aj program Misionár na čas.',
                self::OTHERS,
            ]],
        804 => ['name' => 'Burianova medzierka, Žilina (pri katedrále Najsvätejšej Trojice)', 'fix_name' => 'Burianova medzierka (Žilina)', 'fix_village_id' => 4194,
            'fix_street' => 'Burianova medzierka', 'fix_postcode' => '010 01', 'latitude' => 49.223694, 'longitude' => 18.7403761, 'coordinates_source' => 'address', 'fix_coordinates' => true, 'body' => [
                'Burianova medzierka je pešia ulička v historickom centre Žiliny pri Katedrále Najsvätejšej Trojice a Burianovej veži. Sú na nej kaviarne a obchodíky.',
                'V kaviarni Café Kupé na Burianovej medzierke sa konal napríklad speed dating katRande pre slobodných. Ulička je vychodiskom k námestiu.',
                self::OTHERS,
            ]],
        787 => ['name' => 'Kláštorná 123, Okoličné, Liptovský Mikuláš', 'fix_name' => 'Komunita Blahoslavenstiev (Okoličné)', 'fix_street' => 'Kláštorná 123', 'postcode' => '031 04',
            'latitude' => 49.0726354, 'longitude' => 19.6505978, 'coordinates_source' => 'venue', 'fix_coordinates' => true, 'body' => [
                'Komunita Blahoslavenstiev žije v historickom kláštore pri Kostole sv. Petra z Alkantary v Okoličnom, mestskej časti Liptovského Mikuláša. Je to katolícka komunita bratov, sestier a rodín. Ponúka ticho a modlitbu.',
                'Konajú sa tu duchovné obnovy, napríklad obnova pre mladých Znovu ťa vybudujem. Hostia sa zapájajú do modlitieb komunity.',
                self::OTHERS,
            ]],
        760 => ['name' => 'Mestská hala', 'fix_name' => 'Mestská hala (Prešov)', 'body' => [
            'Mestská hala je športová hala na Ulici Jána Pavla II. v Prešove. V jej areáli slávil pápež František v septembri 2021 božskú liturgiu. Hostí športové aj spoločenské podujatia.',
            'Na mieste pápežovej návštevy sa pripravuje socha pápeža Františka, ku ktorej poklepali základný kameň pri piatom výročí návštevy. Konajú sa tu aj koncerty a veľké stretnutia.',
            self::OTHERS,
        ]],
        735 => ['name' => 'Farský kostol sv. Alžbety (Modrý kostol) v Bratislave', 'fix_village_id' => 242, 'fix_street' => 'Bezručova 2', 'fix_postcode' => '811 09',
            'latitude' => 48.1433603, 'longitude' => 17.1167414, 'coordinates_source' => 'venue', 'fix_coordinates' => true, 'body' => [
                'Kostol sv. Alžbety, známy ako Modrý kostol, stojí na Bezručovej ulici v Starom Meste v Bratislave. Postavili ho v secesnom štýle podľa návrhu Ödöna Lechnera. Je zasvätený sv. Alžbete Uhorskej.',
                'Konajú sa tu bohoslužby, svadby a slávnosti, napríklad sviatok sv. Terézie od Dieťaťa Ježiša so Svetským rádom bosých karmelitánov. Kostol je obľúbeným cieľom turistov.',
                self::OTHERS,
            ]],
        734 => ['name' => 'Medzilaborce', 'fix_name' => 'Chrám sv. Bazila Veľkého (Medzilaborce)', 'postcode' => '068 01', 'body' => [
            'Chrám sv. Bazila Veľkého je gréckokatolícky chrám v Medzilaborciach. Stojí v centre mesta. Slúži gréckokatolíckej farnosti.',
            'Konajú sa tu liturgie a konferencie, napríklad konferencia katolíckej komunity Cor et Lumen Christi. Konferencie trvajú zvyčajne dva dni.',
            self::OTHERS,
        ]],
        731 => ['name' => 'Bazilika Panny Márie Guadalupskej', 'fix_name' => 'Bazilika Panny Márie Guadalupskej (Mexiko)', 'city' => 'mexiko', 'fix_postcode' => '07050', 'country' => 'Mexiko',
            'latitude' => 19.4848583, 'longitude' => -99.1178571, 'coordinates_source' => 'venue', 'fix_coordinates' => true, 'body' => [
                'Bazilika Panny Márie Guadalupskej v Mexiku je najnavštevovanejším mariánskym pútnickým miestom sveta. Uchováva obraz Panny Márie, ktorá sa v roku 1531 zjavila sv. Juanovi Diegovi. Novú baziliku posvätili v roku 1976.',
                'Pápež Lev XIV. vyhlásil jubilejný rok v Guadalupe k 50. výročiu posvätenia novej baziliky. Pútnici prichádzajú najmä na sviatok 12. decembra.',
                self::OTHERS,
            ]],
        730 => ['name' => 'Park sv. Cyrila a Metoda', 'body' => [
            'Park sv. Cyrila a Metoda je verejný park v obci Kapušany pri Prešove. Slúži na oddych aj obecné podujatia.',
            'Konal sa tu napríklad duchovný festival Teba viac s hudbou, svedectvami a modlitbou. Festival je otvorený pre všetkých.',
            self::OTHERS,
        ]],
        729 => ['name' => 'Univerzita Komenského v Bratislave', 'fix_name' => 'Dom Matice slovenskej (Štúrovo)', 'fix_street' => null, 'fix_postcode' => '943 01', 'fix_website' => null,
            'latitude' => 47.7986, 'longitude' => 18.7183, 'coordinates_source' => 'municipality', 'fix_coordinates' => true, 'body' => [
                'Dom Matice slovenskej v Štúrove je kultúrne centrum Matice slovenskej na juhu Slovenska. Slúži miestnym odborom a verejnosti.',
                'Konajú sa tu výstavy, prednášky a vernisáže, napríklad výstava Obrazy svätých Cyrila a Metoda na ilustráciách, ikonách a poštových známkach.',
                self::OTHERS,
            ]],
        727 => ['name' => 'Košice – mestská časť Sídlisko KVP', 'fix_name' => 'Kostol Božieho milosrdenstva (Košice-KVP)', 'fix_street' => 'Trieda KVP 6', 'postcode' => '040 23', 'fix_website' => null, 'body' => [
            'Kostol Božieho milosrdenstva na Triede KVP v Košiciach je diecéznou svätyňou Božieho milosrdenstva Košickej arcidiecézy. Slúži farnosti sídliska KVP. Patrí k veľkým moderným kostolom mesta.',
            'Konajú sa tu bohoslužby a pravidelné sväté omše s chválami, ktoré pripravuje farnosť KVP. Prichádzajú sem aj pútnici.',
            self::OTHERS,
        ]],
        725 => ['name' => 'farský kostol', 'fix_name' => 'Kostol sv. Anny (Šenkvice)', 'fix_street' => 'Chorvátska 52', 'latitude' => 48.2977757, 'longitude' => 17.3449195,
            'coordinates_source' => 'venue', 'fix_coordinates' => true, 'body' => [
                'Kostol sv. Anny je farský kostol v Šenkviciach v okrese Pezinok. Stojí na Chorvátskej ulici. Slúži Farnosti Šenkvice.',
                'Konajú sa tu bohoslužby a spomienky na Božieho služobníka kňaza Alfonza Paulena, ktorého si Šenkvice každoročne pripomínajú. Na spomienky prichádzajú veriaci z okolia.',
                self::OTHERS,
            ]],
        724 => ['name' => 'priestor medzi Národným divadlom Košice a Spievajúcou fontánou', 'fix_name' => 'Spievajúca fontána (Košice)', 'fix_street' => 'Hlavná', 'fix_postcode' => '040 01', 'fix_website' => null,
            'latitude' => 48.7212006, 'longitude' => 21.2576052, 'coordinates_source' => 'venue', 'fix_coordinates' => true, 'body' => [
                'Spievajúca fontána stojí na Hlavnej ulici v Košiciach pred Štátnym divadlom. Priestor medzi divadlom a fontánou je obľúbeným miestom stretnutí. Večer fontánu sprevádza hudba a svetlo.',
                'Konajú sa tu mestské slávnosti a koncerty, napríklad galakoncert Ďakujem, pri ktorom mesto ocenilo saleziánov z Luníka IX.',
                self::OTHERS,
            ]],
        722 => ['name' => 'Domov bielych hláv', 'body' => [
            'Domov bielych hláv v Starej Turej je zariadenie pre seniorov, ktoré založila spisovateľka Kristína Royová. Patrí k dielu, ktoré po nej zostalo v meste. Spomienky na ňu pripravuje aj občianske združenie Sluha.',
            'Konajú sa tu spomienkové slávnosti, napríklad novembrové oslavy pri výročí Kristíny Royovej. Programy spájajú bohoslužbu a kultúrny program.',
            self::OTHERS,
        ]],
        720 => ['name' => 'farský úrad', 'fix_name' => 'Farský úrad Rad', 'fix_street' => null, 'fix_postcode' => '076 37', 'latitude' => 48.4626416, 'longitude' => 21.8581125,
            'coordinates_source' => 'venue', 'fix_coordinates' => true, 'body' => [
                'Farnosť Rad leží v okrese Trebišov. Farským kostolom je Kostol Najsvätejšej Trojice. Obec je spojená s pamiatkou minoritských mučeníkov.',
                'Každoročne sa tu koná púť k pamiatke minoritských mučeníkov, ktorí zomreli pri misijnej službe v 17. storočí. Púť spája svätú omšu a procesiu.',
                self::OTHERS,
            ]],
        718 => ['name' => 'evanjelický kostol', 'fix_name' => 'Evanjelický kostol (Hlboké)', 'body' => [
            'Evanjelický kostol v Hlbokom na Záhorí patrí cirkevnému zboru ECAV Hlboké. V Hlbokom pôsobil Jozef Miloslav Hurban a narodil sa tu jeho syn Svetozár Hurban Vajanský. Obec je spätá so štúrovcami.',
            'Konajú sa tu služby Božie a spomienkové slávnosti, napríklad spomienka pri 110. výročí úmrtia Svetozára Hurbana Vajanského. Hlboké je aj miestom, kde sa zrodila spisovná slovenčina štúrovcov.',
            self::OTHERS,
        ]],
        716 => ['name' => 'Kostol Notre Dame', 'fix_name' => 'Kostol Notre Dame (Trenčín)', 'body' => [
            'Kostol Notre Dame v Trenčíne patrí kongregácii Školských sestier de Notre Dame. Stojí pri ich kláštore a škole. Slúži sestrám, študentom aj veriacim mesta.',
            'Konajú sa tu bohoslužby a školské slávnosti. Sestry pripravujú aj duchovné obnovy.',
            self::OTHERS,
        ]],
        715 => ['name' => 'Rybné námestie', 'fix_name' => 'Jezuitský kostol Najsvätejšieho Spasiteľa (Bratislava)', 'fix_street' => 'Františkánske námestie 5', 'fix_postcode' => '811 01',
            'latitude' => 48.1440618, 'longitude' => 17.1087471, 'coordinates_source' => 'venue', 'fix_coordinates' => true, 'body' => [
                'Kostol Najsvätejšieho Spasiteľa je jezuitský kostol na Františkánskom námestí pri Hlavnom námestí v Bratislave. Pôvodne evanjelický kostol prevzali jezuiti v 17. storočí. Pri kostole sídli Slovenská provincia jezuitov.',
                'Konajú sa tu bohoslužby, duchovné obnovy a modlitby, napríklad Popoludnie s Ignácom a modlitby a spevy z Taizé. Spieva tu zbor Chorus Salvatoris.',
                self::OTHERS,
            ]],
        713 => ['name' => 'Kostol sv. Štefana Uhorského', 'fix_name' => 'Kostol sv. Štefana Uhorského (Piešťany)', 'body' => [
            'Kostol sv. Štefana Uhorského je rímskokatolícky kostol na Štefánikovej ulici v Piešťanoch. Je zasvätený prvému uhorskému kráľovi sv. Štefanovi. Slúži veriacim mesta.',
            'Konajú sa tu bohoslužby a slávnosti, napríklad sväté omše spojené s celoslovenskými iniciatívami za život. Kostol je otvorený aj kúpeľným hosťom.',
            self::OTHERS,
        ]],
        712 => ['name' => 'Múzeum holokaustu v Seredi', 'body' => [
            'Múzeum holokaustu v Seredi je súčasťou Slovenského národného múzea – Múzea židovskej kultúry. Vzniklo v areáli bývalého pracovného a koncentračného tábora. Je jediným múzeom holokaustu na Slovensku.',
            'Konajú sa tu pietne spomienky a vzdelávacie programy, napríklad Pamätný deň obetí holokaustu za účasti preživších. Múzeum navštevujú najmä školy.',
            self::OTHERS,
        ]],
        711 => ['name' => 'Jesenského 2, Bratislava', 'fix_name' => 'Kostol Nanebovzatia Panny Márie – Notre Dame (Bratislava)', 'fix_street' => 'Jesenského 2', 'postcode' => '811 01', 'body' => [
            'Kostol Nanebovzatia Panny Márie, nazývaný Notre Dame, stojí na Jesenského ulici v Bratislave. Patrí k areálu Školských sestier de Notre Dame. Je to barokový kostol.',
            'Konajú sa tu bohoslužby a slávnosti rôznych spoločenstiev, napríklad karmelitánske sväté omše s prijímaním škapuliara či sväté omše za obete samovrážd a ich blízkych.',
            self::OTHERS,
        ]],
        710 => ['name' => 'Ulica J. M. Hurbana (Žilina)', 'fix_name' => 'Katolícky dom – kláštor Školských sestier sv. Františka (Žilina)', 'fix_street' => 'J. M. Hurbana 44', 'postcode' => '010 01',
            'latitude' => 49.2260062, 'longitude' => 18.7357412, 'coordinates_source' => 'address', 'fix_coordinates' => true, 'body' => [
                'Katolícky dom na Ulici J. M. Hurbana v Žiline je kláštorom Školských sestier sv. Františka. V budove sídli aj Gymnázium sv. Františka z Assisi.',
                'Sestry tu pripravujú duchovné obnovy, napríklad obnovy pre slobodných od 25 rokov. Obnovy trvajú zvyčajne celý deň.',
                self::OTHERS,
            ]],
        709 => ['name' => 'Základná škola Sečovská Polianka', 'body' => [
            'Základná škola v Sečovskej Polianke je obecná škola v okrese Vranov nad Topľou. Sídli na Školskej ulici. Slúži deťom z obce a okolia.',
            'Konajú sa tu aj obecné a charitatívne podujatia, napríklad beh a rodinné olympijské hry pre školu v Zambii v rámci programu Nasýťme školu.',
            self::OTHERS,
        ]],
        156 => ['name' => 'cintorín v Dubovciach', 'body' => [
            'Na cintoríne v Dubovciach pri Skalici je pochovaný blahoslavený Ján Havlík, mučeník komunistického prenasledovania. Jeho hrob je cieľom pútnikov. Havlík bol blahorečený v roku 2024.',
            'Koná sa tu Púť vernosti na počesť bl. Jána Havlíka, ktorú pripravuje Hnutie Modlitby za kňazov. Púť spája svätú omšu a modlitbu pri hrobe.',
            self::OTHERS,
        ]],
        155 => ['name' => 'Kostol svätého Pier Giorgia Frassatiho', 'fix_name' => 'Kostol sv. Pier Giorgia Frassatiho (Turín)', 'city' => 'turin', 'fix_postcode' => '10151', 'country' => 'Taliansko',
            'body' => [
                'Kostol sv. Pier Giorgia Frassatiho stojí v Turíne na Via Pietro Cossa. Je zasvätený mladému turínskemu svätcovi, ktorý pomáhal chudobným. Frassatiho svätorečili v roku 2025.',
                'Turínska arcidiecéza tu slávi výročia kanonizácie Pier Giorgia Frassatiho. Prichádzajú sem mladí pútnici z celého sveta.',
                self::OTHERS,
            ]],
        153 => ['name' => 'Nám. padlých hrdinov 7', 'fix_name' => 'Park za kaštieľom (Ivanka pri Dunaji)', 'fix_street' => 'Námestie padlých hrdinov 7', 'postcode' => '900 28',
            'latitude' => 48.19063, 'longitude' => 17.2587549, 'coordinates_source' => 'address', 'fix_coordinates' => true, 'body' => [
                'Park za kaštieľom v Ivanke pri Dunaji je verejný priestor na Námestí padlých hrdinov. V kaštieli sídli Kolégium Antona Neuwirtha.',
                'Konajú sa tu festivaly a podujatia pod holým nebom, napríklad Festival Zjavenie s hudbou a programom pre rodiny.',
                self::OTHERS,
            ]],
        152 => ['name' => 'Mariazell', 'fix_name' => 'Bazilika Mariazell (Rakúsko)', 'city' => 'mariazell', 'fix_postcode' => '8630', 'country' => 'Rakúsko',
            'latitude' => 47.772808, 'longitude' => 15.318868, 'coordinates_source' => 'venue', 'fix_coordinates' => true, 'body' => [
                'Bazilika Mariazell v rakúskom Štajersku je najvýznamnejším mariánskym pútnickým miestom strednej Európy. Uctieva sa tu milostivá soška Panny Márie. Pútnická tradícia siaha do 12. storočia.',
                'Prichádzajú sem púte zo Slovenska, napríklad jubilejná púť evanjelizačného spoločenstva sv. archanjela Rafaela. Pútnici často putujú aj pešo.',
                self::OTHERS,
            ]],
        151 => ['name' => 'Dlhé diely, Pribišova ul. 49, Bratislava', 'fix_name' => 'Kostol Narodenia Panny Márie (Dlhé diely)', 'fix_street' => 'Pribišova 49', 'postcode' => '841 05', 'body' => [
            'Kostol Narodenia Panny Márie je moderný kostol na sídlisku Dlhé diely v Bratislave-Karlovej Vsi. Stojí na Pribišovej ulici. Slúži farnosti Dlhé diely.',
            'Konajú sa tu bohoslužby a duchovné obnovy, napríklad obnovy Planiem Božou láskou a Chcem horieť v Duchu lásky. Obnovy trvajú zvyčajne popoludnie.',
            self::OTHERS,
        ]],
        150 => ['name' => 'Štrbské pleso', 'body' => [
            'Štrbské Pleso je horská osada a jazero vo Vysokých Tatrách. Je východiskom turistických chodníkov a Tatranskej magistrály. Leží v meste Vysoké Tatry.',
            'Koná sa tu napríklad púť Magistrálou cez farnosť Vysoké Tatry, ktorá spája turistiku a modlitbu. V osade je aj kostol.',
            self::OTHERS,
        ]],
        148 => ['name' => 'Bazilika Navštívenia Panny Márie (Staré Hory)', 'body' => [
            'Bazilika Navštívenia Panny Márie v Starých Horách je hlavným mariánskym pútnickým miestom Banskobystrickej diecézy. Bazilikou minor je od roku 1990. Nad obcou je aj prameň Studničky.',
            'Konajú sa tu púte, Deň modlitieb a spoločenstva Rádia Lumen, stretnutie Škapuliarskej rodiny a festival EMFEST. Púte trvajú zvyčajne celý deň.',
            self::OTHERS,
        ]],
        145 => ['name' => 'Sála kardinála Berana', 'fix_name' => 'Sála kardinála Berana (Praha)', 'city' => 'praha-cesko', 'country' => 'Česko',
            'latitude' => 50.0875, 'longitude' => 14.4213, 'coordinates_source' => 'municipality', 'fix_coordinates' => true, 'body' => [
                'Sála kardinála Berana je konferenčná sála v Prahe. Nesie meno pražského arcibiskupa kardinála Josefa Berana, väzňa nacizmu aj komunizmu.',
                'Konajú sa tu konferencie a prednášky, napríklad konferencia o proticirkevnej propagande počas nacizmu a komunizmu, ktorú pripravil Pamätník Terezín.',
                self::OTHERS,
            ]],
        168 => ['name' => 'Košice', 'latitude' => 48.7172272, 'longitude' => 21.2496774, 'coordinates_source' => 'municipality', 'fix_coordinates' => true],
    ];

    /** Zdroj → cieľ: ten istý priestor pod inými názvami. */
    private const MERGES = [
        158 => [104, 'národná mariánska svätyňa', 'Bazilika Sedembolestnej Panny Márie'],
        888 => [959, 'ZOOM', 'Online (Zoom)'],
        726 => [806, 'Kalvária 3, Nitra', 'Misijný dom Matky Božej na Kalvárii (Nitra)'],
        805 => [969, 'Partizánske, Nám. SNP 949/20', 'Kostol Božského Srdca Ježišovho (Partizánske)'],
        732 => [1009, 'Centrum Božieho milosrdenstva', 'Dom pútnika pallotínov (Spišská Nová Ves)'],
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
