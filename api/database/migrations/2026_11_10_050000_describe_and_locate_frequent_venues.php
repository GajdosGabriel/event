<?php

use App\Models\Venue;
use App\Services\Resources\ResourceMerger;
use App\Services\SystemLog\Recorder;
use Illuminate\Database\Migrations\Migration;

/**
 * Najpoužívanejšie miesta konania (november 2026): popis v troch odsekoch
 * (čo je to za miesto, čo sa tu koná, pre koho), ulica a presné súradnice
 * z Nominatim/OpenStreetMap.
 *
 * Zástupné miesta celých miest („Bratislava", „Celé Slovensko") a záznamy,
 * ktoré sú len adresou či nezmyslom („ZOOM", „Dvojka"), sa nepopisujú.
 *
 * Opravy chybných údajov:
 *  - #1045 Katedrála sv. Jána Krstiteľa je gréckokatolícka katedrála v Prešove,
 *    nie v Trnave (všetky jej akcie sú v Prešove),
 *  - #918 Skalné sanktuárium Božieho milosrdenstva je na hore Butkov v Ladcoch,
 *  - #890 Kostol sv. Štefana (kapucíni) stojí na Župnom námestí, nie na Bezručovej
 *    — odtiaľ zdieľali súradnice štyri rôzne kostoly,
 *  - #733 Kostol sv. Vincenta de Paul mal popis o svätcovi a web galérie,
 *  - #736 je duplikát #4 (kostol minoritov sv. Františka v Karlovej Vsi).
 *
 * Popis sa prepíše len vtedy, keď ešte nemá tri odseky; ulica a web sa
 * dopĺňajú do prázdneho poľa (okrem `fix_*`). Miesto musí sedieť ID aj názvom.
 */
