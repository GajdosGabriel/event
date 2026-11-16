<?php

use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\Event;
use App\Models\Municipality;
use App\Models\Venue;
use App\Services\Canals\CanalEmails;
use App\Services\Resources\ResourceMerger;
use App\Services\SystemLog\Recorder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Najnovšie kanály, miesta a podujatia (12. – 16. 11. 2026, najmä prvý import
 * kalendára ECAV): kanály #1957 – #1970, miesta #1067 – #1090, podujatia #11352 – #11397.
 *
 * Kanály dostanú popis v troch odsekoch, telefón, web, e-maily a adresu sídla.
 * Údaje zborov a seniorátov sú z adresára ecav.sk (stránky zborov, seniorátov
 * a dištriktov), súradnice z Nominatim/OpenStreetMap. Osobné kanály
 * používateľov (#1955, #1956) sa nemenia.
 *
 * Opravy importu:
 *  - #1969 CZ ECAV Gelnica mal obec Mníšek nad Hnilcom, #1970 Myjavský seniorát
 *    Vrbovce (sídlo je Turá Lúka), #1964 LOS ECAV Liptovský Mikuláš (sídlo je
 *    Liptovský Ondrej), #1965 ZD a VD ECAV Pezinok (dva dištrikty, teda celé Slovensko),
 *    #1967 Roman Kriszt rakúsky Mariazell namiesto Deutsch Jahrndorfu,
 *  - miesta pomenované len adresou alebo duplicitné sa zlúčia do existujúcich:
 *    #1067 → #998 (Bošany), #1069 → #727 (Košice-KVP), #1071 → #823 (Trnava),
 *    #1073 → #133 (Dom Quo Vadis), #1075 → #1001 (OÚSA), #1081 → #114 (Liptovský Mikuláš),
 *  - #1072 mal ako web archivovanú súťažnú stránku z roku 2006,
 *  - podujatie #11388 malo ako web článok Wikipédie o roku 2014, #11352 odkaz
 *    so sledovacím parametrom chatgpt.com,
 *  - podujatia z ecav.sk: z popisu sa vyhodia prázdne riadky „Ulica" a
 *    „Webstránka" (aj pri starších importoch), tri podujatia, ktorých zdroj má
 *    len jednu vetu, dostanú prehľadný popis.
 *
 * Kanál musí sedieť ID aj názvom; prevzatý, zlúčený či zmazaný sa preskočí.
 * Popis sa prepíše len vtedy, keď ešte nemá tri odseky; telefón a web sa
 * dopĺňajú do prázdneho poľa. Archivované miesta sa nemenia.
 */
