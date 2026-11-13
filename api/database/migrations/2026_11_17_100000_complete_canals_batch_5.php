<?php

use App\Models\Canal;
use App\Models\Municipality;
use App\Services\SystemLog\Recorder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Piata dávka (november 2026): ďalších 60 importovaných kanálov v poradí
 * (#1491 – #1570). Štyridsaťdva z nich malo všeobecný AI popis alebo len
 * „organizátor podujatí" — dostanú tri odseky podľa podujatí kanála a verejne
 * známych faktov. Ostatné už trojodsekový popis majú.
 *
 * Opravy:
 *  - #1532 Múzeum obetí komunizmu v Košiciach bolo zapísané v Bratislave,
 *  - #1550 Teologická fakulta TU mala popis Technickej univerzity v Košiciach,
 *    ide o Teologickú fakultu Trnavskej univerzity v Bratislave.
 *
 * Kanál musí sedieť ID aj názvom; prevzatý, zlúčený či zmazaný sa preskočí.
 */
return new class extends Migration
{
    private const CLOSING = 'Podrobnosti nájdete vždy v detaile podujatia.';

    /** @var array<int, array<string, mixed>> */
    private const PROFILES = [
        1570 => ['name' => 'České centrum Bratislava', 'body' => [
            'České centrum Bratislava je súčasťou siete Českých centier, ktorú zriaďuje Ministerstvo zahraničných vecí Českej republiky. Predstavuje českú kultúru na Slovensku.',
            'Pripravuje výstavy, koncerty a diskusie, napríklad výstavu Labyrint sveta, ktorá prepája myšlienky Jána Amosa Komenského s ilustráciami. Výstavu otvorili v deň výročia jeho narodenia.',
            'Kanál je určený milovníkom českej kultúry. Sledovaním budete vedieť o nových výstavách a podujatiach. '.self::CLOSING,
        ]],
        1569 => ['name' => 'Trstínska farnosť', 'body' => [
            'Farnosť Trstín leží pod Malými Karpatmi v okrese Trnava. Patrí do Trnavskej arcidiecézy.',
            'Pripravuje púte, napríklad nočnú pôstnu púť Železný pútnik z Trstína-Hájička do Šaštína. Púť vedie cez noc pešo k Národnej svätyni.',
            'Kanál je určený veriacim z Trstína a pútnikom. Sledovaním budete vedieť o pútiach farnosti. '.self::CLOSING,
        ]],
        1567 => ['name' => 'Komunita Rodiny Boha Otca', 'body' => [
            'Komunita Rodiny Boha Otca je katolícke spoločenstvo, ktoré šíri úctu k Bohu Otcovi. Spája rodiny a jednotlivcov. Pôsobí na Slovensku.',
            'Pripravuje stretnutia a konferencie, napríklad stretnutie Ukrytý v náručí Otca v Poprade s programom v konkatedrále a Kostole sv. Egídia.',
            'Kanál je určený rodinám a všetkým, ktorí hľadajú duchovnú obnovu. Sledovaním budete vedieť o stretnutiach. '.self::CLOSING,
        ]],
        1563 => ['name' => 'Združenie evanjelických duchovných', 'body' => [
            'Združenie evanjelických duchovných spája farárov a farárky Evanjelickej cirkvi a. v. na Slovensku. Podporuje ich vzdelávanie a vzájomnú pomoc.',
            'Pripravuje webináre a konferencie pre duchovných, napríklad webinár Človek po smrti či medzinárodnú online konferenciu Farárka v 21. storočí.',
            'Kanál je určený evanjelickým duchovným a teológom. Sledovaním budete vedieť o vzdelávaní združenia. '.self::CLOSING,
        ]],
        1561 => ['name' => 'Evanjelické gymnázium J. A. Komenského', 'body' => [
            'Evanjelické gymnázium J. A. Komenského je cirkevné gymnázium v Košiciach. Nesie meno učiteľa národov Jána Amosa Komenského. Zriaďuje ho Evanjelická cirkev a. v.',
            'Pripravuje výstavy a projekty, napríklad výstavu Osudy rasovo prenasledovaných na Slovensku v rokoch 1939 – 1945 či prezentáciu príbehov košického evanjelického cintorína.',
            'Kanál je určený študentom, rodičom a verejnosti. Sledovaním budete vedieť o podujatiach gymnázia. '.self::CLOSING,
        ]],
        1557 => ['name' => 'Považské múzeum v Žiline', 'body' => [
            'Považské múzeum v Žiline je regionálne múzeum Žilinského samosprávneho kraja. Spravuje aj hrad Budatín a Sobášny palác v Bytči.',
            'Pripravuje výstavy, napríklad výstavu Požehnané Vianoce v renesančnom Sobášnom paláci v Bytči. Výstavy sprevádzajú prednášky a tvorivé dielne.',
            'Kanál je určený milovníkom histórie Považia. Sledovaním budete vedieť o nových výstavách. '.self::CLOSING,
        ]],
        1555 => ['name' => 'Mediálny výbor ECAV', 'body' => [
            'Mediálny výbor ECAV sa stará o komunikáciu Evanjelickej cirkvi a. v. na Slovensku. Pripravuje informácie pre médiá a verejnosť.',
            'Pripravil napríklad diskusie s kandidátmi na generálneho dozorcu ECAV pred voľbami v cirkvi. Diskusie prebiehali online.',
            'Kanál je určený evanjelikom a médiám. Sledovaním budete vedieť o dôležitých udalostiach cirkvi. '.self::CLOSING,
        ]],
        1553 => ['name' => 'Chorvátsko', 'body' => [
            'Tento kanál zhromažďuje podujatia, ktoré sa konajú v Chorvátsku a sú určené aj pútnikom a mladým zo Slovenska.',
            'Uverejnený bol napríklad medzinárodný program Magis Europe pre mladých, ktorý sa konal v Chorvátsku v ignaciánskom duchu.',
            'Kanál je určený mladým a pútnikom, ktorí sa chystajú do Chorvátska. Sledovaním budete vedieť o takýchto podujatiach. '.self::CLOSING,
        ]],
        1552 => ['name' => 'Farnosť Trnava – Tulipán', 'body' => [
            'Farnosť Trnava – Tulipán spravujú pavlíni. Pôsobí na sídlisku Tulipán v Trnave. Jej web je trnava-tulipan.sk.',
            'Pripravuje púte a duchovné podujatia, napríklad trojdňové podujatie ku cti sv. Jozefa či pešiu púť z Bratislavy do Marianky.',
            'Kanál je určený veriacim z Trnavy. Sledovaním budete vedieť o pútiach a slávnostiach farnosti. '.self::CLOSING,
        ]],
        1550 => ['name' => 'Teologická fakulta TU', 'body' => [
            'Teologická fakulta Trnavskej univerzity sídli na Kostolnej ulici v Bratislave pri Primaciálnom námestí. Vzdeláva v teológii, poradenstve a mediácii. Pri fakulte pôsobia jezuiti.',
            'Pripravuje konferencie a stretnutia, napríklad stretnutie s bratmi z Taizé. Na fakulte sa konajú aj duchovné obnovy Popoludnie s Ignácom.',
            'Kanál je určený študentom a verejnosti. Sledovaním budete vedieť o podujatiach fakulty. '.self::CLOSING,
        ]],
        1548 => ['name' => 'Rímskokatolícka farnosť sv. Dominika Savia', 'body' => [
            'Farnosť sv. Dominika Savia pôsobí na sídlisku Ťahanovce v Košiciach. Patrí do Košickej arcidiecézy. Jej patrónom je mladý svätec Dominik Savio, žiak sv. Jána Bosca.',
            'V roku 2022 posvätili nový farský kostol sv. Dominika Savia. Farnosť pripravuje bohoslužby a programy pre deti a mladých.',
            'Kanál je určený veriacim z Ťahanoviec. Sledovaním budete vedieť o podujatiach farnosti. '.self::CLOSING,
        ]],
        1543 => ['name' => 'Kolégium piaristov', 'body' => [
            'Kolégium piaristov v Nitre patrí Rehole piaristov na Slovensku. Piaristi sa venujú výchove a vzdelávaniu mládeže. Pri kostole sv. Ladislava majú kláštor a gymnázium.',
            'Pripravujú duchovné obnovy pre záujemcov o kňazské a rehoľné povolanie, napríklad obnovu Život zasvätených.',
            'Kanál je určený mladým mužom a priaznivcom piaristov. Sledovaním budete vedieť o obnovách. '.self::CLOSING,
        ]],
        1542 => ['name' => 'Schola Cantorum Kňazského seminára sv. Gorazda', 'body' => [
            'Schola Cantorum je spevácky zbor bohoslovcov Kňazského seminára sv. Gorazda v Nitre. Spieva pri liturgiách v seminári a v katedrále.',
            'Pripravuje koncerty, napríklad ďakovný koncert pre dobrodincov a priaznivcov seminára.',
            'Kanál je určený milovníkom zborového spevu a priaznivcom seminára. Sledovaním budete vedieť o koncertoch. '.self::CLOSING,
        ]],
        1540 => ['name' => 'Pro-life organizácie Slovenska', 'body' => [
            'Pro-life organizácie Slovenska sú združenia a iniciatívy, ktoré chránia život od počatia po prirodzenú smrť. Spolupracujú pri celoslovenských akciách.',
            'Organizujú 24-hodinovú Modlitbovú reťaz za život a rodinu, ktorá sa koná napríklad na sviatok sv. Jozefa. Zapojiť sa dá z ktoréhokoľvek miesta.',
            'Kanál je určený všetkým, ktorým záleží na ochrane života. Sledovaním budete vedieť o modlitbových reťaziach. '.self::CLOSING,
        ]],
        1539 => ['name' => 'Združenie Evropská kulturní stezka sv. Cyrila a Metoděje', 'body' => [
            'Európska kultúrna cesta sv. Cyrila a Metoda je kultúrna trasa Rady Európy. Spája miesta spojené so solúnskymi bratmi v Česku, na Slovensku a v ďalších krajinách. Jej web je pout.eu.',
            'Združenie pripravuje púte, napríklad pešiu púť z Velehradu do Nitry ku cti sv. Cyrila a Metoda.',
            'Kanál je určený pútnikom a milovníkom cyrilo-metodského dedičstva. Sledovaním budete vedieť o pútiach. '.self::CLOSING,
        ]],
        1537 => ['name' => 'dominikánske sestry a bratia', 'body' => [
            'Dominikánske sestry a bratia patria do dominikánskej rodiny Rehole kazateľov. Na Slovensku spolupracujú pri kázaní, vzdelávaní a práci s mladými.',
            'Pripravujú konferencie, napríklad konferenciu Komunikácia viery mladým v Dominikánskom kultúrnom centre Veritas v Košiciach.',
            'Kanál je určený priateľom dominikánov a pracovníkom s mládežou. Sledovaním budete vedieť o konferenciách. '.self::CLOSING,
        ]],
        1535 => ['name' => 'Združenie Máriina doba', 'body' => [
            'Združenie Máriina doba podporuje Mariánske kňazské hnutie a jeho večeradlá. Spája laikov a kňazov, ktorí sa modlia s Pannou Máriou.',
            'Pripravuje diecézne večeradlá, napríklad v Kostole Zvestovania Pána v Bratislave, a spomienky ako Sviečková manifestácia pri Notre Dame.',
            'Kanál je určený ctiteľom Panny Márie. Sledovaním budete vedieť o večeradlách a spomienkach. '.self::CLOSING,
        ]],
        1534 => ['name' => 'Rodina Panny Márie', 'body' => [
            'Rodina Panny Márie je spoločenstvo kňazov, rehoľníkov a laikov, ktoré založil Pavol Hnilica. Na Slovensku je známe Modlitbovými dňami. Jeho web je modlitbovyden.sk.',
            'Pripravuje modlitbové dni aj online a spomienkové sväté omše, napríklad pri 20. výročí úmrtia biskupa Pavla Hnilicu v Trnavskej katedrále.',
            'Kanál je určený ctiteľom Panny Márie. Sledovaním budete vedieť o modlitbových dňoch. '.self::CLOSING,
        ]],
        1533 => ['name' => 'Rada mládeže Slovenska', 'body' => [
            'Rada mládeže Slovenska je strešná organizácia mládežníckych organizácií na Slovensku. Zastupuje ich záujmy a podporuje dobrovoľníctvo mladých.',
            'Pripravuje podujatia, napríklad Dobrovoľníci deťom na Trojičnom námestí v Trnave. Organizuje aj vzdelávanie pre lídrov mládežníckych organizácií.',
            'Kanál je určený mladým a mládežníckym organizáciám. Sledovaním budete vedieť o podujatiach rady. '.self::CLOSING,
        ]],
        1532 => ['name' => 'Múzeum obetí komunizmu v Košiciach', 'municipality_id' => 1565, 'body' => [
            'Múzeum obetí komunizmu v Košiciach pripravuje Ústav pamäti národa. Pripomína ľudí prenasledovaných komunistickým režimom.',
            'Ústav pripravuje výstavy, diskusie a semináre o obdobiach neslobody. Podujatia sú otvorené verejnosti aj školám.',
            'Kanál je určený všetkým, ktorí sa zaujímajú o dejiny 20. storočia. Sledovaním budete vedieť o výstavách a diskusiách. '.self::CLOSING,
        ]],
        1530 => ['name' => 'Malé sestry Ježišove', 'body' => [
            'Malé sestry Ježišove sú rehoľná kongregácia, ktorá žije spiritualitou sv. Karola de Foucauld. Žijú v malých komunitách medzi obyčajnými ľuďmi, často chudobnými.',
            'Pripravili napríklad ďakovnú svätú omšu za svätorečenie brata Karola de Foucauld v Kostole sv. Vincenta de Paul v Bratislave.',
            'Kanál je určený všetkým, ktorým je blízka spiritualita Karola de Foucauld. Sledovaním budete vedieť o podujatiach. '.self::CLOSING,
        ]],
        1529 => ['name' => 'SLOVENSKÍ KATOLÍCI, o. z', 'body' => [
            'Občianske združenie Slovenskí katolíci podporuje kresťanské a národné tradície. Pripravuje vzdelávacie a spomienkové podujatia.',
            'Spolupracovalo napríklad na sympóziu Slovenské učené tovarišstvo v aule Trnavskej univerzity.',
            'Kanál je určený všetkým, ktorých zaujímajú slovenské dejiny. Sledovaním budete vedieť o podujatiach. '.self::CLOSING,
        ]],
        1528 => ['name' => 'Worldpriest Írsko', 'body' => [
            'Worldpriest je medzinárodná iniciatíva, ktorá vznikla v Írsku. Pozýva veriacich na celom svete k modlitbe za kňazov.',
            'Každý rok na sviatok Najsvätejšieho Srdca Ježišovho organizuje celosvetovú modlitbu za posvätenie kňazov. Zapája sa do nej aj Mariánska hora v Levoči.',
            'Kanál je určený všetkým, ktorí sa modlia za kňazov. Sledovaním budete vedieť o modlitbových iniciatívach. '.self::CLOSING,
        ]],
        1527 => ['name' => 'spoločenstvo SP a projekt Godzone', 'body' => [
            'Spoločenstvo SP zo Sliača spolupracuje s evanjelizačným projektom Godzone. Pripravuje modlitbové stretnutia a konferencie.',
            'Organizuje modlitbové stretnutia Zlomení a ženské konferencie, napríklad online konferenciu Nespútaná – Stopy.',
            'Kanál je určený mladým a ženám, ktoré hľadajú povzbudenie vo viere. Sledovaním budete vedieť o stretnutiach. '.self::CLOSING,
        ]],
        1525 => ['name' => 'Farnosť sv. Petra a Pavla', 'body' => [
            'Farnosť sv. Petra a Pavla pôsobí v Bratislave-Záhorskej Bystrici. Farským kostolom je historický Kostol sv. Petra a Pavla. Patrí do Bratislavskej arcidiecézy.',
            'Pripravuje stretnutia s hosťami, napríklad stretnutie so sýrskou mystičkou Myrnou Nazzour. Organizuje aj farské slávnosti.',
            'Kanál je určený veriacim zo Záhorskej Bystrice. Sledovaním budete vedieť o podujatiach farnosti. '.self::CLOSING,
        ]],
        1524 => ['name' => 'Verejná knižnica Jána Bocatia', 'body' => [
            'Verejná knižnica Jána Bocatia v Košiciach je krajská knižnica Košického samosprávneho kraja. Nesie meno humanistického básnika a richtára Jána Bocatia.',
            'Pripravuje prednáškové cykly, napríklad cyklus Čo nás môže naučiť kresťanská mystika?. Organizuje aj besedy a výstavy.',
            'Kanál je určený čitateľom a záujemcom o vzdelávanie. Sledovaním budete vedieť o prednáškach. '.self::CLOSING,
        ]],
        1517 => ['name' => 'Pátri redemptoristi', 'body' => [
            'Redemptoristi v Podolínci pôsobia v historickom kláštore v centre mesta. Do Podolínca prišli v roku 1922. Venujú sa misiám a duchovným obnovám.',
            'Pripravujú odpustové slávnosti Matky ustavičnej pomoci, napríklad pri 100. výročí príchodu redemptoristov do Podolínca.',
            'Kanál je určený veriacim zo Spiša a priaznivcom redemptoristov. Sledovaním budete vedieť o slávnostiach. '.self::CLOSING,
        ]],
        1516 => ['name' => 'Chrámový zbor Sancta Maria', 'body' => [
            'Chrámový zbor Sancta Maria je spevácky zbor zo Šamorína. Spieva pri bohoslužbách a koncertuje.',
            'Pripravuje koncerty, napríklad letný koncert v Kostole sv. Petra a Pavla v Bohdanovciach nad Trnavou.',
            'Kanál je určený milovníkom zborového spevu. Sledovaním budete vedieť o koncertoch zboru. '.self::CLOSING,
        ]],
        1515 => ['name' => 'Obnova kultúrnych tradícií, n.o', 'body' => [
            'Nezisková organizácia Obnova kultúrnych tradícií podporuje ľudové a duchovné tradície. Venuje sa najmä goralskej kultúre pod Tatrami.',
            'Pripravuje duchovno-kultúrny festival Goral na gory v Starom Smokovci, na ktorom bola napríklad socha Ludzimierskej Panny Márie.',
            'Kanál je určený milovníkom ľudových tradícií. Sledovaním budete vedieť o festivaloch. '.self::CLOSING,
        ]],
        1514 => ['name' => 'Pastoračná rada Bratislavskej arcidiecézy pre rodinu', 'body' => [
            'Pastoračná rada Bratislavskej arcidiecézy pre rodinu sa venuje manželom a rodinám v arcidiecéze. Jej web je rodinaba.sk.',
            'Pripravuje diecézne stretnutia rodín, napríklad stretnutie pri X. svetovom stretnutí rodín v Marianke a Bratislave. Organizuje aj kurzy a obnovy.',
            'Kanál je určený rodinám z Bratislavskej arcidiecézy. Sledovaním budete vedieť o stretnutiach rodín. '.self::CLOSING,
        ]],
        1513 => ['name' => 'Biskupský úrad Rožňava', 'municipality_id' => 3014, 'body' => [
            'Biskupský úrad Rožňava je sídlom Rožňavskej diecézy. Diecéza vznikla v roku 1776 a zahŕňa Gemer a Malohont. Na jej čele stojí rožňavský biskup.',
            'Pripravuje diecézne podujatia, napríklad diecézne stretnutie rodín v Poltári či Rožňavskú novénu.',
            'Kanál je určený veriacim Rožňavskej diecézy. Sledovaním budete vedieť o diecéznych podujatiach. '.self::CLOSING,
        ]],
        1511 => ['name' => 'Diecézna rada pre rodinu a dekanát Krupina', 'body' => [
            'Diecézna rada pre rodinu Banskobystrickej diecézy sa venuje rodinám v diecéze. Spolupracuje s dekanátmi, napríklad s dekanátom Krupina.',
            'Pripravuje diecézne stretnutia rodín, napríklad na Starých Horách v Bazilike Navštívenia Panny Márie.',
            'Kanál je určený rodinám z Banskobystrickej diecézy. Sledovaním budete vedieť o stretnutiach. '.self::CLOSING,
        ]],
        1507 => ['name' => 'Spoločenstvo Kráľovnej rodiny', 'body' => [
            'Spoločenstvo Kráľovnej rodiny je katolícke spoločenstvo, ktoré sa modlí za rodiny a kňazov. Pôsobí najmä v Trnave.',
            'Pripravilo napríklad duchovný darček pre kňazov – modlitbový program, ku ktorému sa môžu pridať veriaci.',
            'Kanál je určený veriacim, ktorí sa chcú modliť za kňazov a rodiny. Sledovaním budete vedieť o iniciatívach. '.self::CLOSING,
        ]],
        1502 => ['name' => 'Bratstvo sv. Jozefa', 'body' => [
            'Bratstvo sv. Jozefa je spoločenstvo mužov zo Žiliny. Spája ich modlitba a služba rodinám.',
            'Organizuje pešiu púť mužov ku Karmelskej Panne Márii do Domaniže. Púť sa koná každé leto.',
            'Kanál je určený mužom zo Žiliny a okolia. Sledovaním budete vedieť o pútiach bratstva. '.self::CLOSING,
        ]],
        1501 => ['name' => 'Diecézne múzeum', 'body' => [
            'Diecézne múzeum v Nitre sídli na Nitrianskom hrade. Vystavuje sakrálne umenie Nitrianskej diecézy. Je súčasťou hradného areálu.',
            'Pripravuje výstavy, napríklad prezentáciu liturgických rúch Jany Zaujecovej Gloriae Dei creatum. Zaujecová navrhla rúcha pre omšu s pápežom v Šaštíne.',
            'Kanál je určený milovníkom sakrálneho umenia. Sledovaním budete vedieť o výstavách. '.self::CLOSING,
        ]],
        1500 => ['name' => 'CVX', 'body' => [
            'CVX (Spoločenstvo kresťanského života) je laické ignaciánske hnutie. Na Slovensku spolupracuje s jezuitmi. Členovia sa stretávajú v malých skupinách.',
            'Pripravuje duchovné obnovy, napríklad Popoludnie s Ignácom na Teologickej fakulte TU v Bratislave, spolu s jezuitmi a spoločenstvom Magis.',
            'Kanál je určený všetkým, ktorým je blízka ignaciánska spiritualita. Sledovaním budete vedieť o obnovách. '.self::CLOSING,
        ]],
        1499 => ['name' => 'farnosť Banská Belá', 'body' => [
            'Farnosť Banská Belá leží pri Banskej Štiavnici. Patrí do Banskobystrickej diecézy. Je známa úctou k Panne Márii Škapuliarskej.',
            'Každé leto pripravuje Škapuliarsku púť k Panne Márii Škapuliarskej. Púť trvá dva dni.',
            'Kanál je určený pútnikom a veriacim zo Štiavnických vrchov. Sledovaním budete vedieť o púti. '.self::CLOSING,
        ]],
        1498 => ['name' => 'farnosť Bôrka', 'body' => [
            'Farnosť Bôrka leží v okrese Rožňava a patrí do Rožňavskej diecézy. Je známa úctou k Panne Márii Škapuliarskej.',
            'Každé leto pripravuje odpustovú slávnosť Panny Márie Škapuliarskej, na ktorú prichádzajú aj biskupi. Púť sa koná cez víkend.',
            'Kanál je určený pútnikom z Gemera. Sledovaním budete vedieť o odpustovej slávnosti. '.self::CLOSING,
        ]],
        1496 => ['name' => 'Ekonomická rada Konferencie biskupov Slovenska', 'body' => [
            'Ekonomická rada je poradný orgán Konferencie biskupov Slovenska pre hospodárske otázky. Sídli v Bratislave.',
            'Vyhlasuje výzvy na projekty, napríklad na podporu ukrajinských utečencov žijúcich na Slovensku.',
            'Kanál je určený farnostiam a organizáciám, ktoré žiadajú o podporu. Sledovaním budete vedieť o výzvach. '.self::CLOSING,
        ]],
        1495 => ['name' => 'Farnosť sv. Anny v Lehote', 'body' => [
            'Farnosť sv. Anny v Lehote pri Nitre patrí do Nitrianskej diecézy. Nad obcou je mariánsky pútnický areál.',
            'Každé leto pripravuje tradičnú mariánsku púť v areáli nad obcou. Púť spája svätú omšu a modlitbu.',
            'Kanál je určený pútnikom z okolia Nitry. Sledovaním budete vedieť o termíne púte. '.self::CLOSING,
        ]],
        1492 => ['name' => 'Vladimír Varga', 'body' => [
            'Vladimír Varga z Košíc informuje o pútiach do Medžugoria. Púte organizuje pre veriacich z východného Slovenska.',
            'Uverejňuje termíny pútí a informácie o programe. Púte sa konajú viackrát do roka.',
            'Kanál je určený pútnikom, ktorí chcú putovať do Medžugoria. Sledovaním budete vedieť o termínoch. '.self::CLOSING,
        ]],
        1491 => ['name' => 'Spoločnosť svätého Gorazda', 'body' => [
            'Spoločnosť svätého Gorazda pestuje odkaz sv. Gorazda, prvého domáceho biskupa Veľkej Moravy. Pôsobí v Bratislave.',
            'Každý rok na sviatok svätých Sedmopočetníkov pripravuje spomienku na sv. Gorazda v Katedrálnom chráme Povýšenia svätého Kríža v Bratislave.',
            'Kanál je určený ctiteľom sv. Gorazda a cyrilo-metodského dedičstva. Sledovaním budete vedieť o spomienkach. '.self::CLOSING,
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

            if (isset($profile['fix_website']) && $canal->website !== $profile['fix_website']) {
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
