<?php

use App\Models\Venue;
use App\Services\Resources\ResourceMerger;
use App\Services\SystemLog\Recorder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Archivované miesta, 2. dávka (november 2026): ďalších 60 konkrétnych
 * archivovaných miest podľa počtu podujatí (väčšinou s jedným podujatím).
 * Miesta dostanú skutočný názov, popis, opravenú obec a súradnice; ostávajú
 * archivované. Nezmyselné záznamy (spam, podujatia priradené k inému miestu,
 * útržky ako „kostoloch") sa nemenia.
 *
 * Opravy: Krivá (rodisko bl. Zdenky) bola v Bratislave, amfiteáter v Námestove
 * sa volal „Amfiteáter Košice", „dome" v Piešťanoch mal súradnice na Novom
 * Zélande, kostoly v Ríme, Bruseli, Prahe a Brne mali slovenské obce či PSČ.
 *
 * Duplikáty sa zlúčia: #48 Klokoč → #82, #793 KCK FUGA → #1031,
 * #750 online → #959, #83 → #769 (Katarínka).
 *
 * Popis sa prepíše len vtedy, keď ešte nemá tri odseky; ulica, PSČ a web sa
 * dopĺňajú do prázdneho poľa (okrem `fix_*`). Miesto musí sedieť ID aj názvom.
 */
return new class extends Migration
{
    private const OTHERS = 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.';

    /** Zahraničné mestá (už existujú; vytvoria sa len ak chýbajú). */
    private const CITIES = [
        'brusel' => ['name' => 'Brusel', 'zip' => '1000'],
        'brno' => ['name' => 'Brno', 'zip' => '603 00'],
        'praha-cesko' => ['name' => 'Praha (Česko)', 'zip' => '110 00'],
    ];

    /** @var array<int, array<string, mixed>> */
    private const VENUES = [
        16 => [
            'name' => 'Krivá',
            'fix_name' => 'Krivá (rodisko bl. Zdenky Schelingovej)',
            'fix_village_id' => 1672,
            'fix_street' => null,
            'fix_postcode' => '027 55',
            'latitude' => 49.2723,
            'longitude' => 19.4669,
            'coordinates_source' => 'municipality',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Krivá je obec na Orave, rodisko blahoslavenej sestry Zdenky Schelingovej. Obec si jej pamiatku uchováva pri farskom kostole.',
                1 => 'Konajú sa tu duchovné stretnutia s bl. Zdenkou, najmä okolo jej sviatku 30. júla. Prichádzajú pútnici z celého Slovenska.',
                2 => self::OTHERS,
            ],
        ],
        22 => [
            'name' => 'Šarišský hrad',
            'fix_name' => 'Šarišský hrad (Veľký Šariš)',
            'latitude' => 49.0523302,
            'longitude' => 21.1761494,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Šarišský hrad je zrúcanina hradu na vrchu nad Veľkým Šarišom pri Prešove. Patril k najväčším hradom na Slovensku.',
                1 => 'Konajú sa tu podujatia pod holým nebom, napríklad Deň rodiny Východného dištriktu ECAV.',
                2 => self::OTHERS,
            ],
        ],
        25 => [
            'name' => 'miestny cintorín',
            'fix_name' => 'Cintorín v Modre (hrob Ľudovíta Štúra)',
            'body' => [
                0 => 'Na cintoríne v Modre je pochovaný Ľudovít Štúr, ktorý v Modre zomrel v roku 1856. Jeho hrob je národnou kultúrnou pamiatkou.',
                1 => 'Konajú sa tu pietne spomienky, napríklad pri 170. výročí úmrtia Ľudovíta Štúra, ktoré pripravuje Múzeum Ľudovíta Štúra.',
                2 => self::OTHERS,
            ],
        ],
        29 => [
            'name' => 'brána väznice',
            'fix_name' => 'Brána väznice (Leopoldov)',
            'body' => [
                0 => 'Väznica v Leopoldove vznikla v bývalej pevnosti. Počas komunizmu v nej väznili aj kňazov a rehoľníkov. V 17. storočí odtiaľ odvliekli evanjelických kazateľov na galeje.',
                1 => 'Pri bráne väznice sa konajú pietne zhromaždenia, napríklad zhromaždenie Z vlasti na galeje, ktoré pripravuje Predsedníctvo ECAV.',
                2 => self::OTHERS,
            ],
        ],
        31 => [
            'name' => 'Evanjelický a. v. kostol',
            'fix_name' => 'Evanjelický kostol na Vrbici (Liptovský Mikuláš)',
            'latitude' => 49.0781443,
            'longitude' => 19.6169299,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Evanjelický a. v. kostol stojí na Vrbickej ulici v Liptovskom Mikuláši. Patrí Cirkevnému zboru ECAV Liptovský Mikuláš.',
                1 => 'Konajú sa tu služby Božie a slávnosti, napríklad pri výročí Augsburského vyznania.',
                2 => self::OTHERS,
            ],
        ],
        34 => [
            'name' => 'Chrám Povýšenia vznešeného a životodarného kríža',
            'fix_name' => 'Katedrála Povýšenia vznešeného a životodarného Kríža (Bratislava)',
            'fix_street' => 'Ulica 29. augusta',
            'fix_postcode' => '811 07',
            'body' => [
                0 => 'Katedrála Povýšenia vznešeného a životodarného Kríža je katedrálou Bratislavskej gréckokatolíckej eparchie. Stojí na Ulici 29. augusta v Bratislave.',
                1 => 'Konajú sa tu archijerejské liturgie a slávnosti, napríklad spomienka na sv. Gorazda na sviatok svätých Sedmopočetníkov. Spieva tu zbor Kyrillomethodeon.',
                2 => self::OTHERS,
            ],
        ],
        35 => [
            'name' => 'Aréne Poprad dynamickým a vizuálne silným otváracím ceremoniálom',
            'fix_name' => 'Aréna Poprad',
            'fix_street' => 'Uherova 4680/2',
            'fix_postcode' => '058 01',
            'latitude' => 49.0466299,
            'longitude' => 20.2909182,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Aréna Poprad je multifunkčná hala na Uherovej ulici v Poprade. Hostí šport, koncerty a veľké podujatia.',
                1 => 'Konal sa tu napríklad otvárací ceremoniál Národného stretnutia mládeže P26.',
                2 => self::OTHERS,
            ],
        ],
        36 => [
            'name' => 'Mechovička',
            'fix_name' => 'Mechovička (Rajčany)',
            'body' => [
                0 => 'Mechovička je mariánske pútnické miesto s prameňom pri obci Rajčany v okrese Topoľčany.',
                1 => 'Konajú sa tu mariánske púte s duchovným programom, ktoré pripravuje farnosť Nadlice.',
                2 => self::OTHERS,
            ],
        ],
        38 => [
            'name' => 'Kultúrne stredisko',
            'fix_name' => 'Kultúrne stredisko (Močenok)',
            'body' => [
                0 => 'Kultúrne stredisko v Močenku slúži obci na kultúrne a spoločenské podujatia.',
                1 => 'Konajú sa tu výstavy, napríklad filatelistická výstava Za pokoj a mier na celom svete, a podujatia festivalu Gorazdov Močenok.',
                2 => self::OTHERS,
            ],
        ],
        39 => [
            'name' => 'Farský kostol sv. Klimenta',
            'fix_name' => 'Farský kostol sv. Klimenta (Močenok)',
            'body' => [
                0 => 'Farský kostol sv. Klimenta stojí v Močenku. Slúži farnosti Močenok.',
                1 => 'Konajú sa tu bohoslužby a slávnosti, napríklad počas festivalu kresťanského divadla Gorazdov Močenok.',
                2 => self::OTHERS,
            ],
        ],
        41 => [
            'name' => 'Amfiteáter Košice',
            'fix_name' => 'Amfiteáter Námestovo',
            'fix_village_id' => 2295,
            'fix_postcode' => '029 01',
            'latitude' => 49.4095,
            'longitude' => 19.4804,
            'coordinates_source' => 'municipality',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Amfiteáter v Námestove leží pri Oravskej priehrade. Slúži na festivaly a koncerty pod holým nebom.',
                1 => 'Každé leto sa tu koná kresťanský festival Verím Pane, ktorý má vyše tridsať ročníkov.',
                2 => self::OTHERS,
            ],
        ],
        42 => [
            'name' => 'Nízke Tatry',
            'fix_name' => 'Campfest – Nízke Tatry (Kráľova Lehota)',
            'body' => [
                0 => 'Pri Kráľovej Lehote pod Nízkymi Tatrami sa každé leto koná kresťanský festival Campfest.',
                1 => 'Festival pripravuje Mládež pre Krista. Spája hudbu, prednášky a spoločenstvo mladých.',
                2 => self::OTHERS,
            ],
        ],
        44 => [
            'name' => 'KPC Jána Pavla II',
            'fix_name' => 'Komunitno-pastoračné centrum sv. Jána Pavla II. (Prešov)',
            'fix_street' => 'Švábska 22',
            'fix_postcode' => '080 05',
            'body' => [
                0 => 'Komunitno-pastoračné centrum sv. Jána Pavla II. stojí na Švábskej ulici v Prešove, v areáli M-Arény. Využíva ho spoločenstvo Marana Tha.',
                1 => 'Konajú sa tu kurzy a stretnutia, napríklad kurz Dejiny spásy.',
                2 => self::OTHERS,
            ],
        ],
        45 => [
            'name' => 'Kuzmányho 9, pri komunite Šolských sestier de Notre Dame',
            'fix_name' => 'Komunita Školských sestier de Notre Dame (Piešťany)',
            'fix_street' => 'Kuzmányho 9',
            'fix_postcode' => '921 01',
            'latitude' => 48.5959133,
            'longitude' => 17.8353366,
            'coordinates_source' => 'address',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Komunita Školských sestier de Notre Dame žije na Kuzmányho ulici v Piešťanoch.',
                1 => 'Sestry tu pripravujú duchovné obnovy, napríklad obnovu pre ženy Nezlomná.',
                2 => self::OTHERS,
            ],
        ],
        47 => [
            'name' => 'Hviezdoslavovej ulici. Organizátorom podujatia je Tatranská galéria',
            'fix_name' => 'Tatranská galéria – Elektráreň (Poprad)',
            'fix_street' => 'Hviezdoslavova 12',
            'fix_postcode' => '058 01',
            'latitude' => 49.0583806,
            'longitude' => 20.2978771,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Tatranská galéria v Poprade sídli v budove bývalej mestskej elektrárne na Hviezdoslavovej ulici. Je regionálnou galériou Prešovského samosprávneho kraja.',
                1 => 'Pripravuje výstavy, napríklad výstavu Farba viery, ktorá otvára dialóg medzi vierou a umením.',
                2 => self::OTHERS,
            ],
        ],
        64 => [
            'name' => 'hora Zvir',
            'fix_name' => 'Hora Zvir (Litmanová)',
            'latitude' => 49.3921694,
            'longitude' => 20.6334492,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Hora Zvir pri Litmanovej je gréckokatolícke mariánske pútnické miesto Prešovskej archieparchie. Spája sa so zjaveniami Panny Márie v 90. rokoch.',
                1 => 'Konajú sa tu púte a duchovné obnovy ctiteľov Presvätej Bohorodičky, najmä v lete.',
                2 => self::OTHERS,
            ],
        ],
        70 => [
            'name' => 'farský chrám Zosnutia presvätej Bohorodičky',
            'fix_name' => 'Chrám Zosnutia Presvätej Bohorodičky (Dvorianky)',
            'body' => [
                0 => 'Gréckokatolícky chrám Zosnutia Presvätej Bohorodičky stojí v obci Dvorianky v okrese Trebišov.',
                1 => 'Konajú sa tu liturgie a spomienky, napríklad na kňaza Emila Zorvana pri výročí jeho úmrtia.',
                2 => self::OTHERS,
            ],
        ],
        97 => [
            'name' => 'Letný amfiteáter',
            'fix_name' => 'Letný amfiteáter (Dudince)',
            'latitude' => 48.165495,
            'longitude' => 18.8822672,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Letný amfiteáter v Dudinciach stojí v kúpeľnej časti na Kúpeľnej ulici.',
                1 => 'Konajú sa tu festivaly a koncerty, napríklad kresťanský festival HONTfest.',
                2 => self::OTHERS,
            ],
        ],
        738 => [
            'name' => 'Učebno-výcvikové zariadenie TU',
            'fix_name' => 'Učebno-výcvikové zariadenie TU (Herľany)',
            'body' => [
                0 => 'Učebno-výcvikové zariadenie Technickej univerzity v Košiciach leží v kúpeľnej obci Herľany. Ponúka ubytovanie a priestory na kurzy.',
                1 => 'Konali sa tu napríklad Silvestrovské duchovné cvičenia.',
                2 => self::OTHERS,
            ],
        ],
        740 => [
            'name' => 'Kultúrny dom',
            'fix_name' => 'Kultúrny dom (Dvorníky)',
            'body' => [
                0 => 'Kultúrny dom v Dvorníkoch slúži obci na kultúrne a spoločenské podujatia.',
                1 => 'Konal sa tu napríklad Mládežnícky ples Trnavskej arcidiecézy.',
                2 => self::OTHERS,
            ],
        ],
        743 => [
            'name' => 'Katolícky dom',
            'fix_name' => 'Katolícky dom (Dubnica nad Váhom)',
            'body' => [
                0 => 'Katolícky dom stojí na Námestí sv. Jakuba v Dubnici nad Váhom pri farskom kostole. Slúži farnosti na stretnutia a kurzy.',
                1 => 'Konajú sa tu kurzy, napríklad kurz Ave pre rozvedených a slobodných rodičov.',
                2 => self::OTHERS,
            ],
        ],
        752 => [
            'name' => 'Église Notre-Dame des Victoires au Sablon',
            'fix_name' => 'Kostol Notre-Dame du Sablon (Brusel)',
            'city' => 'brusel',
            'country' => 'Belgicko',
            'fix_street' => 'Rue de la Régence',
            'fix_postcode' => '1000',
            'latitude' => 50.8403333,
            'longitude' => 4.3561989,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Kostol Notre-Dame du Sablon je gotický kostol v centre Bruselu.',
                1 => 'Konala sa tu napríklad svätá omša za Európu, na ktorej Konferenciu biskupov Slovenska zastupoval biskup Jozef Haľko.',
                2 => self::OTHERS,
            ],
        ],
        753 => [
            'name' => 'Penzión Caritas',
            'fix_name' => 'Penzión Caritas (Dolný Smokovec)',
            'body' => [
                0 => 'Penzión Caritas v Dolnom Smokovci vo Vysokých Tatrách patrí Spišskej katolíckej charite.',
                1 => 'Konajú sa tu pobyty a stretnutia, napríklad silvestrovský pobyt pre slobodných.',
                2 => self::OTHERS,
            ],
        ],
        754 => [
            'name' => 'Gréckokatolícky arcibiskupský úrad',
            'fix_name' => 'Gréckokatolícky arcibiskupský úrad (Prešov)',
            'fix_street' => 'Hlavná 1',
            'fix_postcode' => '080 01',
            'latitude' => 48.993863,
            'longitude' => 21.2431405,
            'coordinates_source' => 'address',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Gréckokatolícky arcibiskupský úrad sídli na Hlavnej ulici v Prešove pri katedrále sv. Jána Krstiteľa.',
                1 => 'Konajú sa tu tlačové konferencie a stretnutia, napríklad s novým prešovským arcibiskupom.',
                2 => self::OTHERS,
            ],
        ],
        762 => [
            'name' => 'Evanjelická bohoslovecká fakulta Univerzity Komenského v Bratislave',
            'body' => [
                0 => 'Evanjelická bohoslovecká fakulta UK sídli na Bartókovej ulici v Bratislave. Pripravuje evanjelických farárov a teológov.',
                1 => 'Konajú sa tu konferencie, napríklad konferencie projektu Smrť v tranzite.',
                2 => self::OTHERS,
            ],
        ],
        769 => [
            'name' => 'Kostol a Kláštor sv. Kataríny Alexandrijskej',
            'fix_name' => 'Katarínka – ruiny kostola a kláštora sv. Kataríny',
            'body' => [
                0 => 'Katarínka sú ruiny kostola a františkánskeho kláštora sv. Kataríny Alexandrijskej v lesoch Malých Karpát pri Dechticiach a Naháči. Obnovuje ich občianske združenie Katarínka s dobrovoľníkmi.',
                1 => 'Konajú sa tu podujatia, napríklad Noc hradov a zrúcanín. Na Katarínku sa vrátila aj replika kalvárie.',
                2 => self::OTHERS,
            ],
        ],
        770 => [
            'name' => 'Dom Spoločnosti Ježišovej',
            'fix_name' => 'Dom Spoločnosti Ježišovej (Košice)',
            'body' => [
                0 => 'Dom Spoločnosti Ježišovej je jezuitský dom na Komenského ulici v Košiciach.',
                1 => 'Konajú sa tu spomienky a stretnutia, napríklad spomienka na tajné rehoľníčky za komunizmu.',
                2 => self::OTHERS,
            ],
        ],
        771 => [
            'name' => 'Kostol svätého Michala archanjela (Dražovce)',
            'fix_website' => null,
            'body' => [
                0 => 'Kostol sv. Michala archanjela je románsky kostolík na skalnom brale nad Dražovcami pri Nitre. Patrí k najstarším kostolom na Slovensku.',
                1 => 'Pri kostole požehnali napríklad míľnik Cyrilo-metodskej cesty pod Zoborom.',
                2 => self::OTHERS,
            ],
        ],
        774 => [
            'name' => 'hotel Olšanka',
            'fix_name' => 'Hotel Olšanka (Praha)',
            'city' => 'praha-cesko',
            'country' => 'Česko',
            'latitude' => 50.0829309,
            'longitude' => 14.456958,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Hotel Olšanka je hotel s kongresovými priestormi v pražskom Žižkove.',
                1 => 'Konalo sa tu napríklad Európske fórum o náboženskom vzdelávaní a umelej inteligencii.',
                2 => self::OTHERS,
            ],
        ],
        775 => [
            'name' => 'Kostol Gesù e Maria',
            'fix_name' => 'Kostol Gesù e Maria (Rím)',
            'country' => 'Taliansko',
            'fix_postcode' => '00187',
            'latitude' => 41.9079337,
            'longitude' => 12.4775995,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Kostol Gesù e Maria je barokový kostol na Via del Corso v Ríme.',
                1 => 'Konala sa tu spomienka na 55. výročie prenesenia pozostatkov Alojza Máriu Chmeľa, ktorú pripravilo slovenské veľvyslanectvo pri Svätej stolici.',
                2 => self::OTHERS,
            ],
        ],
        777 => [
            'name' => 'Organová sieň',
            'fix_name' => 'Organová sieň (Ružomberok)',
            'body' => [
                0 => 'Organová sieň je koncertná sála Katolíckej univerzity v Ružomberku.',
                1 => 'Konajú sa tu koncerty, napríklad koncert na sviatok sv. Cecílie, patrónky hudobníkov.',
                2 => self::OTHERS,
            ],
        ],
        779 => [
            'name' => 'Kostol svätého Jozefa',
            'fix_name' => 'Kostol sv. Jozefa (Zákamenné)',
            'body' => [
                0 => 'Kostol sv. Jozefa stojí v Zákamennom na Orave, rodisku biskupa Jána Vojtaššáka.',
                1 => 'Konajú sa tu bohoslužby a slávnosti, napríklad oslava sviatosti manželstva so spišským biskupom.',
                2 => self::OTHERS,
            ],
        ],
        781 => [
            'name' => 'Bazilika Panny Márie v Trastevere',
            'fix_name' => 'Bazilika Panny Márie v Trastevere (Rím)',
            'country' => 'Taliansko',
            'fix_postcode' => '00153',
            'latitude' => 41.8894229,
            'longitude' => 12.469501,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Bazilika Panny Márie v Trastevere je jedným z najstarších mariánskych kostolov v Ríme. Je známa stredovekými mozaikami.',
                1 => 'Modlí sa tu komunita Sant’Egidio, ktorá pozýva mladých napríklad počas Jubilea mládeže.',
                2 => self::OTHERS,
            ],
        ],
        783 => [
            'name' => 'Hodžovo námestie',
            'fix_name' => 'Hodžovo námestie (Bratislava)',
            'fix_website' => null,
            'fix_postcode' => '811 06',
            'body' => [
                0 => 'Hodžovo námestie je námestie pred Prezidentským palácom v Bratislave.',
                1 => 'Konajú sa tu verejné zhromaždenia a brífingy, napríklad k Národnému pochodu Hrdí na rodinu.',
                2 => self::OTHERS,
            ],
        ],
        784 => [
            'name' => 'kláštor v Maloměřiciach',
            'fix_name' => 'Kláštor sestier františkánok (Brno-Maloměřice)',
            'city' => 'brno',
            'country' => 'Česko',
            'fix_postcode' => '614 00',
            'latitude' => 49.2133204,
            'longitude' => 16.6434859,
            'coordinates_source' => 'address',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Kláštor sestier františkánok Premenenia Pána stojí na Karlovej ulici v brnianskych Maloměřiciach.',
                1 => 'Sestry pozývajú ženy na duchovné pobyty v kláštore.',
                2 => self::OTHERS,
            ],
        ],
        788 => [
            'name' => 'Kostol Najsvätejšieho Mena Ježišovho',
            'fix_name' => 'Kostol Il Gesù (Rím)',
            'country' => 'Taliansko',
            'fix_street' => 'Piazza del Gesù',
            'fix_postcode' => '00186',
            'latitude' => 41.8958842,
            'longitude' => 12.4798212,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Kostol Il Gesù je hlavný kostol jezuitov v Ríme. Je v ňom pochovaný sv. Ignác z Loyoly.',
                1 => 'Konajú sa tu jezuitské slávnosti, napríklad diakonská vysviacka slovenského jezuitu.',
                2 => self::OTHERS,
            ],
        ],
        790 => [
            'name' => 'dome',
            'fix_name' => 'Exercičný dom jezuitov (Piešťany)',
            'latitude' => 48.5918,
            'longitude' => 17.8273,
            'coordinates_source' => 'municipality',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Jezuiti v Piešťanoch ponúkajú duchovné cvičenia vo svojom dome.',
                1 => 'Ignaciánske duchovné cvičenia sa konajú v rôznych termínoch počas roka.',
                2 => self::OTHERS,
            ],
        ],
        792 => [
            'name' => 'kostol',
            'fix_name' => 'Drevený artikulárny kostol (Svätý Kríž)',
            'body' => [
                0 => 'Drevený evanjelický artikulárny kostol vo Svätom Kríži je najväčším dreveným kostolom v strednej Európe. Preniesli ho sem z Paludze, ktorú zatopila Liptovská Mara.',
                1 => 'Konajú sa tu služby Božie a podujatia, napríklad Noc kostolov, ktorú pripravuje CZ ECAV Svätý Kríž – Lazisko.',
                2 => self::OTHERS,
            ],
        ],
        797 => [
            'name' => 'Kysucká knižnica',
            'fix_name' => 'Kysucká knižnica (Čadca)',
            'fix_website' => null,
            'body' => [
                0 => 'Kysucká knižnica v Čadci je regionálna knižnica Žilinského samosprávneho kraja.',
                1 => 'Spolupodieľa sa na súťaži duchovnej tvorby Jurinova jeseň.',
                2 => self::OTHERS,
            ],
        ],
    ];

    /** Zdroj → cieľ: ten istý priestor pod inými názvami. */
    private const MERGES = [
        48 => [
            0 => 82,
            1 => 'Klokoč',
            2 => 'Chrám Zosnutia Presvätej Bohorodičky (Klokočov)',
        ],
        793 => [
            0 => 1031,
            1 => 'KCK FUGA',
            2 => 'Kultúrne centrum kresťanov FUGA',
        ],
        750 => [
            0 => 959,
            1 => 'online',
            2 => 'Online (Zoom)',
        ],
        83 => [
            0 => 769,
            1 => 'ruiny kostola a kláštora sv. Kataríny',
            2 => 'Katarínka – ruiny kostola a kláštora sv. Kataríny',
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