return new class extends Migration
{
    private const CLOSING = 'Podrobnosti nájdete vždy v detaile podujatia.';

    private const OTHERS = 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.';

    /** Súradnice „Celé Slovensko" (stred Slovenska), ako ich majú ostatné celoslovenské kanály. */
    private const NATIONWIDE_POINT = [48.7411522, 19.4528646];

    /** Zahraničné obce, ktoré v číselníku ešte nie sú. */
    private const CITIES = [
        'deutsch-jahrndorf' => ['name' => 'Deutsch Jahrndorf', 'zip' => '2423'],
    ];

    /** @var array<int, array<string, mixed>> */
    private const CANALS = [
        1957 => [
            'name' => 'CZ ECAV Poltár',
            'phone' => '0911 786 411',
            'emails' => [['email' => 'poltar@ecav.sk', 'found_on' => 'https://www.ecav.sk/zapadny-distrikt/novohradsky-seniorat/poltar']],
            'address' => ['city' => 'poltar', 'street' => 'Obrancov mieru 163/26', 'postcode' => '987 01', 'latitude' => 48.4322813, 'longitude' => 19.7962113],
            'body' => [
                'Cirkevný zbor ECAV Poltár patrí do Novohradského seniorátu Evanjelickej cirkvi augsburského vyznania. Zborovým farárom je ThDr. Ivan Lukáč. K zboru patria aj veriaci zo Slanej Lehoty a Zeleného.',
                'Služby Božie sú každú nedeľu o 9.30 v Poltári. Zbor pripravuje adventné koncerty v evanjelickom kostole a spolu s rímskokatolíckou farnosťou ekumenické biblické hodiny.',
                'Kanál je určený evanjelikom z Poltára a okolia. Sledovaním budete vedieť o koncertoch a stretnutiach zboru. '.self::CLOSING,
            ],
        ],
        1958 => [
            'name' => 'SCENE OZ Bratislava',
            'body' => [
                'SCENE je občianske združenie z Bratislavy, ktoré pripravuje umelecké projekty.',
                'Organizuje FESTival UMenia – FESTUM v Prešove. Jeho 4. ročník sa v roku 2026 koná v Dvorane Evanjelického kolégia v spolupráci s Východným dištriktom ECAV a rádiom Regina Východ.',
                'Kanál je určený milovníkom umenia. Sledovaním budete vedieť o ďalších ročníkoch festivalu. '.self::CLOSING,
            ],
        ],
        1959 => [
            'name' => 'modlitby 24-7',
            'body' => [
                'Modlitby 24-7 je Iniciatíva modlitebníkov Slovenska. Chce byť nástrojom mobilizácie k modlitbám za krajinu a spája kresťanov z rôznych cirkví.',
                'Ponúka modlitebné podnety za vládu, rodinu či školstvo a pripravuje dni modlitieb a pôstu. Každý rok 17. novembra zvoláva Zvolanie – modlitby za Slovensko vo viac ako dvadsiatich mestách.',
                'Kanál je určený kresťanom, ktorí sa chcú modliť za Slovensko. Sledovaním budete vedieť o spoločných modlitbách. '.self::CLOSING,
            ],
        ],
        1960 => [
            'name' => 'CZ ECAV Kežmarok',
            'phone' => '052/452 22 42',
            'website' => 'https://www.ecavkk.sk',
            'emails' => [['email' => 'kezmarok@ecav.sk', 'found_on' => 'https://www.ecav.sk/vychodny-distrikt/tatransky-seniorat/kezmarok']],
            'address' => ['city' => 'kezmarok', 'street' => 'Hviezdoslavova 327/18', 'postcode' => '060 01', 'latitude' => 49.1332999, 'longitude' => 20.4277759],
            'body' => [
                'Cirkevný zbor ECAV Kežmarok patrí do Tatranského seniorátu. Sídli na Hviezdoslavovej ulici pri Evanjelickom lýceu. Zborovým farárom je Mgr. Roman Porubän.',
                'Služby Božie sú každú nedeľu o 9.00 v Kežmarku a pravidelne aj v Ľubici, Veľkej Lomnici a Huncovciach. Zbor pripravuje prednášky zo série Na slovíčko v Evanjelickom lýceu.',
                'Kanál je určený evanjelikom z Kežmarku a okolia. Sledovaním budete vedieť o prednáškach a podujatiach zboru. '.self::CLOSING,
            ],
        ],
        1961 => [
            'name' => 'CZ ECAV Pezinok',
            'phone' => '033/641 21 93',
            'website' => 'https://zbory.ecav.sk/pezinok',
            'emails' => [['email' => 'pezinok@ecav.sk', 'found_on' => 'https://www.ecav.sk/zapadny-distrikt/bratislavsky-seniorat/pezinok']],
            'address' => ['city' => 'pezinok', 'street' => 'Potočná 2', 'postcode' => '902 01', 'latitude' => 48.2875059, 'longitude' => 17.2689754],
            'body' => [
                'Cirkevný zbor ECAV Pezinok patrí do Bratislavského seniorátu. Jeho klasicistický kostol na Potočnej ulici z roku 1783 je národnou kultúrnou pamiatkou.',
                'Služby Božie sú každú nedeľu o 9.00 v Pezinku a dvakrát do mesiaca vo Viničnom. Spolu s mestom pripravuje cyklus koncertov Hudba v chráme, kde vystupuje aj zborový spevokol Stafides.',
                'Kanál je určený evanjelikom z Pezinka a milovníkom hudby. Sledovaním budete vedieť o koncertoch v kostole. '.self::CLOSING,
            ],
        ],
        1962 => [
            'name' => 'CZ ECAV Turá Lúka',
            'phone' => '0907 527 333',
            'website' => 'https://ecavturaluka.wixsite.com/turaluka',
            'emails' => [['email' => 'turaluka@ecav.sk', 'found_on' => 'https://www.ecav.sk/zapadny-distrikt/myjavsky-seniorat/tura-luka']],
            'address' => ['city' => 'tura-luka', 'street' => 'Turá Lúka 106', 'postcode' => '907 03', 'latitude' => 48.7436246, 'longitude' => 17.5382783],
            'body' => [
                'Cirkevný zbor ECAV Turá Lúka v Myjave patrí do Myjavského seniorátu. Zborovou farárkou je Mgr. Iveta Vachulová, ktorá je aj seniorkou seniorátu.',
                'Služby Božie sú každú nedeľu o 9.30 v evanjelickom chráme Božom. Zbor pripravuje aj pietnu spomienku na obete dopravných nehôd so zástupcami polície, hasičov a zdravotníkov.',
                'Kanál je určený evanjelikom z Turej Lúky a Myjavy. Sledovaním budete vedieť o podujatiach zboru. '.self::CLOSING,
            ],
        ],
        1963 => [
            'name' => 'CZ ECAV Ľubietová',
            'phone' => '048/419 52 36',
            'website' => 'https://www.ecav.sk/zapadny-distrikt/zvolensky-seniorat/lubietova',
            'emails' => [['email' => 'lubietova@ecav.sk', 'found_on' => 'https://www.ecav.sk/zapadny-distrikt/zvolensky-seniorat/lubietova']],
            'address' => ['city' => 'lubietova', 'street' => 'Hrnčiarska 196/8', 'postcode' => '976 55', 'latitude' => 48.7461636, 'longitude' => 19.3591567],
            'body' => [
                'Cirkevný zbor ECAV Ľubietová patrí do Zvolenského seniorátu. K zboru patria aj veriaci zo Strelníkov a Povrazníka.',
                'Služby Božie sú každú nedeľu o 9.00 v Ľubietovej. Zbor pripravuje zborové dni, napríklad pri posviacke zrekonštruovaných zvonov s benefičným koncertom.',
                'Kanál je určený evanjelikom z Ľubietovej a okolia. Sledovaním budete vedieť o podujatiach zboru. '.self::CLOSING,
            ],
        ],
        1964 => [
            'name' => 'LOS ECAV',
            'phone' => '044/529 61 32',
            'website' => 'https://www.ecavlos.sk',
            'emails' => [['email' => 'los@ecav.sk', 'found_on' => 'https://www.ecav.sk/vychodny-distrikt/liptovsko-oravsky-seniorat']],
            'fix_municipality' => true,
            'address' => ['city' => 'liptovsky-ondrej', 'street' => 'Liptovský Ondrej 77', 'postcode' => '032 04', 'latitude' => 49.0995738, 'longitude' => 19.7121866],
            'body' => [
                'Liptovsko-oravský seniorát ECAV združuje evanjelické cirkevné zbory na Liptove a Orave. Patrí do Východného dištriktu a sídli v Liptovskom Ondreji. Seniorkou je Mgr. Katarína Hudáková.',
                'Pripravuje spoločné podujatia zborov, napríklad stretnutie mužov Liptova a Oravy v Evanjelickom dome v Liptovskom Mikuláši.',
                'Kanál je určený evanjelikom z Liptova a Oravy. Sledovaním budete vedieť o seniorátnych stretnutiach. '.self::CLOSING,
            ],
        ],
        1965 => [
            'name' => 'ZD a VD ECAV',
            'phone' => '045/520 36 36',
            'emails' => [
                ['email' => 'zdecav@ecav.sk', 'found_on' => 'https://www.ecav.sk/zapadny-distrikt'],
                ['email' => 'sekretariat@vdecav.sk', 'found_on' => 'https://www.ecav.sk/vychodny-distrikt'],
            ],
            'nationwide' => true,
            'body' => [
                'Západný a Východný dištrikt sú dve časti Evanjelickej cirkvi augsburského vyznania na Slovensku. Biskupský úrad Západného dištriktu sídli vo Zvolene, Východného v Prešove. Vedú ich biskupi Ján Hroboň a Peter Mihoč.',
                'Biskupské úrady oboch dištriktov spoločne pripravujú online kázňové prípravky k Adventu a Vianociam pre kazateľov a študentov Evanjelickej bohosloveckej fakulty UK.',
                'Kanál je určený kazateľom a teológom. Sledovaním budete vedieť o spoločných podujatiach dištriktov. '.self::CLOSING,
            ],
        ],
        1966 => [
            'name' => 'CZ ECAV Trnava',
            'phone' => '033/551 39 84',
            'website' => 'https://trnava.ecav.sk',
            'emails' => [['email' => 'trnava@ecav.sk', 'found_on' => 'https://www.ecav.sk/zapadny-distrikt/bratislavsky-seniorat/trnava']],
            'address' => ['city' => 'trnava', 'street' => 'Námestie SNP 7', 'postcode' => '917 01', 'latitude' => 48.3733111, 'longitude' => 17.5861432],
            'body' => [
                'Cirkevný zbor ECAV Trnava patrí do Bratislavského seniorátu. Evanjelický kostol stojí na Námestí SNP. K zboru patria aj veriaci zo Serede.',
                'Služby Božie sú každú nedeľu o 9.00 v Trnave a o 10.30 v Seredi. V kostole sa konajú aj koncerty, napríklad vianočný koncert Narodenie najväčšieho kráľa.',
                'Kanál je určený evanjelikom z Trnavy a okolia. Sledovaním budete vedieť o koncertoch a podujatiach zboru. '.self::CLOSING,
            ],
        ],
        1967 => [
            'name' => 'Roman Kriszt z Evanjelickeho cirkevného zboru Deutsch Jahrndorf',
            'phone' => '+43 660 573 35 50',
            'emails' => [['email' => 'romankriszt@gmx.at', 'found_on' => 'https://www.ecav.sk/drazdanske-barokove-vianoce']],
            'fix_municipality' => true,
            'municipality_only' => true,
            'address' => ['city' => 'deutsch-jahrndorf', 'latitude' => 48.0116888, 'longitude' => 17.1069611],
            'body' => [
                'Roman Kriszt pôsobí v evanjelickom cirkevnom zbore v Deutsch Jahrndorfe. Obec leží v rakúskom Burgenlande pri hraniciach so Slovenskom a Maďarskom, neďaleko Čunova.',
                'Organizuje koncerty v evanjelickom kostole, napríklad koncert barokovej hudby Drážďanské barokové Vianoce so súbormi ensemble bach XXI vocal a Capella Leopoldina.',
                'Kanál je určený milovníkom starej hudby z Bratislavy a okolia. Sledovaním budete vedieť o koncertoch. '.self::CLOSING,
            ],
        ],
        1968 => [
            'name' => 'CZ ECAV Rožňava',
            'phone' => '0908 324 755',
            'website' => 'https://www.ecav.sk/vychodny-distrikt/gemersky-seniorat/roznava',
            'emails' => [['email' => 'roznava@ecav.sk', 'found_on' => 'https://www.ecav.sk/vychodny-distrikt/gemersky-seniorat/roznava']],
            'address' => ['city' => 'roznava', 'street' => 'Šafárikova 5', 'postcode' => '048 01', 'latitude' => 48.6605518, 'longitude' => 20.532238],
            'body' => [
                'Cirkevný zbor ECAV Rožňava patrí do Gemerského seniorátu. Služby Božie slávi po slovensky aj po maďarsky.',
                'Služby Božie sú každú nedeľu o 10.00 v slovenčine a o 8.30 v maďarčine. Zbor slúži aj v nemocnici a v zariadeniach pre seniorov a pripravuje adventné podujatia v evanjelickom kostole.',
                'Kanál je určený evanjelikom z Rožňavy a okolia. Sledovaním budete vedieť o podujatiach zboru. '.self::CLOSING,
            ],
        ],
        1969 => [
            'name' => 'CZ ECAV Gelnica',
            'phone' => '0945 506 078',
            'website' => 'https://www.ecav.sk/vychodny-distrikt/kosicky-seniorat/gelnica',
            'emails' => [['email' => 'gelnica@ecav.sk', 'found_on' => 'https://www.ecav.sk/vychodny-distrikt/kosicky-seniorat/gelnica']],
            'fix_municipality' => true,
            'address' => ['city' => 'gelnica', 'street' => 'Lutherovo námestie 5', 'postcode' => '056 01', 'latitude' => 48.8557007, 'longitude' => 20.9350521],
            'body' => [
                'Cirkevný zbor ECAV Gelnica patrí do Košického seniorátu. Sídli na Lutherovom námestí. Zborovou farárkou je Mgr. Karolína Konopeusová.',
                'Služby Božie sú každú nedeľu o 9.00 v Gelnici a o 10.30 vo Veľkom Folkmari. S evanjelikmi z Mníška nad Hnilcom a Švedláru pripravuje ples evanjelikov Hnileckej doliny.',
                'Kanál je určený evanjelikom z Hnileckej doliny. Sledovaním budete vedieť o podujatiach zboru. '.self::CLOSING,
            ],
        ],
        1970 => [
            'name' => 'Myjavský seniorát ECAV',
            'phone' => '0907 527 333',
            'website' => 'https://www.ecav.sk/zapadny-distrikt/myjavsky-seniorat',
            'emails' => [['email' => 'mys@ecav.sk', 'found_on' => 'https://www.ecav.sk/zapadny-distrikt/myjavsky-seniorat']],
            'fix_municipality' => true,
            'address' => ['city' => 'tura-luka', 'street' => 'Turá Lúka 106', 'postcode' => '907 03', 'latitude' => 48.7436246, 'longitude' => 17.5382783],
            'body' => [
                'Myjavský seniorát ECAV združuje evanjelické zbory na Myjavsku a Záhorí, napríklad v Brezovej pod Bradlom, Holíči či Sobotišti. Patrí do Západného dištriktu. Seniorkou je Mgr. Iveta Vachulová z Turej Lúky.',
                'Pripravuje spoločné podujatia zborov, napríklad seniorátny ples, ktorý sa v roku 2027 koná už ôsmykrát.',
                'Kanál je určený evanjelikom z Myjavska. Sledovaním budete vedieť o podujatiach seniorátu. '.self::CLOSING,
            ],
        ],
    ];

    /** @var array<int, array<string, mixed>> */
    private const VENUES = [
        1070 => ['name' => 'Salónik reštaurácie U Kormidla', 'body' => [
            'Salónik reštaurácie U Kormidla v Brne slúži na menšie stretnutia a prednášky.',
            'Konajú sa tu stretnutia o cyrilo-metodskom dedičstve, ktoré pripravuje občianske združenie Cyrilometodiada.',
            self::OTHERS,
        ]],
        1072 => ['name' => 'Univerzitná knižnica Katolíckej univerzity v Ružomberku', 'fix_website' => null, 'body' => [
            'Univerzitná knižnica Katolíckej univerzity v Ružomberku stojí na Hrabovskej ceste. Otvorili ju v roku 2013 a vo voľnom výbere má kapacitu vyše 300-tisíc kníh.',
            'Konajú sa tu podujatia univerzity, napríklad deň otvorených dverí pre budúcich študentov.',
            self::OTHERS,
        ]],
        1074 => ['name' => 'Cesta olympionikov 1271/28, Banská Bystrica', 'fix_name' => 'Športová hala Dukla (Banská Bystrica)', 'fix_postcode' => '974 04', 'body' => [
            'Športová hala Dukla stojí na Ceste olympionikov v Banskej Bystrici. Slúži športovým klubom a hostí aj veľké podujatia.',
            'Konajú sa tu aj kresťanské konferencie, napríklad mužská konferencia Godzone.',
            self::OTHERS,
        ]],
        1077 => ['name' => 'Dvorana Evanjelického kolégia', 'fix_street' => 'Hlavná 137', 'body' => [
            'Dvorana je slávnostná sála Evanjelického kolégia na Hlavnej ulici v Prešove. Kolégium založili evanjelici v roku 1667 a dnes v ňom sídli Východný dištrikt ECAV.',
            'Konajú sa tu koncerty a výstavy, napríklad Festival umenia FESTUM.',
            self::OTHERS,
        ]],
        1078 => ['name' => 'Evanjelické lýceum (Kežmarok)', 'fix_street' => 'Hviezdoslavova 327/18',
            'latitude' => 49.1332999, 'longitude' => 20.4277759, 'coordinates_source' => 'address', 'body' => [
                'Evanjelické lýceum na Hviezdoslavovej ulici v Kežmarku je baroková budova z rokov 1774 – 1776. Je národnou kultúrnou pamiatkou a sídli v nej Lyceálna knižnica so vzácnymi starými tlačami.',
                'Cirkevný zbor ECAV Kežmarok tu pripravuje prednášky zo série Na slovíčko.',
                self::OTHERS,
            ]],
        1079 => ['name' => 'Evanjelický kostol (Pezinok)', 'fix_street' => 'Potočná', 'body' => [
            'Evanjelický kostol stojí na Potočnej ulici v historickom centre Pezinka. Postavili ho v roku 1783, vežu dostal v rokoch 1858 – 1863. Je národnou kultúrnou pamiatkou.',
            'Cirkevný zbor ECAV Pezinok tu pripravuje cyklus koncertov Hudba v chráme a ekumenické stretnutia organistov.',
            self::OTHERS,
        ]],
        1080 => ['name' => 'Evanjelický chrám Boží', 'fix_name' => 'Evanjelický chrám Boží (Turá Lúka)', 'body' => [
            'Evanjelický chrám Boží stojí v Turej Lúke, miestnej časti Myjavy. Patrí cirkevnému zboru ECAV Turá Lúka.',
            'Okrem nedeľných Služieb Božích sa tu konajú pietne spomienky, napríklad na obete dopravných nehôd.',
            self::OTHERS,
        ]],
        1083 => ['name' => 'Mlynská 23', 'fix_name' => 'Evanjelický kostol (Košice)', 'fix_postcode' => '040 01', 'body' => [
            'Evanjelický a. v. chrám stojí na Mlynskej ulici v historickom centre Košíc. Patrí cirkevnému zboru ECAV Košice.',
            'Konajú sa tu bohoslužby a koncerty, napríklad benefičný adventný koncert Brána adventu.',
            self::OTHERS,
        ]],
        1084 => ['name' => 'Evanjelický kostol', 'fix_name' => 'Evanjelický kostol (Sobotište)', 'village' => 3215, 'body' => [
            'Evanjelický a. v. kostol v Sobotišti v okrese Senica patrí miestnemu cirkevnému zboru Myjavského seniorátu.',
            'Konajú sa tu bohoslužby a koncerty, napríklad vianočný koncert Narodenie najväčšieho Kráľa.',
            self::OTHERS,
        ]],
        1085 => ['name' => 'Evanjelický kostol', 'fix_name' => 'Evanjelický kostol (Rožňava)', 'village' => 3014, 'body' => [
            'Evanjelický kostol v Rožňave je klasicistický chrám cirkevného zboru ECAV Rožňava. Bohoslužby sa tu konajú po slovensky aj po maďarsky.',
            'Zbor tu pripravuje adventné podujatia, napríklad Folklórne svetlo Vianoc.',
            self::OTHERS,
        ]],
        1087 => ['name' => 'Rímskokatolícka fara', 'fix_name' => 'Rímskokatolícka fara (Poltár)', 'village' => 2755, 'body' => [
            'Rímskokatolícka fara v Poltári je sídlom miestnej rímskokatolíckej farnosti.',
            'Striedavo s evanjelickou farou sa tu konajú ekumenické biblické hodiny farnosti a cirkevného zboru ECAV Poltár.',
            self::OTHERS,
        ]],
        1088 => ['name' => 'Evanjelický a. v. kostol', 'fix_name' => 'Evanjelický kostol (Poltár)', 'village' => 2755, 'body' => [
            'Evanjelický a. v. kostol stojí na ulici Družby v Poltári. Patrí cirkevnému zboru ECAV Poltár, ktorý tu slávi Služby Božie každú nedeľu o 9.30.',
            'Konajú sa tu aj koncerty, napríklad adventný koncert so súborom musicArtum.',
            self::OTHERS,
        ]],
        1089 => ['name' => 'Sála obecného úradu', 'fix_name' => 'Sála obecného úradu (Mníšek nad Hnilcom)', 'village' => 2218, 'body' => [
            'Sála obecného úradu v Mníšku nad Hnilcom v okrese Gelnica slúži na kultúrne a spoločenské podujatia obce.',
            'Konajú sa tu aj plesy, napríklad ples evanjelikov Hnileckej doliny.',
            self::OTHERS,
        ]],
        1090 => ['name' => 'Leškov kultúrny dom', 'body' => [
            'Leškov kultúrny dom je kultúrny dom v obci Vrbovce v okrese Myjava.',
            'Konajú sa tu spoločenské podujatia, napríklad seniorátny ples Myjavského seniorátu ECAV.',
            self::OTHERS,
        ]],
        // Cieľ zlúčenia #1081: doplní sa adresa, ktorú mal duplikát z podujatia.
        114 => ['name' => 'Evanjelický cirkevný dom (Liptovský Mikuláš)', 'street' => 'Tranovského 1008/4', 'postcode' => '031 01',
            'latitude' => 49.0831223, 'longitude' => 19.6157933, 'coordinates_source' => 'address'],
    ];

    /** Zdroj => [cieľ, názov zdroja, názov cieľa]. */
    private const MERGES = [
        1067 => [998, 'Park garbiarov 107, Bošany', 'Kostol sv. Martina z Tours (Bošany)'],
        1069 => [727, 'Trieda KVP 6, Košice - KVP', 'Kostol Božieho milosrdenstva (Košice-KVP)'],
        1071 => [823, 'Bazilika sv. Mikuláša', 'Bazilika svätého Mikuláša (Trnava)'],
        1073 => [133, 'Veterná 1, Bratislava', 'Dom Quo Vadis'],
        1075 => [1001, 'Heydukova ul. 10.', 'Onkologický ústav sv. Alžbety'],
        1081 => [114, 'Evanjelický dom', 'Evanjelický cirkevný dom (Liptovský Mikuláš)'],
    ];

    /** Web podujatia: [chybná hodnota, správna hodnota]. */
    private const EVENT_WEBSITES = [
        11388 => ['https://cs.wikipedia.org/wiki/2014', 'https://www.zdecav.sk/pozvanka-na-online-kaznove-pripravky-k-adventu-a-vianociam-2026/'],
        11352 => [
            'https://kbs.sk/obsah/sekcia/h/hladat/p/dokumenty-papezov/c/posolstvo-k-svetovemu-dnu-chudobnych-2026?utm_source=chatgpt.com',
            'https://kbs.sk/obsah/sekcia/h/hladat/p/dokumenty-papezov/c/posolstvo-k-svetovemu-dnu-chudobnych-2026',
        ],
    ];

    /** Podujatia, ktorých zdroj má jednu vetu: nový popis, ak popis ešte obsahuje text zo zdroja. */
    private const EVENT_BODIES = [
        11395 => [
            'name' => 'Folklórne svetlo Vianoc v Rožňave',
            'marker' => 'Podujatie sa uskutoční 19. 12. 2026 o 16:00',
            'body' => '<p>Cirkevný zbor ECAV Rožňava pozýva na adventné podujatie Folklórne svetlo Vianoc. Uskutoční sa v sobotu 19. decembra 2026 o 16.00 v evanjelickom kostole v Rožňave.</p>'
                .'<ul><li><strong>Termín:</strong> 19. 12. 2026, 16.00 – 17.00</li>'
                .'<li><strong>Miesto:</strong> Evanjelický kostol, Rožňava</li>'
                .'<li><strong>Organizátor:</strong> CZ ECAV Rožňava, Gemerský seniorát ECAV na Slovensku</li>'
                .'<li><strong>Kategória:</strong> kultúra</li></ul>',
        ],
        11396 => [
            'name' => 'Ples evanjelikov Hnileckej doliny',
            'marker' => 'Mesto: Mníšek nad Hnilcom. Ulica. Webstránka.',
            'body' => '<p>Evanjelické cirkevné zbory Gelnica, Mníšek nad Hnilcom a Švedlár srdečne pozývajú na Ples evanjelikov Hnileckej doliny. Začína sa v piatok 8. januára 2027 o 19.00 v Mníšku nad Hnilcom a potrvá do rána.</p>'
                .'<ul><li><strong>Termín:</strong> 8. 1. 2027 od 19.00 do 9. 1. 2027 do 6.00</li>'
                .'<li><strong>Miesto:</strong> Mníšek nad Hnilcom</li>'
                .'<li><strong>Organizátor:</strong> CZ ECAV Gelnica, Mníšek nad Hnilcom a Švedlár, Košický seniorát ECAV na Slovensku</li>'
                .'<li><strong>Kategória:</strong> kultúra</li></ul>',
        ],
        11397 => [
            'name' => '8. seniorátny ples MYS',
            'marker' => 'Pridajte sa k nám aj vy.',
            'body' => '<p>Myjavský seniorát ECAV pozýva na 8. seniorátny ples. Začína sa v piatok 5. februára 2027 o 18.30 vo Vrbovciach a potrvá do rána. Pridajte sa k nám aj vy.</p>'
                .'<ul><li><strong>Termín:</strong> 5. 2. 2027 od 18.30 do 6. 2. 2027 do 4.00</li>'
                .'<li><strong>Miesto:</strong> Vrbovce</li>'
                .'<li><strong>Organizátor:</strong> Myjavský seniorát ECAV na Slovensku</li>'
                .'<li><strong>Kategória:</strong> kultúra</li></ul>',
        ],
    ];

    public function up(): void
    {
        $this->completeCanals();
        $this->completeVenues();
        $this->mergeVenues();
        $this->fixEvents();
    }

    private function completeCanals(): void
    {
        $emails = Schema::hasTable('canal_emails') ? app(CanalEmails::class) : null;
        $nationwide = Municipality::nationwideId();

        foreach (self::CANALS as $id => $profile) {
            $canal = Canal::withTrashed()->find($id);

            if (! $canal || $canal->trashed() || $canal->merged_into_id !== null || $canal->claimed_at !== null
                || $canal->name !== $profile['name']) {
                continue;
            }

            $changes = [];

            // Prepíše sa len popis, ktorý ešte nemá tri odseky — upravený text ostane.
            if (substr_count((string) $canal->body, '<p') !== 3) {
                $changes['body'] = implode('', array_map(fn (string $p) => '<p>'.e($p).'</p>', $profile['body']));
            }

            foreach (['website', 'phone'] as $field) {
                if (isset($profile[$field]) && trim((string) $canal->$field) === '') {
                    $changes[$field] = $profile[$field];
                }
            }

            $looseCoordinates = in_array($canal->coordinates_source, ['municipality', 'nationwide', 'ai', null], true);

            if (! empty($profile['nationwide']) && $nationwide !== null && $looseCoordinates
                && (int) $canal->municipality_id !== $nationwide) {
                $changes += ['municipality_id' => $nationwide, 'latitude' => self::NATIONWIDE_POINT[0],
                    'longitude' => self::NATIONWIDE_POINT[1], 'coordinates_source' => 'municipality'];
            }

            if (isset($profile['address']) && $looseCoordinates) {
                $a = $profile['address'];
                $cityId = $this->city($a['city']);
                $municipalityOk = ! empty($profile['fix_municipality']) || (int) $canal->municipality_id === $cityId
                    || $canal->municipality_id === null || (int) $canal->municipality_id === $nationwide;

                if ($cityId !== null && $municipalityOk) {
                    $changes['municipality_id'] = $cityId;
                    if (empty($profile['municipality_only'])) {
                        if (trim((string) $canal->street) === '') {
                            $changes['street'] = $a['street'];
                            $changes['postcode'] = $a['postcode'];
                        }
                        $changes += ['latitude' => $a['latitude'], 'longitude' => $a['longitude'], 'coordinates_source' => 'address'];
                    } else {
                        $changes += ['latitude' => $a['latitude'], 'longitude' => $a['longitude'], 'coordinates_source' => 'municipality'];
                    }
                }
            }

            if ($changes !== []) {
                $canal->forceFill($changes)->save();
                Recorder::info('canals', 'profile_described', "Kanál {$canal->name}: doplnené ".implode(', ', array_keys($changes)),
                    status: 'ok', subject: $canal, context: ['fields' => array_keys($changes)]);
            }

            if ($emails && ! empty($profile['emails'])) {
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
                || ($venue->name !== $data['name'] && $venue->name !== ($data['fix_name'] ?? null))
                || (isset($data['village']) && (int) $venue->village_id !== $data['village'])) {
                continue;
            }

            $changes = [];

            if (isset($data['fix_name']) && $venue->name === $data['name']) {
                $changes['name'] = $data['fix_name'];
            }

            if (isset($data['body']) && substr_count((string) $venue->body, '<p') !== 3) {
                $changes['body'] = implode('', array_map(fn (string $p) => '<p>'.e($p).'</p>', $data['body']));
            }

            foreach (['street', 'postcode', 'website'] as $field) {
                if (array_key_exists('fix_'.$field, $data)) {
                    if ($data['fix_'.$field] !== $venue->$field) {
                        $changes[$field] = $data['fix_'.$field];
                    }
                } elseif (isset($data[$field]) && trim((string) $venue->$field) === '') {
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

    private function fixEvents(): void
    {
        foreach (self::EVENT_WEBSITES as $id => [$wrong, $right]) {
            $event = Event::query()->find($id);
            if ($event && $event->website === $wrong && ! $this->claimed($event)) {
                $event->forceFill(['website' => $right])->save();
                Recorder::info('events', 'profile_described', "Podujatie {$event->name}: opravený web",
                    status: 'ok', subject: $event, context: ['fields' => ['website']]);
            }
        }

        foreach (self::EVENT_BODIES as $id => $data) {
            $event = Event::query()->find($id);
            if ($event && $event->name === $data['name'] && str_contains((string) $event->body, $data['marker'])
                && ! $this->claimed($event)) {
                $event->forceFill(['body' => $data['body']])->save();
                Recorder::info('events', 'profile_described', "Podujatie {$event->name}: doplnený popis",
                    status: 'ok', subject: $event, context: ['fields' => ['body']]);
            }
        }

        // Prázdne riadky „Ulica" a „Webstránka" z kalendára ECAV (bez hodnoty za nimi).
        Event::query()->where('orginal_source', 'like', 'https://www.ecav.sk/%')
            ->where(fn ($q) => $q->where('body', 'like', '%Ulica%')->orWhere('body', 'like', '%Webstránka%'))
            ->lazyById()->each(function (Event $event): void {
                if ($this->claimed($event)) {
                    return;
                }

                $body = self::withoutEmptyLabels((string) $event->body);

                if ($body !== $event->body) {
                    $event->forceFill(['body' => $body])->save();
                }
            });
    }

    /** Vyhodí položky „Ulica" / „Webstránka", za ktorými nie je žiadna hodnota. */
    public static function withoutEmptyLabels(string $body): string
    {
        $label = '(?:<strong>)?\s*(?:Ulica|Webstránka)\s*:?\s*(?:<\/strong>)?\s*:?\s*';

        $body = preg_replace('/<li>\s*'.$label.'<\/li>\s*/u', '', $body);
        $body = preg_replace('/<br\s*\/?>\s*<strong>\s*(?:Ulica|Webstránka)\s*:?\s*<\/strong>(?=\s*(?:<br\s*\/?>|<\/p>))/u', '', $body);
        $body = preg_replace('/<strong>\s*(?:Ulica|Webstránka)\s*:?\s*<\/strong>\s*<br\s*\/?>/u', '', $body);

        return (string) $body;
    }

    /** Podujatie prevzatého kanála upravuje už len jeho tím. */
    private function claimed(Event $event): bool
    {
        return $event->canal_id !== null && Canal::withTrashed()->whereKey($event->canal_id)->whereNotNull('claimed_at')->exists();
    }

    /** Obec podľa slugu; zahraničnú obec zo self::CITIES vytvorí raz. */
    private function city(string $slug): ?int
    {
        $id = DB::table('municipalities')->where('slug', $slug)->value('id');
        if ($id !== null) {
            return (int) $id;
        }

        if (! isset(self::CITIES[$slug])) {
            return null;
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
