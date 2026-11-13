<?php

use App\Models\Venue;
use App\Services\Resources\ResourceMerger;
use App\Services\SystemLog\Recorder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Najnovších 60 miest konania (november 2026, #998 – #1066): popis v troch
 * odsekoch, adresa, PSČ a presné súradnice z Nominatim/OpenStreetMap, web
 * z oficiálnej stránky. Overené podľa textu podujatí a verejných zdrojov.
 *
 * Mnohé z týchto miest vznikli pri importe z kúska vety či adresy
 * („Senici. Návštevníci si budú môcť…", „okr. Ilava", „Hviezdoslavova 104/77,
 * Zlaté Moravce"). Takéto miesta dostanú skutočný názov (`fix_name`).
 *
 * Opravy chybných údajov:
 *  - #1037 nesie názov devínskeho kostola (ten je už ako #934), no jeho jediné
 *    podujatie je v Kostole Svätej rodiny v Petržalke — premenuje sa naň,
 *  - #1001 Národný onkologický ústav: podujatie bolo v Onkologickom ústave
 *    sv. Alžbety na Heydukovej, nie v NOÚ na Klenovej — premenuje sa naň,
 *  - #1004 „Výstavisko, pavilón G1" nie je v Sučanoch, ale na brnianskom výstavisku,
 *  - #1058 Katedrála Božského Spasiteľa je v Ostrave, nie v Zákopčí,
 *  - #1034 budova Jagelovskej univerzity je v Krakove, nie v Leviciach,
 *  - #1020 symbolický cintorín vo Vrátnej je v Terchovej, nie v Liptovskej Štiavnici,
 *  - #1016, #1043, #1050 (Rím) a #1046 (Prievidza) mali súradnice stredu Slovenska
 *    či stredu mesta,
 *  - #1049 mal popis a web inej fakulty (RKCMBF UK), #1060 a #1009 nesprávny web,
 *  - #1027 a #1036 mali PSČ iných obcí, #1064 ulicu z inej obce.
 *
 * Duplikáty sa zlúčia: #1032 → #1066, #1025 → #1049, #1000 → #1061,
 * #1007 → #996, #1028 → #1054.
 *
 * Popis sa prepíše len vtedy, keď ešte nemá tri odseky; ulica, PSČ a web
 * sa dopĺňajú do prázdneho poľa (okrem `fix_*`). Miesto musí sedieť ID aj názvom.
 */
return new class extends Migration
{
    private const OTHERS = 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.';

    /** Zahraničné mestá, ktoré v číselníku obcí ešte nie sú (vzor: Rím). */
    private const CITIES = [
        'ostrava' => ['name' => 'Ostrava', 'zip' => '702 00'],
        'krakov' => ['name' => 'Krakov', 'zip' => '31-007'],
        'brno' => ['name' => 'Brno', 'zip' => '603 00'],
    ];

    /** @var array<int, array<string, mixed>> */
    private const VENUES = [
        1066 => [
            'name' => 'Kostol Svätej rodiny',
            'body' => [
                0 => 'Kostol Svätej rodiny je rímskokatolícky kostol v Dolnom Ohaji v okrese Nové Zámky. Stojí na Olivovej ulici. Je zasvätený Svätej rodine.',
                1 => 'Okrem bohoslužieb sa tu konajú ďakovné a spomienkové sväté omše, napríklad za kňaza Šebastiána Košúta, sekretára biskupa Pavla Hnilicu. Na slávnosti prichádzajú aj hostia z okolia. Pripravuje ich farnosť a občianske združenie Cyrilometodiada.',
                2 => self::OTHERS,
            ],
        ],
        1065 => [
            'name' => 'Farská záhrada',
            'fix_name' => 'Farská záhrada (Bratislava-Prievoz)',
            'fix_street' => 'Tomášikova 5535/8',
            'fix_postcode' => '821 03',
            'latitude' => 48.1526149,
            'longitude' => 17.1648172,
            'coordinates_source' => 'address',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Farská záhrada patrí k Farnosti Bratislava-Prievoz pri Kostole sv. Vincenta de Paul na Tomášikovej ulici. Farnosť spravujú vincentíni (Misijná spoločnosť sv. Vincenta de Paul). Záhrada slúži na farské stretnutia pod holým nebom.',
                1 => 'Konajú sa tu farské a charitatívne podujatia, napríklad Polievka, ktorá spája na Svetový deň chudobných. Farnosť ju pripravuje spolu s Útulkom sv. Lujzy de Marillac a mestskou časťou Ružinov. Podujatia sú otvorené pre všetkých.',
                2 => self::OTHERS,
            ],
        ],
        1064 => [
            'name' => 'Chrám Najsvätejšej Trojice',
            'fix_name' => 'Premonštrátsky kostol Najsvätejšej Trojice (Košice)',
            'fix_street' => 'Hlavná',
            'fix_postcode' => '040 01',
            'latitude' => 48.7223127,
            'longitude' => 21.2578419,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Premonštrátsky kostol Najsvätejšej Trojice stojí na Hlavnej ulici v historickom centre Košíc. Barokový kostol dali postaviť jezuiti v 17. storočí, dnes ho spravujú premonštráti z Opátstva Jasov. Patrí k pamiatkam mestskej pamiatkovej rezervácie.',
                1 => 'Konajú sa tu bohoslužby, spovedanie a koncerty, napríklad vianočné koncerty speváčky Márie Podhradskej. Program pripravujú premonštráti. Kostol je otvorený veriacim aj návštevníkom mesta.',
                2 => self::OTHERS,
            ],
        ],
        1063 => [
            'name' => 'Sídlisko III (Prešov)',
            'fix_name' => 'Chrám Kráľovnej pokoja (Prešov)',
            'fix_street' => 'Námestie Kráľovnej pokoja 7373/1',
            'fix_postcode' => '080 01',
            'latitude' => 49.0067336,
            'longitude' => 21.2258437,
            'coordinates_source' => 'venue',
            'body' => [
                0 => 'Chrám Kráľovnej pokoja je rímskokatolícky kostol na sídlisku III v Prešove. Stojí na Námestí Kráľovnej pokoja. Slúži veriacim sídliska III a okolia.',
                1 => 'Konajú sa tu bohoslužby aj stretnutia spoločenstiev, napríklad pravidelné mesačné stretnutia Hnutia na pomoc rozvedeným kresťanom. Stretnutia spájajú modlitbu a rozhovor. Sú otvorené pre všetkých, ktorí hľadajú oporu.',
                2 => self::OTHERS,
            ],
        ],
        1062 => [
            'name' => 'Zrkadlová sála Primaciálneho paláca',
            'latitude' => 48.1437398,
            'longitude' => 17.1095582,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Zrkadlová sála je reprezentačná sála klasicistického Primaciálneho paláca na Primaciálnom námestí v Bratislave. V paláci sídli primátor a mestské zastupiteľstvo. V roku 1805 tu podpísali Bratislavský mier.',
                1 => 'Konajú sa tu koncerty, slávnostné stretnutia a konferencie, napríklad študentská konferencia Nenápadní hrdinovia o ľuďoch, ktorí sa postavili neslobode. Sála hostí aj medzináboženské stretnutia. Vstup na podujatia býva voľný alebo na pozvánky.',
                2 => self::OTHERS,
            ],
        ],
        1061 => [
            'name' => 'Aule Pazmaneum Trnavskej univerzity',
            'fix_name' => 'Aula Pázmaneum Trnavskej univerzity',
            'street' => 'Hornopotočná 23',
            'postcode' => '918 43',
            'website' => 'https://www.truni.sk',
            'latitude' => 48.379698,
            'longitude' => 17.58686,
            'coordinates_source' => 'address',
            'body' => [
                0 => 'Aula Pázmaneum je slávnostná aula Trnavskej univerzity v Trnave. Nesie meno ostrihomského arcibiskupa Petra Pázmaňa, ktorý v roku 1635 založil pôvodnú Trnavskú univerzitu. Univerzita sídli na Hornopotočnej ulici.',
                1 => 'Konajú sa tu akademické slávnosti, konferencie a kolokviá, napríklad kolokvium Imago Forgacsiana o zázračnom obraze Trnavskej Panny Márie či prednášky o zodpovednom vedení. Podujatia pripravujú fakulty univerzity aj partnerské inštitúcie. Mnohé sú otvorené verejnosti.',
                2 => self::OTHERS,
            ],
        ],
        1060 => [
            'name' => 'Katedrála Najsvätejšej Trojice (Žilina)',
            'fix_website' => null,
            'body' => [
                0 => 'Katedrála Najsvätejšej Trojice je katedrálou Žilinskej diecézy. Stojí na Hornom vale nad Námestím Andreja Hlinku v historickom centre Žiliny. Vedľa nej stojí renesančná Burianova veža.',
                1 => 'Konajú sa tu diecézne slávnosti, kňazské vysviacky a mimoriadne udalosti, napríklad slávnostné oznámenie mena nového žilinského biskupa. Slávnosti často vysielajú médiá. Katedrála je aj farským kostolom mesta.',
                2 => self::OTHERS,
            ],
        ],
        1059 => [
            'name' => 'Okresná knižnica Juraja Fándlyho',
            'fix_name' => 'Knižnica Juraja Fándlyho v Trnave',
            'website' => 'https://www.kniznicatrnava.sk',
            'email' => 'kniznica@kniznicatrnava.sk',
            'body' => [
                0 => 'Knižnica Juraja Fándlyho je verejná knižnica v Trnave na Rázusovej ulici. Nesie meno kňaza a osvietenského spisovateľa Juraja Fándlyho. Požičiava knihy, audioknihy aj spoločenské hry.',
                1 => 'Konajú sa tu besedy, výstavy, prezentácie kníh a spomienkové podujatia, napríklad pietna spomienka na obete komunistického režimu z Trnavského kraja. Knižnica spolupracuje so školami a spolkami. Podujatia sú väčšinou otvorené verejnosti.',
                2 => self::OTHERS,
            ],
        ],
        1058 => [
            'name' => 'Ostrava',
            'fix_name' => 'Katedrála Božského Spasiteľa (Ostrava)',
            'city' => 'ostrava',
            'fix_street' => 'náměstí Msgre Šrámka',
            'fix_postcode' => '702 00',
            'country' => 'Česko',
            'latitude' => 49.8358092,
            'longitude' => 18.2889906,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Katedrála Božského Spasiteľa je katedrálou Ostravsko-opavskej diecézy v Ostrave. Stojí v centre mesta pri námestí Msgre Šrámka. Je druhým najväčším kostolom na Morave.',
                1 => 'Konajú sa tu diecézne slávnosti, koncerty a stretnutia, napríklad večer s Raymondom Naderom, ktorého pripravila Rodina sv. Šarbela. Prichádzajú sem aj veriaci zo Slovenska. Katedrála je otvorená návštevníkom.',
                2 => self::OTHERS,
            ],
        ],
        1056 => [
            'name' => 'Dobšinského 20, Kokava nad Rimavicou, okres Poltár.',
            'fix_name' => 'Dom charity sv. Vincenta de Paul (Kokava nad Rimavicou)',
            'fix_street' => 'Dobšinského 20',
            'postcode' => '985 05',
            'body' => [
                0 => 'Dom charity sv. Vincenta de Paul patrí Diecéznej charite Rožňava. Nachádza sa na Dobšinského ulici v Kokave nad Rimavicou v okrese Poltár. Slúži sociálnym službám charity v regióne.',
                1 => 'V novembri 2026 tu charita za účasti rožňavského biskupa otvorila nový denný stacionár. Stacionár ponúka dennú starostlivosť ľuďom odkázaným na pomoc. Otvorenie bolo spojené so slávnosťou.',
                2 => self::OTHERS,
            ],
        ],
        1055 => [
            'name' => 'Pútnický dom Zdenky Schelingovej',
            'body' => [
                0 => 'Pútnický dom Zdenky Schelingovej je duchovné centrum Milosrdných sestier Svätého kríža v Bratislave-Podunajských Biskupiciach. Nesie meno blahoslavenej sestry Zdenky Schelingovej, členky kongregácie a mučeníčky komunistického režimu. Sestry dom obnovili a požehnaný bol v januári 2026.',
                1 => 'Konajú sa tu duchovné obnovy s blahoslavenou Zdenkou, napríklad víkend S dôverou na ceste. Dom ponúka ticho, modlitbu a spoznávanie jej života. Prichádzajú sem pútnici z celého Slovenska.',
                2 => self::OTHERS,
            ],
        ],
        1052 => [
            'name' => 'Nábrežie Jána Pavla II, Svit',
            'fix_name' => 'Kostol sv. Jozefa robotníka (Svit)',
            'postcode' => '059 21',
            'latitude' => 49.0539216,
            'longitude' => 20.2110711,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Kostol sv. Jozefa robotníka je rímskokatolícky farský kostol vo Svite pod Vysokými Tatrami. Stojí na Nábreží Jána Pavla II. Je zasvätený patrónovi robotníkov.',
                1 => 'Okrem bohoslužieb sa tu konajú koncerty, napríklad organové koncerty mladých talentov z organovej triedy Petra Čapóa. Pripravuje ich občianske združenie Familiaris. Vstup býva voľný.',
                2 => self::OTHERS,
            ],
        ],
        1050 => [
            'name' => 'Bazilika sv. Petra',
            'street' => 'Piazza San Pietro',
            'postcode' => '00120',
            'latitude' => 41.9021569,
            'longitude' => 12.4537105,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
        ],
        1049 => [
            'name' => 'Aula Teologickej fakulty Trnavskej univerzity',
            'fix_website' => 'https://tf.truni.sk',
            'body' => [
                0 => 'Teologická fakulta Trnavskej univerzity sídli na Kostolnej ulici v historickom centre Bratislavy. Vzdeláva v teológii, poradenstve a mediácii. Pri fakulte pôsobia jezuiti.',
                1 => 'V aule fakulty sa konajú konferencie a semináre, napríklad o pátrovi Vendelínovi Javorkovi či o saleziánovi Antonovi Hlinkovi. Pripravuje ich fakulta, Ústav pamäti národa či rehole. Mnohé sú otvorené verejnosti.',
                2 => self::OTHERS,
            ],
        ],
        1047 => [
            'name' => 'Kongresová sála',
            'fix_name' => 'Kongresová sála Mestského divadla v Levoči',
            'street' => 'Námestie Majstra Pavla 54',
            'postcode' => '054 01',
            'latitude' => 49.0271436,
            'longitude' => 20.5890602,
            'coordinates_source' => 'venue',
            'body' => [
                0 => 'Kongresová sála je súčasťou Mestského divadla v Levoči na Námestí Majstra Pavla. Divadlo stojí v historickom centre mesta neďaleko Baziliky sv. Jakuba. Sála slúži na kultúrne a spoločenské podujatia.',
                1 => 'Konajú sa tu prezentácie kníh, konferencie a koncerty, napríklad slávnostné uvedenie Teológie tela a Rímskeho triptychu Jána Pavla II. vo vydavateľstve Wojtyla.sk. Podujatia často nadväzujú na sväté omše v bazilike. Sú otvorené verejnosti.',
                2 => self::OTHERS,
            ],
        ],
        1046 => [
            'name' => 'Gréckokatolícka farnosť',
            'street' => 'Mariánska 571/57',
            'postcode' => '971 01',
            'latitude' => 48.7713533,
            'longitude' => 18.6354834,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Gréckokatolícka farnosť v Prievidzi patrí Bratislavskej eparchii. Bohoslužby slávi v Kostole Nanebovzatia Panny Márie na Mariánskej ulici. Spája gréckokatolíkov z hornej Nitry.',
                1 => 'Konajú sa tu sväté liturgie a stretnutia farnosti, napríklad štvordňová návšteva vladyku Milana Lacha v rámci projektu Stretni sa s vladykom Milanom. Program zahŕňa liturgie aj stretnutia s rodinami. Na podujatia sú pozvaní aj ostatní veriaci.',
                2 => self::OTHERS,
            ],
        ],
        1044 => [
            'name' => 'Senici. Návštevníci si budú môcť výstavu pozrieť',
            'fix_name' => 'Záhorské osvetové stredisko (Senica)',
            'fix_street' => 'Vajanského 19/5',
            'postcode' => '905 01',
            'latitude' => 48.6769068,
            'longitude' => 17.3680276,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Záhorské osvetové stredisko je kultúrno-osvetová inštitúcia Trnavského samosprávneho kraja. Sídli na Vajanského ulici v Senici. Podporuje kultúrny život Záhoria.',
                1 => 'V jeho priestoroch sa konajú výstavy, súťaže a vzdelávacie podujatia, napríklad výstava fotografií Martina Petríka Anton Srholec a jeho priatelia. Výstavy sú sprístupnené v pracovných dňoch. Vernisáže sú otvorené verejnosti.',
                2 => self::OTHERS,
            ],
        ],
        1043 => [
            'name' => 'Pápežský slovenský ústav svätých Cyrila a Metoda',
            'postcode' => '00189',
            'country' => 'Taliansko',
            'latitude' => 41.9878142,
            'longitude' => 12.3913983,
            'coordinates_source' => 'address',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Pápežský slovenský ústav sv. Cyrila a Metoda je slovenská katolícka a kultúrna ustanovizeň v Ríme. Zriadil ho kardinál Eugène Tisserant v roku 1961. Sídli v areáli na okraji Ríma, kde je aj kolégium a kostol sv. Cyrila a Metoda.',
                1 => 'Konajú sa tu slávnostné sväté omše a stretnutia Slovákov v Ríme, napríklad spomienka na návštevu sv. Jána Pavla II. v ústave v roku 1981. Ústav prijíma aj pútnikov zo Slovenska. Pri slávnostiach sa stretávajú krajania z Talianska.',
                2 => self::OTHERS,
            ],
        ],
        1040 => [
            'name' => 'Evanjelickom kostole',
            'fix_name' => 'Evanjelický kostol (Liptovský Mikuláš)',
            'street' => 'Tranovského 108',
            'postcode' => '031 01',
            'latitude' => 49.0837141,
            'longitude' => 19.6159386,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Evanjelický kostol v Liptovskom Mikuláši je tolerančný chrám Cirkevného zboru ECAV Liptovský Mikuláš. Stojí na Tranovského ulici v centre mesta. Patrí k významným pamiatkam mesta.',
                1 => 'Konajú sa tu služby Božie a ekumenické slávnosti, napríklad pietna spomienka na obete dopravných nehôd, ktorú vysielala Slovenská televízia. Pripravuje ju aj Ekumenická pastoračná služba ministerstva vnútra. Pozvaní bývajú aj zástupcovia záchranných zložiek.',
                2 => self::OTHERS,
            ],
        ],
        1039 => [
            'name' => 'Grösslingová 23, Bratislava',
            'fix_name' => 'Knižnica slovenskej štátnosti Matice slovenskej',
            'fix_street' => 'Grösslingová 23',
            'postcode' => '811 09',
            'body' => [
                0 => 'Knižnica slovenskej štátnosti je pracovisko Matice slovenskej na Grösslingovej ulici v Bratislave. Zhromažďuje literatúru o dejinách slovenskej štátnosti. Jej priestory slúžia aj na verejné podujatia.',
                1 => 'Konajú sa tu prezentácie kníh, besedy a prednášky, napríklad prezentácia knihy Evy Fordinálovej o sile slovenského slova. Podujatia pripravuje Matica slovenská spolu s občianskymi združeniami, napríklad Cyrilometodiadou. Vstup býva voľný.',
                2 => self::OTHERS,
            ],
        ],
        1038 => [
            'name' => 'okr. Ilava',
            'fix_name' => 'Kostol sv. Ondreja apoštola (Bolešov)',
            'street' => 'Bolešov 108',
            'postcode' => '018 53',
            'latitude' => 48.9870341,
            'longitude' => 18.1594441,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Kostol sv. Ondreja apoštola je rímskokatolícky farský kostol v Bolešove v okrese Ilava. Slúži Farnosti Bolešov. Je zasvätený apoštolovi Ondrejovi.',
                1 => 'Okrem bohoslužieb sa tu konajú prednášky a spomienkové večery, napríklad prednáška prof. Róberta Letza Svedkovia viery o prenasledovaní Cirkvi v rokoch 1948 – 1989. Podujatia pripravuje farnosť. Sú otvorené pre všetkých.',
                2 => self::OTHERS,
            ],
        ],
        1037 => [
            'name' => 'Kostol svätého Kríža (Devín)',
            'fix_name' => 'Kostol Svätej rodiny (Petržalka)',
            'fix_street' => 'Námestie Jána Pavla II. 3661/1',
            'fix_postcode' => '851 01',
            'fix_website' => null,
            'latitude' => 48.1126512,
            'longitude' => 17.1072095,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Kostol Svätej rodiny je farský kostol v Bratislave-Petržalke na Námestí Jána Pavla II. Patrí k najväčším moderným kostolom na Slovensku. Slúži veriacim petržalských sídlisk.',
                1 => 'Okrem bohoslužieb sa tu konajú koncerty, napríklad adventný koncert Proglas – Slovanská óda speváckeho zboru Adoremus. Veľký priestor kostola hostí aj diecézne slávnosti. Koncerty sú otvorené verejnosti.',
                2 => self::OTHERS,
            ],
        ],
        1036 => [
            'name' => 'Pastoračné centrum pri fare',
            'fix_street' => 'Michalská 1/3',
            'fix_postcode' => '059 18',
            'latitude' => 48.9920657,
            'longitude' => 20.2432164,
            'coordinates_source' => 'address',
            'body' => [
                0 => 'Pastoračné centrum pri fare v Spišskom Bystrom patrí Farnosti Spišské Bystré. Nachádza sa pri farskom úrade na Michalskej ulici. Slúži na stretnutia farníkov a spoločenstiev.',
                1 => 'Konajú sa tu prednášky a stretnutia pre rodičov aj mladých, napríklad prednáška Richarda Vašečku z projektu Milovať a ctiť o výchove detí. Podujatia pripravuje farnosť. Sú otvorené pre všetkých.',
                2 => self::OTHERS,
            ],
        ],
        1035 => [
            'name' => 'Kostol Všetkých svätých',
            'fix_street' => null,
            'latitude' => 49.0636308,
            'longitude' => 19.3295219,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Kostol Všetkých svätých v Ludrovej pri Ružomberku je gotický kostol zo 14. storočia. Stojí osamote medzi poľami v časti Ludrová-Kút. Je známy vzácnymi stredovekými nástennými maľbami.',
                1 => 'Dnes slúži najmä ako pamiatka a výstavný priestor, bohoslužby sa tu slávia pri výnimočných príležitostiach. Na patrocínium sa koná vigília Všetkých svätých, napríklad s recitálom Romana Pomajba. Je vyhľadávaným cieľom milovníkov umenia.',
                2 => self::OTHERS,
            ],
        ],
        1034 => [
            'name' => 'Budova Právnickej a administratívnej fakulty Jagelovskej univerzity',
            'fix_name' => 'Právnická a administratívna fakulta Jagelovskej univerzity (Krakov)',
            'city' => 'krakov',
            'fix_street' => 'Gołębia 24',
            'fix_postcode' => '31-007',
            'country' => 'Poľsko',
            'latitude' => 50.0608642,
            'longitude' => 19.9332351,
            'coordinates_source' => 'address',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Právnická a administratívna fakulta Jagelovskej univerzity sídli v historickom centre Krakova na ulici Gołębia. Jagelovská univerzita je najstaršou univerzitou v Poľsku. Fakulta sídli v budove Collegium Novum.',
                1 => 'Konajú sa tu konferencie a diskusie, napríklad konferencia Aby boli jedno o ekumenizme s kardinálom Grzegorzom Ryśom. Pripravujú ich študenti a pedagógovia univerzity. Zúčastňujú sa na nich aj zástupcovia rôznych cirkví.',
                2 => self::OTHERS,
            ],
        ],
        1033 => [
            'name' => 'KD',
            'fix_name' => 'Kultúrny dom Borovce',
            'postcode' => '922 09',
            'body' => [
                0 => 'Kultúrny dom Borovce je obecný kultúrny dom v Borovciach v okrese Piešťany. Slúži obyvateľom obce na kultúrne a spoločenské podujatia. Prenajímajú si ho aj spolky a spoločenstvá.',
                1 => 'Konajú sa tu napríklad celodenné stretnutia Day by the Well – Deň pri studni, ktoré pripravuje Spoločenstvo Vykroč. Program spája chvály, slovo a modlitbu. Je otvorený pre všetkých.',
                2 => self::OTHERS,
            ],
        ],
        1031 => [
            'name' => 'Kultúrnom centre kresťanov FUGA',
            'fix_name' => 'Kultúrne centrum kresťanov FUGA',
            'street' => 'Moyzesova 62',
            'postcode' => '040 01',
            'body' => [
                0 => 'Kultúrne centrum kresťanov FUGA je kultúrny priestor v centre Košíc na Moyzesovej ulici. Spája kultúru a kresťanské hodnoty. Najbližšia električková zastávka je Dom umenia.',
                1 => 'Konajú sa tu koncerty, diskusie a besedy, napríklad debata o odkaze Nežnej revolúcie so spišským biskupom Františkom Trstenským a Ivetou Radičovou. Diskusie pripravuje aj Fórum kresťanských inštitúcií. Na niektoré podujatia sa predávajú vstupenky.',
                2 => self::OTHERS,
            ],
        ],
        1030 => [
            'name' => 'Rotunda, Spiegelsaal',
            'fix_name' => 'Rotunda Spiegelsaal (Trnava)',
            'postcode' => '917 01',
            'body' => [
                0 => 'Rotunda Spiegelsaal je historický záhradný pavilón kruhového pôdorysu v Trnave na Kalinčiakovej ulici. Stojí mimo historického centra, dnes medzi modernými budovami. Meno Spiegelsaal (Zrkadlová sála) prevzal od neďalekého hostinca s tanečnou sálou.',
                1 => 'Po obnove slúži ako spoločenský priestor. Konajú sa tu večierky a spoločenské podujatia, napríklad Singles Oldies Party pre slobodných. Na podujatia sa prihlasuje vopred.',
                2 => self::OTHERS,
            ],
        ],
        1027 => [
            'name' => 'Kostol sv. Kataríny Alexandrijskej',
            'fix_street' => 'Námestie sv. Kataríny',
            'fix_postcode' => '900 55',
            'latitude' => 48.3322949,
            'longitude' => 17.0429065,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Kostol sv. Kataríny Alexandrijskej je rímskokatolícky farský kostol v Lozorne v okrese Malacky. Stojí na Námestí sv. Kataríny v strede obce. V roku 2026 uplynulo 200 rokov od jeho obnovy.',
                1 => 'Konajú sa tu bohoslužby a farské slávnosti, napríklad oslavy 200. výročia obnovy kostola za účasti apoštolského nuncia Nicolu Girasoliho. Slávnosti pripravuje farnosť spolu s obcou. Sú otvorené pre všetkých.',
                2 => self::OTHERS,
            ],
        ],
        1026 => [
            'name' => 'Kostol Zvestovania Pána (Bratislava)',
            'body' => [
                0 => 'Kostol Zvestovania Pána je františkánsky kostol na Františkánskom námestí v Bratislave. Je najstaršou zachovanou sakrálnou stavbou v meste, vysvätený bol v roku 1297. Pri kostole stojí kláštor menších bratov františkánov.',
                1 => 'Po uhorských korunováciách sa tu pasovali rytieri. Dnes sa tu konajú bohoslužby, spovedanie a duchovné stretnutia, napríklad spoločné stretnutie slovenských a maďarských veriacich na záver obnovy chrámu. Kostol je otvorený veriacim aj turistom.',
                2 => self::OTHERS,
            ],
        ],
        1024 => [
            'name' => 'Spolok svätého Vojtecha',
            'fix_street' => 'Radlinského 5',
            'latitude' => 48.3768533,
            'longitude' => 17.5839892,
            'coordinates_source' => 'address',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Spolok svätého Vojtecha je jeden z najstarších slovenských spolkov, založil ho Andrej Radlinský v roku 1870. Sídli v Trnave na Radlinského ulici. Je známy vydávaním duchovnej literatúry a liturgických kníh.',
                1 => 'V jeho priestoroch sa konajú tlačové konferencie a prezentácie, napríklad predstavenie loga a literárnej súťaže k novému Katolíckemu spevníku. Spolok pripravuje aj uvedenia kníh v Bratislave a Trnave. Podujatia sú otvorené verejnosti.',
                2 => self::OTHERS,
            ],
        ],
        1023 => [
            'name' => 'Hviezdoslavova 104/77, Zlaté Moravce',
            'fix_name' => 'Kostol Božského Srdca Ježišovho (Zlaté Moravce)',
            'fix_street' => 'Hviezdoslavova 104/77',
            'postcode' => '953 01',
            'latitude' => 48.3863965,
            'longitude' => 18.3857328,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Kostol Božského Srdca Ježišovho je kláštorný kostol bratov tešiteľov z Getsemani v Zlatých Moravciach. Stojí na Hviezdoslavovej ulici pri ich kláštore. Je zasvätený Božskému Srdcu Ježišovmu.',
                1 => 'Okrem bohoslužieb sa tu konajú stretnutia mládeže a chvály, napríklad Mládežka a chvály v prvý piatok mesiaca. Stretnutia pripravujú zlatomoravské kresťanské spoločenstvá. Sú otvorené pre všetkých.',
                2 => self::OTHERS,
            ],
        ],
        1022 => [
            'name' => 'Nám. sv. Martina 508, Dunajská Lužná',
            'fix_name' => 'Konvent bl. Imeldy (Dunajská Lužná)',
            'fix_street' => 'Námestie sv. Martina 508/14',
            'postcode' => '900 42',
            'latitude' => 48.0849907,
            'longitude' => 17.2564559,
            'coordinates_source' => 'address',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Konvent bl. Imeldy je dom Kongregácie sestier dominikánok bl. Imeldy v Dunajskej Lužnej. Stojí na Námestí sv. Martina. Sestry dominikánky patria do Rádu kazateľov.',
                1 => 'Sestry tu pripravujú duchovné obnovy a formačné kurzy, napríklad kurz Povolaným nablízku pre mladé ženy. Kurz trvá niekoľko mesiacov. Prihlasuje sa vopred.',
                2 => self::OTHERS,
            ],
        ],
        1020 => [
            'name' => 'Malej Fatre. Bratislava',
            'fix_name' => 'Symbolický cintorín v Malej Fatre (Vrátna)',
            'fix_village_id' => 3531,
            'fix_postcode' => '013 06',
            'latitude' => 49.2062345,
            'longitude' => 19.0470382,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Symbolický cintorín v Malej Fatre sa nachádza vo Vrátnej doline v obci Terchová. Pripomína ľudí, ktorí zahynuli v horách Malej Fatry. Je pietnym miestom horských záchranárov aj turistov.',
                1 => 'Každý rok koncom októbra sa tu koná spomienka na obete horských nešťastí. Súčasne sa podobné spomienky konajú na Zverovke a pri Popradskom plese. Spomienky sú otvorené verejnosti.',
                2 => self::OTHERS,
            ],
        ],
        1018 => [
            'name' => 'Kláštor sestier uršulínok',
            'body' => [
                0 => 'Kláštor sestier uršulínok stojí na Mäsiarskej ulici v historickom centre Košíc. Uršulínky pôsobia v Košiciach od konca 17. storočia. Venujú sa výchove a vzdelávaniu mladých.',
                1 => 'V kláštore sa konajú duchovné obnovy a stretnutia, napríklad jesenné zasadnutie Konferencie sekulárnych inštitútov Slovenska s vladykom Jonášom Maximom. Priestory poskytujú sestry aj iným spoločenstvám. Stretnutia sú väčšinou určené pozvaným.',
                2 => self::OTHERS,
            ],
        ],
        1017 => [
            'name' => 'Kostol Najsvätejšej Trojice (Jezuitský kostol)',
            'street' => 'Štefánikova 45',
            'postcode' => '917 01',
            'latitude' => 48.3781907,
            'longitude' => 17.5848642,
            'coordinates_source' => 'venue',
            'body' => [
                0 => 'Kostol Najsvätejšej Trojice v Trnave je barokový kostol na Trojičnom námestí a Štefánikovej ulici. Postavili ho trinitári v 18. storočí, dnes ho spravujú jezuiti. Ľudovo sa nazýva jezuitský kostol.',
                1 => 'Konajú sa tu bohoslužby, univerzitné omše a koncerty, napríklad slávnosť k 300. výročiu svätorečenia sv. Stanislava Kostku či jubileum zboru Cantica Sacra Tyrnaviae. Kostol je duchovným centrom trnavských študentov. Slávnosti sú otvorené pre všetkých.',
                2 => self::OTHERS,
            ],
        ],
        1016 => [
            'name' => 'Auditorium Conciliazione',
            'country' => 'Taliansko',
            'latitude' => 41.9025871,
            'longitude' => 12.4636471,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Auditorium Conciliazione je koncertná a kongresová sála v Ríme na Via della Conciliazione. Stojí neďaleko Námestia sv. Petra. Patrí k najväčším sálam v centre mesta.',
                1 => 'Konajú sa tu koncerty, konferencie a festivaly, napríklad festival Dávidových žalmov Žalmy s Jánom Pavlom II. – Žalmy pokoja. Hostí aj podujatia spojené s Vatikánom. Vstupenky sa predávajú vopred.',
                2 => self::OTHERS,
            ],
        ],
        1015 => [
            'name' => 'Štefánikova 208/7, Námestovo',
            'fix_name' => 'Dom kultúry Námestovo',
            'postcode' => '029 01',
            'body' => [
                0 => 'Dom kultúry v Námestove je mestské kultúrne centrum na Štefánikovej ulici. Slúži obyvateľom Oravy na kultúrne a spoločenské podujatia. Má divadelnú sálu aj menšie priestory.',
                1 => 'Konajú sa tu predstavenia, koncerty a rodinné podujatia, napríklad tradičný lampiónový sprievod sv. Martina, ktorý pripravuje Rodinné centrum Drobček. Sprievod sa začína pred domom kultúry. Podujatia sú otvorené verejnosti.',
                2 => self::OTHERS,
            ],
        ],
        1014 => [
            'name' => 'Aula 1 teologickej fakulty',
            'fix_name' => 'Aula 1 Gréckokatolíckej teologickej fakulty PU',
            'fix_street' => 'Biskupa Gojdiča 2',
            'latitude' => 48.9932877,
            'longitude' => 21.242789,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Aula 1 je hlavná aula Gréckokatolíckej teologickej fakulty Prešovskej univerzity. Fakulta sídli na Ulici biskupa Gojdiča v Prešove. Pripravuje kňazov, katechétov a sociálnych pracovníkov.',
                1 => 'Konajú sa tu vedecké konferencie a prednášky, napríklad medzinárodná konferencia o odkaze sv. Františka a integrálnej ekológii. Podujatia pripravuje fakulta s partnermi. Mnohé sú otvorené aj verejnosti.',
                2 => self::OTHERS,
            ],
        ],
        1011 => [
            'name' => 'Kostol sv. Michala, archanjela',
            'street' => 'Michalská 382/2',
            'postcode' => '059 18',
            'latitude' => 48.9925552,
            'longitude' => 20.2429318,
            'coordinates_source' => 'venue',
            'body' => [
                0 => 'Kostol sv. Michala archanjela je rímskokatolícky farský kostol v Spišskom Bystrom. Stojí na Michalskej ulici v strede obce. Slúži Farnosti Spišské Bystré.',
                1 => 'Po obnove ho v roku 2026 spolu s novým oltárom konsekroval spišský biskup František Trstenský. Konajú sa tu bohoslužby a farské slávnosti. Pri kostole je aj pastoračné centrum.',
                2 => self::OTHERS,
            ],
        ],
        1009 => [
            'name' => 'Hutnícka 4, Spišská Nová Ves',
            'fix_name' => 'Dom pútnika pallotínov (Spišská Nová Ves)',
            'fix_website' => null,
            'postcode' => '052 01',
            'body' => [
                0 => 'Dom pútnika pri Centre Božieho milosrdenstva spravujú pallotíni. Nachádza sa na Hutníckej ulici v Spišskej Novej Vsi. Slúži na duchovné cvičenia a ubytovanie pútnikov.',
                1 => 'Konajú sa tu duchovné cvičenia a obnovy, napríklad duchovné cvičenia pre bezdetných manželov s modlitbou za uzdravenie, ktoré pripravuje Milujúca náruč. Programy trvajú zvyčajne niekoľko dní. Prihlasuje sa vopred.',
                2 => self::OTHERS,
            ],
        ],
        1008 => [
            'name' => '08631 Bardejovské Kúpele. (Začíname v piatok podvečer 6. novembra, končíme v nedeľu obedom 8. novembra.)',
            'fix_name' => 'Penzión Augustineum (Bardejovské Kúpele)',
            'street' => 'Bardejovské Kúpele 2029/63',
            'fix_postcode' => '086 31',
            'latitude' => 49.3310736,
            'longitude' => 21.2661652,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Penzión Augustineum je ubytovacie a duchovné zariadenie v Bardejovských Kúpeľoch. Nachádza sa v kúpeľnom areáli pod lesom. Ponúka ubytovanie a priestory na stretnutia.',
                1 => 'Konajú sa tu víkendové programy, napríklad víkendová oáza pre bezdetných manželov, ktorú pripravuje Úrad pre apoštolát ochrany života Košickej arcidiecézy s PloDarom. Programy trvajú od piatka do nedele. Prihlasuje sa vopred.',
                2 => self::OTHERS,
            ],
        ],
        1006 => [
            'name' => 'Zámocká 47, Bratislava',
            'fix_name' => 'Pálffyho palác (Zámocká 47)',
            'fix_street' => 'Zámocká 47',
            'postcode' => '811 01',
            'latitude' => 48.1449748,
            'longitude' => 17.1010275,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Pálffyho palác na Zámockej ulici je historický palác pod Bratislavským hradom. Patril šľachtickému rodu Pálfiovcov. Má historické sály a záhradu.',
                1 => 'Konajú sa tu výstavy, prezentácie kníh a festivaly, napríklad uvedenie knihy Petrarcovej poézie s výstavou Doroty Sadovskej či Bratislavské Hanusove dni v záhrade paláca. Podujatia pripravujú vydavateľstvá a kultúrne inštitúcie. Väčšina je otvorená verejnosti.',
                2 => self::OTHERS,
            ],
        ],
        1004 => [
            'name' => 'Výstavisko, pavilón G1',
            'fix_name' => 'Výstavisko Brno, pavilón G1',
            'city' => 'brno',
            'fix_street' => 'Výstaviště 405/1',
            'fix_postcode' => '603 00',
            'country' => 'Česko',
            'latitude' => 49.1877268,
            'longitude' => 16.5822648,
            'coordinates_source' => 'address',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Pavilón G1 je koncertná a výstavná hala brnianskeho výstaviska. Výstavisko leží v mestskej časti Pisárky. Pavilón hostí veľké koncerty a festivaly.',
                1 => 'Konajú sa tu aj kresťanské podujatia, napríklad evanjelizačné turné Godzone Tour, ktoré každý rok prechádza mestami Slovenska a Česka. Program spája hudbu, tanec, divadlo a svedectvá. Vstup na Godzone Tour je zadarmo s registráciou.',
                2 => self::OTHERS,
            ],
        ],
        1002 => [
            'name' => 'Katedrála Nanebovzatia Panny Márie (Rožňava)',
            'body' => [
                0 => 'Katedrála Nanebovzatia Panny Márie je katedrálou Rožňavskej diecézy. Gotický kostol z 13. – 14. storočia stojí v Rožňave pri biskupskom úrade. Známy je obrazom Svätej Anny Metercie s výjavmi z baníckeho života.',
                1 => 'Konajú sa tu diecézne slávnosti, kňazské vysviacky a každoročne v októbri Rožňavská novéna k Rožňavskej Panne Márii. V roku 2026 sa niesla v duchu 250. výročia vzniku diecézy. Novénu prenáša aj TV LUX.',
                2 => self::OTHERS,
            ],
        ],
        1001 => [
            'name' => 'Národný onkologický ústav',
            'fix_name' => 'Onkologický ústav sv. Alžbety',
            'fix_street' => 'Heydukova 10',
            'fix_postcode' => '812 50',
            'fix_website' => 'https://www.ousa.sk',
            'phone' => '+421 2 3224 9111',
            'email' => 'ousa@ousa.sk',
            'latitude' => 48.1466605,
            'longitude' => 17.1133732,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Onkologický ústav sv. Alžbety je nemocnica na Heydukovej ulici v Bratislave. Zriaďuje ju Rehoľa sv. Alžbety. Špecializuje sa na liečbu onkologických ochorení.',
                1 => 'V nemocnici je kaplnka, kde sa slávia sväté omše. Konajú sa tu aj odborné stretnutia, napríklad stretnutie Klubu kresťanských lekárov a zdravotníkov o medicíne a digitálnej revolúcii. Pacientom slúži aj duchovná služba.',
                2 => self::OTHERS,
            ],
        ],
        999 => [
            'name' => 'Kolégium Antona Neuwirtha',
            'body' => [
                0 => 'Kolégium Antona Neuwirtha je vzdelávacia inštitúcia, ktorá formuje mladých ľudí v duchu kresťanských hodnôt. Nesie meno lekára, politického väzňa a diplomata Antona Neuwirtha. Jeho gymnázium sídli na Grösslingovej ulici v Bratislave.',
                1 => 'Gymnázium Kolégia Antona Neuwirtha pripravuje aj prednášky pre rodičov a verejnosť, napríklad Krotitelia displejov v akcii o hraniciach pri obrazovkách. Kolégium organizuje aj semináre a letné školy. Podujatia sú otvorené záujemcom.',
                2 => self::OTHERS,
            ],
        ],
        998 => [
            'name' => 'Park garbiarov 107, Bošany',
            'fix_name' => 'Kostol sv. Martina z Tours (Bošany)',
            'fix_street' => 'Park garbiarov 107/8',
            'postcode' => '956 18',
            'latitude' => 48.5805775,
            'longitude' => 18.2458909,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Kostol sv. Martina z Tours je rímskokatolícky farský kostol v Bošanoch v okrese Partizánske. Stojí v Parku garbiarov. Je zasvätený sv. Martinovi z Tours.',
                1 => 'Pravidelne sa tu konajú mesačné modlitby za uzdravenie tela i duše. Program sa začína možnosťou sviatosti zmierenia, pokračuje svätou omšou a modlitbou. Prichádzajú naň veriaci z okolia.',
                2 => self::OTHERS,
            ],
        ],
        996 => [
            'name' => 'Koncertná sála Klarisky',
            'fix_name' => 'Koncertná sieň Klarisky',
            'street' => 'Farská 4',
            'postcode' => '811 01',
            'latitude' => 48.1438684,
            'longitude' => 17.1054672,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Koncertná sieň Klarisky je kultúrny priestor v bývalom gotickom kostole klarisiek v Starom Meste v Bratislave. Vchod je z Farskej ulice. Kostol s kamennou vežou patrí k najcennejším gotickým pamiatkam mesta.',
                1 => 'Konajú sa tu koncerty starej a duchovnej hudby, prednášky a prezentácie, napríklad festival Musica Sacra či predstavenie encykliky Magnifica humanitas. Podujatia pripravujú kultúrne inštitúcie a vydavateľstvá. Na väčšinu sa predávajú vstupenky.',
                2 => self::OTHERS,
            ],
        ],
    ];

    /** Zdroj → cieľ: ten istý priestor pod inými názvami. */
    private const MERGES = [
        1032 => [1066, 'Olivová 300, Dolný Ohaj', 'Kostol Svätej rodiny'],
        1025 => [1049, 'Kostolnej', 'Aula Teologickej fakulty Trnavskej univerzity'],
        1000 => [1061, 'Univerzitné námestie, Trnava', 'Aula Pázmaneum Trnavskej univerzity'],
        1007 => [996, 'Farská 4, Bratislava', 'Koncertná sieň Klarisky'],
        1028 => [1054, 'TURZOVKA 2025 - 14 November 2026 Jesenná púť do Turzovky', 'Živčáková, Turzovka'],
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
