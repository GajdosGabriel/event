<?php

use App\Models\Canal;
use App\Models\Municipality;
use App\Services\SystemLog\Recorder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Siedma dávka (november 2026): ďalších 80 importovaných kanálov v poradí
 * (#1269 – #1379). Päťdesiatdva z nich malo všeobecný AI popis alebo len
 * „organizátor podujatí" — dostanú tri odseky podľa podujatí kanála a verejne
 * známych faktov. Ostatné už trojodsekový popis majú.
 *
 * Opravy:
 *  - #1369 Prešovský samosprávny kraj bol zapísaný v Bardejove,
 *  - #1356 „Národný výbor SDM" pripravuje ekumenický Svetový deň modlitieb,
 *    nie Svetové dni mládeže — popis to už nezamieňa,
 *  - #1292 Calvary mal web filmu z Hollywoodu.
 *
 * Kanál musí sedieť ID aj názvom; prevzatý, zlúčený či zmazaný sa preskočí.
 */
return new class extends Migration
{
    private const CLOSING = 'Podrobnosti nájdete vždy v detaile podujatia.';

    /** @var array<int, array<string, mixed>> */
    private const PROFILES = [
        1379 => ['name' => 'zboru Košice - Terasa', 'body' => [
            'Cirkevný zbor ECAV Košice-Terasa je evanjelický zbor na sídlisku Terasa v Košiciach. Bohoslužby slávi v Evanjelickom božom chráme na Toryskej ulici.',
            'Pripravuje večery pre mladých a dospelých, napríklad večer Chcemviac s inšpiratívnymi hosťami. Organizuje aj Noc kostolov.',
            'Kanál je určený evanjelikom z Košíc. Sledovaním budete vedieť o podujatiach zboru. '.self::CLOSING,
        ]],
        1378 => ['name' => 'Detská misia', 'body' => [
            'Detská misia je kresťanská organizácia, ktorá sa venuje evanjelizácii a vyučovaniu detí. Spolupracuje s cirkevnými zbormi rôznych cirkví.',
            'Pripravuje kurzy pre pracovníkov s deťmi, napríklad kurz Základy vyučovania detí a dorastu, a online programy pre deti, napríklad veľkonočné stíšenie.',
            'Kanál je určený učiteľom besiedok a rodičom. Sledovaním budete vedieť o kurzoch. '.self::CLOSING,
        ]],
        1377 => ['name' => 'FullHD cykloklub', 'body' => [
            'FullHD cykloklub spája cyklistov, ktorí spájajú šport s vierou. Pôsobí na Slovensku.',
            'Pripravuje seriál cyklopútí, ktoré vedú k pútnickým miestam počas celého roka.',
            'Kanál je určený cyklistom a pútnikom. Sledovaním budete vedieť o cyklopútiach. '.self::CLOSING,
        ]],
        1369 => ['name' => 'Prešovský samosprávny kraj', 'municipality_id' => 2822, 'body' => [
            'Prešovský samosprávny kraj je vyšší územný celok so sídlom v Prešove. Zriaďuje múzeá, divadlá a kultúrne strediská v regióne.',
            'Spolupodieľa sa na kultúrnych podujatiach, napríklad na výstave o kresťanských námetoch vo filatelii v Šarišskom múzeu v Bardejove či na festivale Dni Spišského Jeruzalema.',
            'Kanál je určený obyvateľom a návštevníkom kraja. Sledovaním budete vedieť o podujatiach. '.self::CLOSING,
        ]],
        1368 => ['name' => 'spišský diecézny administrátor Mons. Ján Kuboš', 'body' => [
            'Mons. Ján Kuboš je spišský pomocný biskup. Istý čas spravoval Spišskú diecézu ako diecézny administrátor.',
            'Podporuje modlitbovú iniciatívu Slovensko na kolenách, ktorá spája veriacich v spoločnej modlitbe, napríklad v popradskej konkatedrále.',
            'Kanál je určený veriacim Spišskej diecézy. Sledovaním budete vedieť o modlitbových stretnutiach. '.self::CLOSING,
        ]],
        1366 => ['name' => 'Centrum celého človeka - ENOIA', 'body' => [
            'Centrum celého človeka ENOIA z Považskej Bystrice spája psychológiu a vieru. Venuje sa duševnému zdraviu.',
            'Spolupodieľa sa na celoslovenskom stretnutí katolíckych psychológov a psychiatrov v Nimnici.',
            'Kanál je určený odborníkom a všetkým, ktorých zaujíma duševné zdravie. Sledovaním budete vedieť o stretnutiach. '.self::CLOSING,
        ]],
        1364 => ['name' => 'Duchovná správa FN Trnava', 'body' => [
            'Duchovná správa Fakultnej nemocnice Trnava sa stará o pacientov a zdravotníkov. Pôsobí v nemocničnej kaplnke.',
            'Pripravuje sväté omše za zdravotníkov, a koncerty v nemocničnej kaplnke.',
            'Kanál je určený pacientom a zdravotníkom. Sledovaním budete vedieť o bohoslužbách. '.self::CLOSING,
        ]],
        1362 => ['name' => 'Roveri skautského zboru Silvestra Krčméryho', 'body' => [
            'Roveri skautského zboru Silvestra Krčméryho sú starší skauti z Bratislavy. Zbor nesie meno lekára a väzňa komunizmu Silvestra Krčméryho.',
            'Pripravujú ranný chlapský pôstny powertraining na Kalvárii, ktorý spája cvičenie a modlitbu, napríklad s vladykom Milanom Lachom.',
            'Kanál je určený mladým mužom z Bratislavy. Sledovaním budete vedieť o akciách. '.self::CLOSING,
        ]],
        1360 => ['name' => 'Veriaci', 'body' => [
            'Tento kanál vznikol pri importe podujatí, pri ktorých bol ako organizátor uvedený len „Veriaci". Ide o modlitbové stretnutia v Partizánskom.',
            'Obsahuje napríklad modlitbu chvál pred Eucharistiou v Kostole Božského Srdca Ježišovho v Partizánskom.',
            'Kanál je určený veriacim z Partizánskeho. '.self::CLOSING,
        ]],
        1357 => ['name' => 'Farnosť Spišská Nová Ves', 'body' => [
            'Farnosť Spišská Nová Ves patrí do Spišskej diecézy. Farským kostolom je Kostol Nanebovzatia Panny Márie s najvyššou kostolnou vežou na Slovensku.',
            'Pripravuje duchovné obnovy, napríklad pôstnu obnovu Pôstom k vnútornej slobode.',
            'Kanál je určený veriacim zo Spišskej Novej Vsi. Sledovaním budete vedieť o obnovách. '.self::CLOSING,
        ]],
        1356 => ['name' => 'Národný výbor SDM', 'body' => [
            'Národný výbor Svetového dňa modlitieb pripravuje na Slovensku ekumenický Svetový deň modlitieb. Je to celosvetové hnutie kresťanských žien rôznych cirkví.',
            'Svetový deň modlitieb sa koná každý rok prvý piatok v marci. Liturgiu pripravujú vždy ženy z inej krajiny.',
            'Kanál je určený ženám a spoločenstvám, ktoré sa chcú zapojiť. Sledovaním budete vedieť o modlitbách. '.self::CLOSING,
        ]],
        1354 => ['name' => 'CZ ECAV Dúbravka', 'body' => [
            'Cirkevný zbor ECAV Bratislava-Dúbravka je evanjelický zbor v mestskej časti Dúbravka. Vznikol v roku 2013.',
            'Pripravuje služby Božie a slávnosti, napríklad oslavu 10. výročia založenia zboru, a stretnutia pre manželov.',
            'Kanál je určený evanjelikom z Dúbravky. Sledovaním budete vedieť o podujatiach zboru. '.self::CLOSING,
        ]],
        1352 => ['name' => 'Sestry z Nižného Hrušova', 'body' => [
            'Sestry v Nižnom Hrušove pri Vranove nad Topľou sú Sestry Božieho milosrdenstva. Žijú v kláštore a ponúkajú duchovné obnovy.',
            'Pozývajú ženy na víkendy Stretnúť Ježiša v tichu kláštora. Sestry sa modlia aj s pútnikmi vo vranovskej bazilike.',
            'Kanál je určený ženám, ktoré hľadajú ticho a modlitbu. Sledovaním budete vedieť o víkendoch. '.self::CLOSING,
        ]],
        1351 => ['name' => 'olomouckí katechéti', 'body' => [
            'Katechetické centrum Olomouckej arcidiecézy pripravuje vzdelávanie v katechéze a Biblii. Jeho web je katechetiolomouc.cz.',
            'Organizuje Letnú školu Božieho slova v Olomouci, napríklad s témou proroctvo. Zapájajú sa do nej aj Slováci.',
            'Kanál je určený katechétom a záujemcom o Bibliu. Sledovaním budete vedieť o letných školách. '.self::CLOSING,
        ]],
        1350 => ['name' => 'Kláštor Panny Márie Karmelskej a sv. Jozefa', 'body' => [
            'Kláštor Panny Márie Karmelskej a sv. Jozefa je kláštor bosých karmelitánov v Košiciach-Lorinčíku. Ponúka ticho a duchovné obnovy.',
            'Pripravuje obnovy pre rôzne skupiny, napríklad obnovu pre ženy či obnovu pre rozvedených.',
            'Kanál je určený všetkým, ktorí hľadajú obnovu. Sledovaním budete vedieť o programe kláštora. '.self::CLOSING,
        ]],
        1348 => ['name' => 'CK Trinity Košice', 'body' => [
            'CK Trinity je cestovná kancelária z Košíc. Organizuje pútnické a poznávacie zájazdy.',
            'Ponúka napríklad poznávací zájazd do Izraela či letecký zájazd Rím s evanjelikmi.',
            'Kanál je určený pútnikom. Sledovaním budete vedieť o zájazdoch. '.self::CLOSING,
        ]],
        1347 => ['name' => 'Cirkevné zbory ECAV Kežmarok, Levoča a Sabinov', 'body' => [
            'Cirkevné zbory ECAV Kežmarok, Levoča a Sabinov sú evanjelické zbory na Spiši a v Šariši. Spolupracujú pri spoločných podujatiach.',
            'Pripravujú online diskusie, napríklad o Fonde finančného zabezpečenia, a zájazdy, napríklad Protestantské Sedmohradsko.',
            'Kanál je určený evanjelikom zo Spiša a Šariša. Sledovaním budete vedieť o podujatiach. '.self::CLOSING,
        ]],
        1345 => ['name' => 'cirkevný odbor MK SR', 'body' => [
            'Cirkevný odbor Ministerstva kultúry SR zabezpečuje vzťahy štátu s cirkvami a náboženskými spoločnosťami.',
            'Podporuje aj vzdelávacie podujatia, napríklad online podujatie Digitálne závislosti.',
            'Kanál je určený cirkvám a verejnosti. '.self::CLOSING,
        ]],
        1342 => ['name' => 'Svetové dni mládeže', 'body' => [
            'Svetové dni mládeže sú celosvetové stretnutie katolíckej mládeže s pápežom. Konajú sa každé dva až tri roky v inej krajine.',
            'Pred SDM v Lisabone v roku 2023 sa na Slovensku konali promo výjazdy Portugalské večery v Bratislave, Martine a Košiciach. Ďalšie SDM budú v Soule v roku 2027.',
            'Kanál je určený mladým, ktorí sa chystajú na SDM. Sledovaním budete vedieť o prípravách. '.self::CLOSING,
        ]],
        1341 => ['name' => 'Farnosť sv. Jakuba', 'body' => [
            'Farnosť sv. Jakuba v Kysuckom Novom Meste patrí do Žilinskej diecézy. Farským kostolom je Kostol sv. Jakuba.',
            'Pripravuje podujatia pre farníkov aj rodiny, napríklad požehnanie novej Kysuckej krížovej cesty či rodinnú súťaž.',
            'Kanál je určený veriacim z Kysúc. Sledovaním budete vedieť o podujatiach farnosti. '.self::CLOSING,
        ]],
        1333 => ['name' => 'Ekonomika spoločenstva', 'body' => [
            'Ekonomika spoločenstva (Economy of Communion) je medzinárodné hnutie podnikateľov, ktoré vzniklo v Hnutí fokolárov. Spája podnikanie s etikou a solidaritou.',
            'Pripravuje webináre a stretnutia, napríklad webinár Prečo sú v podnikateľskom prostredí dôležité čnosti?.',
            'Kanál je určený podnikateľom a manažérom. Sledovaním budete vedieť o webinároch. '.self::CLOSING,
        ]],
        1330 => ['name' => 'farnosť pod Braniskom', 'body' => [
            'Farnosť Poľanovce leží pod pohorím Branisko. Patrí do Spišskej diecézy.',
            'Pripravuje kultúrne podujatia, napríklad muzikál Príbeh Veľkej noci v podaní farníkov.',
            'Kanál je určený veriacim z okolia Braniska. Sledovaním budete vedieť o podujatiach farnosti. '.self::CLOSING,
        ]],
        1326 => ['name' => 'Výbor cirkevnej hudby a hymnológie ECAV', 'body' => [
            'Výbor cirkevnej hudby a hymnológie ECAV sa stará o hudbu a spev v Evanjelickej cirkvi a. v. na Slovensku. Spolupracuje s Evanjelickým misijným centrom.',
            'Pripravuje dirigentské a kantorské kurzy, napríklad v Liptovskom Mikuláši a v Prešove.',
            'Kanál je určený kantorom, dirigentom a speváckym zborom. Sledovaním budete vedieť o kurzoch. '.self::CLOSING,
        ]],
        1325 => ['name' => 'farnosť Miloslavov', 'body' => [
            'Farnosť Miloslavov leží pri Bratislave a patrí do Bratislavskej arcidiecézy. K farnosti patrí Kostol Božieho milosrdenstva v Alžbetinom Dvore.',
            'Pripravuje slávnosti, napríklad Duchovné hody k 25. výročiu posviacky kostola.',
            'Kanál je určený veriacim z Miloslavova. Sledovaním budete vedieť o slávnostiach. '.self::CLOSING,
        ]],
        1324 => ['name' => 'Cirkevný zbor ECAV na Slovensku Betliar', 'body' => [
            'Cirkevný zbor ECAV Betliar je evanjelický zbor v obci Betliar pri Rožňave. Obec je známa kaštieľom Andrássyovcov.',
            'Pripravuje služby Božie a slávnosti, napríklad inštaláciu zborovej farárky.',
            'Kanál je určený evanjelikom z Betliara a okolia. Sledovaním budete vedieť o slávnostiach. '.self::CLOSING,
        ]],
        1322 => ['name' => 'Monika Ficzová', 'body' => [
            'Monika Ficzová informuje o podujatiach Cirkevného zboru ECAV Bratislava Legionárska.',
            'Ohlasuje napríklad výnimočné služby Božie k výročiu niekdajšieho farára zboru v Novom evanjelickom kostole na Legionárskej ulici.',
            'Kanál je určený evanjelikom z Bratislavy. '.self::CLOSING,
        ]],
        1318 => ['name' => 'SOŠ sv. Jozefa Robotníka', 'body' => [
            'Stredná odborná škola sv. Jozefa Robotníka v Žiline je saleziánska škola. Pripravuje mladých na remeslá a technické povolania.',
            'Študenti pripravujú aj projekty, napríklad ikony so saleziánskym svätcom Artemidom Zattim.',
            'Kanál je určený uchádzačom, rodičom a priaznivcom školy. Sledovaním budete vedieť o podujatiach školy. '.self::CLOSING,
        ]],
        1317 => ['name' => 'Farský úrad v Rajeckých Tepliciach', 'body' => [
            'Farnosť Rajecké Teplice patrí do Žilinskej diecézy. Farským kostolom je Kostol Božského Srdca Ježišovho.',
            'Pripravuje púte, napríklad Púť k sv. Gianne Berettovej Mollovej, patrónke matiek a rodín.',
            'Kanál je určený veriacim a kúpeľným hosťom. Sledovaním budete vedieť o pútiach. '.self::CLOSING,
        ]],
        1316 => ['name' => 'Obec Močenok', 'body' => [
            'Močenok je obec v okrese Šaľa. Jej súčasťou je Gorazdov, spojený s úctou k sv. Gorazdovi.',
            'Spolu so združením Krediv organizuje Celonárodný festival kresťanského divadla Gorazdov Močenok, ktorý má vyše tridsať ročníkov.',
            'Kanál je určený milovníkom divadla. Sledovaním budete vedieť o festivale. '.self::CLOSING,
        ]],
        1315 => ['name' => 'komunita františkánov', 'body' => [
            'Komunita františkánov v Beckove žije v kláštore pod Beckovským hradom. Patrí Slovenskej provincii františkánov.',
            'Pripravuje podujatia františkánskeho roka, napríklad Deň Greccio s biskupom Jozefom Haľkom.',
            'Kanál je určený veriacim z Beckova a okolia. Sledovaním budete vedieť o podujatiach. '.self::CLOSING,
        ]],
        1309 => ['name' => 'Hnutie Modlitby matiek z komunity Útecha', 'body' => [
            'Hnutie Modlitby matiek spája matky, ktoré sa v malých skupinách modlia za svoje deti. Vzniklo v Anglicku a pôsobí po celom svete.',
            'Organizuje celoslovenské stretnutia, napríklad vo Svite. Skupiny sa stretávajú vo farnostiach.',
            'Kanál je určený matkám a starým mamám. Sledovaním budete vedieť o stretnutiach. '.self::CLOSING,
        ]],
        1307 => ['name' => 'Gréckokatolícka farnosť bl. Vasiľa Hopku', 'body' => [
            'Gréckokatolícka farnosť bl. Vasiľa Hopka pôsobí v Brezne. Patrí do Bratislavskej eparchie.',
            'Pripravuje odpustové slávnosti ku cti blahoslaveného hieromučeníka Vasiľa Hopka.',
            'Kanál je určený gréckokatolíkom z Horehronia. Sledovaním budete vedieť o slávnostiach. '.self::CLOSING,
        ]],
        1306 => ['name' => 'SNM – Spišské múzeum v Levoči', 'body' => [
            'Spišské múzeum v Levoči je súčasťou Slovenského národného múzea. Spravuje zbierky z dejín Spiša, aj v Levočskej radnici.',
            'Prevzalo napríklad výstavu Pápežské dary Slovensku z Bratislavského hradu.',
            'Kanál je určený milovníkom histórie Spiša. Sledovaním budete vedieť o výstavách. '.self::CLOSING,
        ]],
        1304 => ['name' => 'Múzeum Lutherovej reformácie', 'body' => [
            'Múzeum Lutherovej reformácie je malé evanjelické múzeum. Predstavuje dejiny reformácie na Slovensku.',
            'Zapája sa do Noci múzeí a galérií v Martine.',
            'Kanál je určený evanjelikom a milovníkom histórie. '.self::CLOSING,
        ]],
        1303 => ['name' => 'Duchovná rada Slovenského skautingu', 'body' => [
            'Duchovná rada Slovenského skautingu sa stará o duchovný rozmer skautingu. Spája skautov rôznych cirkví.',
            'Pripravuje podujatia, napríklad 24-hodinovku o skautingu. Podporuje aj duchovných radcov oddielov.',
            'Kanál je určený skautom a ich vedúcim. Sledovaním budete vedieť o podujatiach. '.self::CLOSING,
        ]],
        1299 => ['name' => 'vdp. dekan Jozef Dronzek', 'body' => [
            'Jozef Dronzek je kňaz a dekan v Prešove. Pripravuje duchovné programy pre veriacich.',
            'Pozval napríklad na Deň s Božím milosrdenstvom v Prešove.',
            'Kanál je určený veriacim z Prešova. '.self::CLOSING,
        ]],
        1298 => ['name' => 'Spoločenstvo Hebron', 'body' => [
            'Spoločenstvo Hebron je modlitbové spoločenstvo z okolia Piešťan a Topoľčian.',
            'Pripravuje večery modlitieb a chvál, napríklad v Kuzmiciach pri Topoľčanoch.',
            'Kanál je určený veriacim, ktorí hľadajú chvály. Sledovaním budete vedieť o večeroch. '.self::CLOSING,
        ]],
        1297 => ['name' => 'Misionári Najsvätejšich Sŕdc Pána Ježiša a Panny Márie', 'body' => [
            'Misionári Najsvätejších sŕdc Ježiša a Márie sú rehoľa, ktorá na Slovensku pôsobí v Žiline a ďalších mestách. Ich web je misionari.sk.',
            'Pripravujú sväté omše a adorácie za povolania a kurzy, napríklad Rešpektujúca komunikácia vo vzťahoch.',
            'Kanál je určený mladým a rodinám. Sledovaním budete vedieť o programoch. '.self::CLOSING,
        ]],
        1294 => ['name' => 'Mládež pre Krista', 'body' => [
            'Mládež pre Krista (Youth for Christ) je medzinárodná evanjelikálna organizácia pre mladých. Na Slovensku pôsobí v kluboch a táboroch.',
            'Organizuje letný festival Campfest v Kráľovej Lehote.',
            'Kanál je určený mladým. Sledovaním budete vedieť o festivaloch a táboroch. '.self::CLOSING,
        ]],
        1292 => ['name' => 'Calvary', 'fix_website' => null, 'body' => [
            'Calvary je kresťanské spoločenstvo v Bratislave, ktoré pripravuje chvály.',
            'Pripravuje napríklad Chvály pre ženy v Kostole sv. Cyrila a Metoda na Puškinovej ulici.',
            'Kanál je určený ženám a všetkým, ktorí hľadajú chvály. '.self::CLOSING,
        ]],
        1290 => ['name' => 'Rektor Baziliky sv. Benedikta', 'body' => [
            'Rektor Baziliky sv. Benedikta v Hronskom Beňadiku spravuje pútnickú baziliku v areáli benediktínskeho kláštora.',
            'Pozýva na púte, napríklad Turíčnu púť, keď sa uctieva relikvia Svätej Krvi.',
            'Kanál je určený pútnikom. Sledovaním budete vedieť o pútiach do baziliky. '.self::CLOSING,
        ]],
        1289 => ['name' => 'CZ ECAV Banská Štiavnica', 'body' => [
            'Cirkevný zbor ECAV Banská Štiavnica je evanjelický zbor v pamiatkovom meste UNESCO. Jeho kostol stojí na Námestí sv. Trojice.',
            'Zapája sa do Noci kostolov a pripravuje koncerty a slávnosti.',
            'Kanál je určený evanjelikom a návštevníkom Štiavnice. Sledovaním budete vedieť o podujatiach. '.self::CLOSING,
        ]],
        1283 => ['name' => 'Turčiansky seniorát', 'body' => [
            'Turčiansky seniorát spája evanjelické zbory v Turci. Patrí do Západného dištriktu ECAV.',
            'Pripravuje seniorátne podujatia, napríklad stretnutie rodín.',
            'Kanál je určený evanjelikom z Turca. Sledovaním budete vedieť o podujatiach seniorátu. '.self::CLOSING,
        ]],
        1282 => ['name' => 'Bratislavský seniorát', 'body' => [
            'Bratislavský seniorát spája evanjelické cirkevné zbory v Bratislave a okolí. Patrí do Západného dištriktu ECAV.',
            'Pripravuje seniorátne podujatia, napríklad stretnutie detí a rodičov.',
            'Kanál je určený evanjelikom z Bratislavy a okolia. Sledovaním budete vedieť o podujatiach. '.self::CLOSING,
        ]],
        1281 => ['name' => 'Evanjelický cirkevný zbor v Modre-Kráľovej', 'body' => [
            'Evanjelický cirkevný zbor v Modre-Kráľovej pôsobí v miestnej časti Modry. Slúži evanjelikom z Kráľovej.',
            'Pripravuje kultúrne podujatia, napríklad Kráľovské hodovanie či koncert s vernisážou v Zelenom lese.',
            'Kanál je určený evanjelikom z Modry. Sledovaním budete vedieť o podujatiach zboru. '.self::CLOSING,
        ]],
        1279 => ['name' => 'don Jozef Luscoň', 'body' => [
            'Don Jozef Luscoň je saleziánsky kňaz. Venuje sa miništrantom a mladým.',
            'Pripravuje formačno-pobytový tábor Tymian pre miništrantov.',
            'Kanál je určený miništrantom a ich rodičom. Sledovaním budete vedieť o tábore. '.self::CLOSING,
        ]],
        1276 => ['name' => 'Farnosť Narodenia P. Márie', 'body' => [
            'Farnosť Narodenia Panny Márie v Michalovciach patrí do Košickej arcidiecézy. Jej web je michalovce.rimkat.sk.',
            'Zapája sa do Noci kostolov a pripravuje slávnosti farnosti.',
            'Kanál je určený veriacim z Michaloviec. Sledovaním budete vedieť o podujatiach. '.self::CLOSING,
        ]],
        1275 => ['name' => 'Hospic – Dom pokoja a zmieru u Bernadetky', 'body' => [
            'Hospic – Dom pokoja a zmieru u Bernadetky v Nitre poskytuje paliatívnu starostlivosť nevyliečiteľne chorým.',
            'Pripravuje benefičné podujatia, napríklad Beh za Hospic pod Zoborom.',
            'Kanál je určený podporovateľom hospicu. Sledovaním budete vedieť o benefíciách. '.self::CLOSING,
        ]],
        1274 => ['name' => 'Farnosť Modrý Kameň - Dolné Strháre', 'body' => [
            'Farnosť Modrý Kameň – Dolné Strháre leží v okrese Veľký Krtíš. Patrí do Banskobystrickej diecézy.',
            'Zapája sa do Noci kostolov a pripravuje slávnosti farnosti.',
            'Kanál je určený veriacim z Novohradu. Sledovaním budete vedieť o podujatiach. '.self::CLOSING,
        ]],
        1273 => ['name' => 'Rada pre laické a apoštolské hnutia', 'body' => [
            'Rada pre laické a apoštolské hnutia je orgán Konferencie biskupov Slovenska. Spája hnutia a spoločenstvá pôsobiace v Cirkvi.',
            'Pripravuje stretnutia lídrov hnutí, napríklad v Trnave a Banskej Bystrici.',
            'Kanál je určený lídrom hnutí a spoločenstiev. Sledovaním budete vedieť o stretnutiach. '.self::CLOSING,
        ]],
        1272 => ['name' => 'Noc kostolov', 'body' => [
            'Noc kostolov je podujatie, pri ktorom sa večer otvárajú kostoly, kaplnky a kláštory pre verejnosť. Na Slovensku sa koná každý rok koncom mája alebo začiatkom júna.',
            'Program pripravujú farnosti a cirkevné zbory: prehliadky, koncerty, výstavy a modlitbu.',
            'Kanál je určený všetkým, ktorí chcú spoznať kostoly. Sledovaním budete vedieť o Noci kostolov. '.self::CLOSING,
        ]],
        1269 => ['name' => 'Komunita sestier dominikánok', 'body' => [
            'Komunita sestier dominikánok bl. Imeldy žije v Dunajskej Lužnej. Venuje sa výchove a práci s mladými.',
            'Pripravuje víkendy pre mladé ženy, napríklad Víkend s dominikánkami, a kurzy.',
            'Kanál je určený mladým ženám. Sledovaním budete vedieť o víkendoch. '.self::CLOSING,
        ]],
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

            if (array_key_exists('fix_website', $profile) && $canal->website !== $profile['fix_website']) {
                $changes['website'] = $profile['fix_website'];
            }

            foreach (['website', 'phone'] as $field) {
                if (isset($profile[$field]) && trim((string) $canal->$field) === '') {
                    $changes[$field] = $profile[$field];
                }
            }

            if (isset($profile['municipality_id']) && (int) $canal->municipality_id !== $profile['municipality_id']
                && DB::table('municipalities')->where('id', $profile['municipality_id'])->exists()) {
                $changes['municipality_id'] = $profile['municipality_id'];
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
