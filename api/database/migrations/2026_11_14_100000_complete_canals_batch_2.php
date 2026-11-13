<?php

use App\Models\Canal;
use App\Models\Municipality;
use App\Services\SystemLog\Recorder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Druhá dávka (november 2026): ďalších 60 importovaných kanálov v poradí
 * (#1784 – #1865), väčšinou pôvodom z hlascirkvi.sk. Ich popisy boli všeobecné
 * AI texty bez overených faktov („organizuje rôzne podujatia pre rodiny,
 * mládež a seniorov…") — nahradia sa tromi odsekmi podľa podujatí kanála
 * a verejných zdrojov. Kanálom s faktografickým jednoodsekovým popisom
 * (história zboru, fakulty, cirkvi) sa text ponechá a doplnia sa dva odseky.
 *
 * Adresy sídiel dostanú súradnice z Nominatim/OpenStreetMap.
 *
 * Opravy obce:
 *  - #1852 Cirkev bratská sídli na Cukrovej 14 v Bratislave, nie v Prešove,
 *  - #1824 Krakovská arcidiecéza je v Krakove, nie v Leviciach.
 *
 * Kanál musí sedieť ID aj názvom; prevzatý, zlúčený či zmazaný sa preskočí.
 */
return new class extends Migration
{
    private const CLOSING = 'Podrobnosti nájdete vždy v detaile podujatia.';

    /** @var array<int, array<string, mixed>> */
    private const PROFILES = [
        1865 => [
            'name' => 'CZ ECAV Zvolen',
            'address' => ['street' => 'Námestie SNP 11/17', 'postcode' => '960 01', 'municipality_id' => 4153, 'latitude' => 48.5755087, 'longitude' => 19.1250155],
            'extend' => [
                0 => 'Okrem služieb Božích pripravuje koncerty, napríklad tradičný vianočný Jánsky koncert v kostole Svätej Trojice. Zbor žije aj bohatým spoločenstvom detí, mládeže a dospelých.',
                1 => 'Kanál je určený evanjelikom zo Zvolena a milovníkom duchovnej hudby. Sledovaním budete vedieť o koncertoch a slávnostiach zboru. '.self::CLOSING,
            ],
        ],
        1864 => [
            'name' => 'evanjelická, reformovaná a metodistická cirkev a rímskokatolícka diecéza v Graz-Seckau',
            'extend' => [
                0 => 'Spolu s evanjelickou, reformovanou a metodistickou cirkvou pripravuje ekumenické podujatia, napríklad Stretnutie kresťanov v Grazi s mottom Tvárou v tvár. Dnes. Boh. Viera. Stretnutie spája kresťanov zo strednej Európy.',
                1 => 'Kanál je určený kresťanom, ktorých zaujímajú ekumenické stretnutia v susednom Rakúsku. Sledovaním budete vedieť o ďalších stretnutiach. '.self::CLOSING,
            ],
        ],
        1862 => [
            'name' => 'Nadácia Antona Srholca António',
            'address' => ['street' => 'Nezábudková 807/12', 'postcode' => '821 01', 'municipality_id' => 242, 'latitude' => 48.150096, 'longitude' => 17.1527804],
            'extend' => [
                0 => 'Nadácia pripravuje spomienkové večery, napríklad Trojkráľový večer slova pri výročí úmrtia Antona Srholca. Podporuje aj výstavy a publikácie o jeho živote.',
                1 => 'Kanál je určený všetkým, ktorým je blízky odkaz Antona Srholca. Sledovaním budete vedieť o spomienkových podujatiach. '.self::CLOSING,
            ],
        ],
        1861 => [
            'name' => 'Českobratská cirkev evanjelická',
            'extend' => [
                0 => 'Pripravuje aj vzdelávacie podujatia, napríklad tradičný zbormajstrovský kurz pre dirigentov, spevákov a kantorov. Kurzov sa zúčastňujú aj hudobníci zo Slovenska.',
                1 => 'Kanál je určený evanjelikom a cirkevným hudobníkom. Sledovaním budete vedieť o kurzoch a stretnutiach cirkvi. '.self::CLOSING,
            ],
        ],
        1860 => [
            'name' => 'ProChrist',
            'extend' => [
                0 => 'Regionálne podujatie ProChrist má podobu piatich večerov s piatimi témami, rečníkmi, hosťami a svedectvami. Večery spájajú hudbu, slovo a rozhovor. Vstup je zadarmo.',
                1 => 'Kanál je určený všetkým, ktorí hľadajú odpovede na otázky viery. Sledovaním budete vedieť o ďalších ročníkoch ProChrist. '.self::CLOSING,
            ],
        ],
        1859 => [
            'name' => 'Misijné centrum Prameň',
            'body' => [
                0 => 'Misijné centrum Prameň sídli na Súši pri Starej Turej. Je kresťanským misijným a rekreačným strediskom. Ponúka ubytovanie a programy pre rodiny, mládež aj skupiny.',
                1 => 'Pripravuje pobytové a prázdninové programy, napríklad týždenný pobyt Ruka v ruke. Programy spájajú oddych, spoločenstvo a duchovný rast. Organizuje aj zájazdy do zahraničia.',
                2 => 'Kanál je určený rodinám a skupinám, ktoré hľadajú kresťanský program a oddych. Sledovaním budete vedieť o pobytoch centra. '.self::CLOSING,
            ],
        ],
        1857 => [
            'name' => 'Evanjelická bohoslovecká fakulta UK v Bratislave',
            'address' => ['street' => 'Bartókova 8', 'postcode' => '811 02', 'municipality_id' => 242, 'latitude' => 48.1520513, 'longitude' => 17.0857196],
            'extend' => [
                0 => 'Fakulta pripravuje dni otvorených dverí pre uchádzačov a spolu s Rímskokatolíckou cyrilometodskou bohosloveckou fakultou UK ekumenické bohoslužby počas Týždňa modlitieb za jednotu kresťanov. Pripravuje aj konferencie a prednášky.',
                1 => 'Kanál je určený študentom, uchádzačom a odbornej verejnosti. Sledovaním budete vedieť o podujatiach fakulty. '.self::CLOSING,
            ],
        ],
        1854 => [
            'name' => 'Ekumenické kresťanské spoločenstvo mesta Prešov',
            'body' => [
                0 => 'Ekumenické kresťanské spoločenstvo mesta Prešov spája veriacich rôznych cirkví v Prešove. Podporuje spoločnú modlitbu a spoluprácu cirkví v meste. Pripravuje ekumenické podujatia.',
                1 => 'Organizuje spoločné modlitby, napríklad ekumenické modlitby za voľby v Konkatedrále sv. Mikuláša. Na modlitbách sa stretávajú duchovní aj veriaci rôznych cirkví. Sú otvorené pre všetkých.',
                2 => 'Kanál je určený veriacim z Prešova, ktorým záleží na jednote kresťanov. Sledovaním budete vedieť o ekumenických stretnutiach. '.self::CLOSING,
            ],
        ],
        1853 => [
            'name' => 'jezuitské spoločenstvo MAG+S',
            'address' => ['street' => 'Františkánske námestie 4', 'postcode' => '814 99', 'municipality_id' => 242, 'latitude' => 48.1442545, 'longitude' => 17.1085883],
            'extend' => [
                0 => 'Pripravuje sobotné duchovné obnovy, stretká a spoločné modlitby pri jezuitskom kostole v Bratislave. Obnovy majú vždy hlavnú tému, napríklad Cesty k radosti. Spolupracuje s jezuitmi a spoločenstvom CVX.',
                1 => 'Kanál je určený vysokoškolákom a mladým pracujúcim. Sledovaním budete vedieť o obnovách a stretnutiach. '.self::CLOSING.' Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1852 => [
            'name' => 'Cirkev bratská',
            'fix_municipality' => true,
            'address' => ['street' => 'Cukrová 14', 'postcode' => '811 08', 'municipality_id' => 242, 'latitude' => 48.148737, 'longitude' => 17.1194045],
            'extend' => [
                0 => 'Zbory pripravujú bohoslužby, biblické stretnutia aj podujatia pre verejnosť, napríklad výstavu Stretnutie s Bibliou v Prešove s Bibliami starými až 400 rokov. Ústredie cirkvi sídli na Cukrovej ulici v Bratislave.',
                1 => 'Kanál je určený členom a priateľom Cirkvi bratskej. Sledovaním budete vedieť o podujatiach zborov. '.self::CLOSING,
            ],
        ],
        1851 => [
            'name' => 'DEDIČSTVO OTCOV, o. z.',
            'address' => ['street' => 'Štítová 2', 'postcode' => '841 10', 'municipality_id' => 242, 'latitude' => 48.1737359, 'longitude' => 16.9878439],
            'body' => [
                0 => 'Občianske združenie Dedičstvo otcov pôsobí pri farnosti Bratislava-Devín. Pestuje cyrilo-metodskú a veľkomoravskú tradíciu Devína. Spolupracuje s farnosťou pri Kostole sv. Kríža.',
                1 => 'Pripravuje spomienkové slávnosti, napríklad pri výročí úmrtia sv. Konštantína-Cyrila. Slávnosti spájajú svätú omšu a kultúrny program. Konajú sa najmä v Devíne.',
                2 => 'Kanál je určený veriacim a všetkým, ktorým je blízke cyrilo-metodské dedičstvo. Sledovaním budete vedieť o slávnostiach. '.self::CLOSING.' Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1850 => [
            'name' => 'Linka Valentín',
            'extend' => [
                0 => 'Okrem rozhovorov a sprevádzania pripravuje trojdňové duchovné obnovy pre ľudí zranených v oblasti vzťahov, citov a sexuality. Obnovy sú otvorené aj pre ich blízkych. Pôsobí pri gréckokatolíckej farnosti Košice-Staré mesto.',
                1 => 'Kanál je určený ľuďom, ktorí hľadajú duchovnú pomoc a sprevádzanie. Sledovaním budete vedieť o obnovách. '.self::CLOSING.' Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1846 => [
            'name' => 'Evanjelická základná škola',
            'body' => [
                0 => 'Evanjelická základná škola v Bratislave sídli na ulici Palisády 57. Patrí k evanjelickým školám v Bratislave spolu s Evanjelickým lýceom. Spája vzdelávanie s kresťanskými hodnotami.',
                1 => 'Pripravuje dni otvorených dverí pre budúcich prvákov a ich rodičov. Organizuje aj školské slávnosti a služby Božie. Rodičia sa môžu zoznámiť s učiteľmi a prostredím školy.',
                2 => 'Kanál je určený rodičom a deťom, ktorí hľadajú základnú školu. Sledovaním budete vedieť o dňoch otvorených dverí a zápise. '.self::CLOSING,
            ],
        ],
        1844 => [
            'name' => 'Kňazský seminár sv. Františka Xaverského',
            'address' => ['street' => 'Banská 28', 'postcode' => '976 32', 'municipality_id' => 47, 'latitude' => 48.6689734, 'longitude' => 19.1224325],
            'extend' => [
                0 => 'Seminár pripravuje aj podujatia pre verejnosť, napríklad vernisáže výstav, ako bola výstava obrazov Martina Vaisa Consummatum est. V priestoroch seminára sa konajú aj konferencie a duchovné cvičenia.',
                1 => 'Kanál je určený veriacim Banskobystrickej diecézy a priateľom seminára. Sledovaním budete vedieť o jeho podujatiach. '.self::CLOSING,
            ],
        ],
        1843 => [
            'name' => 'ECAV Turany',
            'address' => ['street' => 'Komenského 313/2', 'postcode' => '038 53', 'municipality_id' => 3629, 'latitude' => 49.1152605, 'longitude' => 19.0344714],
            'extend' => [
                0 => 'Zbor pripravuje okrem služieb Božích aj prednáškové cykly, napríklad sériu prednášok o bolesti v zborovej miestnosti. Prednášky sú otvorené verejnosti.',
                1 => 'Kanál je určený evanjelikom z Turian a okolia. Sledovaním budete vedieť o prednáškach a podujatiach zboru. '.self::CLOSING,
            ],
        ],
        1842 => [
            'name' => 'Evanjelická cirkev v Maďarsku',
            'body' => [
                0 => 'Evanjelická cirkev v Maďarsku je luteránska cirkev pôsobiaca v Maďarsku. Jej web je evangelikus.hu.',
                1 => 'Pripravuje aj rozhlasové služby Božie v maďarskom jazyku, ktoré môžu počúvať veriaci aj na Slovensku. Spolupracuje s evanjelickými zbormi v susedných krajinách.',
                2 => 'Kanál je určený evanjelikom, ktorí hovoria po maďarsky. Sledovaním budete vedieť o vysielaniach a podujatiach. '.self::CLOSING,
            ],
        ],
        1841 => [
            'name' => 'CZ ECAV Hanušovce nad Topľou',
            'body' => [
                0 => 'Cirkevný zbor ECAV Hanušovce nad Topľou je evanjelický zbor v okrese Vranov nad Topľou. Patrí do Východného dištriktu Evanjelickej cirkvi a. v. na Slovensku. Slúži evanjelikom mesta a okolia.',
                1 => 'Okrem služieb Božích ich vysiela aj online, napríklad cez svoju facebookovú stránku. Pripravuje aj stretnutia pre deti, mládež a dospelých.',
                2 => 'Kanál je určený evanjelikom z Hanušoviec a okolia. Sledovaním budete vedieť o službách Božích a podujatiach. '.self::CLOSING,
            ],
        ],
        1840 => [
            'name' => 'CZ ECAV Svätý Jur',
            'body' => [
                0 => 'Cirkevný zbor ECAV Svätý Jur je evanjelický zbor v meste Svätý Jur pri Bratislave. Patrí do Západného dištriktu Evanjelickej cirkvi a. v. na Slovensku. Slúži evanjelikom mesta a okolia.',
                1 => 'Okrem služieb Božích ponúka aj online kázne a príhovory. Pripravuje stretnutia pre deti, mládež a dospelých.',
                2 => 'Kanál je určený evanjelikom zo Svätého Jura a okolia. Sledovaním budete vedieť o službách Božích a podujatiach. '.self::CLOSING,
            ],
        ],
        1839 => [
            'name' => 'Gréckokatolícke mládežnícke pastoračné centrum',
            'body' => [
                0 => 'Gréckokatolícke mládežnícke pastoračné centrum v Prešove sa venuje mladým v Prešovskej archieparchii. Pripravuje programy pre mladých východného obradu. Spolupracuje s farnosťami.',
                1 => 'Organizuje duchovné obnovy, stretnutia a výzvy, napríklad digitálnu Pôstnu výzvu. Spolupodieľa sa aj na veľkých stretnutiach mládeže.',
                2 => 'Kanál je určený mladým gréckokatolíkom. Sledovaním budete vedieť o programoch centra. '.self::CLOSING,
            ],
        ],
        1838 => [
            'name' => 'spoločenstvo mladých vysokoškolákov zo saleziánskeho strediska Bratislava - Trnávka',
            'body' => [
                0 => 'Spoločenstvo mladých vysokoškolákov sa stretáva v saleziánskom stredisku Bratislava-Trnávka. Spája študentov a mladých pracujúcich. Žije saleziánskou spiritualitou.',
                1 => 'Pripravuje modlitbové iniciatívy, napríklad nepretržitú sieť modlitieb za Božiu pomoc. Stretáva sa pri modlitbe, rozhovoroch a službe.',
                2 => 'Kanál je určený mladým z Bratislavy. Sledovaním budete vedieť o stretnutiach a iniciatívach. '.self::CLOSING,
            ],
        ],
        1837 => [
            'name' => 'Sestra farárka Anna Činčuráková',
            'body' => [
                0 => 'Anna Činčuráková-Tipulová je evanjelická farárka v Cirkevnom zbore ECAV Stará Turá. Venuje sa najmä ženám a rodinám. Služby a stretnutia ponúka aj online.',
                1 => 'Pripravuje stretnutia pre ženy a dievčatá, napríklad Čas pre ženy, ktoré prebiehajú v neformálnom prostredí. Témy vychádzajú z biblických príbehov žien.',
                2 => 'Kanál je určený ženám zo Starej Turej a okolia. Sledovaním budete vedieť o ďalších stretnutiach. '.self::CLOSING,
            ],
        ],
        1835 => [
            'name' => 'Cirkev Pána Ježiša',
            'body' => [
                0 => 'Cirkev Pána Ježiša je kresťanské spoločenstvo, ktoré pripravuje programy pre mladých. Pôsobí na Slovensku. Spája modlitbu, chvály a slovo.',
                1 => 'Pripravuje akcie pre mladých, napríklad stretnutie Zmenený samotou. Stretnutia prebiehajú aj online. Sú otvorené pre všetkých mladých.',
                2 => 'Kanál je určený mladým, ktorí hľadajú spoločenstvo. Sledovaním budete vedieť o ďalších akciách. '.self::CLOSING,
            ],
        ],
        1834 => [
            'name' => '3. ekumenický Kirchentag',
            'body' => [
                0 => 'Ekumenický Kirchentag je veľké stretnutie katolíkov a evanjelikov v Nemecku. Tretí ekumenický Kirchentag sa konal v roku 2021 vo Frankfurte nad Mohanom s mottom Choďte pozrieť. Pripravujú ho nemecké katolícke a evanjelické laické organizácie.',
                1 => 'Program tvoria bohoslužby, diskusie, prednášky a kultúrne podujatia. Kvôli pandémii prebiehal najmä digitálne. Prihlásiť sa naň mohli aj veriaci zo Slovenska.',
                2 => 'Kanál je určený všetkým, ktorých zaujíma ekumenizmus. Sledovaním budete vedieť o podobných stretnutiach. '.self::CLOSING,
            ],
        ],
        1833 => [
            'name' => 'Farnosť Dobrého pastiera Žilina - Solinky',
            'body' => [
                0 => 'Farnosť Dobrého pastiera je rímskokatolícka farnosť na sídlisku Solinky v Žiline. Patrí do Žilinskej diecézy. Farským kostolom je Kostol Dobrého pastiera.',
                1 => 'Pripravuje programy pre deti aj dospelých, napríklad biblický kurz Spoznávame Bibliu pre deti do desať rokov či online obnovu Ako žili prví kresťania. Organizuje aj duchovné cvičenia.',
                2 => 'Kanál je určený veriacim zo Solinky a Žiliny. Sledovaním budete vedieť o kurzoch a obnovách farnosti. '.self::CLOSING,
            ],
        ],
        1830 => [
            'name' => 'CZ ECAV Stará Turá',
            'body' => [
                0 => 'Cirkevný zbor ECAV Stará Turá je evanjelický zbor v Starej Turej. Patrí do Západného dištriktu Evanjelickej cirkvi a. v. na Slovensku. Pôsobia v ňom manželia Činčurákovci.',
                1 => 'Pripravuje stretnutia pre ženy a mamičky aj online, napríklad na tému Ester – vyvolená pre túto chvíľu. V ťažkých časoch ponúkal aj službu Nie si sám.',
                2 => 'Kanál je určený evanjelikom zo Starej Turej. Sledovaním budete vedieť o stretnutiach zboru. '.self::CLOSING,
            ],
        ],
        1828 => [
            'name' => 'Pápežská rada pre medzináboženský dialóg',
            'body' => [
                0 => 'Pápežská rada pre medzináboženský dialóg bola úradom Svätej stolice pre vzťahy s inými náboženstvami. Od roku 2022 pokračuje ako Dikastérium pre medzináboženský dialóg. Podporuje vzájomné porozumenie a spoluprácu.',
                1 => 'Podporuje spoločné iniciatívy, napríklad výzvu pápeža Františka na deň modlitby, pôstu a skutkov milosrdenstva za koniec pandémie. Pozvala k nej veriacich všetkých náboženstiev.',
                2 => 'Kanál je určený všetkým, ktorých zaujíma dialóg náboženstiev. Sledovaním budete vedieť o ďalších iniciatívach. '.self::CLOSING,
            ],
        ],
        1826 => [
            'name' => 'Českí pallotíni',
            'body' => [
                0 => 'Českí pallotíni patria do Spoločnosti katolíckeho apoštolátu, ktorú založil sv. Vincent Pallotti. Pôsobia v Českej republike. Venujú sa pastorácii a duchovnému sprevádzaniu.',
                1 => 'Pripravujú duchovné cvičenia, aj online, napríklad exercície formou Lectio divina na YouTube. Cvičenia sú otvorené aj veriacim zo Slovenska.',
                2 => 'Kanál je určený veriacim, ktorí hľadajú duchovné cvičenia. Sledovaním budete vedieť o ďalších exercíciách. '.self::CLOSING,
            ],
        ],
        1825 => [
            'name' => 'OF Záchráňme kultúru',
            'body' => [
                0 => 'Záchráňme kultúru je iniciatíva, ktorá podporuje koncerty a kultúrne podujatia. Upozorňuje na význam kultúry v spoločnosti. Spolupracuje s umelcami a cirkevnými inštitúciami.',
                1 => 'Pripravila napríklad koncert Vďaka, Slovensko! k 100. výročiu narodenia sv. Jána Pavla II. v Katedrále – Bazilike sv. Emeráma na Nitrianskom hrade. Koncert bol venovaný jeho vzťahu k Slovensku.',
                2 => 'Kanál je určený milovníkom hudby a kultúry. Sledovaním budete vedieť o ďalších koncertoch. '.self::CLOSING,
            ],
        ],
        1824 => [
            'name' => 'Krakovská arcidiecéza',
            'city' => 'krakov',
            'body' => [
                0 => 'Krakovská arcidiecéza je rímskokatolícka arcidiecéza so sídlom v Krakove v Poľsku. Jej arcibiskupom bol v rokoch 1964 – 1978 Karol Wojtyła, neskorší pápež Ján Pavol II. Sídli v Arcibiskupskom paláci na Franciszkańskej ulici.',
                1 => 'Pripravuje slávnosti spojené s Jánom Pavlom II., napríklad oslavy stého výročia narodenia Karola Wojtyłu. Organizuje aj konferencie a stretnutia s krakovským arcibiskupom.',
                2 => 'Kanál je určený veriacim, ktorým je blízky Krakov a odkaz Jána Pavla II. Sledovaním budete vedieť o slávnostiach arcidiecézy. '.self::CLOSING,
            ],
        ],
        1822 => [
            'name' => 'Charita ČR',
            'body' => [
                0 => 'Charita Česká republika je najväčším neštátnym poskytovateľom sociálnych a zdravotných služieb v Českej republike. Patrí Katolíckej cirkvi. Pomáha aj v zahraničí.',
                1 => 'Spolupodieľa sa na veľkých podujatiach, napríklad na Dňoch ľudí dobrej vôle na Velehrade s benefičným koncertom. Organizuje Trojkráľovú zbierku.',
                2 => 'Kanál je určený podporovateľom charity a pútnikom na Velehrad. Sledovaním budete vedieť o jej podujatiach. '.self::CLOSING,
            ],
        ],
        1821 => [
            'name' => 'Kláštor sestier redemptoristiek',
            'body' => [
                0 => 'Kláštor sestier redemptoristiek sa nachádza vo Vranove nad Topľou v časti Lomnica. Redemptoristky sú kontemplatívne mníšky Rádu Najsvätejšieho Vykupiteľa. Ich život sa sústreďuje na modlitbu.',
                1 => 'Sestry ponúkajú duchovné obnovy, napríklad obnovu o Lectio divina, ktorá učí denne žiť s Božím slovom. Obnovy sa konajú v kláštore. Treba sa na ne prihlásiť vopred.',
                2 => 'Kanál je určený veriacim, ktorí hľadajú ticho a modlitbu. Sledovaním budete vedieť o obnovách v kláštore. '.self::CLOSING,
            ],
        ],
        1820 => [
            'name' => 'Gréckokatolícky kňaz Peter Paľovčík',
            'body' => [
                0 => 'Peter Paľovčík je gréckokatolícky kňaz, ktorý sa venuje mladým a hudbe. Spolupracuje s hudobníkom Mariánom Čekovským a pedagógmi konzervatória. Spája vieru a hudobné vzdelávanie.',
                1 => 'Organizuje Hudobný kresťanský tábor Hraj, spievaj, modli sa pre deti od 10 do 16 rokov. Tábor sa konal napríklad v Jahodnej pri Košiciach. Spája hudbu, modlitbu a oddych.',
                2 => 'Kanál je určený deťom a rodičom, ktorí hľadajú hudobný tábor. Sledovaním budete vedieť o ďalších ročníkoch. '.self::CLOSING,
            ],
        ],
        1819 => [
            'name' => 'Františkánsky kláštor',
            'body' => [
                0 => 'Františkánsky kláštor v Trnave stojí pri Kostole sv. Jakuba na Františkánskej ulici. Patrí Slovenskej provincii františkánov. Slúži veriacim aj návštevníkom mesta.',
                1 => 'Pripravuje podujatia pre verejnosť, napríklad Františkánske dvere dokorán k výročiu Akcie K, keď komunistický režim v roku 1950 násilne zrušil kláštory. Návštevníci môžu spoznať kláštor a jeho históriu.',
                2 => 'Kanál je určený veriacim a návštevníkom Trnavy. Sledovaním budete vedieť o podujatiach kláštora. '.self::CLOSING,
            ],
        ],
        1816 => [
            'name' => 'Cirkevný zbor v Ružomberku',
            'body' => [
                0 => 'Cirkevný zbor ECAV v Ružomberku je evanjelický zbor na Liptove. Patrí do Východného dištriktu Evanjelickej cirkvi a. v. Slúži evanjelikom mesta a okolia.',
                1 => 'Okrem služieb Božích sa zapája do dištriktuálnych podujatí. Jeho služby Božie vysielala aj RTVS. Pripravuje stretnutia pre deti, mládež a dospelých.',
                2 => 'Kanál je určený evanjelikom z Ružomberka. Sledovaním budete vedieť o službách Božích a podujatiach zboru. '.self::CLOSING,
            ],
        ],
        1814 => [
            'name' => 'Združenie zázračnej medaily',
            'body' => [
                0 => 'Združenie zázračnej medaily je mariánske laické hnutie, ktoré šíri úctu k Panne Márii prostredníctvom zázračnej medaily. Medailu dala raziť sv. Katarína Labouré po zjaveniach v Paríži v roku 1830. Združenie sprevádzajú vincentíni a Dcéry kresťanskej lásky.',
                1 => 'Pripravuje púte, napríklad ďakovnú púť členov do Rajeckej Lesnej, a modlitbové stretnutia. Členovia sa stretávajú vo farnostiach po celom Slovensku.',
                2 => 'Kanál je určený ctiteľom Panny Márie. Sledovaním budete vedieť o pútiach združenia. '.self::CLOSING,
            ],
        ],
        1811 => [
            'name' => 'Rímskokatolícky farský úrad sv. Klimenta Močenok',
            'body' => [
                0 => 'Farnosť sv. Klimenta v Močenku patrí do Nitrianskej diecézy. K obci patrí časť Gorazdov, ktorá nesie meno sv. Gorazda, učeníka sv. Cyrila a Metoda. Gorazdov je miestom úcty k tomuto svätcovi.',
                1 => 'Spolu s obcou a združením Krediv pripravuje slávnosti v Močenku-Gorazdove, napríklad spomienku na výročie kňazského svätenia sv. Gorazda. Slávnosti spájajú svätú omšu a kultúrny program.',
                2 => 'Kanál je určený veriacim z Močenku a ctiteľom sv. Gorazda. Sledovaním budete vedieť o slávnostiach farnosti. '.self::CLOSING,
            ],
        ],
        1810 => [
            'name' => 'Rímskokatolícky farský úrad v Lehote pri Nitre',
            'body' => [
                0 => 'Farnosť Lehota pri Nitre patrí do Nitrianskej diecézy. Nad obcou je mariánsky pútnický areál. Farnosť sa oň stará.',
                1 => 'Každé leto pripravuje tradičnú mariánsku púť v areáli nad obcou. Púť spája svätú omšu, modlitbu a stretnutie pútnikov. Prichádzajú na ňu veriaci z okolia Nitry.',
                2 => 'Kanál je určený pútnikom a veriacim z okolia Nitry. Sledovaním budete vedieť o termíne púte. '.self::CLOSING,
            ],
        ],
        1809 => [
            'name' => 'Jaroslav Slašťan',
            'body' => [
                0 => 'Jaroslav Slašťan je autor z Kremnice, ktorý pripravuje publikácie o osobnostiach Cirkvi. Venuje sa najmä postave banskobystrického biskupa Rudolfa Baláža.',
                1 => 'Pripravoval spomienkovú monografiu o biskupovi Rudolfovi Balážovi, bývalom predsedovi KBS. Do jej tvorby pozval aj verejnosť, ktorá mohla poslať svoje spomienky.',
                2 => 'Kanál je určený všetkým, ktorým je blízky odkaz biskupa Baláža. Sledovaním budete vedieť o nových publikáciách. '.self::CLOSING,
            ],
        ],
        1807 => [
            'name' => 'CZ ECAV Pliešovce',
            'body' => [
                0 => 'Cirkevný zbor ECAV Pliešovce je evanjelický zbor v okrese Zvolen. Slúži evanjelikom obce a okolia.',
                1 => 'Okrem služieb Božích pripravuje Deň diakonie, popoludnie venované starším členom zboru. Program sa koná v evanjelickom kostole. Spája stretnutie, rozhovor a slovo.',
                2 => 'Kanál je určený evanjelikom z Pliešoviec. Sledovaním budete vedieť o podujatiach zboru. '.self::CLOSING,
            ],
        ],
        1806 => [
            'name' => 'Františkánska komunita',
            'body' => [
                0 => 'Františkánska komunita v Beckove patrí Slovenskej provincii františkánov. Bratia pôsobia v kláštore pri Kostole sv. Jozefa v Beckove. Žijú podľa reguly sv. Františka z Assisi.',
                1 => 'Pripravujú slávnosti františkánskeho roka, napríklad sviatok Porciunkuly, keď možno získať úplné odpustky. Ponúkajú aj spovedanie a duchovné rozhovory.',
                2 => 'Kanál je určený veriacim z Beckova a okolia. Sledovaním budete vedieť o slávnostiach komunity. '.self::CLOSING,
            ],
        ],
        1804 => [
            'name' => 'levočská farnosť',
            'body' => [
                0 => 'Farnosť Levoča patrí do Spišskej diecézy. Farským kostolom je Bazilika sv. Jakuba s najvyšším gotickým oltárom na svete od Majstra Pavla. K farnosti patrí aj pútnická Bazilika Navštívenia Panny Márie na Mariánskej hore.',
                1 => 'Pripravuje púte na Mariánskej hore, napríklad slávnosť výročia posviacky rímskej baziliky Santa Maria Maggiore. Najväčšia púť sa koná začiatkom júla.',
                2 => 'Kanál je určený veriacim z Levoče a pútnikom na Mariánsku horu. Sledovaním budete vedieť o pútiach a slávnostiach. '.self::CLOSING,
            ],
        ],
        1801 => [
            'name' => 'saleziánska komunita',
            'body' => [
                0 => 'Saleziáni don Bosca pôsobia na Slovensku vo farnostiach, školách a strediskách mládeže. Venujú sa výchove mladých v duchu sv. Jána Bosca. Táto komunita pôsobí v Poprade.',
                1 => 'Pripravujú podujatia pre deti a mladých, napríklad tradičnú Bodku za prázdninami na Mariánskej hore v Levoči. Organizujú aj tábory a stretká.',
                2 => 'Kanál je určený deťom, mladým a ich rodičom. Sledovaním budete vedieť o podujatiach saleziánov. '.self::CLOSING,
            ],
        ],
        1800 => [
            'name' => 'Kancelária Biskupského úradu v Banskej Bystrici',
            'body' => [
                0 => 'Kancelária Biskupského úradu v Banskej Bystrici zabezpečuje správu Banskobystrickej diecézy. Diecéza vznikla v roku 1776. Na jej čele stojí banskobystrický biskup.',
                1 => 'Ohlasuje diecézne slávnosti a púte, napríklad oslavu 30. výročia vyhlásenia chrámu na Starých Horách za baziliku. Staré Hory sú hlavným mariánskym pútnickým miestom diecézy.',
                2 => 'Kanál je určený veriacim Banskobystrickej diecézy. Sledovaním budete vedieť o diecéznych podujatiach. '.self::CLOSING,
            ],
        ],
        1799 => [
            'name' => 'Obec Oravská Polhora',
            'body' => [
                0 => 'Oravská Polhora je obec na hornej Orave pod Babou horou. Je najsevernejšou obcou Slovenska. Leží pri hranici s Poľskom.',
                1 => 'Spolupodieľa sa na tradičnom výstupe na Babiu horu k Pamätníku Jána Pavla II., spojenom so svätou omšou. Výstupu sa každoročne zúčastňujú desiatky turistov zo Slovenska aj Poľska.',
                2 => 'Kanál je určený turistom a pútnikom na Babiu horu. Sledovaním budete vedieť o termíne výstupu. '.self::CLOSING,
            ],
        ],
        1798 => [
            'name' => 'Farský úrad Nová Ves nad Žitavou',
            'body' => [
                0 => 'Farnosť Nová Ves nad Žitavou patrí do Nitrianskej diecézy. Stará sa o pútnické miesto Studnička. Studnička je zasvätená Panne Márii Kráľovnej.',
                1 => 'Každé leto pripravuje trojdňovú Púť k Panne Márii Kráľovnej na Studničke. Púť spája sväté omše, modlitbu a spoločenstvo pútnikov. Konajú sa tu aj ďalšie púte.',
                2 => 'Kanál je určený pútnikom a veriacim z Ponitria. Sledovaním budete vedieť o pútiach na Studničke. '.self::CLOSING,
            ],
        ],
        1797 => [
            'name' => 'Informačné stredisko Prešovskej archieparchie',
            'body' => [
                0 => 'Informačné stredisko Prešovskej archieparchie informuje o živote Gréckokatolíckej cirkvi na východe Slovenska. Pôsobí pri arcibiskupskom úrade v Prešove. Pripravuje tlačové správy a pozvánky.',
                1 => 'Ohlasuje archieparchiálne púte a slávnosti, napríklad odpustovú slávnosť v Ľutine či slávnosť bl. biskupa Vasiľa Hopka v jeho rodisku Hrabskom. Ľutina je najvýznamnejším gréckokatolíckym pútnickým miestom.',
                2 => 'Kanál je určený gréckokatolíkom a pútnikom. Sledovaním budete vedieť o archieparchiálnych slávnostiach. '.self::CLOSING,
            ],
        ],
        1796 => [
            'name' => 'združenie Gotická cesta',
            'body' => [
                0 => 'Združenie Gotická cesta spravuje turistickú trasu po gotických pamiatkach Gemera, Spiša a Malohontu. Trasa spája stredoveké kostoly so vzácnymi freskami. Spolupracuje s cirkevnými zbormi a farnosťami.',
                1 => 'Každý rok koncom augusta pripravuje dni, keď dobrovoľníci otvoria brány gotických kostolov. Podujatie sa rozšírilo aj do maďarskej časti Gemera. Návštevníci dostanú výklad o pamiatkach.',
                2 => 'Kanál je určený milovníkom histórie a umenia. Sledovaním budete vedieť o dňoch otvorených kostolov. '.self::CLOSING,
            ],
        ],
        1794 => [
            'name' => 'Rada pre mládež Košickej eparchie',
            'body' => [
                0 => 'Rada pre mládež Košickej eparchie sa venuje mladým gréckokatolíkom východného Slovenska. Pripravuje programy v spolupráci s eparchiálnym biskupom. Pôsobí v Košiciach.',
                1 => 'Organizuje Kvetný víkend mladých na Kvetnú nedeľu a Púť synov a otcov s vladykom Cyrilom do Klokočova. Niektoré stretnutia prebiehajú aj online.',
                2 => 'Kanál je určený mladým a otcom z Košickej eparchie. Sledovaním budete vedieť o stretnutiach a pútiach. '.self::CLOSING,
            ],
        ],
        1793 => [
            'name' => 'Farnosť Rudinská',
            'body' => [
                0 => 'Farnosť Rudinská leží v okrese Kysucké Nové Mesto a patrí do Žilinskej diecézy. V areáli kostola je mariánske miesto zasvätené Fatimskej Panne Márii. Farnosť ho rozvíja ako miesto modlitby.',
                1 => 'Pripravuje pravidelné pobožnosti Fatimskej soboty v areáli kostola. Pobožnosť spája ruženec, svätú omšu a zasvätenie. Prichádzajú na ňu veriaci z Kysúc.',
                2 => 'Kanál je určený ctiteľom Fatimskej Panny Márie. Sledovaním budete vedieť o fatimských sobotách. '.self::CLOSING,
            ],
        ],
        1790 => [
            'name' => 'Žilinská Farnosť Dobrého pastiera',
            'body' => [
                0 => 'Farnosť Dobrého pastiera pôsobí na sídlisku Solinky v Žiline. Patrí do Žilinskej diecézy. Má aktívne spoločenstvá dospelých aj mladých.',
                1 => 'Pripravuje duchovné cvičenia, napríklad cvičenia s hosťami z Nemecka v Čičmanoch. Ponúka aj kurzy a obnovy pre farníkov.',
                2 => 'Kanál je určený veriacim zo Solinky a Žiliny. Sledovaním budete vedieť o duchovných cvičeniach. '.self::CLOSING,
            ],
        ],
        1787 => [
            'name' => 'Rimavský seniorát ECAV na Slovensku',
            'body' => [
                0 => 'Rimavský seniorát spája evanjelické cirkevné zbory v okolí Rimavskej Soboty. Patrí do Východného dištriktu Evanjelickej cirkvi a. v. na Slovensku. Na jeho čele stojí senior.',
                1 => 'Pripravuje seniorátne podujatia a výstavy, napríklad výstavu Vyšívaná história reformácie na Slovensku v Gemersko-malohontskom múzeu. Organizuje aj stretnutia mládeže a duchovných.',
                2 => 'Kanál je určený evanjelikom z Gemera a Malohontu. Sledovaním budete vedieť o podujatiach seniorátu. '.self::CLOSING,
            ],
        ],
        1785 => [
            'name' => 'Rímskokatolícky úrad v Kežmarku',
            'body' => [
                0 => 'Rímskokatolícky farský úrad v Kežmarku spravuje farnosť pri Bazilike minor Povýšenia sv. Kríža. Patrí do Spišskej diecézy. Gotická bazilika patrí k pamiatkam mesta.',
                1 => 'Pripravuje aj vzdelávacie podujatia, napríklad dekanátny kurz lektorov s Katolíckym biblickým dielom. V Kežmarku sa konajú aj učenícke školy Spišskej diecézy.',
                2 => 'Kanál je určený veriacim z Kežmarku a okolia. Sledovaním budete vedieť o kurzoch a slávnostiach farnosti. '.self::CLOSING,
            ],
        ],
        1784 => [
            'name' => 'Oravské kultúrne stredisko',
            'body' => [
                0 => 'Oravské kultúrne stredisko v Dolnom Kubíne je kultúrno-osvetová inštitúcia Žilinského samosprávneho kraja. Podporuje kultúru a tradície Oravy. Organizuje festivaly, súťaže a tvorivé dielne.',
                1 => 'Pripravuje napríklad medzinárodný kamenársky plenér v Oravskom Bielom Potoku, kde kamenári zo Slovenska a Poľska tvoria sochy patrónov kostolov. Diela potom zdobia obce Studenej doliny.',
                2 => 'Kanál je určený obyvateľom Oravy a milovníkom ľudovej kultúry. Sledovaním budete vedieť o podujatiach strediska. '.self::CLOSING,
            ],
        ],
    ];

    public function up(): void
    {
        $nationwide = Municipality::nationwideId();

        foreach (self::PROFILES as $id => $profile) {
            $canal = Canal::withTrashed()->find($id);

            if (! $canal || $canal->trashed() || $canal->merged_into_id !== null || $canal->claimed_at !== null
                || $canal->name !== $profile['name']) {
                continue;
            }

            $changes = [];
            $paragraphs = substr_count((string) $canal->body, '<p');

            if (isset($profile['body']) && $paragraphs !== 3) {
                $changes['body'] = implode('', array_map(fn (string $p) => '<p>'.e($p).'</p>', $profile['body']));
            } elseif (isset($profile['extend']) && $paragraphs <= 1 && trim(strip_tags((string) $canal->body)) !== '') {
                // Faktografický jednoodsekový popis ostane, pridajú sa dva odseky.
                $first = $paragraphs === 1 ? rtrim((string) $canal->body) : '<p>'.trim((string) $canal->body).'</p>';
                $changes['body'] = $first.implode('', array_map(fn (string $p) => '<p>'.e($p).'</p>', $profile['extend']));
            }

            if (isset($profile['city'])) {
                $cityId = DB::table('municipalities')->where('slug', $profile['city'])->value('id');
                if ($cityId !== null && (int) $canal->municipality_id !== (int) $cityId) {
                    $changes['municipality_id'] = (int) $cityId;
                }
            }

            if (isset($profile['address']) && in_array($canal->coordinates_source, ['municipality', 'nationwide', 'ai', null], true)) {
                $a = $profile['address'];
                $municipalityOk = ! empty($profile['fix_municipality']) || $canal->municipality_id === $a['municipality_id']
                    || $canal->municipality_id === null || (int) $canal->municipality_id === $nationwide;

                if ($municipalityOk) {
                    $changes['municipality_id'] = $a['municipality_id'];
                    if (trim((string) $canal->street) === '') {
                        $changes['street'] = $a['street'];
                        $changes['postcode'] = $a['postcode'];
                    }
                    $changes += ['latitude' => $a['latitude'], 'longitude' => $a['longitude'], 'coordinates_source' => 'address'];
                }
            }

            if ($changes !== []) {
                $canal->forceFill($changes)->save();
                Recorder::info('canals', 'profile_described', "Kanál {$canal->name}: doplnené ".implode(', ', array_keys($changes)),
                    status: 'ok', subject: $canal, context: ['fields' => array_keys($changes)]);
            }
        }
    }

    public function down(): void
    {
        // Doplnené údaje sa nevracajú.
    }
};
