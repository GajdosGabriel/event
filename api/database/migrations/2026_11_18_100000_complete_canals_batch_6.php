<?php

use App\Models\Canal;
use App\Models\Municipality;
use App\Services\SystemLog\Recorder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Šiesta dávka (november 2026): ďalších 80 importovaných kanálov v poradí
 * (#1380 – #1490). Päťdesiatdva z nich malo všeobecný AI popis alebo len
 * „organizátor podujatí" — dostanú tri odseky podľa podujatí kanála a verejne
 * známych faktov. Ostatné už trojodsekový popis majú.
 *
 * Opravy:
 *  - #1449 Filozofická fakulta TU mala popis Technickej univerzity v Košiciach,
 *    ide o Filozofickú fakultu Trnavskej univerzity,
 *  - #1469 farnosť Poprad-mesto patrí do Spišskej diecézy, nie Košickej,
 *  - #1490 Dubovská fara a #1436 mali cudzí web (Kežmarok, český neurobeat.cz).
 *
 * Kanál musí sedieť ID aj názvom; prevzatý, zlúčený či zmazaný sa preskočí.
 */
return new class extends Migration
{
    private const CLOSING = 'Podrobnosti nájdete vždy v detaile podujatia.';

    /** @var array<int, array<string, mixed>> */
    private const PROFILES = [
        1490 => ['name' => 'Dubovská fara', 'fix_website' => null, 'body' => [
            'Farnosť Dubová leží pod Malými Karpatmi v okrese Pezinok. Patrí do Bratislavskej arcidiecézy. Fara stojí na Podhorskej ulici.',
            'Pripravuje duchovné obnovy pre mladých, napríklad obnovu pre páry S kým ísť do manželstva? o snúbeneckom období.',
            'Kanál je určený veriacim z Dubovej a mladým párom. Sledovaním budete vedieť o obnovách. '.self::CLOSING,
        ]],
        1486 => ['name' => 'Farnosti dekanátu Prešov – stred a farnosť Vyšná Šebastová', 'body' => [
            'Farnosti dekanátu Prešov – stred a farnosť Vyšná Šebastová spoločne pripravujú misie a programy pre veriacich Prešova. Patria do Košickej arcidiecézy.',
            'Organizujú Prešovské misie v Konkatedrále sv. Mikuláša a ďalších kostoloch mesta. Misie spájajú kázne, spovedanie a modlitbu.',
            'Kanál je určený veriacim z Prešova. Sledovaním budete vedieť o misiách. '.self::CLOSING,
        ]],
        1483 => ['name' => 'Senior, konseniori a kňazi CZ Turčianskeho seniorátu ECAV na Slovensku', 'body' => [
            'Turčiansky seniorát spája evanjelické cirkevné zbory v Turci. Patrí do Západného dištriktu Evanjelickej cirkvi a. v. Na jeho čele stojí senior.',
            'Pripravuje seniorátne dni, napríklad v Turčianskych Tepliciach. Program spája služby Božie a stretnutie zborov.',
            'Kanál je určený evanjelikom z Turca. Sledovaním budete vedieť o podujatiach seniorátu. '.self::CLOSING,
        ]],
        1481 => ['name' => 'PRE ŠUŇAVU o. z', 'body' => [
            'Občianske združenie Pre Šuňavu pôsobí v obci Šuňava pod Tatrami. Podporuje kultúrny a duchovný život obce.',
            'Organizuje festival Za jazerom, duchovno-kultúrne stretnutie pri jazere v Šuňave. Spolupracuje s Kresťanskou policajnou asociáciou a Alianciou za rodinu.',
            'Kanál je určený rodinám a návštevníkom Podtatranska. Sledovaním budete vedieť o festivale. '.self::CLOSING,
        ]],
        1480 => ['name' => 'spoločenstvo sv.Anny', 'body' => [
            'Spoločenstvo sv. Anny je katolícke spoločenstvo žien. Pripravuje duchovné obnovy pre ženy.',
            'Organizuje napríklad obnovu Kráľovná – divožienka v Centre novej evanjelizácie bl. Jána Pavla II. v Beckove.',
            'Kanál je určený ženám, ktoré hľadajú duchovnú obnovu. Sledovaním budete vedieť o obnovách. '.self::CLOSING,
        ]],
        1478 => ['name' => 'Pastoračný dom Anny Kolesárovej', 'body' => [
            'Pastoračný dom Anny Kolesárovej, nazývaný Domček, stojí vo Vysokej nad Uhom, rodisku blahoslavenej Anny Kolesárovej. Slúži pútnikom a na duchovné obnovy.',
            'Pripravuje spomienky na výročie blahorečenia Anny Kolesárovej (1. septembra 2018), Ankine soboty a duchovné obnovy.',
            'Kanál je určený pútnikom a ctiteľom bl. Anny Kolesárovej. Sledovaním budete vedieť o obnovách a púťach. '.self::CLOSING,
        ]],
        1474 => ['name' => 'Centrum novej evanjelizácie JP2, o.z', 'body' => [
            'Centrum novej evanjelizácie bl. Jána Pavla II. sídli v Beckove. Ponúka duchovné obnovy, kurzy a ubytovanie.',
            'Pripravuje napríklad ikonopisecké kurzy spojené s duchovnou obnovou. Konajú sa tu aj obnovy rôznych spoločenstiev.',
            'Kanál je určený všetkým, ktorí hľadajú obnovu a tvorivé kurzy. Sledovaním budete vedieť o programe centra. '.self::CLOSING,
        ]],
        1471 => ['name' => 'miestna komunita pavlínov', 'body' => [
            'Pavlíni vo Vranove nad Topľou spravujú Baziliku Narodenia Panny Márie. Je to významné mariánske pútnické miesto Košickej arcidiecézy.',
            'Pripravujú odpustové slávnosti Narodenia Panny Márie v septembri, s modlitbami a adoráciou. Prichádzajú na ne pútnici z celého regiónu.',
            'Kanál je určený pútnikom a veriacim z Vranova. Sledovaním budete vedieť o slávnostiach. '.self::CLOSING,
        ]],
        1469 => ['name' => 'Rímskokatolícka farnosť Poprad-mesto', 'body' => [
            'Farnosť Poprad-mesto patrí do Spišskej diecézy. Jej hlavným chrámom je Konkatedrála Sedembolestnej Panny Márie. Pastoračné centrum má na Nábreží Jána Pavla II.',
            'Pripravuje konferencie a modlitbové stretnutia, napríklad konferenciu Nech zostúpi oheň či stretnutie Slovensko na kolenách v konkatedrále.',
            'Kanál je určený veriacim z Popradu. Sledovaním budete vedieť o podujatiach farnosti. '.self::CLOSING,
        ]],
        1468 => ['name' => 'Detská železnica Košice', 'body' => [
            'Košická detská historická železnica jazdí v údolí Čermeľa. Úzkokoľajka patrí k turistickým atrakciám Košíc.',
            'Na železnici osadili prvú železničnú zvonicu na Slovensku. Pripravuje aj rodinné a sezónne jazdy.',
            'Kanál je určený rodinám s deťmi. Sledovaním budete vedieť o podujatiach na železnici. '.self::CLOSING,
        ]],
        1467 => ['name' => 'Dominikánske Centrum', 'body' => [
            'Dominikánske centrum ponúka duchovné cvičenia a obnovy v dominikánskom duchu. Spolupracuje s exercičnými domami.',
            'Pripravilo napríklad duchovné cvičenia v Exercičnom dome sv. Ignáca v Prešove.',
            'Kanál je určený všetkým, ktorí hľadajú duchovné cvičenia. Sledovaním budete vedieť o programe. '.self::CLOSING,
        ]],
        1465 => ['name' => 'bratia vincentíni', 'body' => [
            'Vincentíni, Misijná spoločnosť sv. Vincenta de Paul, spravujú farnosť Bratislava-Prievoz. Jej kostolom je Kostol sv. Vincenta de Paul na Tomášikovej ulici.',
            'Pripravujú slávnosti a spomienky, napríklad svätú omšu pri výročí posviacky kostola a nedožitých sedemdesiatinách biskupa Milana Šášika, ktorý bol vincentín.',
            'Kanál je určený veriacim z Prievozu. Sledovaním budete vedieť o slávnostiach. '.self::CLOSING,
        ]],
        1464 => ['name' => 'Centrum univerzitnej pastorácie', 'body' => [
            'Centrum univerzitnej pastorácie v Martine sa venuje študentom Jesseniovej lekárskej fakulty UK. Ponúka sväté omše a stretnutia.',
            'Spolupodieľa sa na Celoslovenskej kresťanskej medickej konferencii, ktorá sa koná na internátoch lekárskej fakulty v Martine.',
            'Kanál je určený študentom medicíny a zdravotníkom. Sledovaním budete vedieť o konferenciách. '.self::CLOSING,
        ]],
        1462 => ['name' => 'Štátna filharmónia Košice', 'body' => [
            'Štátna filharmónia Košice je profesionálny symfonický orchester so sídlom v Dome umenia v Košiciach. Patrí k popredným orchestrom na Slovensku.',
            'Organizuje Medzinárodný organový festival Ivana Sokola, ktorý rozozvučí organy v kostoloch viacerých miest. Pripravuje aj Košickú hudobnú jar.',
            'Kanál je určený milovníkom klasickej a organovej hudby. Sledovaním budete vedieť o koncertoch. '.self::CLOSING,
        ]],
        1456 => ['name' => 'Laický inštitút Synov a dcér Nepoškvrneného Srdca Panny Márie v Zjavení Pána - Miles Jesu', 'body' => [
            'Miles Jesu je laický inštitút zasväteného života, ktorý vznikol v USA. Jeho spiritualita sa sústreďuje na udalosť Troch kráľov. Na Slovensku pôsobí v Banskej Bystrici.',
            'Pripravuje prezentácie svojej charizmy a duchovné stretnutia v Banskej Bystrici na Jegorovovej ulici.',
            'Kanál je určený všetkým, ktorí sa zaujímajú o zasvätený život. Sledovaním budete vedieť o stretnutiach. '.self::CLOSING,
        ]],
        1454 => ['name' => 'Tím pastorácie povolaní', 'body' => [
            'Tím pastorácie povolaní Bratislavskej arcidiecézy pomáha mladým pri hľadaní životného povolania.',
            'Pripravuje Kurz Samuel pre slobodných mladých od 19 do 30 rokov. Kurz spája stretnutia, modlitbu a sprevádzanie.',
            'Kanál je určený mladým, ktorí hľadajú svoje povolanie. Sledovaním budete vedieť o kurzoch. '.self::CLOSING,
        ]],
        1452 => ['name' => 'Misionári Najsvätejších sŕdc Pána Ježiša a Panny Márie a Sestry Služobníčky Nepoškvrneného počatia Panny Márie', 'body' => [
            'Misionári Najsvätejších sŕdc Ježiša a Márie a sestry Služobníčky spolupracujú pri práci s mladými. Sestry majú kláštor vo Svite.',
            'Pripravujú duchovné obnovy pre mladých od 15 rokov, napríklad adventné obnovy vo Svite.',
            'Kanál je určený mladým. Sledovaním budete vedieť o obnovách. '.self::CLOSING,
        ]],
        1449 => ['name' => 'Filozofická fakulta TU', 'body' => [
            'Filozofická fakulta Trnavskej univerzity sídli na Hornopotočnej ulici v Trnave. Ponúka štúdium psychológie, histórie, filozofie a ďalších humanitných odborov.',
            'Pripravuje populárno-odborné prednášky, napríklad Psychologické večery v Trnave, a konferencie, napríklad kolokvium o Trnavskej Panne Márii.',
            'Kanál je určený študentom a verejnosti. Sledovaním budete vedieť o prednáškach fakulty. '.self::CLOSING,
        ]],
        1448 => ['name' => 'Kancelária BÚ Rožňava', 'body' => [
            'Kancelária Biskupského úradu Rožňava zabezpečuje správu Rožňavskej diecézy. Ohlasuje diecézne podujatia.',
            'Pripravuje Rožňavskú novénu k Rožňavskej Panne Márii, ktorá sa koná od roku 2021 každý október v katedrále aj farnostiach.',
            'Kanál je určený veriacim Rožňavskej diecézy. Sledovaním budete vedieť o novéne a podujatiach. '.self::CLOSING,
        ]],
        1447 => ['name' => 'Modlitebná liga cisára Karola', 'body' => [
            'Modlitebná liga bl. cisára Karola za mier medzi národmi šíri úctu k blahoslavenému Karolovi I. Habsburskému. Slovenská pobočka pôsobí v Nitre.',
            'Pripravuje prednášky a sympóziá, napríklad prednášku v Ponitrianskom múzeu o význame cisára Karola sto rokov po jeho smrti.',
            'Kanál je určený ctiteľom bl. Karola a priateľom histórie. Sledovaním budete vedieť o prednáškach. '.self::CLOSING,
        ]],
        1445 => ['name' => 'Arcidiecézny katechetický úrad Bratislavskej arcidiecézy', 'body' => [
            'Arcidiecézny katechetický úrad Bratislavskej arcidiecézy sa stará o katechézu a vyučovanie náboženstva. Podporuje katechétov a farnosti.',
            'Pripravil napríklad nový program prípravy na birmovanie Poď a uvidíš, do ktorého sa môžu zapojiť farnosti.',
            'Kanál je určený katechétom a farnostiam. Sledovaním budete vedieť o programoch. '.self::CLOSING,
        ]],
        1444 => ['name' => 'Trnavské osvetové stredisko', 'body' => [
            'Trnavské osvetové stredisko je kultúrno-osvetová inštitúcia Trnavského samosprávneho kraja. Podporuje ľudovú kultúru a záujmovú umeleckú činnosť.',
            'Spolupodieľalo sa napríklad na svetovej premiére Missa solemnis Tyrnaviensis Mikuláša Schneidera-Trnavského v trnavskej bazilike.',
            'Kanál je určený milovníkom kultúry z Trnavského kraja. Sledovaním budete vedieť o podujatiach. '.self::CLOSING,
        ]],
        1443 => ['name' => 'Konzervatívny denník Postoj', 'body' => [
            'Postoj je slovenský konzervatívny internetový denník. Prináša spravodajstvo, komentáre a témy viery. Jeho web je postoj.sk.',
            'Pripravuje verejné diskusie, napríklad diskusiu Počúva Cirkev svojich veriacich? v Bratislave.',
            'Kanál je určený čitateľom Postoja. Sledovaním budete vedieť o diskusiách. '.self::CLOSING,
        ]],
        1440 => ['name' => 'Lukas Lauro', 'body' => [
            'Lukas Lauro pripravuje online modlitbové večery katolíckej komunity Cor et Lumen Christi.',
            'Ohlasuje napríklad online večery modlitby za uzdravenie. Do večerov sa dá zapojiť z domu.',
            'Kanál je určený všetkým, ktorí sa chcú pripojiť k online modlitbe. Sledovaním budete vedieť o ďalších večeroch. '.self::CLOSING,
        ]],
        1436 => ['name' => 'www', 'fix_website' => null, 'body' => [
            'Tento kanál vznikol pri importe podujatí bez uvedeného organizátora.',
            'Obsahuje napríklad pozvánku na Košické Hanusove dni v Dominikánskom kultúrnom centre Veritas, ktoré pripravuje Inštitút Ladislava Hanusa.',
            'Organizátora konkrétneho podujatia nájdete vždy v jeho detaile. '.self::CLOSING,
        ]],
        1434 => ['name' => 'Slovenská katolícka charity', 'body' => [
            'Slovenská katolícka charita je charitatívna organizácia Katolíckej cirkvi na Slovensku. Spája diecézne a eparchiálne charity. Pomáha doma aj v zahraničí.',
            'Sprostredkúva aj zahraničnú pomoc, napríklad pomoc z Nemecka pre núdznych z Ukrajiny. Organizuje zbierky a Národný týždeň charity.',
            'Kanál je určený podporovateľom charity. Sledovaním budete vedieť o zbierkach. '.self::CLOSING,
        ]],
        1430 => ['name' => 'Komunita sv. Egídia', 'body' => [
            'Komunita sv. Egídia (Sant’Egidio) je katolícke laické hnutie, ktoré vzniklo v Ríme. Venuje sa modlitbe, chudobným a mieru. Pôsobí aj v Bratislave.',
            'Pripravuje medzinárodné modlitby za mier, napríklad pri Koloseu v Ríme, ktoré prenáša aj TV LUX. Organizuje aj stretnutia náboženstiev.',
            'Kanál je určený všetkým, ktorým záleží na mieri a chudobných. Sledovaním budete vedieť o podujatiach komunity. '.self::CLOSING,
        ]],
        1429 => ['name' => 'Farnosť Zvolen-Západ', 'body' => [
            'Farnosť Zvolen-Západ pôsobí na sídlisku Západ vo Zvolene. Farským kostolom je Kostol sv. Dominika. Patrí do Banskobystrickej diecézy.',
            'Pripravuje modlitbové stretnutia, napríklad Lámačské chvály vo Zvolene. Konajú sa tu aj modlitby za pokoj.',
            'Kanál je určený veriacim zo Zvolena. Sledovaním budete vedieť o podujatiach farnosti. '.self::CLOSING,
        ]],
        1426 => ['name' => 'Úrad vlády SR', 'body' => [
            'Úrad vlády Slovenskej republiky zabezpečuje činnosť vlády a predsedu vlády. Sídli v Bratislave.',
            'Kanál obsahuje oficiálne účasti predstaviteľov vlády na cirkevných podujatiach, napríklad na svätej omši za obete tragédie na Zámockej ulici v Katedrále sv. Martina.',
            'Kanál je určený verejnosti. '.self::CLOSING,
        ]],
        1425 => ['name' => 'Spoločenstvo Baránkovej krvi', 'body' => [
            'Spoločenstvo Baránkovej krvi je modlitbové spoločenstvo spojené s pôsobením pátra Eliasa Vellu. Pôsobí najmä v okolí Ladiec.',
            'Spolu s farnosťou Ladce a Považskou cementárňou pripravilo zádušnú svätú omšu za pátra Eliasa Vellu. Pripravuje aj modlitbové stretnutia.',
            'Kanál je určený ctiteľom pátra Vellu. Sledovaním budete vedieť o stretnutiach. '.self::CLOSING,
        ]],
        1424 => ['name' => 'Farnosť v Hronskom Beňadiku', 'body' => [
            'Farnosť Hronský Beňadik spravuje Baziliku sv. Benedikta opáta v areáli opevneného benediktínskeho kláštora nad Hronom. Kláštor patrí k najvýznamnejším gotickým pamiatkam Slovenska.',
            'Pripravuje púte do baziliky, napríklad púť ku Kristovi Kráľovi a slávnosť Zoslania Ducha Svätého, keď sa uctieva relikvia Svätej Krvi.',
            'Kanál je určený pútnikom a návštevníkom kláštora. Sledovaním budete vedieť o pútiach. '.self::CLOSING,
        ]],
        1422 => ['name' => 'Dobrota pre Afriku', 'body' => [
            'Dobrota pre Afriku je občianske združenie, ktoré pomáha v Stredoafrickej republike. Podporuje núdzne rodiny, deti a misijné projekty. Jeho web je dobrotapreafriku.sk.',
            'Organizuje zbierky, napríklad na výstavbu domov pre najchudobnejšie rodiny alebo dielo Chlieb sv. Jozefa pre hladujúcich.',
            'Kanál je určený podporovateľom pomoci Afrike. Sledovaním budete vedieť o zbierkach. '.self::CLOSING,
        ]],
        1421 => ['name' => 'Divadlo Andreja Bagara', 'body' => [
            'Divadlo Andreja Bagara v Nitre je profesionálne divadlo Nitrianskeho samosprávneho kraja. Hrá činohru aj muzikály.',
            'Uvádza napríklad pôvodný slovenský muzikál Povolanie pápež o živote Jána Pavla II., ktorý mal už vyše sto repríz.',
            'Kanál je určený divákom. Sledovaním budete vedieť o predstaveniach s duchovnou tematikou. '.self::CLOSING,
        ]],
        1420 => ['name' => 'Saleziánska stredná odborná škola v Žiline', 'body' => [
            'Stredná odborná škola sv. Jozefa Robotníka v Žiline je saleziánska škola. Pripravuje mladých na remeslá a technické povolania.',
            'Pripravuje dni otvorených dverí pre žiakov deviatych ročníkov a ich rodičov.',
            'Kanál je určený uchádzačom a rodičom. Sledovaním budete vedieť o dňoch otvorených dverí. '.self::CLOSING,
        ]],
        1418 => ['name' => 'Červená streda', 'body' => [
            'Červená streda je celosvetová iniciatíva Pápežskej nadácie ACN, ktorá upozorňuje na prenasledovanie kresťanov. Budovy a kostoly sa v ten deň nasvietia na červeno.',
            'Na Slovensku sa koná napríklad večer Skúšaní vo viere v Dóme sv. Martina. Pripomína príbehy prenasledovaných kresťanov.',
            'Kanál je určený všetkým, ktorým záleží na náboženskej slobode. Sledovaním budete vedieť o Červenej strede. '.self::CLOSING,
        ]],
        1416 => ['name' => 'Farnosť sv. Martina z Tours', 'body' => [
            'Farnosť sv. Martina z Tours v Bošanoch patrí do Nitrianskej diecézy. Farským kostolom je Kostol sv. Martina v Parku garbiarov.',
            'Pripravuje pravidelné mesačné modlitby za uzdravenie a oslobodenie. Organizuje aj misie a chvály.',
            'Kanál je určený veriacim z Bošian a okolia. Sledovaním budete vedieť o modlitbách. '.self::CLOSING,
        ]],
        1415 => ['name' => 'Ekumenické združenie kresťanských žien Aglow', 'body' => [
            'Aglow je medzinárodné ekumenické spoločenstvo kresťanských žien. Na Slovensku pôsobí v Bratislave a ďalších mestách.',
            'Pripravuje stretnutia žien, napríklad adventné stretnutie v Dome Quo Vadis v Bratislave.',
            'Kanál je určený ženám. Sledovaním budete vedieť o stretnutiach. '.self::CLOSING,
        ]],
        1414 => ['name' => 'Rímskokatolícky farský úrad Sv. Kríža', 'body' => [
            'Farnosť Povýšenia sv. Kríža v Snine patrí do Košickej arcidiecézy. Slúži veriacim mesta.',
            'Hostí program AMORIS pre manželov, víkendové stretnutie zamerané na posilnenie manželstva.',
            'Kanál je určený veriacim zo Sniny a manželom. Sledovaním budete vedieť o programoch. '.self::CLOSING,
        ]],
        1413 => ['name' => 'Saleziánska rozvojová organizácia SAVIO o.z', 'body' => [
            'SAVIO je saleziánska rozvojová organizácia. Podporuje projekty saleziánov v rozvojových krajinách a vysiela dobrovoľníkov.',
            'Pripravuje adventnú zbierku Štyri dary pre chudobných vo svete a prihlasovanie na ročnú misiu so saleziánmi.',
            'Kanál je určený podporovateľom a budúcim dobrovoľníkom. Sledovaním budete vedieť o zbierkach a misiách. '.self::CLOSING,
        ]],
        1412 => ['name' => 'Nadácia Pontis', 'body' => [
            'Nadácia Pontis je slovenská nadácia, ktorá podporuje darcovstvo, firemnú zodpovednosť a rozvojovú spoluprácu.',
            'Koordinuje na Slovensku kampaň #GivingTuesday, sviatok štedrosti, do ktorej sa zapájajú aj cirkevné organizácie.',
            'Kanál je určený darcom a neziskovým organizáciám. Sledovaním budete vedieť o kampaniach. '.self::CLOSING,
        ]],
        1409 => ['name' => 'Farský úrad v Močenku', 'body' => [
            'Farnosť Močenok patrí do Nitrianskej diecézy. K obci patrí Gorazdov, spojený s úctou k sv. Gorazdovi.',
            'Pripravuje koncerty, napríklad organový koncert v Močenku a na Nitrianskom hrade. Organizuje aj festival kresťanského divadla Gorazdov Močenok.',
            'Kanál je určený veriacim z Močenku. Sledovaním budete vedieť o koncertoch a festivaloch. '.self::CLOSING,
        ]],
        1408 => ['name' => 'gréckokatolícka farnosť biskupa Vasiľa Hopka', 'body' => [
            'Gréckokatolícka farnosť bl. biskupa Vasiľa Hopka pôsobí v Stropkove. Patrí do Prešovskej archieparchie.',
            'Pripravuje duchovné obnovy, napríklad adventnú obnovu na pozvanie dekana. Slávi liturgie a odpusty.',
            'Kanál je určený gréckokatolíkom zo Stropkova. Sledovaním budete vedieť o obnovách. '.self::CLOSING,
        ]],
        1405 => ['name' => 'Farnosť sv. Michala Archanjela', 'body' => [
            'Farnosť sv. Michala Archanjela v Bánove pri Nových Zámkoch patrí do Nitrianskej diecézy.',
            'Pripravuje koncerty, napríklad adventný koncert v kostole. Organizuje aj farské slávnosti.',
            'Kanál je určený veriacim z Bánova. Sledovaním budete vedieť o koncertoch. '.self::CLOSING,
        ]],
        1404 => ['name' => 'redakcia časopisu Studia Biblica Slovaca', 'body' => [
            'Studia Biblica Slovaca je slovenský odborný biblický časopis. Venuje sa výkladu Svätého písma.',
            'Redakcia udeľuje Cenu Jozefa Búdu za biblické dielo, laureátov vyhlasuje v Nedeľu Božieho slova.',
            'Kanál je určený biblistom a záujemcom o Sväté písmo. Sledovaním budete vedieť o cene a vydaniach. '.self::CLOSING,
        ]],
        1400 => ['name' => 'Slovo+', 'body' => [
            'Slovo+ je kresťanská mediálna iniciatíva. Ponúka duchovné texty, podcasty a projekty.',
            'Každý rok pred Vianocami ponúka možnosť odovzdať úmysly k jasličkám priamo v Betleheme.',
            'Kanál je určený všetkým, ktorí chcú poslať svoje úmysly. Sledovaním budete vedieť o projektoch. '.self::CLOSING,
        ]],
        1399 => ['name' => 'Tlačové stredisko Košickej eparchie', 'body' => [
            'Tlačové stredisko Košickej eparchie informuje o živote gréckokatolíkov východného Slovenska. Pôsobí pri eparchiálnom úrade v Košiciach.',
            'Ohlasuje podujatia eparchie, napríklad ponuku spovede na košickej železničnej stanici pred Vianocami.',
            'Kanál je určený gréckokatolíkom a médiám. Sledovaním budete vedieť o podujatiach eparchie. '.self::CLOSING,
        ]],
        1398 => ['name' => 'Credo, nadační fond', 'body' => [
            'Credo je český nadačný fond, ktorý podporuje evanjelizačné projekty. Spolupracuje so spoločenstvami v Česku aj na Slovensku.',
            'Pripravuje večery chvál, aj online, napríklad z Kroměříža, a podporuje obnovy, napríklad s pátrom Jamesom Manjackalom.',
            'Kanál je určený všetkým, ktorí sa chcú pripojiť k chválam. Sledovaním budete vedieť o večeroch. '.self::CLOSING,
        ]],
        1397 => ['name' => 'Občianske združenie Dobrý pastier Kláštor pod Znievom', 'body' => [
            'Občianske združenie Dobrý pastier z Kláštora pod Znievom pôsobí v Turci. Venuje sa vzdelávaniu vo viere.',
            'Jeho členovia prednášajú, napríklad kňaz Miroslav Klimant o sviatostiach kresťanskej iniciácie v Expozícii Ikony v Žiline.',
            'Kanál je určený veriacim z Turca a okolia. Sledovaním budete vedieť o prednáškach. '.self::CLOSING,
        ]],
        1396 => ['name' => 'Katedra ďalšieho vzdelávania na Fakulte teológie a pastorácie Boston College', 'body' => [
            'Boston College je jezuitská univerzita v USA. Jej fakulta teológie a pastorácie ponúka online kurzy ďalšieho vzdelávania.',
            'Spolu so sekretariátom Biskupskej synody pripravila bezplatný online kurz Dejiny, teológia a prax synodality.',
            'Kanál je určený záujemcom o teologické vzdelávanie. Sledovaním budete vedieť o kurzoch. '.self::CLOSING,
        ]],
        1388 => ['name' => 'Tomáš Rusňák HomoViator', 'body' => [
            'Tomáš Rusňák pripravuje prednášky o zdraví a životnom štýle pod názvom HomoViator.',
            'Organizuje napríklad prednášky Integrálne inovujeme zdravie v Chorvátskom Grobe.',
            'Kanál je určený všetkým, ktorí sa zaujímajú o zdravie. Sledovaním budete vedieť o prednáškach. '.self::CLOSING,
        ]],
        1385 => ['name' => 'Cirkevný zbor ECAV na Slovensku Modra', 'body' => [
            'Cirkevný zbor ECAV Modra je evanjelický zbor v Modre. Bohoslužby slávi v Kostole apoštolov Petra a Pavla na Dolnej ulici. Modra je spätá so štúrovcami.',
            'Pripravuje spomienky na Ľudovíta Štúra, Karola Štúra a Samuela Zocha. Konajú sa tu aj koncerty a výročné slávnosti.',
            'Kanál je určený evanjelikom z Modry a ctiteľom štúrovcov. Sledovaním budete vedieť o spomienkach. '.self::CLOSING,
        ]],
        1383 => ['name' => 'PREDSEDNÍCTVO EVANJELICKEJ CIRKVI A. V. NA SLOVENSKU', 'body' => [
            'Predsedníctvo Evanjelickej cirkvi a. v. na Slovensku tvoria generálny biskup a generálny dozorca. Zastupuje cirkev navonok.',
            'Pripravuje celocirkevné slávnosti, napríklad inštaláciu generálnej dozorkyne ECAV v Nitre.',
            'Kanál je určený evanjelikom. Sledovaním budete vedieť o celocirkevných slávnostiach. '.self::CLOSING,
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