return new class extends Migration
{
    /** @var array<int, array<string, mixed>> */
    private const VENUES = [
        4 => [
            'name' => 'kostol sv. Františka Minoriti',
            'street' => 'Námestie sv. Františka 4',
            'body' => [
                0 => 'Kostol sv. Františka z Assisi v Karlovej Vsi spravujú bratia minoriti. Nachádza sa na Námestí sv. Františka v Bratislave. Pri kostole pôsobí komunita minoritov.',
                1 => 'Konajú sa tu celonočné adorácie, Piatky so sv. Františkom a oslavy františkánskych výročí, napríklad 800. výročia smrti sv. Františka. Kostol je aj východiskom pútí a stretnutí. Program je otvorený pre všetkých.',
                2 => 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.',
            ],
        ],
        18 => [
            'name' => 'Dom Xaver',
            'street' => 'Banská 28',
            'latitude' => 48.6689734,
            'longitude' => 19.1224325,
            'coordinates_source' => 'address',
            'postcode' => '976 32',
            'body' => [
                0 => 'Dom Xaver je exercičný a pastoračný dom v Badíne pri Banskej Bystrici. Nachádza sa na Banskej ulici. Slúži ako miesto duchovných cvičení, konferencií a stretnutí.',
                1 => 'Konajú sa tu plenárne zasadnutia Konferencie biskupov Slovenska, Celoslovenský liturgický deň, ekumenické obnovy aj misijné kurzy. Prichádzajú sem veriaci z celého Slovenska. Dom ponúka ubytovanie aj priestory na stretnutia.',
                2 => 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.',
            ],
        ],
        21 => [
            'name' => 'evanjelický kostol ap. Petra a Pavla',
            'street' => 'Dolná 5',
            'latitude' => 48.3325956,
            'longitude' => 17.3123441,
            'coordinates_source' => 'address',
            'postcode' => '900 01',
            'body' => [
                0 => 'Evanjelický kostol apoštolov Petra a Pavla stojí na Dolnej ulici v Modre. Slúži Cirkevnému zboru ECAV Modra. Spája sa s odkazom štúrovcov, ktorí sú s Modrou spätí.',
                1 => 'Konajú sa tu služby Božie, koncerty a spomienkové slávnosti, napríklad k výročiu Svetozára Hurbana Vajanského. Konal sa tu aj Figušov memoriál či oslavy výročia Spoločenstva evanjelických žien. Niektoré služby Božie sa vysielali v televízii.',
                2 => 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.',
            ],
        ],
        26 => [
            'name' => 'hotel SOREA MÁJ',
            'body' => [
                0 => 'Hotel SOREA MÁJ je horský hotel v Liptovskom Jáne v Nízkych Tatrách. Ponúka ubytovanie a konferenčné priestory. Využíva sa aj na viacdňové konferencie.',
                1 => 'Konajú sa tu rodinné konferencie Rodinného spoločenstva ECAV a Misijné dni Východného dištriktu ECAV. Konferencie trvajú niekoľko dní a sú určené celým rodinám. Program spája prednášky, chvály a oddych.',
                2 => 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.',
            ],
        ],
        37 => [
            'name' => 'Kalvária (Nitra)',
            'body' => [
                0 => 'Nitrianska kalvária je kalvária z prelomu 18. a 19. storočia na skalnatom vrchu v juhovýchodnej časti Nitry. Z vrchu je výhľad na mesto a Zobor. Pri Kalvárii pôsobia misionári verbisti.',
                1 => 'Konajú sa tu procesie, púte k Matke Božej a slávnosti verbistov, napríklad skladanie sľubov seminaristov. Krížová cesta na vrchol je cieľom pútnikov z Nitry aj okolia. Najväčšia púť býva v lete.',
                2 => 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.',
            ],
        ],
        46 => [
            'name' => 'Centrum Salvator na Jakubovom námestí',
            'street' => 'Jakubovo námestie 4',
            'latitude' => 48.1436831,
            'longitude' => 17.1199592,
            'coordinates_source' => 'address',
            'postcode' => '811 09',
            'body' => [
                0 => 'Centrum Salvator sídli na Jakubovom námestí v Bratislave. Spravujú ho sestry Najsvätejšieho Spasiteľa. Ponúka priestory na duchovné obnovy a konferencie.',
                1 => 'Konajú sa tu duchovné obnovy, napríklad K výšinám či Božia vôľa pre môj život, a konferencie, napríklad o mariánskej úcte. Podujatia pripravujú sestry aj ďalšie organizácie. Program je otvorený pre všetkých.',
                2 => 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.',
            ],
        ],
        74 => [
            'name' => 'Kostol Nanebovzatia Panny Márie',
            'body' => [
                0 => 'Kostol Nanebovzatia Panny Márie na Dominikánskom námestí v Košiciach je dominikánskym kostolom. Spravujú ho bratia dominikáni. Patrí k najstarším kostolom v meste.',
                1 => 'Konajú sa tu bohoslužby, prednášky a podujatia dominikánov a ich priateľov. Program je otvorený pre veriacich aj verejnosť.',
                2 => 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.',
            ],
        ],
        77 => [
            'name' => 'Domček, Hlavná 94',
            'street' => 'Hlavná 94',
            'latitude' => 48.6257794,
            'longitude' => 22.0877244,
            'coordinates_source' => 'address',
            'postcode' => '072 14',
            'body' => [
                0 => 'Domček – Pastoračné centrum bl. Anny Kolesárovej stojí vo Vysokej nad Uhom, v rodisku blahoslavenej Anny. Je centrom pastorácie mladých na východe Slovenska. Je cieľom pútí.',
                1 => 'Konajú sa tu Púť radosti, ktorá má už vyše sto ročníkov, Festival radosti a púte zrelých mladých. Prichádzajú sem mladí z celého Slovenska. Program spája modlitbu, svedectvá a spoločenstvo.',
                2 => 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.',
            ],
        ],
        87 => [
            'name' => 'Pálffyho palác (Ventúrska)',
            'street' => 'Ventúrska 10',
            'latitude' => 48.142716,
            'longitude' => 17.1066455,
            'coordinates_source' => 'address',
            'postcode' => '811 01',
            'body' => [
                0 => 'Pálffyho palác je barokový mestský palác na Ventúrskej ulici v Bratislave. Má charakteristický portál s vojenskými trofejami, ktorý pripomína Leopolda Pálffyho. Slúži aj na kultúrne podujatia.',
                1 => 'Konajú sa tu prezentácie kníh Spolku svätého Vojtecha a odovzdávanie Ceny sv. Matky Terezy, ktorú udeľuje Sociálna subkomisia KBS. Podujatia majú slávnostný charakter. Sú otvorené pozvaným aj verejnosti.',
                2 => 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.',
            ],
        ],
        104 => [
            'name' => 'Bazilika Sedembolestnej Panny Márie',
            'body' => [
                0 => 'Bazilika Sedembolestnej Panny Márie v Šaštíne-Strážach je národnou svätyňou Slovenska. Patrónka Slovenska tu má svoje hlavné pútnické miesto. Baziliku dnes spravujú otcovia pavlíni.',
                1 => 'Každoročne v septembri sa tu koná Národná púť k Sedembolestnej Panne Márii. Konajú sa tu aj ďalšie púte, napríklad za kňazov, pre rozvedených či ružencových bratstiev. Program púte trvá niekoľko dní.',
                2 => 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.',
            ],
        ],
        107 => [
            'name' => 'Seminárny kostol na Hlavnej 89',
            'body' => [
                0 => 'Seminárny kostol stojí na Hlavnej ulici 89 v Košiciach. Je súčasťou areálu Kňazského seminára sv. Karola Boromejského a Teologickej fakulty. Slúži seminaristom aj veriacim.',
                1 => 'Konajú sa tu bohoslužby, ekumenické Veni Sancte na začiatok akademického roka a večery pre páry. Kostol hostí aj podujatia fakulty. Program je otvorený pre všetkých.',
                2 => 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.',
            ],
        ],
        108 => [
            'name' => 'Spišská Kapitula',
            'latitude' => 49.0007896,
            'longitude' => 20.7426616,
            'coordinates_source' => 'venue',
            'postcode' => '053 04',
            'body' => [
                0 => 'Spišská Kapitula je historické cirkevné mestečko pri Spišskom Podhradí. Je sídlom Spišskej diecézy a spolu so Spišským hradom patrí k pamiatkam UNESCO. Dominantou je Katedrála sv. Martina.',
                1 => 'Konajú sa tu sympóziá kánonického práva, kurzy, darovania krvi aj podujatia k 250. výročiu Spišskej diecézy. Sídli tu aj kňazský seminár. Prichádzajú sem pútnici aj turisti.',
                2 => 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.',
            ],
        ],
        109 => [
            'name' => 'Dóm svätej Alžbety',
            'street' => 'Hlavné námestie 1',
            'latitude' => 48.7202696,
            'longitude' => 21.2582156,
            'coordinates_source' => 'venue',
            'postcode' => '040 01',
            'body' => [
                0 => 'Dóm svätej Alžbety v Košiciach je najväčší kostol na Slovensku. Je považovaný za najvýchodnejšiu gotickú katedrálu francúzskeho typu v Európe. Stojí v strede Hlavnej ulice a je metropolitnou katedrálou Košickej arcidiecézy.',
                1 => 'Konajú sa tu arcidiecézne slávnosti, odpustová slávnosť sv. Alžbety a obnovy pred ňou. Počas prvých novembrových dní býva otvorená krypta a katedrála ponúka aj večerné prehliadky. V sprievode od katedrály sa konajú modlitby ruženca za pokoj vo svete.',
                2 => 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.',
            ],
        ],
        116 => [
            'name' => 'Kláštor Hronský Beňadik',
            'body' => [
                0 => 'Benediktínsky kláštor v Hronskom Beňadiku je súbor opevnených sakrálnych stavieb nad riekou Hron. Je národnou kultúrnou pamiatkou. Jeho súčasťou je Bazilika sv. Benedikta opáta.',
                1 => 'Konajú sa tu púte, duchovné cvičenia, napríklad s rehoľou Vteleného Slova, a stretnutia biskupa s mladými na slávnosť Krista Kráľa. Pri bazilike pôsobí Bratstvo Kristovej krvi. Kláštor je cieľom pútnikov aj turistov.',
                2 => 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.',
            ],
        ],
        119 => [
            'name' => 'Prešovská kalvária',
            'latitude' => 48.9881289,
            'longitude' => 21.2259993,
            'coordinates_source' => 'venue',
            'postcode' => '080 01',
            'body' => [
                0 => 'Prešovská kalvária je pútnické miesto na kopci nad mestom Prešov. Tvorí ju kostol a kaplnky krížovej cesty. Patrí k významným sakrálnym pamiatkam mesta.',
                1 => 'Konajú sa tu odpust Povýšenia sv. Kríža a púte rodín. Krížová cesta je cieľom pútnikov najmä v pôstnom období. Program pripravujú farnosti mesta.',
                2 => 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.',
            ],
        ],
        124 => [
            'name' => 'budova HOUR',
            'body' => [
                0 => 'Budova HOUR v Žiline je kultúrny priestor na Štefánikovej ulici. Je sídlom expozície ikon. Konajú sa tu výstavy a prednášky.',
                1 => 'Konajú sa tu komentované prehliadky výstavy originálnych ikon a biblické prednášky, napríklad o prorokoch Jonášovi a Izaiášovi. Podujatia približujú umenie ikon a Sväté písmo. Sú otvorené verejnosti.',
                2 => 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.',
            ],
        ],
        127 => [
            'name' => 'Mariánska sála',
            'body' => [
                0 => 'Mariánska sála je spoločenská sála na Františkánskej ulici v Trnave. Nachádza sa pri františkánskom kláštore. Slúži na stretnutia a kultúrne podujatia.',
                1 => 'Konali sa tu napríklad celoslovenské stretnutie františkánskej rodiny SPOLU, jubilejné františkánske misie či módna prehliadka spoločenského oblečenia. Sála hostí podujatia farnosti aj ďalších organizátorov. Program je otvorený verejnosti.',
                2 => 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.',
            ],
        ],
        132 => [
            'name' => 'Bazilika Narodenia Panny Márie (Marianka)',
            'body' => [
                0 => 'Bazilika Narodenia Panny Márie v Marianke je najstarším mariánskym pútnickým miestom na Slovensku. Baziliku, pútnické miesto a pútnický dom spravujú bratia Tešitelia z Getseman. Leží neďaleko Bratislavy.',
                1 => 'Každoročne v septembri sa tu koná veľká mariánska púť. Konajú sa tu aj ďalšie púte a duchovné obnovy. Prichádzajú sem pútnici z celého Slovenska.',
                2 => 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.',
            ],
        ],
        133 => [
            'name' => 'Dom Quo Vadis',
            'body' => [
                0 => 'Dom Quo Vadis je dobrovoľnícka kaviareň a evanjelizačné centrum v Bratislave. Nachádza sa na Veternej ulici v blízkosti Hurbanovho námestia. Je otvorený priestor pre stretnutia, kultúru a vieru.',
                1 => 'Konajú sa tu koncerty, výstavy, diskusie, modlitbové stretnutia aj tanečné večery. Svoj program tu pravidelne majú viaceré spoločenstvá a hnutia. Je jedným z najčastejšie využívaných miest kresťanských podujatí v Bratislave.',
                2 => 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.',
            ],
        ],
        136 => [
            'name' => 'Bazilika svätého Emeráma',
            'body' => [
                0 => 'Bazilika svätého Emeráma je katedrálou Nitrianskej diecézy a súčasťou Nitrianskeho hradu. Je zasvätená sv. Emerámovi, biskupovi a mučeníkovi zo 7. storočia. Patrí k najstarším sakrálnym pamiatkam na Slovensku.',
                1 => 'Konajú sa tu diecézne slávnosti, otvorenie akademického roka bohosloveckej fakulty a Katedrálny organový festival. Pripomínajú sa tu aj osobnosti, napríklad kardinál Ján Chryzostom Korec. Svoje brány otvára aj pri dňoch otvorených dverí seminárov.',
                2 => 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.',
            ],
        ],
        140 => [
            'name' => 'farský Kostol svätého Jakuba',
            'body' => [
                0 => 'Farský kostol sv. Jakuba stojí na Námestí sv. Jakuba v Dubnici nad Váhom. Je centrom miestnej rímskokatolíckej farnosti. Nachádza sa v ňom milostivá socha Panny Márie.',
                1 => 'Konajú sa tu dubnické mariánske púte s milostivou sochou Panny Márie. Farnosť odtiaľ podporuje aj misijné projekty, napríklad stavbu kostola v Burundi. Program je otvorený pre veriacich z okolia.',
                2 => 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.',
            ],
        ],
        144 => [
            'name' => 'Kostol Narodenia Panny Márie',
            'body' => [
                0 => 'Kostol Narodenia Panny Márie je farský kostol v Krušovciach. Nachádza sa na ulici Cyrila a Metoda. Farnosť si tu pripomína aj kardinála Alexandra Rudnaya.',
                1 => 'Konajú sa tu bohoslužby a koncerty, napríklad pri výročí úmrtia kardinála Rudnaya. Farnosť pripravuje aj ďalšie spomienkové podujatia. Program je otvorený pre veriacich z okolia.',
                2 => 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.',
            ],
        ],
        717 => [
            'name' => 'Teologická fakulta v Košiciach Katolíckej univerzity v Ružomberku',
            'street' => 'Hlavná 89',
            'latitude' => 48.7243731,
            'longitude' => 21.256988,
            'coordinates_source' => 'address',
            'postcode' => '040 01',
            'body' => [
                0 => 'Teologická fakulta Katolíckej univerzity v Košiciach sídli na Hlavnej ulici. Nadväzuje na odkaz Košickej univerzity založenej v roku 1657. Pripravuje kňazov aj laických odborníkov.',
                1 => 'V jej priestoroch sa konajú konferencie, napríklad o náboženskom extrémizme či o odkaze sv. Františka. Konajú sa tu aj Košické Hanusove dni a diskusie o digitálnom svete. Podujatia sú otvorené verejnosti.',
                2 => 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.',
            ],
        ],
        719 => [
            'name' => 'Aula sv. Jána Pavla II.',
            'body' => [
                0 => 'Aula sv. Jána Pavla II. je reprezentačná aula Katolíckej univerzity v Ružomberku. Nachádza sa na Hrabovskej ceste. Slúži na akademické a kultúrne podujatia.',
                1 => 'Konajú sa tu akademické zhromaždenia, voľby rektora a vernisáže, napríklad výstavy Ikony sv. Mikuláša. Podujatia pripravuje univerzita aj jej partneri. Niektoré sú otvorené verejnosti.',
                2 => 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.',
            ],
        ],
        721 => [
            'name' => 'Kostolík sv. Heleny',
            'street' => 'Hlavná 47',
            'latitude' => 48.3740128,
            'longitude' => 17.5873712,
            'coordinates_source' => 'venue',
            'postcode' => '917 01',
            'body' => [
                0 => 'Kostolík sv. Heleny je jedným z najstarších kostolov v Trnave. Nachádza sa na Hlavnej ulici. Patrí k pamiatkam historického centra mesta.',
                1 => 'Konajú sa tu bohoslužby a podujatia spojené s františkánskymi jubileami, napríklad pri 800. výročí smrti sv. Františka. Kostol je súčasťou programu mestských slávností. Program je otvorený pre všetkých.',
                2 => 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.',
            ],
        ],
        723 => [
            'name' => 'Zrkadlová sieň Primaciálneho paláca',
            'street' => 'Primaciálne námestie 1',
            'latitude' => 48.1445175,
            'longitude' => 17.1091382,
            'coordinates_source' => 'address',
            'postcode' => '811 01',
            'body' => [
                0 => 'Zrkadlová sieň je reprezentačná sála Primaciálneho paláca v Bratislave. Palác dal postaviť ostrihomský arcibiskup v 18. storočí. Dnes je sídlom primátora hlavného mesta.',
                1 => 'Konajú sa tu slávnostné podujatia, napríklad odovzdávanie ceny Fides et ratio a prezentácie kníh za účasti apoštolského nuncia. Svoje stretnutia tu pripravuje aj Komunita Sant’Egidio. Sála je určená slávnostným príležitostiam.',
                2 => 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.',
            ],
        ],
        733 => [
            'name' => 'Kostol sv. Vincenta de Paul',
            'fix_website' => 'https://fara-ba-prievoz.sk',
            'street' => 'Tomášikova 8',
            'postcode' => '821 03',
            'latitude' => 48.1526149,
            'longitude' => 17.1648172,
            'coordinates_source' => 'address',
            'body' => [
                0 => 'Kostol sv. Vincenta de Paul je farský kostol Rímskokatolíckej farnosti Bratislava – Prievoz. Nachádza sa na Tomášikovej ulici v mestskej časti Ružinov. Je zasvätený sv. Vincentovi de Paul, patrónovi charitatívnej služby.',
                1 => 'Okrem bohoslužieb sa tu konajú večery ohlasovania evanjelia a katechézy. Farnosť tu pripravuje aj ďalšie stretnutia pre veriacich. Program je otvorený pre všetkých.',
                2 => 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.',
            ],
        ],
        751 => [
            'name' => 'Exercičný dom Matky Božej',
            'body' => [
                0 => 'Exercičný dom Matky Božej je zariadenie v Nitre určené na duchovné cvičenia. Ponúka ubytovanie a priestor na ticho a modlitbu. Prijíma jednotlivcov aj skupiny.',
                1 => 'Konajú sa tu duchovné cvičenia podľa sv. Ignáca a ďalšie obnovy. Cvičenia trvajú zvyčajne niekoľko dní. Prihlasuje sa na ne vopred.',
                2 => 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.',
            ],
        ],
        757 => [
            'name' => 'Katolícka univerzita v Ružomberku',
            'body' => [
                0 => 'Katolícka univerzita v Ružomberku je verejná vysoká škola s konfesijným charakterom. Vznikla 1. júla 2000. Sídli na Námestí A. Hlinku.',
                1 => 'Konajú sa tu konferencie, napríklad k 250. výročiu vzniku troch slovenských diecéz, a katechetické konferencie. Podujatia pripravujú fakulty univerzity aj partneri. Mnohé sú otvorené verejnosti.',
                2 => 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.',
            ],
        ],
        763 => [
            'name' => 'Kostol San Girolamo della Carità',
            'body' => [
                0 => 'Kostol San Girolamo della Carità sa nachádza v Ríme na Via di Monserrato. Slúži slovenskej katolíckej misii v Ríme. Stretávajú sa tu Slováci žijúci vo Večnom meste.',
                1 => 'Slovenská misia tu otvára pastoračný rok svätou omšou a pravidelne slávi bohoslužby v slovenčine. Kostol je miestom stretnutí slovenskej komunity. Prichádzajú sem aj slovenskí pútnici.',
                2 => 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.',
            ],
        ],
        773 => [
            'name' => 'Kňazský seminár biskupa Jána Vojtaššáka',
            'body' => [
                0 => 'Kňazský seminár biskupa Jána Vojtaššáka sídli v Spišskej Kapitule. Pripravuje budúcich kňazov Spišskej diecézy. Nesie meno biskupa a mučeníka komunizmu Jána Vojtaššáka.',
                1 => 'Konajú sa tu duchovné obnovy pre mladých mužov a celoslovenské stretnutia chrámových hudobníkov. Seminár otvára brány aj pri dňoch otvorených dverí. Program je určený najmä mladým.',
                2 => 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.',
            ],
        ],
        785 => [
            'name' => 'Bazilika svätého Jakuba (Levoča)',
            'body' => [
                0 => 'Bazilika svätého Jakuba je gotický chrám v Levoči. Spolu s renesančnou radnicou tvorí dominantu Námestia Majstra Pavla. Nachádza sa v nej najvyšší gotický drevený oltár na svete od Majstra Pavla.',
                1 => 'Konajú sa tu bohoslužby, Večery milosrdenstva Spišskej diecézy a slávnostné podujatia, napríklad predstavenie prekladov diel Jána Pavla II. Bazilika je cieľom pútnikov aj turistov. Je zapísaná do zoznamu UNESCO spolu s historickým centrom Levoče.',
                2 => 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.',
            ],
        ],
        795 => [
            'name' => 'Katedrála svätého Martina (Bratislava)',
            'body' => [
                0 => 'Katedrála svätého Martina je pôvodne gotický chrám v historickom centre Bratislavy. Od roku 2008 je hlavným chrámom Bratislavskej arcidiecézy. V rokoch 1563 až 1830 sa v nej korunovali uhorskí panovníci.',
                1 => 'Konajú sa tu arcidiecézne slávnosti, kňazské vysviacky a modlitby za povolania. Každoročne tu býva aj svätá omša za darcov orgánov a koncerty duchovnej hudby. Katedrála je otvorená pre veriacich aj návštevníkov mesta.',
                2 => 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.',
            ],
        ],
        819 => [
            'name' => 'exercičný dom sv. Ignáca',
            'body' => [
                0 => 'Exercičný dom sv. Ignáca v Prešove spravujú jezuiti. Nachádza sa na ulici Pod Kalváriou. Je určený na duchovné cvičenia a rekolekcie.',
                1 => 'Konajú sa tu ignaciánske duchovné cvičenia a modlitby jezuitov, napríklad pred provinčnou kongregáciou. Cvičenia sú určené jednotlivcom aj skupinám. Prihlasuje sa na ne vopred.',
                2 => 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.',
            ],
        ],
        842 => [
            'name' => 'Katedrála svätého Martina (Spišská Kapitula)',
            'body' => [
                0 => 'Katedrála svätého Martina v Spišskej Kapitule je katedrálnym chrámom Spišskej diecézy. Ide o románsko-gotickú stavbu z prvej tretiny 13. storočia. Spolu so Spišskou Kapitulou patrí k pamiatkam UNESCO.',
                1 => 'Konajú sa tu diecézne slávnosti, napríklad oslavy 250. výročia diecézy, a pripomienky biskupa Jána Vojtaššáka. Slávnosti tu viedli aj vzácni hostia, napríklad kardinál Grech. Niektoré bohoslužby prenáša TV LUX.',
                2 => 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.',
            ],
        ],
        850 => [
            'name' => 'Diecézna svätyňa Božieho milosrdenstva',
            'body' => [
                0 => 'Diecézna svätyňa Božieho milosrdenstva sa nachádza v Smižanoch pri Spišskej Novej Vsi. Spravujú ju pallotíni. Je centrom úcty k Božiemu milosrdenstvu v Spišskej diecéze.',
                1 => 'Konajú sa tu oslavy Nedele Božieho milosrdenstva, ktoré prenáša aj Rádio LUMEN, a púte, napríklad púť Rómov s biskupom. Pri svätyni je aj formačné centrum. Prichádzajú sem pútnici z celého Spiša.',
                2 => 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.',
            ],
        ],
        890 => [
            'name' => 'Kostol svätého Štefana (Bratislava)',
            'latitude' => 48.1454162,
            'longitude' => 17.1050719,
            'coordinates_source' => 'address',
            'postcode' => '811 03',
            'fix_street' => 'Župné námestie 10',
            'fix_postcode' => '811 03',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Kostol svätého Štefana Kráľa s kláštorom kapucínov stojí na Župnom námestí v historickom centre Bratislavy. Spravujú ho bratia kapucíni. Patrí k barokovým pamiatkam mesta.',
                1 => 'Konajú sa tu bohoslužby a podujatia, napríklad požehnanie mobilov a tabletov v duchu sv. Carla Acutisa či spomienky politických väzňov. Kostol je otvorený veriacim aj návštevníkom. Program pripravujú kapucíni a ich hostia.',
                2 => 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.',
            ],
        ],
        918 => [
            'name' => 'Skalné sanktuárium Božieho milosrdenstva',
            'latitude' => 49.0174221,
            'longitude' => 18.327272,
            'coordinates_source' => 'venue',
            'postcode' => '018 63',
            'fix_village_id' => 1769,
            'fix_coordinates' => true,
            'body' => [
                0 => 'Skalné sanktuárium Božieho milosrdenstva sa nachádza na hore Butkov nad obcou Ladce. Vedie k nemu krížová cesta vytesaná do skál bývalého lomu. Je cieľom pútnikov z celého Slovenska.',
                1 => 'Konajú sa tu Soboty Božieho milosrdenstva a púte, napríklad spoločná púť charít. Sanktuárium sa ďalej rozširuje, napríklad o bránu rodiny so súsoším a zvonom. Program sa koná najmä v teplejších mesiacoch.',
                2 => 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.',
            ],
        ],
        957 => [
            'name' => 'Kultúrny dom',
            'body' => [
                0 => 'Kultúrny dom vo Vrbovom je centrom spoločenského a kultúrneho života mesta. Nachádza sa na ulici P. Jantauscha. Slúži na konferencie, kurzy a kultúrne podujatia.',
                1 => 'Konajú sa tu manželské kurzy Manželstvo v kocke, duchovné obnovy a mužská ekumenická konferencia. Podujatia pripravujú miestne spoločenstvá aj organizácie z okolia. Program je otvorený verejnosti.',
                2 => 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.',
            ],
        ],
        969 => [
            'name' => 'Kostol Božského Srdca Ježišovho (Partizánske)',
            'body' => [
                0 => 'Kostol Božského Srdca Ježišovho je dominantou mesta Partizánske. Navrhol ho architekt Vladimír Karfík. Základný kameň bol posvätený 8. júla 1943.',
                1 => 'Konajú sa tu bohoslužby a podujatia miestnej farnosti. Farnosť pripravuje aj púte, napríklad na Živčákovú či k pamätníku sv. Jána Pavla II. na Babej hore. Kostol patrí k pamiatkam modernej architektúry.',
                2 => 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.',
            ],
        ],
        972 => [
            'name' => 'Univerzitné pastoračné centrum (UPC) Pavla Straussa',
            'body' => [
                0 => 'Univerzitné pastoračné centrum Pavla Straussa je katolícke pastoračné centrum pre vysokoškolákov v Nitre. Nachádza sa na Svätourbanskej ulici. Nesie meno lekára, spisovateľa a esejistu Pavla Straussa.',
                1 => 'Konajú sa tu sväté omše, prednáškové cykly a stretnutia študentov. Na začiatku akademického roka centrum otvára nový rok cyklom prednášok. Program je určený najmä mladým.',
                2 => 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.',
            ],
        ],
        976 => [
            'name' => 'Kostol svätých Cyrila a Metoda (Poprad)',
            'body' => [
                0 => 'Kostol svätých Cyrila a Metoda je rímskokatolícky kostol farnosti Poprad – Juh. Nachádza sa na ulici Ludvíka Svobodu. Patrí do Spišskej diecézy.',
                1 => 'Konajú sa tu bohoslužby a podujatia farnosti, napríklad učenícke školy Spišskej diecézy. Farnosť organizuje aj púte, napríklad na Velehrad. Program je otvorený pre veriacich z Popradu.',
                2 => 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.',
            ],
        ],
        990 => [
            'name' => 'Kalvársky vrch',
            'latitude' => 48.1610469,
            'longitude' => 17.099964,
            'coordinates_source' => 'venue',
            'postcode' => '900 32',
            'body' => [
                0 => 'Kalvársky vrch je kopec v Bratislave s historickou kalváriou. Krížová cesta a kaplnky tvoria Bratislavskú kalváriu. O areál sa starajú bratia dominikáni a dobrovoľníci.',
                1 => 'Konajú sa tu pravidelné krížové cesty, Kalvárske utorky a slávnosti, napríklad Nedeľa Božieho milosrdenstva. Areál sa zapája aj do Víkendu otvorených parkov a záhrad. Je to otvorený priestor v prírode.',
                2 => 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.',
            ],
        ],
        1005 => [
            'name' => 'Rodinkovo, Beluša',
            'latitude' => 49.0288331,
            'longitude' => 18.3643283,
            'coordinates_source' => 'venue',
            'postcode' => '018 61',
            'body' => [
                0 => 'Rodinkovo je miesto prijatia pre rodiny v Belušských Slatinách. Ponúka ubytovanie, kurzy a programy pre manželov a rodiny. Patrí k Žilinskej diecéze.',
                1 => 'Konajú sa tu víkendové manželské stretnutia, kurzy Ligy pár páru a Letná biblická škola. Využívajú ho viaceré organizácie pracujúce s rodinami. Programy trvajú zvyčajne cez víkend.',
                2 => 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.',
            ],
        ],
        1019 => [
            'name' => 'Aula Kňazského seminára sv. Gorazda',
            'body' => [
                0 => 'Aula Kňazského seminára sv. Gorazda sa nachádza v Nitre. Je reprezentačnou sálou seminára. Slúži na konferencie a sympóziá.',
                1 => 'Konajú sa tu vedecké konferencie, napríklad o diele kardinála Korca, a sympóziá, napríklad k výročiu Modlitebnej ligy bl. cisára Karola. Podujatia pripravuje Nitrianska diecéza a jej inštitúcie. Sú otvorené odbornej aj širšej verejnosti.',
                2 => 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.',
            ],
        ],
        1045 => [
            'name' => 'Katedrála svätého Jána Krstiteľa',
            'latitude' => 48.993863,
            'longitude' => 21.2431405,
            'coordinates_source' => 'address',
            'postcode' => '080 01',
            'fix_village_id' => 2822,
            'fix_street' => 'Hlavná 1',
            'fix_postcode' => '080 01',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Katedrála svätého Jána Krstiteľa v Prešove je katedrálou Prešovskej archieparchie Gréckokatolíckej cirkvi. Stojí na Hlavnej ulici v historickom centre. Sú v nej uložené ostatky blahoslaveného biskupa Pavla Petra Gojdiča.',
                1 => 'Konajú sa tu archieparchiálne slávnosti, napríklad otvorenie Jubilejného roka blahoslavených. Slávia sa tu aj archijerejské liturgie a púte. Katedrála je otvorená veriacim aj návštevníkom.',
                2 => 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.',
            ],
        ],
        1050 => [
            'name' => 'Bazilika sv. Petra',
            'body' => [
                0 => 'Bazilika svätého Petra vo Vatikáne je jedným z najvýznamnejších chrámov kresťanstva. Stojí na mieste spájanom s hrobom apoštola Petra. Súčasná bazilika vznikla v období renesancie.',
                1 => 'Konajú sa tu pápežské liturgie, ktoré prenáša aj TV LUX. Slováci sa pripájajú k slávnostiam, napríklad pri zasvätení Ruska a Ukrajiny. Bazilika je cieľom pútnikov z celého sveta.',
                2 => 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.',
            ],
        ],
        1053 => [
            'name' => 'Univerzitné pastoračné centrum sv. Jozefa Freinademetza',
            'body' => [
                0 => 'Univerzitné pastoračné centrum sv. Jozefa Freinademetza sa nachádza v areáli internátov Mlyny v Bratislave. Spravujú ho misionári verbisti. Slúži najmä študentom.',
                1 => 'Konajú sa tu trojdňové duchovné obnovy, otvorenie akademického roka, Bioetické večery a projekcie filmov. Veľká sála centra hostí aj väčšie podujatia. Program je určený najmä vysokoškolákom.',
                2 => 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.',
            ],
        ],
        1054 => [
            'name' => 'Živčáková, Turzovka',
            'latitude' => 49.4002527,
            'longitude' => 18.5790025,
            'coordinates_source' => 'venue',
            'postcode' => '023 21',
            'body' => [
                0 => 'Živčáková je pútnické miesto nad Turzovkou v obci Korňa na Kysuciach. Je spojená s mariánskou úctou. Nachádza sa tu Kostol Panny Márie Matky Cirkvi a kaplnka.',
                1 => 'Konajú sa tu jesenné a letné púte, napríklad dvojdňová jesenná púť do Turzovky, aj festival Živčákfest. Prichádzajú sem pútnici z celého Slovenska. Program sa koná najmä cez víkendy.',
                2 => 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.',
            ],
        ],
        1057 => [
            'name' => 'Pútnický dom',
            'body' => [
                0 => 'Pútnický dom v Marianke poskytuje zázemie pútnikom najstaršieho mariánskeho pútnického miesta na Slovensku. Spravujú ho bratia Tešitelia z Getseman. Nachádza sa pri Bazilike Narodenia Panny Márie.',
                1 => 'Konajú sa tu duchovné obnovy, napríklad s o. Tomášom Prajzendancom. Dom slúži aj na ubytovanie pútnikov. Obnovy sa konajú počas celého roka.',
                2 => 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.',
            ],
        ],
    ];

    public function up(): void
    {
        foreach (self::VENUES as $id => $data) {
            $venue = Venue::query()->find($id);

            if (! $venue || $venue->name !== $data['name']) {
                continue;
            }

            $changes = [];

            if (isset($data['body']) && substr_count((string) $venue->body, '<p') !== 3) {
                $changes['body'] = implode('', array_map(fn (string $p) => '<p>'.e($p).'</p>', $data['body']));
            }

            foreach (['street', 'postcode', 'website'] as $field) {
                if (isset($data['fix_'.$field]) || (isset($data[$field]) && trim((string) $venue->$field) === '')) {
                    $changes[$field] = $data['fix_'.$field] ?? $data[$field];
                }
            }

            if (isset($data['fix_village_id'])) {
                $changes['village_id'] = $data['fix_village_id'];
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

        // Duplikát: ten istý kostol minoritov pod adresou aj názvom.
        $source = Venue::query()->find(736);
        $target = Venue::query()->find(4);
        if ($source && $target && $source->name === 'kostol sv. Františka Minoriti, Nám. sv. Františka 4' && $target->name === 'kostol sv. Františka Minoriti') {
            try {
                app(ResourceMerger::class)->merge('venues', 736, 4, null);
            } catch (Throwable $e) {
                Recorder::warning('admin', 'resource.merge_skipped', 'Zlúčenie miesta #736 → #4 preskočené',
                    subject: $target, context: ['error' => mb_substr($e->getMessage(), 0, 250)]);
            }
        }
    }

    public function down(): void
    {
        // Doplnené údaje sa nevracajú.
    }
};
