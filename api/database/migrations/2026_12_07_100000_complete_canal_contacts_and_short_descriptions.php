<?php

use App\Models\Canal;
use App\Services\SystemLog\Recorder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Kontakty organizácií z ich oficiálnych webov (web, e-mail, adresa, súradnice
 * z OpenStreetMap) a trojodsekové popisy namiesto jednoodsekových všeobecných.
 *
 * Opravy: #399 Diecézna charita Žilina mala web a e-mail mesta Žilina, #78
 * Západný dištrikt všeobecný ecav.sk, farnosti #46 a #62 a ďalšie boli
 * v nesprávnej obci alebo „Celé Slovensko".
 *
 * Údaj sa dopĺňa len do prázdneho poľa; neprázdny sa prepíše iba vtedy, keď má
 * presne uvedenú chybnú hodnotu. Adresa a súradnice sa menia len kanálu bez
 * ulice, ktorého poloha pochádza z obce. Kanál musí sedieť ID aj názvom;
 * prevzatý, zlúčený či zmazaný sa preskočí.
 */
return new class extends Migration
{
    private const CLOSING = 'Podrobnosti nájdete vždy v detaile podujatia.';

    /**
     * ID => [názov, web, e-mail, obec, ulica, PSČ, šírka, dĺžka]
     *
     * @var array<int, array{0: string, 1: ?string, 2: ?string, 3: ?int, 4: ?string, 5: ?string, 6: ?float, 7: ?float}>
     */
    private const CONTACTS = [
        3 => ['Kongregácia milosrdných sestier Svätého kríža', 'https://zdenka.sk', 'sekretariat.zdenka@gmail.com', 3596, 'Ružová 4', '917 02', 48.366613, 17.584263],
        46 => ['Farnosť Nadlice', 'https://www.farnostnadlice.sk', 'nadlice@nrb.sk', 2291, 'Nadlice 32', '956 32', 48.620476, 18.251759],
        54 => ['Misijná spoločnosť sv. Vincenta de Paul', 'https://www.vincentini.sk', 'provincial@vincentini.sk', 242, 'Sv. Vincenta 1', '820 12', null, null],
        58 => ['Farnosť Dunajská Lužná', 'https://www.farnostdl.sk', 'farnostdl@dominikani.sk', 751, 'Jánošíkovská 754/34', '900 42', 48.080216, 17.258134],
        62 => ['Farnosť Kláštor pod Znievom', null, null, 1437, 'A. Moyzesa 73', '038 43', 48.973939, 18.799529],
        78 => ['ZD ECAV', 'https://www.zdecav.sk', 'zdecav@zdecav.sk', 4153, 'Námestie SNP 5', '960 01', 48.574327, 19.126098],
        137 => ['ANGELUS modlitbové spoločenstvo', 'https://angelus.sk', null, 4194, 'Jezuitská 143/6', '010 01', 49.223186, 18.738225],
        281 => ['Základná umelecká škola sv. Cecílie', 'https://www.zusc.sk', 'info@zusc.sk', 242, 'Chlumeckého 10', '821 03', 48.155380, 17.172094],
        282 => ['Ordinariát OS a OZ SR', 'https://www.ordinariat.sk', 'ordinariat@ordinariat.sk', 2114, 'Námestie 4. apríla 18', '900 33', 48.248158, 17.064268],
        306 => ['Spoločenstvo Christoforus', 'https://www.christoforus.sk', 'spolocenstvo@christoforus.sk', 1565, 'Hlavná 79/89', '040 01', 48.724310, 21.257018],
        351 => ['Kňazský seminár sv. Karola Boromejského', null, 'rektor@kske.sk', 1565, 'Hlavná 91', '040 01', 48.724514, 21.256368],
        358 => ['CK Mária s.r.o', 'https://ckmaria.sk', 'ck@ck-maria.sk', 242, 'Guothova 11', '831 01', 48.165093, 17.101076],
        384 => ['Kláštor kapucínov', 'https://www.kapucini.sk/bratstva/bratstvo-zilina/', 'zilina@kapucin.sk', 4194, 'Mariánske námestie 23', '010 01', 49.223084, 18.738806],
        399 => ['Diecézna charita Žilina', 'https://www.charitaza.sk', 'charitaza@charitaza.sk', 4194, 'Bratislavská 423/27', '010 01', 49.230124, 18.730133],
        458 => ['Farnosť Šaľa', 'https://www.farnostsala.sk', 'farnost.sala@abu.sk', 3379, 'Školská 348/2', '927 01', 48.151000, 17.876844],
        492 => ['Kongregácia sestier Matky Božieho Milosrdenstva', null, 'hrusov@milosrdenstvo.sk', null, null, null, null, null],
        503 => ['Kresťania v meste (KvM)', 'https://krestaniavmeste.sk', 'kancelaria@krestaniavmeste.sk', 251, 'Ambroseho 2455/6', '851 02', 48.115872, 17.114505],
        525 => ['Duchovná správa Baziliky sv. Benedikta opáta', 'https://farnosthronskybenadik.sk', 'hronsky.benadik@nrb.sk', 1122, 'Pod kláštorom 1', '966 53', 48.344916, 18.557922],
        527 => ['Diecézna charita Nitra', 'https://nr.charita.sk', 'sekretariat@charitanitra.sk', 2333, 'Samova 4', '949 01', 48.315770, 18.086681],
        553 => ['Rada pre spoločenské komunikačné prostriedky Konferencie biskupov Slovenska (KBS)', 'https://www.kbs.sk', null, 242, 'Kapitulská 11', '814 99', 48.143312, 17.105203],
        558 => ['Gréckokatolícka rómska misia', 'https://romskamisia.sk', 'romskamisia@romskamisia.sk', 4130, 'Sigord 154', '082 52', 48.955129, 21.392446],
        563 => ['Horská záchranná služba', null, null, 3977, 'Horný Smokovec 17052', '062 01', 49.142176, 20.241517],
        616 => ['Teologicko-spirituálna komisia Konferencie vyšších rehoľných predstavených na Slovensku (KVRPS)', 'https://kvrps.sk', null, null, null, null, null, null],
        656 => ['Spoločnosť dcér kresťanskej lásky', 'https://www.vincentky.sk', null, null, null, null, null, null],
        792 => ['bratislavskí kapucíni', 'https://www.kapucini.sk/bratstva/bratstvo-bratislava/', 'bratislava@kapucin.sk', 242, 'Župné námestie 10', '814 99', 48.145416, 17.105072],
        811 => ['Farnosť KVP', 'https://farnostkvp.sk', 'kvpfarnost@gmail.com', 1565, 'Trieda KVP 6', '040 23', 48.715937, 21.215802],
        871 => ['Nadácia OPORA', null, null, 242, 'Šafárikovo námestie 77/4', '811 02', 48.141489, 17.116786],
        927 => ['pro-life organizácie pod záštitou Konferencie biskupov Slovenska', 'https://pochodzazivot.sk', 'info@pochodzazivot.sk', null, null, null, null, null],
        1037 => ['Domov sv. Jána z Boha, n.o', null, null, 242, 'Hattalova 1070/6', '831 03', 48.175150, 17.136941],
        1040 => ['Poradňa ALEXIS, n.o', null, null, 242, 'Kutuzovova 253/17', '831 03', 48.165246, 17.129698],
        1061 => ['Gymnázium sv. Cyrila a Metoda', 'https://www.gcm.sk', 'gcm@gcm.sk', 2333, 'Farská 19', '949 01', 48.312583, 18.085634],
    ];

    /** @var array<int, array<string, array{0: string, 1: string}>> pole => [chybná, správna] */
    private const FIXES = [
        78 => ['website' => ['https://www.ecav.sk', 'https://www.zdecav.sk']],
        399 => [
            'website' => ['https://zilina.sk/en', 'https://www.charitaza.sk'],
            'email' => ['hovorca@zilina.sk', 'charitaza@charitaza.sk'],
        ],
    ];

    /** ID => [názov, prvý odsek, druhý odsek, komu je kanál určený] */
    private const BODIES = [
        540 => ['Farnosť sv. Alžbety',
            'Farnosť svätej Alžbety je rímskokatolícka farnosť v centre Košíc. Jej farským kostolom je Dóm svätej Alžbety.',
            'V katedrále sa konajú modlitby a kultúrne podujatia, napríklad adorácie alebo prezentácia zaniknutého Oltára Poslednej večere.',
            'Kanál je určený farníkom a návštevníkom košickej katedrály.'],
        545 => ['Rímskokatolícka farnosť Poprad',
            'Rímskokatolícka farnosť Poprad slúži veriacim v meste pod Tatrami. S farnosťou je spojená Konkatedrála Sedembolestnej Panny Márie.',
            'Pripravuje farské slávnosti a formáciu, napríklad oslavu výročia posviacky konkatedrály či seminár Život v Duchu.',
            'Kanál je určený veriacim z Popradu a okolia.'],
        562 => ['DC pre zranených potratom',
            'Duchovné cvičenia pre zranených potratom sú známe pod názvom Milujúca náruč. Pomáhajú ľuďom, ktorí nesú následky umelého potratu.',
            'Viacdňové stretnutia sa konajú v Spišskej Novej Vsi v prostredí pallotínskeho domu a spájajú modlitbu, rozhovory a duchovné sprevádzanie.',
            'Kanál je určený ženám a mužom, ktorí hľadajú uzdravenie a diskrétnu pomoc.'],
        570 => ['Sto talentov o.z',
            'Sto talentov je občianske združenie, ktoré podporuje ľudí pri objavovaní a rozvíjaní ich darov.',
            'Pripravuje stretnutia a koncerty, napríklad program Ako horieť a nevyhorieť alebo koncert speváčky Janais v Spišskom Bystrom.',
            'Kanál je určený ľuďom, ktorí hľadajú inšpiráciu, hudbu a spoločenstvo.'],
        616 => ['Teologicko-spirituálna komisia Konferencie vyšších rehoľných predstavených na Slovensku (KVRPS)',
            'Teologicko-spirituálna komisia pôsobí pri Konferencii vyšších rehoľných predstavených na Slovensku. Venuje sa formácii zasvätených osôb.',
            'Pripravuje pobyty a formačné programy, napríklad formačno-relaxačný pobyt pre rehoľné sestry v Tatrách.',
            'Kanál je určený rehoľníkom a rehoľniciam.'],
        626 => ['farnosť Smižany',
            'Farnosť Smižany je rímskokatolícka farnosť v obci pri Spišskej Novej Vsi. Pôsobí v nej aj Centrum Božieho milosrdenstva.',
            'Spája sa s podujatiami ako Celoslovenský kongres milosrdenstva alebo púte mladých.',
            'Kanál je určený farníkom, mladým a ctiteľom Božieho milosrdenstva.'],
        632 => ['Konferencia biskupov Rímskokatolíckej cirkvi na Ukrajine',
            'Konferencia biskupov Rímskokatolíckej cirkvi na Ukrajine združuje biskupov latinského obradu na Ukrajine.',
            'Vydáva výzvy k modlitbe a solidarite, napríklad k celosvetovej modlitbe a pôstu za mier.',
            'Kanál je určený všetkým, ktorí chcú duchovne a ľudsky podporiť Ukrajinu a jej susedov.'],
        637 => ['Mesto Trnava',
            'Trnava je krajské mesto s bohatou cirkevnou a kultúrnou históriou. Mestská samospráva podporuje kultúrny život v meste.',
            'Medzi podujatia patrí medzinárodný festival Trnavské organové dni s koncertmi v Bazilike svätého Mikuláša.',
            'Kanál je určený obyvateľom a návštevníkom Trnavy, ktorí sledujú kultúrny program.'],
        641 => ['farnosť Močenok',
            'Farnosť Močenok je rímskokatolícka farnosť v obci pri Šali. Farský kostol je zasvätený svätému Klimentovi.',
            'Farnosť je spojená s celonárodným festivalom kresťanského divadla Gorazdov Močenok, ktorý sa koná v lete.',
            'Kanál je určený veriacim, divadelným súborom a divákom festivalu.'],
        644 => ['Farnosť sv. Gorazda',
            'Farnosť svätého Gorazda je rímskokatolícke spoločenstvo, ktoré pripravuje bohoslužby a podujatia pre veriacich.',
            'V kanáli sa objavili napríklad spomienková slávnosť na kňaza Júliusa Gašparíka a svätá omša za pokoj a požehnanie v rodinách.',
            'Kanál je určený farníkom a rodinám.'],
        651 => ['Škapuliarsky spolok Panny Márie Karmelskej',
            'Škapuliarsky spolok Panny Márie Karmelskej šíri úctu k Panne Márii Karmelskej. Pôsobí v obci Bôrka v okrese Rožňava.',
            'Každé leto pozýva na odpustovú slávnosť v Bôrke s modlitbou a svätými omšami.',
            'Kanál je určený členom spolku, nositeľom škapuliara a pútnikom.'],
        653 => ['Úrad pre apoštolát ochrany života v Košiciach',
            'Úrad pre apoštolát ochrany života v Košiciach sa venuje pastorácii zameranej na ochranu života a pomoc rodinám.',
            'Spolu so združením Plodar pripravuje víkendové duchovné obnovy pre bezdetné páry, napríklad v Bardejovských Kúpeľoch.',
            'Kanál je určený manželom, ktorí túžia po dieťati, a priaznivcom ochrany života.'],
        656 => ['Spoločnosť dcér kresťanskej lásky',
            'Spoločnosť dcér kresťanskej lásky svätého Vincenta de Paul, známa ako vincentky, slúži chudobným a ľuďom v núdzi. Patrí do vincentskej rodiny.',
            'Pre dievčatá a mladé ženy pripravuje viacdňové programy, napríklad Pútničky nádeje alebo Odvážna žena.',
            'Kanál je určený mladým ženám, ktoré chcú spoznať službu a duchovný život sestier.'],
        659 => ['Združenie kresťanských seniorov a občanov Slovenska – Prešov',
            'Združenie kresťanských seniorov a občanov Slovenska spája seniorov, ktorí chcú spoločne prežívať vieru a aktívny život. Tento kanál patrí prešovskej organizácii.',
            'Seniori sa zúčastňujú na celoslovenskej púti na Mariánskej hore v Levoči.',
            'Kanál je určený seniorom z Prešova a okolia.'],
        662 => ['Apoštolát modlitby',
            'Apoštolát modlitby, dnes Celosvetová sieť modlitby s pápežom, spája veriacich v modlitbe na úmysly Svätého Otca.',
            'Zverejňuje mesačné úmysly pápeža a pozýva na spoločné modlitby, napríklad na novénu za mier vo svete.',
            'Kanál je určený všetkým, ktorí sa chcú pravidelne modliť spolu s Cirkvou.'],
        664 => ['Farnosť Vysoké Tatry',
            'Farnosť Vysoké Tatry slúži veriacim a návštevníkom tatranských osád.',
            'Spája sa s horskou duchovnosťou, napríklad so svätými omšami na horách či posvätením lán horských vodcov v Starom Smokovci.',
            'Kanál je určený obyvateľom Tatier, turistom a priateľom hôr.'],
        667 => ['Farnosť Saletínov',
            'Farnosť Saletínov je bratislavské farské spoločenstvo spravované misionármi Panny Márie z La Salette.',
            'Pripravuje duchovné iniciatívy, napríklad letnú turistickú výzvu po vrchoch s biblickými menami v spolupráci s Katolíckym biblickým dielom.',
            'Kanál je určený farníkom, rodinám a priateľom saletínov.'],
        671 => ['Centrum Božieho milosrdenstva (CBM)',
            'Centrum Božieho milosrdenstva je duchovný dom spojený s farnosťou Smižany pri Spišskej Novej Vsi.',
            'Ponúka duchovné cvičenia, napríklad pre kňazov a zasvätené osoby, často pod vedením biskupov.',
            'Kanál je určený tým, ktorí hľadajú duchovnú obnovu a čas na modlitbu.'],
        685 => ['Farnosť Fričovce',
            'Farnosť Fričovce je rímskokatolícka farnosť v Prešovskom okrese. Patrí do Košickej arcidiecézy.',
            'Pripravuje duchovné a kultúrne podujatia, napríklad ľudové misie alebo koncert k výročiu svätorečenia košických mučeníkov.',
            'Kanál je určený veriacim z Fričoviec a okolitých obcí.'],
        710 => ['Komisia KBS pre Jubilejný rok 2025',
            'Komisia Konferencie biskupov Slovenska pre Jubilejný rok 2025 pomáhala pripraviť slávenie jubilea na Slovensku.',
            'V kanáli sú výzvy a pozvánky z jubilejného roka, napríklad na Noc kostolov alebo na spoločné modlitby.',
            'Kanál je určený farnostiam, spoločenstvám a jednotlivcom, ktorí sa chceli do jubilea zapojiť.'],
        719 => ['Strapar',
            'Strapar je organizátor podujatí z prostredia kresťanského spoločenstva Godzone.',
            'Pripravuje programy pre deti, mužov a služobníkov, napríklad Godzone KIDS tour, mužskú konferenciu alebo vzdelávanie Deti v srdci.',
            'Kanál je určený rodinám, mužom a ľuďom, ktorí slúžia deťom.'],
        721 => ['Komisia Konferencie biskupov Slovenska pre prípravu a realizáciu Jubilejného roka 2025',
            'Komisia Konferencie biskupov Slovenska pre prípravu a realizáciu Jubilejného roka 2025 koordinovala jubilejné iniciatívy Cirkvi na Slovensku.',
            'Spolu s biskupmi pozývala farnosti, rehoľné aj laické spoločenstvá napríklad na Noc kostolov.',
            'Kanál je určený veriacim, ktorých zaujímajú jubilejné podujatia.'],
        725 => ['Farnosť Bratislava-Vajnory',
            'Farnosť Bratislava-Vajnory je rímskokatolícka farnosť v bratislavskej mestskej časti Vajnory.',
            'Spolu s miestnym úradom pripravila slávnosť k 35. výročiu návštevy svätého Jána Pavla II. vo Vajnoroch.',
            'Kanál je určený farníkom a obyvateľom Vajnor.'],
        741 => ['Rada európskych biskupských konferencií',
            'Rada európskych biskupských konferencií (CCEE) združuje biskupské konferencie z celej Európy a podporuje ich spoluprácu.',
            'Pripravuje spoločné iniciatívy, napríklad eucharistickú reťaz za mier alebo webinár o pastorácii Rómov.',
            'Kanál je určený veriacim a pastoračným pracovníkom so záujmom o dianie v Cirkvi v Európe.'],
        749 => ['Farnosť Brezno',
            'Farnosť Brezno je rímskokatolícka farnosť v Brezne. Patrí do Banskobystrickej diecézy.',
            'Pripravuje duchovné cvičenia, napríklad víkendy pre mamy s malými deťmi.',
            'Kanál je určený farníkom a rodinám z Horehronia.'],
        754 => ['Oblastná organizácia cestovného ruchu (OOCR) Trnava Tourism',
            'Oblastná organizácia cestovného ruchu Trnava Tourism propaguje Trnavu a jej okolie.',
            'Pripravuje a propaguje programy ako tematické prehliadky trnavských kostolov či Trnavské organové dni.',
            'Kanál je určený návštevníkom a obyvateľom, ktorí chcú spoznávať región.'],
        759 => ['Katolícka zdravotnícka únia',
            'Katolícka zdravotnícka únia spája zdravotníkov, ktorí chcú vykonávať svoje povolanie v duchu kresťanských hodnôt.',
            'Organizuje konferenciu Kresťan v dnešnej nemocnici v Martine v spolupráci s duchovnou službou v zdravotníctve.',
            'Kanál je určený lekárom, sestrám, študentom a nemocničným kaplánom.'],
        777 => ['Slovenský výbor SDM',
            'Slovenský výbor Svetového dňa modlitieb pripravuje na Slovensku toto ekumenické stretnutie, ktoré vychádza z iniciatívy kresťanských žien.',
            'Svetový deň modlitieb sa slávi začiatkom marca, napríklad v Bratislave a v Košiciach, a každý rok ho pripravujú ženy z inej krajiny.',
            'Kanál je určený všetkým, ktorí sa chcú pridať k ekumenickej modlitbe.'],
        778 => ['Komisia pre prípravu a slávenie Jubilejného roka 2025 na Slovensku',
            'Komisia pre prípravu a slávenie Jubilejného roka 2025 na Slovensku koordinovala jubilejné slávenia. Na jej čele stál biskup František Trstenský.',
            'Pozývala napríklad na iniciatívu 24 hodín pre Pána s adoráciou a možnosťou spovede.',
            'Kanál je určený farnostiam a veriacim, ktorí sledujú jubilejné iniciatívy.'],
        792 => ['bratislavskí kapucíni',
            'Bratislavskí kapucíni sú rehoľné bratstvo, ktoré pôsobí pri kláštore na Župnom námestí.',
            'Pripravujú modlitbové a vzdelávacie programy, napríklad prípravu k Roku rodiny alebo prednášku o diele Majstra Eckharta.',
            'Kanál je určený ľuďom, ktorí hľadajú františkánsku spiritualitu a ticho.'],
        804 => ['Slovenský historický ústav v Ríme',
            'Slovenský historický ústav v Ríme skúma slovenské dejiny vo vatikánskych a talianskych archívoch a ich vzťah k európskej histórii.',
            'Pripravuje vedecké kolokviá a prezentácie kníh, napríklad kolokvium Imago Forgacsiana v Trnave alebo predstavenie dejín slovenského historického výskumu v Ríme.',
            'Kanál je určený historikom, študentom a záujemcom o cirkevné a národné dejiny.'],
        810 => ['Akadémia Karola Wojtylu',
            'Akadémia Karola Wojtylu je vzdelávacia iniciatíva, ktorá čerpá z myslenia a učenia svätého Jána Pavla II.',
            'Pripravuje formačné stretnutia pre manželov, kňazov a zasvätených v Dome Xaver v Badíne i vedomostné programy o pápežovi.',
            'Kanál je určený manželom, kňazom a všetkým, ktorí sa chcú vzdelávať.'],
        811 => ['Farnosť KVP',
            'Farnosť Božieho milosrdenstva je rímskokatolícka farnosť na košickom sídlisku KVP. Jej kostol je zároveň diecéznou svätyňou Božieho milosrdenstva.',
            'Pravidelne v piatok večer pozýva na svätú omšu s chválami v kostole na Triede KVP.',
            'Kanál je určený obyvateľom sídliska, mladým a ľuďom, ktorí majú radi chvály.'],
        836 => ['Občianske združenie Via regum',
            'Via regum je občianske združenie, ktoré oživuje kresťanské tradície vo verejnom priestore.',
            'V Bratislave organizuje na sviatok Zjavenia Pána Trojkráľový sprievod, ktorý môže podporiť aj verejnosť.',
            'Kanál je určený rodinám s deťmi a všetkým priateľom tradícií.'],
        855 => ['občianske združenie Dobrý svet',
            'Dobrý svet je občianske združenie spojené s gospelovou hudbou a prácou s mladými talentmi.',
            'Organizuje národnú spevácku súťaž Gospeltalent Junior pre deti od 4 do 14 rokov.',
            'Kanál je určený deťom, rodičom a vedúcim speváckych zborov.'],
        871 => ['Nadácia OPORA',
            'Nadácia OPORA je slovenská nadácia so sídlom v Bratislave.',
            'Organizovala verejné diskusie Moja sloboda, napríklad v Košiciach a v Banskej Bystrici.',
            'Kanál je určený ľuďom, ktorí chcú diskutovať o slobode, viere a spoločnosti.'],
        899 => ['Spolok sv. Vincenta de Paul na Slovensku',
            'Spolok svätého Vincenta de Paul na Slovensku je laické združenie, ktoré v duchu svätého Vincenta pomáha ľuďom v núdzi.',
            'Pripravuje aj stretnutia mladých, napríklad víkend v Bratislave s vincentínmi z Filipín.',
            'Kanál je určený dobrovoľníkom a mladým, ktorí chcú spojiť vieru so službou.'],
        901 => ['Matica slovenská',
            'Matica slovenská je národná kultúrna ustanovizeň, ktorá sa stará o slovenskú kultúru, vzdelanosť a národné povedomie.',
            'Pripomína významné osobnosti, napríklad výročia narodenia Andreja Hlinku a Františka J. Fugu.',
            'Kanál je určený záujemcom o slovenské dejiny a kultúru.'],
        927 => ['pro-life organizácie pod záštitou Konferencie biskupov Slovenska',
            'Pod týmto názvom spolupracujú organizácie na ochranu života, ktoré majú záštitu Konferencie biskupov Slovenska.',
            'Spoločne pripravujú Národný pochod za život so svätou omšou a sprievodným programom.',
            'Kanál je určený rodinám, mladým a všetkým, ktorí chcú podporiť ochranu života.'],
        952 => ['Subkomisia pre bioetiku Teologickej komisie Konferencie biskupov Slovenska',
            'Subkomisia pre bioetiku pôsobí pri Teologickej komisii Konferencie biskupov Slovenska. Venuje sa etickým otázkam medicíny a ochrany života.',
            'Pripravuje vzdelávanie, napríklad kurz pre spovedníkov v Badíne.',
            'Kanál je určený kňazom, zdravotníkom a záujemcom o bioetiku.'],
        975 => ['Hnutie Laudato si’',
            'Hnutie Laudato si’ spája katolíkov, ktorí sa v duchu rovnomennej encykliky pápeža Františka starajú o stvorenie.',
            'Pozýva na vzdelávanie animátorov a na Týždeň Laudato si’ spoločný pre Česko a Slovensko.',
            'Kanál je určený farnostiam, spoločenstvám a ľuďom, ktorým záleží na životnom prostredí.'],
        1037 => ['Domov sv. Jána z Boha, n.o',
            'Domov svätého Jána z Boha je nezisková organizácia v Bratislave, ktorú založili milosrdní bratia. Pomáha ľuďom bez domova a v sociálnej núdzi.',
            'Verejnosť pozýva na dobročinné podujatia, napríklad benefičný koncert alebo kampaň GivingTuesday.',
            'Kanál je určený darcom, dobrovoľníkom a priateľom domova.'],
        1040 => ['Poradňa ALEXIS, n.o',
            'Poradňa ALEXIS je nezisková organizácia, ktorá pomáha tehotným ženám a ich blízkym.',
            'Pripravuje spomienkové a podporné podujatia, napríklad futbal Za tých, ktorí chýbajú v Banskej Bystrici.',
            'Kanál je určený ženám, rodinám a podporovateľom poradne.'],
        1043 => ['Aglow Slovakia',
            'Aglow Slovakia je slovenská vetva medzinárodného kresťanského združenia Aglow, ktoré spája najmä ženy v modlitbe a spoločenstve.',
            'Organizuje stretnutia so Slovom, modlitbami a chválami v Bratislave a národné konferencie.',
            'Kanál je určený ľuďom z rôznych cirkví, ktorí hľadajú modlitbové spoločenstvo.'],
        1052 => ['Vydavateľstvo Tranoscius, a. s.',
            'Tranoscius je vydavateľstvo s dlhou tradíciou so sídlom v Liptovskom Mikuláši, úzko späté s evanjelickou cirkvou.',
            'Pripravuje prezentácie kníh a prednášky, napríklad uvedenie noviniek v Prešove a Bratislave.',
            'Kanál je určený čitateľom a priaznivcom kresťanskej literatúry.'],
        1061 => ['Gymnázium sv. Cyrila a Metoda',
            'Gymnázium svätého Cyrila a Metoda je cirkevná stredná škola na Farskej ulici v Nitre.',
            'Študenti pripravujú aj duchovné podujatia pre verejnosť, napríklad modlitbový večer Nightfever vo farskom kostole.',
            'Kanál je určený študentom, rodičom, absolventom a priateľom školy.'],
        1096 => ['Projekt Radostná',
            'Projekt Radostná podporuje ženy na ceste k materstvu, najmä tie, ktoré sa dlho nemôžu stať matkami.',
            'Ponúka kurz Radosť, úvodné informačné stretnutia a online podporné skupiny.',
            'Kanál je určený ženám, ktoré hľadajú porozumenie a podporu.'],
    ];

    public function up(): void
    {
        $ids = array_unique(array_merge(array_keys(self::CONTACTS), array_keys(array_filter(self::BODIES))));

        foreach ($ids as $id) {
            $canal = Canal::query()->find($id);

            if (! $canal || $canal->claimed_at !== null || $canal->merged_into_id !== null) {
                continue;
            }

            $changes = $this->contacts($canal);

            $body = self::BODIES[$id] ?? null;

            if ($body !== null && $canal->name === $body[0] && substr_count((string) $canal->body, '<p') < 3) {
                [, $first, $second, $audience] = $body;
                $paragraphs = [$first, $second, $audience.' '.self::CLOSING];
                $changes['body'] = implode('', array_map(fn (string $p) => '<p>'.e($p).'</p>', $paragraphs));
            }

            if ($changes === []) {
                continue;
            }

            $canal->forceFill($changes)->save();
            Recorder::info('canals', 'profile_described', "Kanál {$canal->name}: doplnené ".implode(', ', array_keys($changes)),
                status: 'ok', subject: $canal, context: ['fields' => array_keys($changes)]);
        }
    }

    public function down(): void
    {
        // Doplnené údaje sa nevracajú.
    }

    /** @return array<string, mixed> */
    private function contacts(Canal $canal): array
    {
        $contact = self::CONTACTS[$canal->id] ?? null;

        if ($contact === null || $canal->name !== $contact[0]) {
            return [];
        }

        [, $website, $email, $municipality, $street, $postcode, $latitude, $longitude] = $contact;
        $changes = [];

        foreach (['website' => $website, 'email' => $email] as $field => $value) {
            if ($value !== null && trim((string) $canal->$field) === '') {
                $changes[$field] = $value;
            }
        }

        foreach (self::FIXES[$canal->id] ?? [] as $field => [$wrong, $right]) {
            if ($wrong === $canal->$field) {
                $changes[$field] = $right;
            }
        }

        if ($street !== null && trim((string) $canal->street) === ''
            && in_array($canal->coordinates_source, ['municipality', 'nationwide', 'ai', null], true)
            && DB::table('municipalities')->where('id', $municipality)->exists()) {
            $changes += ['municipality_id' => $municipality, 'street' => $street, 'postcode' => $postcode];

            if ($latitude !== null) {
                $changes += ['latitude' => $latitude, 'longitude' => $longitude, 'coordinates_source' => 'address'];
            }
        }

        return $changes;
    }
};
