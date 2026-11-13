<?php

use App\Enums\ModelStatus;
use App\Models\Venue;
use App\Services\Resources\ResourceMerger;
use App\Services\SystemLog\Recorder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Druhá dávka miest konania (november 2026): ďalších 60 miest v poradí
 * (#926 – #997). Popis v troch odsekoch, adresa, PSČ a súradnice
 * z Nominatim/OpenStreetMap; miesta vzniknuté z útržkov textu či adries
 * dostanú skutočný názov (`fix_name`).
 *
 * Opravy chybných údajov:
 *  - #995 Santuário de Fátima je v Portugalsku, nie v Trenčíne,
 *  - #938 hotel Belvedere je v Zakopanom (lyžovačka TV LUX), nie v Bratislave,
 *  - #926 Kostol Sedembolestnej Panny Márie (Vajnory) bol v Martine,
 *  - #991 je sála Gréckokatolíckeho eparchiálneho úradu v Bratislave, nie Ulič,
 *  - #935 Kostol Navštívenia Panny Márie (milosrdní bratia) mal adresu z Ľubotíc,
 *  - #992 Mamateyova a Rím (#936, #982) mali súradnice stredu Slovenska či mesta
 *    a PSČ z Bratislavy,
 *  - #986, #984, #983 mali popis mesta z Wikipédie, #959 anglický popis Zoomu,
 *  - #993 evanjelický chrám na Terase stojí na Toryskej 1/G, #964 je kláštor
 *    Služobníčok vo Svite.
 *
 * Duplikáty sa zlúčia: #989 → #123 (Katedrála sv. Jána Krstiteľa v Trnave,
 * premenuje sa z chybného „gotický kostolík"),
 * #983 → #976, #971 → #990, #946 → #996, #950 → #37.
 *
 * #985 je Dom Quo Vadis z Hurbanovho námestia — zlúčenie s #133 blokuje
 * priložený súbor, preto sa len opraví.
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
        'fatima' => ['name' => 'Fátima', 'zip' => '2495'],
        'zakopane' => ['name' => 'Zakopané', 'zip' => '34-500'],
    ];

    /** @var array<int, array<string, mixed>> */
    private const VENUES = [
        997 => [
            'name' => 'Symbolický cintorín na Kláštorisku',
            'postcode' => '053 76',
            'body' => [
                0 => 'Symbolický cintorín na Kláštorisku je pietne miesto v Národnom parku Slovenský raj. Pripomína ľudí, ktorí zahynuli v horách. Kláštorisko je známe aj zrúcaninami kartuziánskeho kláštora.',
                1 => 'Každú jeseň sa tu, podobne ako na iných symbolických cintorínoch, koná spomienka na obete hôr. Spomienku sprevádzajú horskí záchranári. Je otvorená pre všetkých.',
                2 => self::OTHERS,
            ],
        ],
        995 => [
            'name' => 'Santuário de Fátima',
            'fix_name' => 'Svätyňa vo Fatime (Portugalsko)',
            'city' => 'fatima',
            'fix_postcode' => '2496-908',
            'country' => 'Portugalsko',
            'latitude' => 39.6300079,
            'longitude' => -8.6746971,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Svätyňa vo Fatime je mariánske pútnické miesto v Portugalsku. Panna Mária sa tu v roku 1917 zjavila trom pastierikom. Patrí k najnavštevovanejším pútnickým miestam sveta.',
                1 => 'Od mája do októbra sa tu vždy 12. v mesiaci modlí ruženec so sviečkovým sprievodom, ktorý prenáša aj Televízia LUX. Najväčšie púte sú 13. mája a 13. októbra.',
                2 => self::OTHERS,
            ],
        ],
        994 => [
            'name' => 'Kostol Preblahoslavenej Panny Márie',
            'body' => [
                0 => 'Kostol Preblahoslavenej Panny Márie je farský kostol vo Vrábľoch na Hlavnej ulici. V roku 2026 si pripomenul 125. výročie posviacky. Je dominantou centra mesta.',
                1 => 'Okrem bohoslužieb sa tu konajú hodové koncerty, napríklad hudobno-poetické dielo Proglas – Slovanská óda v podaní zboru Adoremus. Koncerty pripravuje aj mesto Vráble. Sú otvorené verejnosti.',
                2 => self::OTHERS,
            ],
        ],
        993 => [
            'name' => 'Evanjelický boží chrám Košice-Terasa',
            'fix_street' => 'Toryská 1/G',
            'fix_postcode' => '040 11',
            'body' => [
                0 => 'Evanjelický boží chrám na Terase je kostol Cirkevného zboru ECAV Košice. Stojí na Toryskej ulici na sídlisku Terasa. Slúži evanjelikom západnej časti Košíc.',
                1 => 'Konajú sa tu služby Božie aj ekumenické slávnosti, napríklad ekumenická ďakovná bohoslužba za úrodu, ktorú pripravujú cirkvi pôsobiace v Košiciach. Bohoslužby sú otvorené pre všetkých.',
                2 => self::OTHERS,
            ],
        ],
        992 => [
            'name' => 'Saleziánske mládežnícke stredisko Mamateyova',
            'postcode' => '851 04',
            'latitude' => 48.1173043,
            'longitude' => 17.1329588,
            'coordinates_source' => 'address',
            'body' => [
                0 => 'Saleziánske mládežnícke stredisko na Mamateyovej ulici pôsobí v Bratislave-Petržalke. Spravujú ho saleziáni don Bosca. Je centrom práce s deťmi a mladými.',
                1 => 'Konajú sa tu sväté omše, stretká, krúžky aj prezentácie, napríklad predstavenie knihy V službe Šuarom s večerom s misionárom. Podujatia pripravuje aj Vydavateľstvo Don Bosco a združenie Savio.',
                2 => self::OTHERS,
            ],
        ],
        991 => [
            'name' => 'Bratislave. Záštitu nad podujatím prevzala Gréckokatolícka eparchia Bratislava. Organizáto',
            'fix_name' => 'Gréckokatolícky eparchiálny úrad (Bratislava)',
            'fix_village_id' => 242,
            'fix_street' => 'Ulica 29. augusta 7',
            'fix_postcode' => '811 07',
            'latitude' => 48.1494471,
            'longitude' => 17.1166499,
            'coordinates_source' => 'address',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Gréckokatolícky eparchiálny úrad sídli na Ulici 29. augusta v Bratislave. Je sídlom Bratislavskej eparchie Gréckokatolíckej cirkvi. Má sálu na stretnutia a prednášky.',
                1 => 'V jeho sále sa konajú kolokviá a stretnutia, napríklad kolokvium o liturgickom speve byzantskej tradície k 90. výročiu zboru Kyrillomethodeon. Podujatia pripravuje eparchia s partnermi. Sú otvorené verejnosti.',
                2 => self::OTHERS,
            ],
        ],
        123 => [
            'name' => 'gotický kostolík sv. Jána Krstiteľa',
            'fix_name' => 'Katedrála sv. Jána Krstiteľa (Trnava)',
            'postcode' => '917 01',
            'latitude' => 48.3804983,
            'longitude' => 17.5883632,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Katedrála sv. Jána Krstiteľa je katedrálou Trnavskej arcidiecézy. Ranobarokový chrám na Univerzitnom námestí postavili v 17. storočí ako univerzitný kostol Trnavskej univerzity. Je jednou z najvýznamnejších barokových stavieb na Slovensku.',
                1 => 'Konajú sa tu arcidiecézne slávnosti, kňazské vysviacky a spomienkové sväté omše, napríklad pri výročí úmrtia biskupa Pavla Hnilicu. Katedrála je otvorená veriacim aj návštevníkom.',
                2 => self::OTHERS,
            ],
        ],
        988 => [
            'name' => 'Michalská 8, Bratislava',
            'fix_name' => 'Kaplnka sv. Kataríny (Bratislava)',
            'fix_street' => 'Michalská 8',
            'postcode' => '811 01',
            'latitude' => 48.144425,
            'longitude' => 17.1068441,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Kaplnka sv. Kataríny je gotická kaplnka na Michalskej ulici v historickom centre Bratislavy. Je zasvätená sv. Kataríne Alexandrijskej. Patrí k najstarším sakrálnym stavbám Starého Mesta.',
                1 => 'Konajú sa tu sväté omše, adorácie a chvály, napríklad pravidelné večery komunity Carry His Glory či adorácie dobrovoľníkov Domu Quo Vadis. Program je určený najmä mladým.',
                2 => self::OTHERS,
            ],
        ],
        987 => [
            'name' => 'kláštor redemptoristiek, Kežmarok',
            'fix_name' => 'Kláštor sestier redemptoristiek (Kežmarok)',
            'postcode' => '060 01',
            'body' => [
                0 => 'Kláštor sestier redemptoristiek stojí na Kláštornej ulici v Kežmarku. Žijú v ňom kontemplatívne mníšky Rádu Najsvätejšieho Vykupiteľa. Ich život sa sústreďuje na modlitbu.',
                1 => 'Konajú sa tu duchovné obnovy, napríklad víkendové obnovy na rozlišovanie povolania, ktoré pripravuje farnosť Kežmarok. Obnovy ponúkajú ticho a modlitbu. Prihlasuje sa vopred.',
                2 => self::OTHERS,
            ],
        ],
        986 => [
            'name' => 'Sigord, Prešov',
            'fix_name' => 'Centrum pre rodinu Sigord',
            'fix_village_id' => 4130,
            'fix_street' => 'Sigord 134',
            'fix_postcode' => '082 52',
            'fix_website' => null,
            'latitude' => 48.9559394,
            'longitude' => 21.3913847,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Centrum pre rodinu Sigord leží v lesnom prostredí pri Zlatej Bani neďaleko Prešova. Patrí Košickej arcidiecéze. Ponúka ubytovanie a programy pre rodiny.',
                1 => 'Konajú sa tu kurzy prípravy na manželstvo, víkendy pre manželov a konferencie, napríklad konferencia pre mužov Spoznaj svoju silu. Programy pripravujú aj spoločenstvá ako Skala či Chemin Neuf.',
                2 => self::OTHERS,
            ],
        ],
        985 => [
            'name' => 'Hurbanovo námestie (Bratislava)',
            'fix_name' => 'Dom Quo Vadis (Hurbanovo námestie 1)',
            'fix_street' => 'Hurbanovo námestie 1',
            'fix_website' => null,
            'latitude' => 48.1462689,
            'longitude' => 17.1068034,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Dom Quo Vadis je kresťanské centrum v centre Bratislavy. Vstupuje sa doň z Hurbanovho námestia aj z Veternej ulice. Patrí Bratislavskej arcidiecéze.',
                1 => 'Konajú sa tu besedy, prednášky, tanečné večery a adorácie, napríklad beseda o knihe Hľadajúci stratených či tanečné večery klubu Iskra. Podujatia sú otvorené pre všetkých.',
                2 => self::OTHERS,
            ],
        ],
        984 => [
            'name' => 'Petržalská 21, Kežmarok',
            'fix_name' => 'ZŠ s MŠ sv. Kríža (Kežmarok)',
            'fix_website' => null,
            'postcode' => '060 01',
            'body' => [
                0 => 'Základná škola s materskou školou sv. Kríža je cirkevná škola v Kežmarku. Sídli na Petržalskej ulici. Zriaďuje ju Spišská diecéza.',
                1 => 'V jej priestoroch sa konajú aj podujatia pre dospelých, napríklad Diecézna učenícka škola, dvojročná formácia Spišskej diecézy. Stretnutia sa konajú večer.',
                2 => self::OTHERS,
            ],
        ],
        982 => [
            'name' => 'Námestie sv. Petra',
            'fix_postcode' => '00120',
            'country' => 'Vatikán',
            'latitude' => 41.90227,
            'longitude' => 12.45728,
            'coordinates_source' => 'manual',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Námestie sv. Petra je námestie pred Bazilikou sv. Petra vo Vatikáne. Obklopuje ho Berniniho kolonáda. Je miestom stretnutí veriacich s pápežom.',
                1 => 'Konajú sa tu pápežské omše, generálne audiencie a modlitby, napríklad októbrové ruženec za pokoj s pochodňami. Odtiaľ pápež udeľuje požehnanie Urbi et Orbi.',
                2 => self::OTHERS,
            ],
        ],
        981 => [
            'name' => 'Námestie slobody 13, Skalica',
            'fix_name' => 'Záhorské múzeum (Skalica)',
            'postcode' => '909 01',
            'body' => [
                0 => 'Záhorské múzeum je regionálne múzeum Trnavského samosprávneho kraja v Skalici. Sídli na Námestí slobody. Približuje dejiny a ľudovú kultúru Záhoria.',
                1 => 'Konajú sa tu výstavy, prednášky a prezentácie kníh, napríklad prezentácia knihy prof. Evy Fordinálovej O sile slovenského slova. Podujatia sú otvorené verejnosti.',
                2 => self::OTHERS,
            ],
        ],
        979 => [
            'name' => 'Diecézne sanktuárium',
            'fix_name' => 'Diecézne sanktuárium Ružencovej Panny Márie (Obišovce)',
            'postcode' => '044 81',
            'latitude' => 48.8610724,
            'longitude' => 21.2429852,
            'coordinates_source' => 'venue',
            'body' => [
                0 => 'Diecézne sanktuárium v Obišovciach je pútnický kostol Ružencovej Panny Márie z 17. storočia. Za diecéznu svätyňu Košickej arcidiecézy ho vyhlásili v roku 2011. Uctieva sa tu milostivý obraz Obišovskej Panny Márie.',
                1 => 'Najväčšou udalosťou je odpustová púť na prvú októbrovú nedeľu s tradíciou vyše 300 rokov. Konajú sa tu aj fatimské soboty.',
                2 => self::OTHERS,
            ],
        ],
        977 => [
            'name' => 'Dom kultúry',
            'fix_name' => 'Dom kultúry Cífer',
            'body' => [
                0 => 'Dom kultúry v Cíferi je obecný kultúrny dom na Ulici sv. Petra a Pavla. Slúži obyvateľom obce na kultúrne a spoločenské podujatia. Má divadelnú sálu.',
                1 => 'Konajú sa tu koncerty, napríklad Jesenný cyklus koncertov Spolku priateľov hudby v Cíferi, a jarný festival Cíferská hudobná jar. Koncerty sú otvorené verejnosti.',
                2 => self::OTHERS,
            ],
        ],
        973 => [
            'name' => 'Švábska 22/A, Prešov',
            'fix_name' => 'M-Aréna (Prešov)',
            'fix_street' => 'Švábska 22/A',
            'postcode' => '080 05',
            'latitude' => 48.9752312,
            'longitude' => 21.2623996,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'M-Aréna je eventové centrum na Švábskej ulici v Prešove. Využíva ho katolícke spoločenstvo Marana Tha. V areáli je aj Komunitno-pastoračné centrum sv. Jána Pavla II.',
                1 => 'Konajú sa tu konferencie, chvály a modlitbové stretnutia, napríklad konferencia Oheň, modlitbové stretnutia mužov či modlitba tancom. Programy sú otvorené pre všetkých.',
                2 => self::OTHERS,
            ],
        ],
        970 => [
            'name' => 'Cintorín',
            'fix_name' => 'Cintorín na Kamennej ceste (Trnava)',
            'body' => [
                0 => 'Cintorín na Kamennej ceste je mestský cintorín v Trnave. Nachádza sa v časti Za štrekou. Slúži obyvateľom celého mesta.',
                1 => 'Na cintoríne stojí Pamätník nenarodeným deťom, ktorý po požiari obnovili a požehnali v roku 2026. Konajú sa tu aj spomienkové pobožnosti, najmä okolo Pamiatky zosnulých.',
                2 => self::OTHERS,
            ],
        ],
        968 => [
            'name' => 'Mlynská 1, Vrútky',
            'fix_name' => 'Kostol sv. Jána Krstiteľa (Vrútky)',
            'fix_street' => 'Mlynská 1',
            'postcode' => '038 61',
            'body' => [
                0 => 'Kostol sv. Jána Krstiteľa je farský kostol vo Vrútkach na Mlynskej ulici. Slúži Farnosti Vrútky. Je zasvätený sv. Jánovi Krstiteľovi.',
                1 => 'Okrem bohoslužieb sa tu konajú kultúrne podujatia, napríklad literárno-hudobné pásmo Ja František k 800. výročiu smrti sv. Františka. Podujatia sú otvorené verejnosti.',
                2 => self::OTHERS,
            ],
        ],
        967 => [
            'name' => 'Poprade. Hlavným celebrantom bude spišský biskup Mons. František Trstenský. Po svätej omši bude',
            'fix_name' => 'Konkatedrála Sedembolestnej Panny Márie (Poprad)',
            'fix_street' => 'Mnoheľova 834/24',
            'fix_postcode' => '058 01',
            'latitude' => 49.0561826,
            'longitude' => 20.2974947,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Konkatedrála Sedembolestnej Panny Márie je moderný chrám v centre Popradu na Mnoheľovej ulici. Je konkatedrálou Spišskej diecézy. Je zasvätená Sedembolestnej Panne Márii.',
                1 => 'Konajú sa tu diecézne slávnosti a podujatia, napríklad diskusia o projekte Biblia za rok vydavateľstva novaJAR so svätou omšou, ktorú celebroval spišský biskup. Konkatedrála je otvorená veriacim.',
                2 => self::OTHERS,
            ],
        ],
        964 => [
            'name' => 'kláštor',
            'fix_name' => 'Kláštor sestier Služobníčok (Svit)',
            'fix_street' => 'Nábrežie Jána Pavla II. 697/2',
            'latitude' => 49.0542896,
            'longitude' => 20.2105804,
            'coordinates_source' => 'address',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Kláštor sestier Služobníčok Najsvätejšej Panny Márie Nepoškvrnene počatej stojí na Nábreží Jána Pavla II. vo Svite. Je jedným z domov kongregácie na Slovensku. Sestry sa venujú aj mladým.',
                1 => 'Konajú sa tu duchovné víkendy pre dievčatá, ktoré uvažujú o duchovnom povolaní, napríklad víkend Pripravme cestu Pánovi. Program spája modlitbu a rozhovor so sestrami.',
                2 => self::OTHERS,
            ],
        ],
        963 => [
            'name' => 'Kostol Kráľovnej rodiny',
            'body' => [
                0 => 'Kostol Kráľovnej rodiny je rímskokatolícky kostol na Teplickej ulici v Bratislave-Novom Meste. Postavili ho v rokoch 1997 – 1999. Posvätil ho arcibiskup Ján Sokol.',
                1 => 'Okrem bohoslužieb sa tu konajú výstavy a kultúrne podujatia farnosti. Kostol slúži veriacim z okolia Teplickej ulice a Vinohradov.',
                2 => self::OTHERS,
            ],
        ],
        961 => [
            'name' => 'Kostol Všetkých svätých',
            'body' => [
                0 => 'Kostol Všetkých svätých je farský kostol v Hlohovci. Stojí neďaleko Františkánskeho námestia v centre mesta. Je hlavným kostolom hlohovskej farnosti.',
                1 => 'Konajú sa tu bohoslužby a farské slávnosti. Je aj cieľom pútí, napríklad pešej púte z Piešťan do Hlohovca v Roku sv. Františka.',
                2 => self::OTHERS,
            ],
        ],
        957 => [
            'name' => 'Kultúrny dom',
            'fix_name' => 'Kultúrny dom Vrbové',
            'body' => [
                0 => 'Kultúrny dom vo Vrbovom je mestský kultúrny dom na Ulici Pavla Jantauscha. Slúži na kultúrne a spoločenské podujatia mesta. Prenajímajú si ho aj spolky a cirkvi.',
                1 => 'Konajú sa tu napríklad Mužské ekumenické konferencie, ktoré pripravuje OZ Vykročiť s rímskokatolíckou farnosťou Vrbové. Konferencie trvajú celý deň.',
                2 => self::OTHERS,
            ],
        ],
        954 => [
            'name' => 'Športovo-zábavný areál',
            'fix_name' => 'Športovo-zábavný areál Alejová (Košice)',
            'body' => [
                0 => 'Športovo-zábavný areál na Alejovej ulici sa nachádza v Košiciach-Juhu. Má ihriská a priestor na športové podujatia. Slúži obyvateľom mesta.',
                1 => 'Konajú sa tu napríklad charitatívne futbalové turnaje Odkopnime predsudky, ktoré pripravuje Arcidiecézna charita Košice. Turnaje spájajú ľudí bez domova, dobrovoľníkov a verejnosť.',
                2 => self::OTHERS,
            ],
        ],
        953 => [
            'name' => 'Kostol Sedembolestnej Panny Márie',
            'body' => [
                0 => 'Kostol Sedembolestnej Panny Márie stojí vo Vysokej nad Uhom v okrese Michalovce. Obec je rodiskom blahoslavenej Anny Kolesárovej, mučeníčky čistoty. Jej hrob je cieľom pútnikov.',
                1 => 'Konajú sa tu púte a spomienky na bl. Annu Kolesárovú. Cieľom je aj Beh S Ankou za život, ktorý spája Košice s jej rodiskom.',
                2 => self::OTHERS,
            ],
        ],
        951 => [
            'name' => 'Butkov',
            'fix_name' => 'Hora Butkov (Ladce)',
            'fix_website' => null,
            'body' => [
                0 => 'Butkov je vrch nad Ladcami v Strážovských vrchoch. Na jeho skalných terasách vzniklo Skalné sanktuárium Božieho milosrdenstva s veľkými krížmi krížovej cesty. Vrch je známy aj vápencovým lomom.',
                1 => 'Konajú sa tu púte a Večery milosrdenstva, ktoré pripravuje farnosť Butkov s cementárňou Ladce. Na Butkov vedie pútnická cesta.',
                2 => self::OTHERS,
            ],
        ],
        949 => [
            'name' => 'Rooseveltova 12, Košice',
            'fix_name' => 'Univerzitné pastoračné centrum (Košice)',
            'fix_street' => 'Rooseveltova 12',
            'postcode' => '040 01',
            'latitude' => 48.7188077,
            'longitude' => 21.2616,
            'coordinates_source' => 'address',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Univerzitné pastoračné centrum v Košiciach sídli na Rooseveltovej ulici. Slúži vysokoškolákom košických univerzít. Ponúka sväté omše, stretká a rozhovory.',
                1 => 'Konajú sa tu aj stretnutia iných spoločenstiev, napríklad modlitbové stretnutia slobodných túžiacich po manželstve Nádej. Stretnutia sú otvorené pre všetkých.',
                2 => self::OTHERS,
            ],
        ],
        948 => [
            'name' => 'Katolícke gymnázium Štefana Moysesa, Banská Bystrica',
            'fix_name' => 'Katolícke gymnázium Štefana Moysesa (Banská Bystrica)',
            'body' => [
                0 => 'Katolícke gymnázium Štefana Moysesa je cirkevné gymnázium v Banskej Bystrici. Nesie meno biskupa Štefana Moysesa, prvého predsedu Matice slovenskej. Sídli na Záhradnej ulici.',
                1 => 'Okrem vyučovania sa tu konajú športové a charitatívne podujatia, napríklad Futbal za tých, ktorí chýbajú, ktorý pripravila Poradňa Alexis. Podujatia sú otvorené aj verejnosti.',
                2 => self::OTHERS,
            ],
        ],
        945 => [
            'name' => 'Kostol svätého Jakuba (Trnava)',
            'fix_website' => null,
            'body' => [
                0 => 'Kostol sv. Jakuba je františkánsky kostol na Františkánskej ulici v Trnave. Pri kostole stojí františkánsky kláštor. Patrí k historickým pamiatkam mesta.',
                1 => 'Konajú sa tu bohoslužby a slávnosti, ktoré prenáša aj Rádio Lumen. Františkáni pripravujú aj podujatia pre verejnosť, napríklad Františkánske dvere dokorán.',
                2 => self::OTHERS,
            ],
        ],
        944 => [
            'name' => 'Pútnicky farský kostol Panny Márie Snežnej',
            'fix_name' => 'Pútnický kostol Panny Márie Snežnej (Bratislava)',
            'fix_street' => 'Hlboká cesta',
            'fix_postcode' => '811 04',
            'latitude' => 48.1577708,
            'longitude' => 17.0987116,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Pútnický kostol Panny Márie Snežnej stojí pri Hlbokej ceste pod Kalváriou v Bratislave. Je farským kostolom farnosti Panny Márie Snežnej. Je to mariánske pútnické miesto.',
                1 => 'Konajú sa tu bohoslužby, púte a výstavy, napríklad výstava tapisérií k storočnici narodenia výtvarníčky Evy Trizuljakovej. Podujatia pripravuje farnosť.',
                2 => self::OTHERS,
            ],
        ],
        941 => [
            'name' => 'Svätojakubská cesta na Slovensku',
            'body' => [
                0 => 'Svätojakubská cesta na Slovensku je značená pútnická trasa, ktorá je súčasťou siete ciest do Santiaga de Compostela. Vedie z Košíc cez Spiš a Liptov až do Bratislavy. Obnovuje ju združenie Confraternity Sancti Iacobi.',
                1 => 'Konajú sa tu spoločné putovania, napríklad Jacobeo, Jacobeo YOUTH, Jakubské trojdnie či Putovanie zmierenia. Pútnici dostávajú pútnický preukaz.',
                2 => self::OTHERS,
            ],
        ],
        938 => [
            'name' => 'hotel Belvedere',
            'fix_name' => 'Hotel Belvedere (Zakopané)',
            'city' => 'zakopane',
            'fix_street' => 'Droga do Białego 3',
            'fix_postcode' => '34-500',
            'country' => 'Poľsko',
            'latitude' => 49.2848922,
            'longitude' => 19.9565909,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Hotel Belvedere je horský hotel v Zakopanom pod poľskými Tatrami. Ponúka ubytovanie a konferenčné priestory. Leží na okraji mesta pri Białej doline.',
                1 => 'Televízia LUX tu pripravila zimnú lyžovačku pre rodiny s deťmi. Pobyt spájal lyžovanie, oddych a program pre rodiny.',
                2 => self::OTHERS,
            ],
        ],
        936 => [
            'name' => 'Bazilika sv. Klementa',
            'fix_street' => 'Via di San Giovanni in Laterano',
            'fix_postcode' => '00184',
            'country' => 'Taliansko',
            'latitude' => 41.8891952,
            'longitude' => 12.4975379,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Bazilika sv. Klementa je starobylá bazilika v Ríme neďaleko Kolosea. Je v nej hrob sv. Cyrila, ktorý zomrel v Ríme v roku 869. Spravujú ju írski dominikáni.',
                1 => 'Každý rok 14. februára sa tu stretávajú slovanské národy pri slávnosti sv. Cyrila a Metoda. Na slávnosti sa zúčastňujú aj Slováci žijúci v Ríme.',
                2 => self::OTHERS,
            ],
        ],
        935 => [
            'name' => 'Kostol Návštevy Panny Márie',
            'fix_name' => 'Kostol Navštívenia Panny Márie (milosrdní bratia)',
            'fix_street' => 'Námestie SNP 10',
            'fix_postcode' => '811 06',
            'latitude' => 48.1460496,
            'longitude' => 17.110459,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Kostol Navštívenia Panny Márie je kostol rehole milosrdných bratov na Námestí SNP v Bratislave. Pri ňom stojí ich nemocnica. Je to barokový kostol z 18. storočia.',
                1 => 'Konajú sa tu bohoslužby a slávnosti spojené so službou chorým, napríklad Svetový deň chorých. Kostol je otvorený veriacim aj pacientom nemocnice.',
                2 => self::OTHERS,
            ],
        ],
        934 => [
            'name' => 'Kostol svätého Kríža (Devín)',
            'fix_website' => null,
            'body' => [
                0 => 'Kostol sv. Kríža je farský kostol v Bratislave-Devíne. Pôvodne gotická stavba bola viackrát prestavaná. Stojí neďaleko Devínskeho hradu.',
                1 => 'Konajú sa tu bohoslužby a cyrilo-metodské slávnosti, napríklad spomienka na výročie úmrtia sv. Konštantína-Cyrila, ktorú pripravuje združenie Dedičstvo otcov. Devín je spojený s veľkomoravskou históriou.',
                2 => self::OTHERS,
            ],
        ],
        931 => [
            'name' => 'Chrám Narodenia presvätej Bohorodičky (Košice)',
            'fix_street' => 'Dominikánske námestie 2',
            'fix_postcode' => '040 01',
            'latitude' => 48.72053,
            'longitude' => 21.2528161,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Katedrálny chrám Narodenia Presvätej Bohorodičky je katedrálou Gréckokatolíckej eparchie Košice. Stojí na Dominikánskom námestí v Košiciach. Je sídelným chrámom košického eparchu.',
                1 => 'Konajú sa tu archijerejské liturgie, slávnosti eparchie a stretnutia mladých, napríklad Kvetný víkend Rady pre mládež. Chrám je otvorený veriacim aj návštevníkom.',
                2 => self::OTHERS,
            ],
        ],
        929 => [
            'name' => 'Evanjelický kostol',
            'fix_name' => 'Evanjelický kostol (Banská Bystrica)',
            'body' => [
                0 => 'Evanjelický kostol na Lazovnej ulici je hlavný chrám Cirkevného zboru ECAV Banská Bystrica. Stojí v centre mesta. Postavili ho v 19. storočí.',
                1 => 'Konajú sa tu služby Božie, koncerty a slávnosti, ktoré niekedy prenáša aj RTVS. Kostol je otvorený pre všetkých.',
                2 => self::OTHERS,
            ],
        ],
        927 => [
            'name' => 'balkón biskupskej rezidencie',
            'fix_name' => 'Biskupská rezidencia (Rožňava)',
            'body' => [
                0 => 'Biskupská rezidencia v Rožňave je sídlom rožňavského biskupa. Stojí v centre mesta neďaleko katedrály. Je sídlom Rožňavskej diecézy.',
                1 => 'Z balkóna rezidencie viedol biskup Stanislav Stolárik mariánsku pobožnosť, ktorú mohli veriaci sledovať online. Biskupstvo pripravuje aj ďalšie diecézne podujatia.',
                2 => self::OTHERS,
            ],
        ],
        926 => [
            'name' => 'Kostol Sedembolestnej Panny Márie (Vajnory)',
            'fix_village_id' => 242,
            'fix_street' => 'Baničova',
            'fix_postcode' => '831 07',
            'latitude' => 48.2064244,
            'longitude' => 17.2059318,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Kostol Sedembolestnej Panny Márie je farský kostol v Bratislave-Vajnoroch. Vajnory sú rodiskom blahoslaveného Titusa Zemana. Kostol slúži farnosti Vajnory.',
                1 => 'Konajú sa tu bohoslužby a slávnosti, napríklad výročia blahorečenia Titusa Zemana. Kostol je spojený aj s mariánskou úctou.',
                2 => self::OTHERS,
            ],
        ],
        959 => [
            'name' => 'Zoom Communications',
            'fix_name' => 'Online (Zoom)',
            'fix_street' => null,
            'fix_postcode' => null,
            'fix_website' => null,
            'body' => [
                0 => 'Online (Zoom) označuje podujatia, ktoré prebiehajú cez videokonferenčnú aplikáciu Zoom. Pripojiť sa dá z domu cez počítač alebo mobil. Odkaz dostanú prihlásení účastníci.',
                1 => 'Takto prebiehajú webináre a online stretnutia, napríklad biblický webinár Katolíckeho biblického diela Keď Abrahám riešil veci po svojom.',
                2 => self::OTHERS,
            ],
        ],
    ];

    /** Zdroj → cieľ: ten istý priestor pod inými názvami. */
    private const MERGES = [
        989 => [123, 'Univerzitné námestie 602/3, Trnava', 'Katedrála sv. Jána Krstiteľa (Trnava)'],
        983 => [976, 'sídlisko Juh, Poprad', 'Kostol svätých Cyrila a Metoda (Poprad)'],
        971 => [990, 'Za Sokolovňou', 'Kalvársky vrch'],
        946 => [996, 'Slovensku Mons. Nicolu Giraso', 'Koncertná sieň Klarisky'],
        950 => [37, 'Kalvária 1, Nitra', 'Kalvária (Nitra)'],
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
