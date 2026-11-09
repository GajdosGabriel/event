<?php

use App\Models\Canal;
use App\Services\Canals\CanalEmails;
use App\Services\SystemLog\Recorder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Tretia dávka profilov importovaných kanálov (november 2026): organizácie
 * s jednou akciou, ktoré mali len zástupný popis „… — organizátor podujatí.
 * Podujatia sú preberané z verejných zdrojov." Kanály pomenované po osobách,
 * nejasné a duplicitné sa vynechali.
 *
 * Každý kanál môže dostať:
 *  - popis v troch odsekoch (kto je, čo pripravuje, komu je kanál určený),
 *    len ak má stále zástupný text,
 *  - web a telefón do prázdneho poľa,
 *  - e-mail cez CanalEmails::discover (adresa doslova na stránke `found_on`),
 *  - adresu sídla z kontaktnej stránky organizácie so súradnicami
 *    z Nominatim/OpenStreetMap (overené: ulica aj orientačné číslo sedia).
 *    Zapíše sa len kanálu s polohou na stred obce alebo Slovenska a ulica
 *    len do prázdneho poľa; obec len keď kanál mal „Celé Slovensko".
 *
 * Kanál musí sedieť ID aj názvom; prevzatý, zlúčený či zmazaný sa preskočí.
 */
return new class extends Migration
{
    private const PLACEHOLDER = 'Podujatia sú preberané z verejných zdrojov';

    /** @var array<int, array<string, mixed>> */
    private const PROFILES = [
        64 => [
            'name' => 'Považská Bystrica – Rozkvet',
            'body' => [
                0 => 'Farnosť Považská Bystrica – Rozkvet je rímskokatolícka farnosť na sídlisku Rozkvet v Považskej Bystrici. Jej farským kostolom je Kostol Lasaletskej Panny Márie. Okrem bohoslužieb ponúka aj formačné stretnutia pre veriacich.',
                1 => 'Pripravuje napríklad Biblickú formáciu, cyklus prednášok o Svätom písme. Stretnutia sa konajú v priestoroch farského kostola. Sú otvorené pre všetkých, ktorí chcú lepšie poznať Bibliu.',
                2 => 'Kanál je určený veriacim z Považskej Bystrice a okolia. Sledovaním budete vedieť o nových prednáškach a podujatiach farnosti. Miesto a čas nájdete vždy v detaile podujatia.',
            ],
        ],
        101 => [
            'name' => 'Exallievi',
            'body' => [
                0 => 'Exallievi sú združenie bývalých chovancov a priateľov saleziánov dona Bosca. Patria do veľkej saleziánskej rodiny. Udržiavajú vzťahy a ducha, ktorý získali v saleziánskych strediskách.',
                1 => 'Pozývajú celú saleziánsku rodinu na Púť vďačnosti do Šaštína. Púť je príležitosťou stretnúť sa, poďakovať a pripomenúť si odkaz dona Bosca. Koná sa v Národnej svätyni Sedembolestnej Panny Márie.',
                2 => 'Kanál je určený bývalým odchovancom saleziánov a ich priateľom. Sledovaním budete vedieť o ďalších stretnutiach a pútiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        105 => [
            'name' => 'Agentúra VŠEHOMÍR',
            'body' => [
                0 => 'Agentúra VŠEHOMÍR je produkčná agentúra, ktorá pripravuje televízne relácie s duchovnou tematikou. Spolupracuje s verejnoprávnou televíziou. Jej tvorbu možno vidieť na Dvojke.',
                1 => 'Pripravila napríklad reláciu Veni Sancte na začiatok školského roka. Relácia nadväzuje na tradíciu vzývania Ducha Svätého pred novým školským rokom. Vysiela sa koncom augusta.',
                2 => 'Kanál je určený divákom, ktorí hľadajú duchovné relácie v televízii. Sledovaním budete vedieť o termínoch vysielania. Čas a stanicu nájdete vždy v detaile podujatia.',
            ],
        ],
        180 => [
            'name' => 'Meli Beli',
            'body' => [
                0 => 'Meli Beli je organizátor duchovných a kultúrnych podujatí z Ivanky pri Dunaji. Pripravuje podujatia pre jednotlivcov aj rodiny. Pôsobí v okolí Bratislavy.',
                1 => 'Organizuje Festival Zjavenie, ktorý sa koná za kaštieľom v Ivanke pri Dunaji. Festival trvá celé popoludnie a večer a ponúka priestor na pokoj a duchovný rast. Je otvorený pre jednotlivcov aj rodiny.',
                2 => 'Kanál je určený obyvateľom Ivanky pri Dunaji, Bratislavy a okolia. Sledovaním budete vedieť o ďalšom ročníku festivalu. Program nájdete vždy v detaile podujatia.',
            ],
        ],
        205 => [
            'name' => 'Obec Sečovská Polianka',
            'body' => [
                0 => 'Obec Sečovská Polianka leží v okrese Vranov nad Topľou. Popri samosprávnej činnosti podporuje aj komunitné a dobročinné podujatia. Zapája do nich miestnu školu a rodiny.',
                1 => 'Organizuje beh a rodinné olympijské hry na pomoc škole Chipungu v Zambii. Podujatie je súčasťou programu Nasýťme školu, ktorý zabezpečuje jedlo pre deti. Koná sa v areáli základnej školy.',
                2 => 'Kanál je určený obyvateľom obce a okolia. Sledovaním budete vedieť o ďalších obecných a benefičných podujatiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        263 => [
            'name' => 'Bezhraničná',
            'body' => [
                0 => 'Bezhraničná je projekt pre mladých, ktorý pripravuje púte a duchovné akcie. Spolupracuje s Rádiom Mária. Spája putovanie, spoločenstvo a oddych.',
                1 => 'Organizuje púť mladých do Medžugoria spojenú s kúpaním v mori. Púť trvá týždeň a jej program tvoria modlitba, sväté omše a spoločný čas. Zúčastňujú sa na nej mladí z celého Slovenska.',
                2 => 'Kanál je určený mladým, ktorí hľadajú púť v spoločenstve rovesníkov. Sledovaním budete vedieť o nových termínoch. Cenu a prihlasovanie nájdete vždy v detaile podujatia.',
            ],
        ],
        285 => [
            'name' => 'OZ Trlica',
            'body' => [
                0 => 'OZ Trlica je občianske združenie z obce Poruba v okrese Prievidza. Venuje sa kultúrnemu a spoločenskému životu obce. Spolupracuje s miestnou farnosťou.',
                1 => 'Pripravuje Noc kostolov v Porube vo farskom kostole sv. Mikuláša. Program ponúka večernú prehliadku kostola, hudbu a modlitbu. Noc kostolov sa koná na Slovensku aj v ďalších krajinách Európy.',
                2 => 'Kanál je určený obyvateľom Poruby a okolia. Sledovaním budete vedieť o ďalších ročníkoch a podujatiach. Program nájdete vždy v detaile podujatia.',
            ],
        ],
        292 => [
            'name' => 'OZ Bonum et gaudium',
            'body' => [
                0 => 'OZ Bonum et gaudium je občianske združenie, ktoré sa podieľa na kultúrnych a duchovných podujatiach. Jeho názov v latinčine znamená „dobro a radosť“. Pôsobí najmä v Nových Zámkoch.',
                1 => 'Koordinuje Noc kostolov v Nových Zámkoch, počas ktorej sa večer otvárajú kostoly aj synagóga. Návštevníci môžu spoznať sakrálne pamiatky mesta s programom. Podujatie sa koná začiatkom júna.',
                2 => 'Kanál je určený obyvateľom Nových Zámkov a návštevníkom mesta. Sledovaním budete vedieť o ďalšom ročníku. Program nájdete vždy v detaile podujatia.',
            ],
        ],
        337 => [
            'name' => 'OZ Juhaska reguľa',
            'body' => [
                0 => 'OZ Juhaska reguľa je občianske združenie, ktoré pestuje pastierske a ovčiarske tradície. Pripomína kultúrne dedičstvo salašníctva na Slovensku. Spája tradície s duchovným rozmerom.',
                1 => 'Pripravilo stretnutie ovčiarov, pastierov a priaznivcov pastierskych tradícií v Spišskej Kapitule. Program spájal bohoslužbu s ukážkami tradičnej kultúry. Podujatie je určené širokej verejnosti.',
                2 => 'Kanál je určený priaznivcom ľudových tradícií a pastierstva. Sledovaním budete vedieť o ďalších stretnutiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        398 => [
            'name' => 'ACM Vinica',
            'body' => [
                0 => 'ACM Vinica je spoločenstvo mladých v Bratislave. Pripravuje duchovné programy pre mladých ľudí. Dôraz kladie na spoločenstvo a liturgiu.',
                1 => 'Pozýva mladých prežiť Veľkonočné trojdnie v spoločenstve. Program spája liturgiu Veľkého týždňa so spoločným časom a zamyslením. Je pomocou prežiť najväčšie sviatky hlbšie.',
                2 => 'Kanál je určený mladým z Bratislavy. Sledovaním budete vedieť o ďalších programoch. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        423 => [
            'name' => 'Spoločenstvo Kanaán',
            'emails' => [
                0 => [
                    'email' => 'zivotvduchu@gmail.com',
                    'found_on' => 'https://www.vyveska.sk/sieversov-seminar-zivot-v-duchu.html',
                ],
            ],
            'body' => [
                0 => 'Spoločenstvo Kanaán je kresťanské spoločenstvo z Trnavy. Venuje sa modlitbe chvál a životu v Duchu Svätom. Stretnutia pripravuje v univerzitnom pastoračnom centre.',
                1 => 'Organizuje Sieversov seminár Život v Duchu, ktorý trvá niekoľko týždňov. Seminár vedie účastníkov k hlbšiemu poznaniu Ducha Svätého. Súčasťou sú chvály a modlitba.',
                2 => 'Kanál je určený veriacim z Trnavy a okolia. Sledovaním budete vedieť o nových seminároch. Miesto a čas nájdete vždy v detaile podujatia.',
            ],
        ],
        455 => [
            'name' => 'AFCM UK',
            'body' => [
                0 => 'AFCM UK je britská vetva katolíckej služby Anointing Fire Catholic Ministry. Jej spoluzakladateľom je o. Soji Olikkal. Venuje sa evanjelizácii a duchovnej obnove.',
                1 => 'Na Slovensku pripravuje duchovné cvičenia pre kňazov s o. Sojim, napríklad v Poprade. Cvičenia trvajú niekoľko dní. Pomáhajú kňazom prehĺbiť duchovný život.',
                2 => 'Kanál je určený kňazom a veriacim, ktorí sledujú službu AFCM. Sledovaním budete vedieť o nových termínoch. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        509 => [
            'name' => 'Obec Veľké Držkovce',
            'body' => [
                0 => 'Obec Veľké Držkovce leží v okrese Bánovce nad Bebravou. Je rodiskom arcibiskupa Karola Kmeťka, prvého slovenského arcibiskupa. Obec si jeho odkaz pripomína.',
                1 => 'K 150. výročiu narodenia Karola Kmeťka sa v Nitre a v Dolných Držkovciach konali pripomienkové podujatia. Približujú jeho život a prínos pre Cirkev aj národ. Spájajú bohoslužbu a kultúrny program.',
                2 => 'Kanál je určený obyvateľom obce a všetkým, ktorých zaujíma odkaz Karola Kmeťka. Sledovaním budete vedieť o ďalších podujatiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        550 => [
            'name' => 'Skala rodín',
            'emails' => [
                0 => [
                    'email' => 'skalarodin1@gmail.com',
                    'found_on' => 'https://www.vyveska.sk/28124/duchovna-obnova-pre-manzelov.html',
                ],
            ],
            'body' => [
                0 => 'Skala rodín je spoločenstvo, ktoré sa venuje manželom a rodinám. Pripravuje duchovné programy pre páry. Pôsobí na východnom Slovensku.',
                1 => 'Organizuje duchovnú obnovu pre manželov v Centre pre rodinu Sigord pri Prešove. Víkend pomáha manželom pozvať Boha do vzťahu a obnoviť manželstvo. Program spája prednášky, modlitbu a čas vo dvojici.',
                2 => 'Kanál je určený manželským párom. Sledovaním budete vedieť o nových termínoch obnov. Prihlasovanie nájdete vždy v detaile podujatia.',
            ],
        ],
        640 => [
            'name' => 'CK Hvězda mořská',
            'website' => 'https://hvezdamorska.cz',
            'emails' => [
                0 => [
                    'email' => 'info@hvezdamorska.cz',
                    'found_on' => 'https://hvezdamorska.cz/kontakty/',
                ],
            ],
            'body' => [
                0 => 'CK Hvězda mořská je česká cestovná kancelária, ktorá sa venuje pútnickým zájazdom. Púte pripravuje s duchovným sprievodom. Ponuka je dostupná aj pre pútnikov zo Slovenska.',
                1 => 'Organizuje napríklad púť do Medžugoria k slávnosti Nanebovzatia Panny Márie s kňazom Bogdanem Stepienom. Zájazd trvá približne týždeň. Program tvoria sväté omše, modlitba a návšteva pútnických miest.',
                2 => 'Kanál je určený pútnikom, ktorí hľadajú organizovanú púť. Sledovaním budete vedieť o nových termínoch. Cenu a program nájdete vždy v detaile podujatia.',
            ],
        ],
        650 => [
            'name' => 'Centrum ochrany dieťaťa',
            'body' => [
                0 => 'Centrum ochrany dieťaťa je poľské pracovisko pri Univerzite Ignatianum v Krakove. Venuje sa prevencii sexuálneho násilia voči deťom a mládeži. Vzdeláva odborníkov aj pracovníkov Cirkvi.',
                1 => 'Otvára postgraduálne štúdium Prevencia sexuálneho násilia voči deťom a mládeži. Štúdium je určené aj záujemcom zo Slovenska. Pripravuje ľudí na ochranu detí v inštitúciách.',
                2 => 'Kanál je určený pedagógom, vychovávateľom a pracovníkom s deťmi. Sledovaním budete vedieť o ďalších možnostiach štúdia. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        674 => [
            'name' => 'Ukrajinská gréckokatolícka cirkev',
            'body' => [
                0 => 'Ukrajinská gréckokatolícka cirkev je najväčšia východná katolícka cirkev. Jej veriaci žijú na Ukrajine aj v mnohých krajinách sveta. V jednote s pápežom slávi byzantskú liturgiu.',
                1 => 'V jubilejnom roku pripravila púť k hrobu svätého apoštola Petra v Ríme. Púť spojila veriacich z Ukrajiny aj z diaspóry. Bola výrazom jednoty s pápežom.',
                2 => 'Kanál je určený gréckokatolíckym veriacim a všetkým, ktorí sa modlia za Ukrajinu. Sledovaním budete vedieť o ďalších podujatiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        687 => [
            'name' => 'Stretnutiami pri tanci',
            'body' => [
                0 => 'Stretnutiami pri tanci je bratislavský projekt pre slobodných. Spája tanec so zoznamovaním. Večery sú určené pre jednotlivé vekové kategórie.',
                1 => 'Organizuje tanečný slow-quick dating na Zochovej ulici v Bratislave. Účastníci sa pri tanci postupne zoznámia s ďalšími. Na večer je potrebná registrácia.',
                2 => 'Kanál je určený slobodným, ktorí chcú spoznať nových ľudí. Sledovaním budete vedieť o ďalších večeroch. Registráciu nájdete vždy v detaile podujatia.',
            ],
        ],
        691 => [
            'name' => 'Škola Božej lásky',
            'body' => [
                0 => 'Škola Božej lásky je letný duchovný program v Nitre. Je zameraný na duchovný rast a obnovu. Trvá niekoľko dní.',
                1 => 'Koná sa na Gymnáziu sv. Cyrila a Metoda na Farskej ulici v Nitre. Program spája prednášky, modlitbu a spoločenstvo. Je určený tým, ktorí hľadajú hlbší vzťah s Bohom.',
                2 => 'Kanál je určený veriacim z Nitry a celého Slovenska. Sledovaním budete vedieť o ďalšom ročníku. Prihlasovanie nájdete vždy v detaile podujatia.',
            ],
        ],
        700 => [
            'name' => 'Renovabis',
            'body' => [
                0 => 'Renovabis je solidárna akcia nemeckých katolíkov s ľuďmi v strednej a východnej Európe. Podporuje pastoračné, sociálne a vzdelávacie projekty. Na Slovensku spolupracuje s Konferenciou biskupov Slovenska.',
                1 => 'Spolu s KBS podporuje rozvoj centier pastorácie Rómov a rómskych misií. Program posilňuje kapacity pre pastoračnú prácu s Rómami. Pomáha zabezpečiť udržateľnosť misií.',
                2 => 'Kanál je určený farnostiam, misiám a všetkým, ktorých zaujíma pastorácia Rómov. Sledovaním budete vedieť o nových výzvach a projektoch. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        701 => [
            'name' => 'OZ Slobodní',
            'emails' => [
                0 => [
                    'email' => 'slobodni28plus@gmail.com',
                    'found_on' => 'https://www.vyveska.sk/chorvatske-leto-vodice-11-20-7-2025.html',
                ],
            ],
            'body' => [
                0 => 'OZ Slobodní je občianske združenie pre slobodných kresťanov. Pripravuje spoločné dovolenky a pobyty. Spája oddych so spoločenstvom a modlitbou.',
                1 => 'Organizuje Chorvátske leto vo Vodiciach neďaleko Zadaru. Pobyt trvá desať dní a spája oddych pri mori s duchovným programom. Je príležitosťou spoznať podobne zmýšľajúcich ľudí.',
                2 => 'Kanál je určený slobodným, ktorí hľadajú kresťanské spoločenstvo. Sledovaním budete vedieť o nových pobytoch. Cenu a prihlasovanie nájdete vždy v detaile podujatia.',
            ],
        ],
        737 => [
            'name' => 'Obec Lacková',
            'body' => [
                0 => 'Obec Lacková leží v okrese Stará Ľubovňa. Pripomína si obete komunistického režimu a Akcie K. Spolupracuje pri tom s Ústavom pamäti národa.',
                1 => 'Pri 75. výročí Akcie K pripravila s ÚPN spomienku na martýrov v Deň nespravodlivo stíhaných. Program pripomenul likvidáciu mužských reholí v roku 1950. Konal sa v Lackovej aj v Bratislave.',
                2 => 'Kanál je určený obyvateľom obce a všetkým, ktorým záleží na pamäti národa. Sledovaním budete vedieť o ďalších pripomienkach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        744 => [
            'name' => 'Plamienok',
            'website' => 'https://plamienok.sk',
            'body' => [
                0 => 'Plamienok je nezisková organizácia, ktorá poskytuje detskú paliatívnu starostlivosť. Sprevádza nevyliečiteľne choré deti a ich rodiny. Pomáha aj pozostalým.',
                1 => 'Pripravuje prednášky o detskej domácej paliatívnej starostlivosti, napríklad v Univerzitnom pastoračnom centre v Bratislave. Približuje, ako vyzerá starostlivosť o dieťa doma. Prednášky sú otvorené verejnosti.',
                2 => 'Kanál je určený študentom, zdravotníkom a všetkým, ktorých zaujíma paliatívna starostlivosť. Sledovaním budete vedieť o ďalších prednáškach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        780 => [
            'name' => 'Národná banka Slovenska',
            'website' => 'https://nbs.sk',
            'emails' => [
                0 => [
                    'email' => 'info@nbs.sk',
                    'found_on' => 'https://nbs.sk/o-narodnej-banke/kontakty/',
                ],
            ],
            'address' => [
                'street' => 'Imricha Karvaša 1',
                'postcode' => '813 25',
                'municipality_id' => 242,
                'latitude' => 48.1534392,
                'longitude' => 17.1151016,
            ],
            'body' => [
                0 => 'Národná banka Slovenska je centrálna banka Slovenskej republiky. Okrem iného vydáva pamätné a zberateľské mince. Mnohé z nich pripomínajú cirkevné a kultúrne výročia.',
                1 => 'Vydala napríklad striebornú pamätnú mincu k 950. výročiu opátstva v Hronskom Beňadiku. Mince razí Mincovňa Kremnica. Ich uvedenie sprevádza slávnostné predstavenie.',
                2 => 'Kanál je určený zberateľom a všetkým, ktorých zaujímajú pamätné mince. Sledovaním budete vedieť o nových emisiách. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        795 => [
            'name' => 'AFCM Global Team',
            'body' => [
                0 => 'AFCM Global Team je medzinárodný tím katolíckej služby Anointing Fire Catholic Ministry. Služba sa venuje evanjelizácii a duchovnej obnove. Na Slovensku pripravuje programy najmä pre kňazov.',
                1 => 'Organizuje duchovné cvičenia pre kňazov s o. Xavierom v Poprade. Cvičenia trvajú niekoľko dní. Pomáhajú kňazom posilniť ich službu a duchovný život.',
                2 => 'Kanál je určený kňazom a veriacim, ktorí sledujú službu AFCM. Sledovaním budete vedieť o nových termínoch. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        827 => [
            'name' => 'CZ Modra',
            'body' => [
                0 => 'Cirkevný zbor ECAV Modra je evanjelický zbor v Modre. Patrí do Evanjelickej cirkvi augsburského vyznania na Slovensku. Pestuje aj odkaz štúrovcov spojených s Modrou.',
                1 => 'Pripravuje pietnu spomienku na Karola a Ľudovíta Štúrovcov, Samuela Zocha a Máriu Zochovú. Spomienka pripomína ich prínos pre slovenskú kultúru. Spája bohoslužbu a kultúrny program.',
                2 => 'Kanál je určený veriacim z Modry a všetkým, ktorých zaujíma odkaz štúrovcov. Sledovaním budete vedieť o ďalších podujatiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        857 => [
            'name' => 'OZ Euterpa',
            'body' => [
                0 => 'OZ Euterpa je občianske združenie, ktoré pripravuje koncerty starej hudby. Jeho názov odkazuje na antickú múzu hudby. Pôsobí najmä v Pezinku.',
                1 => 'Organizuje cyklus Hudba v Pezinku, napríklad záverečný koncert Il Cuore Barocco vo farskom kostole. Koncerty sa venujú barokovej a starej hudbe. Konajú sa v priestoroch s výnimočnou akustikou.',
                2 => 'Kanál je určený milovníkom klasickej a starej hudby. Sledovaním budete vedieť o ďalších koncertoch. Termín a miesto nájdete vždy v detaile podujatia.',
            ],
        ],
        868 => [
            'name' => 'Tlakový hrniec',
            'website' => 'https://tlakovyhrniec.sk',
            'emails' => [
                0 => [
                    'email' => 'info@tlakovyhrniec.sk',
                    'found_on' => 'https://tlakovyhrniec.sk/kontakt/',
                ],
            ],
            'body' => [
                0 => 'Tlakový hrniec je projekt rýchlych a výživných debát na školách. Otvára s mladými témy, ktoré ich zaujímajú, napríklad závislosti a vzťahy. Pripravuje aj prednášky pre verejnosť.',
                1 => 'Pripravil prednášku Opakom závislosti je vzťah o závislosti na pornografii. Konala sa v Univerzitnom pastoračnom centre v Mlynskej doline. Vstup bol voľný.',
                2 => 'Kanál je určený mladým a všetkým, ktorí hľadajú pomoc pri závislostiach. Sledovaním budete vedieť o ďalších prednáškach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        900 => [
            'name' => 'Spoločenstvo Rafael',
            'body' => [
                0 => 'Spoločenstvo Rafael pripravuje púte na známe pútnické miesta. Púte spája s duchovným programom. Ponúka ich veriacim z celého Slovenska.',
                1 => 'Organizuje napríklad silvestrovskú púť do Medžugoria, Egeru, na ostrov Krk a do Budapešti. Púť trvá cez prelom rokov. Program spája modlitbu, sväté omše a spoznávanie miest.',
                2 => 'Kanál je určený pútnikom, ktorí hľadajú organizovanú púť. Sledovaním budete vedieť o nových termínoch. Cenu a program nájdete vždy v detaile podujatia.',
            ],
        ],
        934 => [
            'name' => 'CZAP TRANS s. r. o',
            'body' => [
                0 => 'CZAP TRANS je dopravná spoločnosť z Veľkých Kapušian. Pripravuje autobusové zájazdy, vrátane pútí. Odchody bývajú z východného Slovenska.',
                1 => 'Organizovala napríklad Mariánsku púť do Medžugoria, Ostrihomu a Zadaru. Púť trvá približne týždeň. Spája návštevu pútnických miest s oddychom.',
                2 => 'Kanál je určený pútnikom z východného Slovenska. Sledovaním budete vedieť o nových zájazdoch. Cenu a program nájdete vždy v detaile podujatia.',
            ],
        ],
        948 => [
            'name' => 'KBD',
            'website' => 'https://www.kbd.sk',
            'phone' => '+421 915 909 914',
            'emails' => [
                0 => [
                    'email' => 'kbd@kbd.sk',
                    'found_on' => 'https://www.vyveska.sk/letna-biblicka-skola-janova-apokalypsa-zrkadlo-a-nadej-pre-cirkev.html',
                ],
            ],
            'body' => [
                0 => 'KBD je skratka Katolíckeho biblického diela na Slovensku. Dielo podporuje čítanie a štúdium Svätého písma. Pripravuje kurzy, webináre a biblické školy.',
                1 => 'Organizuje Letnú biblickú školu, napríklad na tému Jánova apokalypsa: zrkadlo a nádej pre Cirkev. Škola sa koná v Rodinkove v Belušských Slatinách. Trvá niekoľko dní.',
                2 => 'Kanál je určený všetkým, ktorí chcú lepšie poznať Bibliu. Sledovaním budete vedieť o ďalších biblických školách. Prihlasovanie nájdete vždy v detaile podujatia.',
            ],
        ],
        950 => [
            'name' => 'Rieka Života Podolínec',
            'body' => [
                0 => 'Rieka Života Podolínec je kresťanské spoločenstvo z Podolínca. Venuje sa chválam a modlitbe. Pripravuje večery pre veriacich zo Spiša.',
                1 => 'Spolu s ďalšími organizovalo modlitbový večer Mosty viery s Jeremym Riddlom v Katedrále sv. Martina v Spišskej Kapitule. Večer sa konal pod záštitou spišského biskupa. Spojil chvály a modlitbu.',
                2 => 'Kanál je určený veriacim zo Spiša a okolia. Sledovaním budete vedieť o ďalších večeroch chvál. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        953 => [
            'name' => 'Miles Jesu',
            'emails' => [
                0 => [
                    'email' => 'milesjesu.bb@gmail.com',
                    'found_on' => 'https://www.vyveska.sk/26625/pane-ja-hladam-tvoju-tvar.html',
                ],
            ],
            'body' => [
                0 => 'Miles Jesu je katolícke spoločenstvo zasvätených laikov. Na Slovensku pôsobí v Banskej Bystrici. Venuje sa formácii mladých.',
                1 => 'Pripravuje duchovné obnovy pre mladé ženy, napríklad „Pane, ja hľadám Tvoju tvár“. Obnova dáva priestor na ticho, modlitbu a zamyslenie. Koná sa v Banskej Bystrici.',
                2 => 'Kanál je určený mladým ženám, ktoré hľadajú hlbší duchovný život. Sledovaním budete vedieť o ďalších obnovách. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        989 => [
            'name' => 'ŠuterPárty Events',
            'body' => [
                0 => 'ŠuterPárty Events je košický organizátor benefičných zábav. Výťažok venuje dobročinným projektom. Spája zábavu s pomocou druhým.',
                1 => 'Benefičnou zábavou podporil program Omamy organizácie Cesta von. Program pomáha rómskym rodinám s najmenšími deťmi pripraviť sa na školu. Pôsobí napríklad v Jarovniciach.',
                2 => 'Kanál je určený všetkým, ktorí sa chcú zabaviť a zároveň pomôcť. Sledovaním budete vedieť o ďalších benefičných akciách. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1013 => [
            'name' => 'Drobné divadlo',
            'body' => [
                0 => 'Drobné divadlo je divadelný súbor. Uvádza predstavenia s kresťanskou tematikou. Hrá vo farnostiach a kultúrnych domoch.',
                1 => 'Uviedol muzikál Príbeh Veľkej noci, napríklad v Poľanovciach. Hudobné spracovanie muzikálu je inšpirované piesňami skupiny ABBA. Predstavenie približuje udalosti Veľkej noci.',
                2 => 'Kanál je určený rodinám a divákom, ktorí hľadajú divadlo s duchovným posolstvom. Sledovaním budete vedieť o ďalších predstaveniach. Termín a miesto nájdete vždy v detaile podujatia.',
            ],
        ],
        1025 => [
            'name' => 'Healing Grooves Production',
            'body' => [
                0 => 'Healing Grooves Production je hudobná produkcia, ktorá sa venuje kresťanskej hudbe. Podporuje tvorbu nových piesní. Pôsobí na východnom Slovensku.',
                1 => 'Pripravila workshop Tvoriť môže každý o skladaní a prekladaní piesní. Workshop sa konal v Savore Sigord pri Prešove. Je určený všetkým, ktorí chcú tvoriť hudbu.',
                2 => 'Kanál je určený hudobníkom a autorom piesní. Sledovaním budete vedieť o ďalších workshopoch. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1035 => [
            'name' => 'Svetský rád bosých karmelitánov v Bratislave',
            'body' => [
                0 => 'Svetský rád bosých karmelitánov v Bratislave združuje laikov, ktorí žijú karmelitánsku spiritualitu vo svete. Inšpiruje sa sv. Terézou z Ávily a sv. Jánom z Kríža. Pravidelne sa stretáva v Bratislave.',
                1 => 'Pripravuje sväté omše k sviatkom karmelitánskych svätých, napríklad k sviatku sv. Terézie od Dieťaťa Ježiša. Slávnosť sa konala v Modrom kostolíku sv. Alžbety. Je otvorená pre všetkých veriacich.',
                2 => 'Kanál je určený veriacim, ktorým je blízka karmelitánska spiritualita. Sledovaním budete vedieť o ďalších slávnostiach. Miesto a čas nájdete vždy v detaile podujatia.',
            ],
        ],
        1036 => [
            'name' => 'Spoločenstvo Jozue',
            'body' => [
                0 => 'Spoločenstvo Jozue je kresťanské spoločenstvo z Košíc. Venuje sa modlitbe a úcte k Božiemu milosrdenstvu. Pripravuje duchovné popoludnia pre veriacich.',
                1 => 'Organizuje Cestu milosrdenstva v diecéznej svätyni Božieho milosrdenstva na sídlisku KVP v Košiciach. Program trvá niekoľko hodín a spája modlitbu a slovo. Je otvorený pre všetkých.',
                2 => 'Kanál je určený veriacim z Košíc a okolia. Sledovaním budete vedieť o ďalších stretnutiach. Miesto a čas nájdete vždy v detaile podujatia.',
            ],
        ],
        1041 => [
            'name' => 'psychologicko-fyziatrické centrum Enoia',
            'website' => 'https://enoia.sk',
            'emails' => [
                0 => [
                    'email' => 'enoia@enoia.sk',
                    'found_on' => 'https://enoia.sk/kontakt/',
                ],
            ],
            'address' => [
                'street' => 'Štúrova 40',
                'postcode' => '017 01',
                'municipality_id' => 2790,
                'latitude' => 49.1169734,
                'longitude' => 18.4450202,
            ],
            'body' => [
                0 => 'Enoia je psychologicko-fyziatrické centrum. Spája starostlivosť o telo a dušu. Pripravuje aj vzdelávacie prednášky pre verejnosť.',
                1 => 'Pripravilo online seminár Keď bolí duša o psychickej pohode v zimnom období. Prednáška bola spojená s diskusiou. Bola dostupná z celého Slovenska.',
                2 => 'Kanál je určený všetkým, ktorí sa zaujímajú o duševné zdravie. Sledovaním budete vedieť o ďalších seminároch. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1042 => [
            'name' => 'LECA production',
            'body' => [
                0 => 'LECA production je produkčná skupina, ktorá pripravuje hudobné a divadelné predstavenia. Spolupracuje s divadlom Teatro Colorato v Bratislave. Predstavenia sú určené rodinám.',
                1 => 'Uviedla predstavenie Šnirele perele v Teatro Colorato na Františkánskom námestí. Večer trvá približne hodinu a pol. Spája hudbu, divadlo a humor.',
                2 => 'Kanál je určený divákom, ktorí hľadajú kultúrny program v Bratislave. Sledovaním budete vedieť o ďalších predstaveniach. Termín a miesto nájdete vždy v detaile podujatia.',
            ],
        ],
        1063 => [
            'name' => 'Veľvyslanectvo Poľskej republiky v Bratislave',
            'body' => [
                0 => 'Veľvyslanectvo Poľskej republiky v Bratislave zastupuje Poľsko na Slovensku. Podporuje kultúrne vzťahy oboch krajín. Pripravuje aj kultúrne podujatia.',
                1 => 'Zorganizovalo premietanie filmu o blahoslavenej poľskej rodine Ulmovcov. Film režíroval Dariusz Walusiak. Rodina Ulmovcov počas vojny ukrývala Židov a zaplatila za to životom.',
                2 => 'Kanál je určený všetkým, ktorých zaujíma poľská kultúra a história. Sledovaním budete vedieť o ďalších podujatiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1084 => [
            'name' => 'Slovenská pošta, a. s',
            'website' => 'https://www.posta.sk',
            'emails' => [
                0 => [
                    'email' => 'zakaznickyservis@slposta.sk',
                    'found_on' => 'https://posta.sk/podpora/kontakty',
                ],
            ],
            'body' => [
                0 => 'Slovenská pošta je národný poštový operátor. Vydáva aj poštové známky, ktoré pripomínajú významné osobnosti. Ich inaugurácie sa konajú na miestach spojených s osobnosťami.',
                1 => 'Spolu s obcou Bošany, Spoločenstvom sv. Gabriel a Zväzom slovenských filatelistov uviedla známku venovanú kardinálom Jánovi Chryzostomovi Korcovi a Jozefovi Tomkovi. Inaugurácia sa konala v kostole v Bošanoch. Známka patrí do emisného radu Osobnosti.',
                2 => 'Kanál je určený filatelistom a všetkým, ktorých zaujímajú osobnosti slovenských dejín. Sledovaním budete vedieť o nových emisiách. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1152 => [
            'name' => 'Občianske združenie Vŕby',
            'emails' => [
                0 => [
                    'email' => 'info@vrby.sk',
                    'found_on' => 'https://www.vrby.sk',
                ],
            ],
            'address' => [
                'street' => 'Heydukova 3',
                'postcode' => '811 08',
                'municipality_id' => 242,
                'latitude' => 48.1464714,
                'longitude' => 17.1113774,
            ],
            'body' => [
                0 => 'Občianske združenie Vŕby sa už viac ako dvadsať rokov venuje dospelým onkologickým pacientom. Dobrovoľníci ich navštevujú v nemocniciach. Pomáhajú im zvládať samotu a ťažké obdobie liečby.',
                1 => 'Pravidelne hľadá a pripravuje nových dobrovoľníkov. Ponúka im školenie a sprevádzanie pri službe. Dobrovoľníci poskytujú pacientom emocionálnu oporu a rozhovor.',
                2 => 'Kanál je určený ľuďom, ktorí chcú pomáhať chorým. Sledovaním budete vedieť o nových výzvach a stretnutiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1153 => [
            'name' => 'komunita Eben Ezer',
            'website' => 'https://ebenezer.sk',
            'emails' => [
                0 => [
                    'email' => 'info@eben-ezer.sk',
                    'found_on' => 'https://ebenezer.sk',
                ],
            ],
            'body' => [
                0 => 'Komunita Eben Ezer je kresťanské spoločenstvo z Bratislavy. Venuje sa modlitbe za Slovensko. Zapája sa do modlitebných stretnutí v Martindome.',
                1 => 'Pripravuje Modlitebné stredy na Rožňavskej 17, napríklad večer s Petrom Volfom s modlitbami za Slovensko. Stretnutia sa konajú vo večerných hodinách. Sú otvorené pre všetkých.',
                2 => 'Kanál je určený veriacim z Bratislavy. Sledovaním budete vedieť o ďalších modlitebných večeroch. Miesto a čas nájdete vždy v detaile podujatia.',
            ],
        ],
        1160 => [
            'name' => 'Vztahovo',
            'body' => [
                0 => 'Vztahovo je projekt, ktorý sa venuje vzťahom v rodine. Pripravuje prednášky a stretnutia pre rodičov. Pomáha rodinám budovať pevné väzby.',
                1 => 'Pripravil napríklad stretnutie Návyky šťastných rodín na farskom úrade v Šuranoch. Program sa venuje rodinnej pohode a spoločnému času. Je určený rodičom aj celým rodinám.',
                2 => 'Kanál je určený rodinám, ktoré chcú posilniť svoje vzťahy. Sledovaním budete vedieť o ďalších stretnutiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1177 => [
            'name' => 'novaJAR',
            'website' => 'https://novajar.sk',
            'emails' => [
                0 => [
                    'email' => 'info@novajar.sk',
                    'found_on' => 'https://novajar.sk/kontakt/',
                ],
            ],
            'body' => [
                0 => 'novaJAR je kresťanské vydavateľstvo. Vydáva knihy a projekty, ktoré pomáhajú pri čítaní Svätého písma. Pripravuje aj diskusie pri ich predstavení.',
                1 => 'Pri projekte Biblia za rok pripravilo moderovanú diskusiu „Dá sa prečítať Biblia za rok?“ vo farnosti Poprad. Diskusia sa venuje tomu, ako môže čítanie Biblie zmeniť každodenný život. Je otvorená verejnosti.',
                2 => 'Kanál je určený čitateľom a všetkým, ktorí chcú čítať Bibliu pravidelne. Sledovaním budete vedieť o ďalších diskusiách a novinkách. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1193 => [
            'name' => 'Portál Nové mesto',
            'website' => 'https://www.nm.sk',
            'emails' => [
                0 => [
                    'email' => 'info@nm.sk',
                    'found_on' => 'https://nm.sk',
                ],
            ],
            'address' => [
                'street' => 'Cablkova 10',
                'postcode' => '821 04',
                'municipality_id' => 242,
                'latitude' => 48.1671959,
                'longitude' => 17.1597775,
            ],
            'body' => [
                0 => 'Portál Nové mesto je kresťanský portál, ktorý sa venuje spoločenským témam. Pripravuje aj vzdelávacie webináre. Vydáva knihy a časopis.',
                1 => 'Pripravil webinár Ako čeliť šikane inovatívnym spôsobom o metóde No Blame Approach. Metóda rieši šikanu v školách bez obviňovania. Webinár je určený pedagógom a rodičom.',
                2 => 'Kanál je určený učiteľom, rodičom a vychovávateľom. Sledovaním budete vedieť o ďalších webinároch. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1209 => [
            'name' => 'Tri chotáre',
            'body' => [
                0 => 'Tri chotáre je organizátor tradičných podujatí v okolí Nitry. Pripravuje podujatia v prírode. Spája ich s duchovným rozmerom.',
                1 => 'Organizuje tradičnú Hubertovu svätú omšu v Párovských hájoch pri kríži na Fabiánke. Omša sa slávi pri príležitosti dní sv. Huberta. Spája poľovnícke tradície s duchovným rozmerom.',
                2 => 'Kanál je určený poľovníkom, priateľom prírody a veriacim z Nitry. Sledovaním budete vedieť o ďalšom ročníku. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1223 => [
            'name' => 'Centrum Ateneum',
            'body' => [
                0 => 'Centrum Ateneum je vzdelávacie centrum, ktoré sa venuje duchovnému vzdelávaniu. Pripravuje programy pre kňazov. Pôsobí v Bratislave.',
                1 => 'Organizuje vzdelávacie stretnutia pre kňazov, napríklad dvojdňové stretnutie v Melčiciach. Cieľom je ponúknuť kňazom nové podnety pre ich službu. Program spája prednášky a výmenu skúseností.',
                2 => 'Kanál je určený kňazom a pastoračným pracovníkom. Sledovaním budete vedieť o ďalších stretnutiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1238 => [
            'name' => 'Turistické informačné centrum',
            'website' => 'https://nitra.sk',
            'emails' => [
                0 => [
                    'email' => 'info@nitra.sk',
                    'found_on' => 'https://nitra.sk',
                ],
            ],
            'body' => [
                0 => 'Turistické informačné centrum v Nitre poskytuje informácie návštevníkom mesta. Pomáha objavovať pamiatky a kultúru Nitry. Pripravuje aj sezónne projekty.',
                1 => 'V lete otvára turistom nitrianske kostoly, ktoré sú bežne zatvorené. Návštevníci tak môžu spoznať sakrálne pamiatky mesta. Projekt prebieha počas júla a augusta.',
                2 => 'Kanál je určený turistom a obyvateľom Nitry. Sledovaním budete vedieť o otváracích časoch a nových projektoch. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1270 => [
            'name' => 'Spol. Hebron',
            'body' => [
                0 => 'Spoločenstvo Hebron je kresťanské spoločenstvo z Piešťan. Venuje sa modlitbe chvál. Pripravuje stretnutia pre veriacich z mesta.',
                1 => 'Organizuje Piešťanské chvály v Jezuitskej kaplnke na Poštovej ulici. Stretnutia trvajú približne hodinu a sú zamerané na vďaku a chválu. Sú otvorené pre všetkých.',
                2 => 'Kanál je určený veriacim z Piešťan a okolia. Sledovaním budete vedieť o ďalších chválach. Miesto a čas nájdete vždy v detaile podujatia.',
            ],
        ],
        1278 => [
            'name' => 'KASIH House of Mission',
            'body' => [
                0 => 'KASIH House of Mission je misijné spoločenstvo z Popradu. Venuje sa evanjelizácii mladých. Spolupracuje s Komisiou pre mládež Spišskej diecézy.',
                1 => 'Spolu s komisiou pripravilo prvý ročník konferencie KAIROS s názvom Odomkni svoju radosť. Konferencia sa konala v pastoračnom centre v Poprade. Trvala celý deň.',
                2 => 'Kanál je určený mladým zo Spiša. Sledovaním budete vedieť o ďalších ročníkoch konferencie. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1295 => [
            'name' => 'Faith & Reason Institute',
            'emails' => [
                0 => [
                    'email' => 'fss@kolegium.org',
                    'found_on' => 'https://www.vyveska.sk/letna-skola-free-society-seminar.html',
                ],
            ],
            'body' => [
                0 => 'Faith & Reason Institute je vzdelávacia inštitúcia, ktorá spája vieru a rozum. Pripravuje programy pre študentov. Venuje sa otázkam slobodnej spoločnosti.',
                1 => 'Organizuje letnú školu Free Society Seminar v Kolégiu Antona Neuwirtha v Ivanke pri Dunaji. Škola trvá vyše týždňa. Spája prednášky, diskusie a spoločný život.',
                2 => 'Kanál je určený študentom a mladým, ktorých zaujíma filozofia a spoločnosť. Sledovaním budete vedieť o ďalšom ročníku. Prihlasovanie nájdete vždy v detaile podujatia.',
            ],
        ],
        1358 => [
            'name' => 'Miništranti',
            'body' => [
                0 => 'Miništranti.sk je projekt pre miništrantov na Slovensku. Ponúka materiály a programy pre službu pri oltári. Spája miništrantov z rôznych farností.',
                1 => 'Pripravuje online podujatie PAMin, ktoré sprevádza miništrantov v pôste. Stretnutia trvajú približne hodinu. Sú určené miništrantom aj maxištrantom.',
                2 => 'Kanál je určený miništrantom a ich animátorom. Sledovaním budete vedieť o ďalších stretnutiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1372 => [
            'name' => 'Consolacion',
            'emails' => [
                0 => [
                    'email' => 'nitra@consolacion.org',
                    'found_on' => 'https://www.vyveska.sk/ii-duchovno-formacna-obnova.html',
                ],
            ],
            'body' => [
                0 => 'Consolacion je organizátor duchovných programov pre mladých v Nitre. Ponúka formačné obnovy. Pomáha mladým hľadať zmysel života.',
                1 => 'Pripravuje víkendové duchovno-formačné obnovy, napríklad na Stračej ulici v Nitre. Obnova trvá od piatka do nedele. Spája modlitbu, prednášky a spoločenstvo.',
                2 => 'Kanál je určený mladým z Nitry a okolia. Sledovaním budete vedieť o ďalších obnovách. Prihlasovanie nájdete vždy v detaile podujatia.',
            ],
        ],
        1380 => [
            'name' => 'JANAIS',
            'emails' => [
                0 => [
                    'email' => 'manager@janais.sk',
                    'found_on' => 'http://www.janais.sk',
                ],
            ],
            'body' => [
                0 => 'JANAIS je speváčka, ktorá vystupuje so svojou kapelou. Jej piesne majú duchovný rozmer. Koncertuje na Slovensku.',
                1 => 'Pripravila predvianočný koncert Vianočná JANAIS v Dvorane evanjelického kolégia v Prešove. Koncert sa konal priamo na Hlavnej ulici. Vystúpila aj v Teatro Colorato v Bratislave.',
                2 => 'Kanál je určený poslucháčom, ktorí hľadajú koncerty s duchovným posolstvom. Sledovaním budete vedieť o ďalších koncertoch. Termín a miesto nájdete vždy v detaile podujatia.',
            ],
        ],
        1410 => [
            'name' => 'Timothy Worship Centrum School',
            'body' => [
                0 => 'Timothy Worship Centrum School je kresťanská naddenominačná škola chvál. Vedie ju kapela Timothy spolu s kresťanskými vedúcimi z celého Slovenska. Rozvíja dary pre službu chvál.',
                1 => 'Otvára nové ročníky školy chvál, kreativity a uctievania. Stretnutia sa konali napríklad na Ranči Kráľová Lehota. Škola je určená hudobníkom a vedúcim chvál.',
                2 => 'Kanál je určený muzikantom a vedúcim chvál zo všetkých cirkví. Sledovaním budete vedieť o otvorení nového ročníka. Prihlasovanie nájdete vždy v detaile podujatia.',
            ],
        ],
        1431 => [
            'name' => 'Relax Studio Danillo',
            'body' => [
                0 => 'Relax Studio Danillo je štúdio v Bratislave na Hrobákovej ulici. Ponúka aj kresťanské stretnutia pre verejnosť. Spája oddych a duchovný program.',
                1 => 'Pripravuje kresťanské filmové večery spojené s modlitbou. Účastníci si pozrú inšpiratívny film a spolu sa pomodlia. Večery sú otvorené pre všetkých.',
                2 => 'Kanál je určený obyvateľom Petržalky a Bratislavy. Sledovaním budete vedieť o ďalších večeroch. Miesto a čas nájdete vždy v detaile podujatia.',
            ],
        ],
        1453 => [
            'name' => 'Bafpro',
            'body' => [
                0 => 'Bafpro je organizátor vzdelávania pre kresťanských finančných poradcov. Spája odbornosť s kresťanskými hodnotami. Kurzy ponúka online.',
                1 => 'Pripravil vzdelávací kurz pre kresťanských finančných poradcov. Kurz rozvíja odborné vedomosti aj osobnú integritu v práci s financiami. Prebieha online s väzbou na Košice.',
                2 => 'Kanál je určený finančným poradcom a podnikateľom. Sledovaním budete vedieť o nových kurzoch. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1466 => [
            'name' => 'CUP Martin',
            'body' => [
                0 => 'CUP Martin je centrum univerzitnej pastorácie v Martine. Venuje sa študentom, najmä medicíny. Pripravuje formačné a odborné stretnutia.',
                1 => 'Organizuje Celoslovenskú kresťanskú medickú konferenciu Povolaní slúžiť na vysokoškolskom internáte v Martine. Konferencia prepája medicínu a vieru. Má už niekoľko ročníkov.',
                2 => 'Kanál je určený študentom medicíny a zdravotníkom. Sledovaním budete vedieť o ďalšom ročníku konferencie. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1470 => [
            'name' => 'Spoločenstvo Hrubáci',
            'body' => [
                0 => 'Spoločenstvo Hrubáci sa podieľa na podujatiach v Bazilike sv. Mikuláša v Trnave. Zapája sa do slávností farnosti. Spája veriacich z Trnavy.',
                1 => 'Spolupodieľalo sa na oslavách 600 rokov od posviacky Baziliky sv. Mikuláša v Trnave. Dvojdňový program pripomenul históriu baziliky. Spojil bohoslužby a kultúrny program.',
                2 => 'Kanál je určený veriacim z Trnavy. Sledovaním budete vedieť o ďalších slávnostiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1503 => [
            'name' => 'Jugend für das Leben',
            'website' => 'https://jugendfuerdasleben.at',
            'body' => [
                0 => 'Jugend für das Leben (Mladí za život) je rakúska pro-life organizácia. Pracuje najmä s mladou generáciou. Pripravuje putovné kampane.',
                1 => 'Organizuje každoročnú putovnú Pro Life Tour, ktorej 25. ročník viedol z Passau do Bratislavy. Účastníci počas leta prechádzajú mestami a hovoria o hodnote života. Tour spája putovanie a diskusie.',
                2 => 'Kanál je určený mladým, ktorí sa chcú zapojiť do ochrany života. Sledovaním budete vedieť o ďalších ročníkoch. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1523 => [
            'name' => 'Show your talent',
            'emails' => [
                0 => [
                    'email' => 'ahoj@showyourtalent.sk',
                    'found_on' => 'https://showyourtalent.sk',
                ],
            ],
            'address' => [
                'street' => 'Nám. SNP 16',
                'postcode' => '974 01',
                'municipality_id' => 73,
                'latitude' => 48.7353516,
                'longitude' => 19.144296,
            ],
            'body' => [
                0 => 'Show your talent je projekt, ktorý pomáha mladým objaviť svoje talenty. Jeho heslo je „Nájdi svoj sen a začni na sebe makať“. Spája ľudí, ktorí pracujú s mládežou.',
                1 => 'Pripravil konferenciu pre organizácie, školy a odborníkov pracujúcich s mládežou. Konala sa v Banskej Bystrici a Badíne. Ponúkla prednášky a výmenu skúseností.',
                2 => 'Kanál je určený mladým a ich vedúcim. Sledovaním budete vedieť o ďalších konferenciách. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1536 => [
            'name' => 'IKOSI IKONES o.z',
            'body' => [
                0 => 'IKOSI IKONES je občianske združenie, ktoré sa venuje písaniu ikon. Pripravuje ikonopisecké kurzy. Spája umenie a modlitbu.',
                1 => 'Organizuje ikonopisecké kurzy pod vedením gréckeho ikonopisca Theodorosa Papadopoulosa. Kurz sa konal v Kláštore premonštrátov v Jasove. Trvá niekoľko dní.',
                2 => 'Kanál je určený záujemcom o sakrálne umenie a ikony. Sledovaním budete vedieť o ďalších kurzoch. Prihlasovanie nájdete vždy v detaile podujatia.',
            ],
        ],
        1541 => [
            'name' => 'Kresťanskodemokratické hnutie',
            'emails' => [
                0 => [
                    'email' => 'sekretariat@kdh.sk',
                    'found_on' => 'https://kdh.sk/kontakt/',
                ],
            ],
            'body' => [
                0 => 'Kresťanskodemokratické hnutie je politická strana, ktorá vychádza z hodnôt kresťanskej demokracie. V Trnave sa jej členovia zapájajú aj do spoločenských podujatí. Tie sú spojené s odkazom osobností.',
                1 => 'Trnavčania sa každoročne stretávajú na Pocte sv. Jánovi Pavlovi II. pri jeho soche na Univerzitnom námestí. Stretnutie má už vyše pätnásť ročníkov. Spája modlitbu a spomienku.',
                2 => 'Kanál je určený obyvateľom Trnavy. Sledovaním budete vedieť o ďalších stretnutiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1558 => [
            'name' => 'Predsedníctvo Západného dištriktu',
            'body' => [
                0 => 'Predsedníctvo Západného dištriktu vedie Západný dištrikt Evanjelickej cirkvi augsburského vyznania na Slovensku. Stará sa o cirkevné zbory dištriktu. Pripravuje aj podujatia pre duchovných.',
                1 => 'Organizuje pastorálne konferencie, napríklad o revitalizácii cirkevných zborov vo Zvolene. Konferencia ponúka prednášky a diskusie. Je určená farárom a farárkam.',
                2 => 'Kanál je určený duchovným a pracovníkom Evanjelickej cirkvi. Sledovaním budete vedieť o ďalších konferenciách. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1564 => [
            'name' => 'Medzinárodná aliancia pre mier a bezpečnosť',
            'body' => [
                0 => 'Medzinárodná aliancia pre mier a bezpečnosť stojí za iniciatívou Národy za mier. Spája ľudí, ktorí sa modlia a pracujú za mier medzi národmi. Pôsobí v strednej Európe.',
                1 => 'Pripravila slávnostné zhromaždenie Národy za mier za Ukrajinu a Rusko v Prahe. Stretnutie spájalo modlitbu a svedectvá. Bolo reakciou na vojnu na Ukrajine.',
                2 => 'Kanál je určený všetkým, ktorí sa modlia za mier. Sledovaním budete vedieť o ďalších stretnutiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1582 => [
            'name' => 'Na každom záleží',
            'emails' => [
                0 => [
                    'email' => 'info@nakazdomzalezi.sk',
                    'found_on' => 'https://www.nakazdomzalezi.sk',
                ],
            ],
            'body' => [
                0 => 'Na každom záleží je nezisková organizácia, ktorá chce priniesť dobrú správu o Božej láske ľuďom, ktorí ju nepoznajú. Pomáha kresťanom hovoriť o viere prirodzene. Sídli v Badíne.',
                1 => 'Pripravuje misijné konferencie, napríklad Staré poslanie novou formou v Dome Xaver v Badíne. Konferencia je určená všetkým, ktorí chcú prinášať evanjelium do svojho okolia. Ponúka prednášky a workshopy.',
                2 => 'Kanál je určený veriacim, ktorí chcú žiť misijne. Sledovaním budete vedieť o ďalších konferenciách. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1583 => [
            'name' => 'VIDES',
            'website' => 'https://vides.sk',
            'emails' => [
                0 => [
                    'email' => 'vides.misie@gmail.com',
                    'found_on' => 'https://vides.sk/kontakt/',
                ],
            ],
            'address' => [
                'street' => 'Miletičova 7',
                'postcode' => '821 08',
                'municipality_id' => 242,
                'latitude' => 48.1557119,
                'longitude' => 17.1344389,
            ],
            'body' => [
                0 => 'VIDES je dobrovoľnícka organizácia spojená so sestrami saleziánkami. Pripravuje mladých na dobrovoľnícku službu. Zapája sa aj do programov pre mládež na Slovensku.',
                1 => 'Spolupracovala na projekte Tripy mladých, ktorý pripravoval mladých na národné stretnutie mládeže T22. V rámci neho sa uskutočnil výstup na Demänovskú horu. Projekt spájal turistiku a duchovnú prípravu.',
                2 => 'Kanál je určený mladým, ktorí sa chcú zapojiť do dobrovoľníctva. Sledovaním budete vedieť o nových projektoch. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1594 => [
            'name' => 'Misia na Níle',
            'body' => [
                0 => 'Misia na Níle je organizácia, ktorá pomáha ľuďom v Južnom Sudáne. Jej heslo je Nádej pre ľudí na Níle. Podporuje vzdelávanie a misijnú prácu.',
                1 => 'Každoročne pripravuje výročnú misijnú konferenciu, v posledných rokoch online cez ZOOM. Konferencia prináša správy z misie a stretnutie s podporovateľmi. Odkaz na pripojenie zverejňuje na svojom webe.',
                2 => 'Kanál je určený priaznivcom misií a podporovateľom. Sledovaním budete vedieť o ďalšej konferencii. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1655 => [
            'name' => 'DKC Veritas',
            'website' => 'https://www.dkcveritas.sk',
            'phone' => '0910 842 242',
            'emails' => [
                0 => [
                    'email' => 'dkc@dkcveritas.sk',
                    'found_on' => 'https://www.dkcveritas.sk/kontakt',
                ],
            ],
            'address' => [
                'street' => 'Dominikánske námestie 8',
                'postcode' => '040 01',
                'municipality_id' => 1565,
                'latitude' => 48.720853,
                'longitude' => 21.2548041,
            ],
            'body' => [
                0 => 'DKC Veritas je kultúrne centrum v Košiciach na Dominikánskom námestí. Pripravuje koncerty, diskusie a kultúrne večery. Spája kultúru a kresťanské hodnoty.',
                1 => 'Pripravilo napríklad Šansónový večer s piesňami Hany Hegerovej v podaní Jany Záchenskej. V jeho priestoroch sa konajú aj Košické Hanusove dni. Program je pestrý a otvorený verejnosti.',
                2 => 'Kanál je určený obyvateľom Košíc a milovníkom kultúry. Sledovaním budete vedieť o ďalších večeroch. Termín a miesto nájdete vždy v detaile podujatia.',
            ],
        ],
        1670 => [
            'name' => 'Verejno-súkromné partnerstvo Stredný Gemer',
            'body' => [
                0 => 'Verejno-súkromné partnerstvo Stredný Gemer spolupracuje so Združením Gotická cesta. Sprístupňuje stredoveké kostoly Gemera. Podporuje cestovný ruch v regióne.',
                1 => 'Každoročne v poslednú augustovú sobotu otvára kostoly na Gotickej ceste so vzácnymi freskami. Návštevníci môžu nahliadnuť do interiérov aj menej známych kostolov. Každý rok sa zameriava na iný okruh.',
                2 => 'Kanál je určený turistom a milovníkom pamiatok. Sledovaním budete vedieť o termíne otvorených kostolov. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1698 => [
            'name' => 'Katedra náboženských štúdií',
            'body' => [
                0 => 'Katedra náboženských štúdií pôsobí na Univerzite Konštantína Filozofa v Nitre. Venuje sa štúdiu náboženstiev a príprave učiteľov. Ponúka aj ďalšie vzdelávanie.',
                1 => 'Otvára trojročné rozširujúce štúdium učiteľstva náboženskej výchovy. Je určené učiteľom z praxe, ktorí si chcú doplniť aprobáciu. Štúdium začína v septembri.',
                2 => 'Kanál je určený učiteľom a záujemcom o štúdium. Sledovaním budete vedieť o otvorení nových študijných programov. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1708 => [
            'name' => 'OZ Cortona',
            'body' => [
                0 => 'OZ Cortona je občianske združenie, ktoré pripravuje duchovné semináre. Pozýva zahraničných kazateľov. Semináre sú otvorené pre verejnosť.',
                1 => 'Organizovalo semináre s pátrom Eliasom Vellom v Gréckokatolíckom kňazskom seminári P. P. Gojdiča v Prešove. Semináre sa venujú duchovnému rastu a uzdraveniu. Sú otvorené pre veriacich.',
                2 => 'Kanál je určený veriacim z východného Slovenska. Sledovaním budete vedieť o ďalších seminároch. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1724 => [
            'name' => 'Foxx Media Group, s. r. o',
            'body' => [
                0 => 'Foxx Media Group je distribučná spoločnosť, ktorá uvádza filmy s duchovnou tematikou. Spolupracuje s kinami a farnosťami. Na Slovensku distribuovala film Fatima.',
                1 => 'Ponúka farnostiam možnosť zorganizovať verejnú projekciu historickej drámy Fatima. Film zachytáva zjavenia Panny Márie vo Fatime v roku 1917. Projekcie môžu prebiehať aj mimo kín.',
                2 => 'Kanál je určený farnostiam a divákom, ktorí hľadajú filmy s hodnotami. Sledovaním budete vedieť o nových filmoch a projekciách. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1735 => [
            'name' => 'BUDDY',
            'emails' => [
                0 => [
                    'email' => 'info@tvojbuddy.sk',
                    'found_on' => 'https://www.tvojbuddy.sk',
                ],
            ],
            'body' => [
                0 => 'BUDDY je program, ktorý spája dobrovoľníkov s deťmi z centier pre deti a rodiny. Dobrovoľník sa dieťaťu venuje raz týždenne. Cieľom je dlhodobé priateľstvo.',
                1 => 'Hľadá nových dobrovoľníkov v regiónoch, kde program pôsobí, napríklad v Trenčíne. Dobrovoľníci prejdú prípravou a majú podporu. Pomáhajú deťom pripraviť sa na samostatný život.',
                2 => 'Kanál je určený ľuďom, ktorí chcú pomôcť deťom z detských domovov. Sledovaním budete vedieť o nových výzvach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1738 => [
            'name' => 'ZED',
            'body' => [
                0 => 'ZED je Združenie evanjelických duchovných na Slovensku. Spája farárov a farárky Evanjelickej cirkvi a. v. Podporuje ich vzdelávanie a spoločenstvo.',
                1 => 'Ponúka webináre pre duchovných v spolupráci s Evanjelickou bohosloveckou fakultou UK. Webináre sa venujú teologickým a pastoračným témam. Prebiehajú online.',
                2 => 'Kanál je určený evanjelickým duchovným. Sledovaním budete vedieť o nových webinároch. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1739 => [
            'name' => 'Iniciatíva Oplatí.sa',
            'body' => [
                0 => 'Iniciatíva Oplatí.sa je kresťanská iniciatíva na sociálnych sieťach. Stojí za ňou Tadeáš Jacko s kolektívom. Prináša duchovné projekty pre mladých.',
                1 => 'Spustila projekt Pôst podľa púštnych otcov. Projekt sprevádza účastníkov pôstom cez myšlienky púštnych otcov. Prebieha na Facebooku.',
                2 => 'Kanál je určený všetkým, ktorí chcú prežiť pôst hlbšie. Sledovaním budete vedieť o nových projektoch. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1741 => [
            'name' => 'Univerzita Komenského',
            'body' => [
                0 => 'Univerzita Komenského v Bratislave je najstaršia a najväčšia univerzita na Slovensku. Má aj teologické fakulty. Každoročne pripravuje Deň otvorených dverí.',
                1 => 'Na Dni otvorených dverí sa predstavujú všetky fakulty, vrátane Bohosloveckej fakulty. Podujatie prebieha aj virtuálne. Je určené uchádzačom o štúdium.',
                2 => 'Kanál je určený študentom a uchádzačom o štúdium. Sledovaním budete vedieť o termíne Dňa otvorených dverí. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1746 => [
            'name' => 'Milosrdenstvo',
            'body' => [
                0 => 'Milosrdenstvo je kanál projektu milosrdenstvo.sk, ktorý šíri úctu k Božiemu milosrdenstvu a odkaz sv. Faustíny. Pripravuje aj duchovné obnovy. Mnohé prebiehajú online.',
                1 => 'Pripravil online obnovu Zranená, ale milovaná pre dievčatá a ženy. Obnova prebiehala cez ZOOM v dvoch termínoch. Venuje sa uzdraveniu a prijatiu Božej lásky.',
                2 => 'Kanál je určený dievčatám a ženám, ktoré hľadajú duchovnú obnovu. Sledovaním budete vedieť o ďalších obnovách. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1760 => [
            'name' => 'LUX communication',
            'body' => [
                0 => 'LUX communication je občianske združenie, ktoré sa vyše dvadsať rokov venuje evanjelizácii cez médiá. Vyrába filmy a televízne programy. Je spojené s Televíziou LUX.',
                1 => 'Vypisuje výzvy na námety 13-minútových hraných filmov. Hľadá kreatívnych autorov s pohľadom na súčasný svet. Vybrané námety sa realizujú ako krátke filmy.',
                2 => 'Kanál je určený scenáristom a tvorcom. Sledovaním budete vedieť o nových výzvach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1764 => [
            'name' => 'Deti Afriky',
            'website' => 'https://detiafriky.sk',
            'emails' => [
                0 => [
                    'email' => 'detiafriky@detiafriky.sk',
                    'found_on' => 'https://detiafriky.sk',
                ],
            ],
            'body' => [
                0 => 'Deti Afriky je projekt, ktorý podporuje vzdelávanie chudobných detí v Tanzánii. Podporovatelia sa stretávajú v Košiciach. Projekt informuje o novinkách z Afriky.',
                1 => 'Každoročne pripravuje Africký večer v Košiciach, v ťažších časoch aj online. Večer prináša správy o podporovaných deťoch a projektoch. Je stretnutím priateľov projektu.',
                2 => 'Kanál je určený podporovateľom a všetkým, ktorí chcú pomáhať deťom v Afrike. Sledovaním budete vedieť o ďalšom večere. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1766 => [
            'name' => 'Medzinárodná federácia pre slobodu voľby terapie a sprevádzania',
            'website' => 'https://iftcc.org',
            'body' => [
                0 => 'Medzinárodná federácia pre slobodu voľby terapie a sprevádzania (IFTCC) je medzinárodná organizácia poradcov. Venuje sa poradenstvu v oblasti sexuality a identity. Pripravuje medzinárodné sympóziá.',
                1 => 'Organizuje medzinárodnú konferenciu, ktorej 6. ročník prebehol online. Konferencia sa venuje slobodám, právam a sebaurčeniu v poradenstve. Je určená poradcom a odborníkom.',
                2 => 'Kanál je určený poradcom a pomáhajúcim profesiám. Sledovaním budete vedieť o ďalších konferenciách. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1788 => [
            'name' => 'SP (Silné posolstvo)',
            'body' => [
                0 => 'SP (Silné posolstvo) je spoločenstvo zo Sliača. Pripravuje konferencie pre ženy. Pôsobí v okolí Banskej Bystrice.',
                1 => 'Organizuje ženskú konferenciu Nespútaná, napríklad ročník s názvom Esencia v Banskej Bystrici. Konferencia prebiehala aj online. Je určená ženám všetkých vekových kategórií.',
                2 => 'Kanál je určený ženám, ktoré hľadajú duchovné povzbudenie. Sledovaním budete vedieť o ďalších ročníkoch. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1813 => [
            'name' => 'Continental film',
            'emails' => [
                0 => [
                    'email' => 'program@cofilm.sk',
                    'found_on' => 'https://www.continental-film.sk/kontakt',
                ],
            ],
            'address' => [
                'street' => 'Ševčenkova 19',
                'postcode' => '851 01',
                'municipality_id' => 242,
                'latitude' => 48.1196967,
                'longitude' => 17.1024842,
            ],
            'body' => [
                0 => 'Continental film je jedna z najväčších filmových distribučných spoločností na Slovensku. Do kín prináša hollywoodske aj európske filmy. Uvádza aj filmy s duchovnou tematikou.',
                1 => 'Priniesla do slovenských kín historický film Fatima. Slovensko patrilo medzi prvé krajiny, kde mal premiéru. Film zachytáva zjavenia Panny Márie vo Fatime.',
                2 => 'Kanál je určený divákom, ktorí hľadajú filmy s hodnotami. Sledovaním budete vedieť o nových premiérach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1815 => [
            'name' => 'Medzinárodná únia generálnych predstavených',
            'body' => [
                0 => 'Medzinárodná únia generálnych predstavených združuje predstavených mužských reholí. Pôsobí pri Svätej stolici v Ríme. Pripravuje aj spoločné iniciatívy.',
                1 => 'Zorganizovala online modlitbu „Nemôžem dýchať“ za ľudí zasiahnutých ochorením COVID-19. Modlitba spájala rehoľníkov a veriacich z celého sveta. Bola výrazom solidarity s chorými.',
                2 => 'Kanál je určený rehoľníkom a veriacim. Sledovaním budete vedieť o ďalších iniciatívach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1818 => [
            'name' => 'Nitriansky samosprávny kraj',
            'website' => 'https://www.unsk.sk',
            'emails' => [
                0 => [
                    'email' => 'info@unsk.sk',
                    'found_on' => 'https://unsk.sk/zobraz/contact',
                ],
            ],
            'address' => [
                'street' => 'Rázusova 2A',
                'postcode' => '949 01',
                'municipality_id' => 2333,
                'latitude' => 48.2996657,
                'longitude' => 18.080946,
            ],
            'body' => [
                0 => 'Nitriansky samosprávny kraj je vyšší územný celok so sídlom v Nitre. Podporuje kultúru a tradície regiónu. Podieľa sa na Pribinových a Cyrilo-metodských slávnostiach.',
                1 => 'V rámci slávností pripravil výstavu Nitra a svätý Metod na poštových známkach. Výstava predstavila exponáty a publikácie Milana Šajgalíka. Spojila filatelistiku a dejiny.',
                2 => 'Kanál je určený obyvateľom regiónu a návštevníkom Nitry. Sledovaním budete vedieť o ďalších výstavách. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1872 => [
            'name' => 'CENTRUM ABRAHAM',
            'body' => [
                0 => 'CENTRUM ABRAHAM je iniciatíva, ktorá podporuje dialóg a zmierenie medzi náboženstvami. Pripravuje putovania a stretnutia. Pozýva k modlitbe a stíšeniu.',
                1 => 'Organizuje Putovanie zmierenia na pamiatku otca Jacquesa Hamela, desať rokov po jeho smrti. Putovanie vedie po Svätojakubskej ceste na Slovensku. Spája modlitbu a stíšenie.',
                2 => 'Kanál je určený pútnikom a všetkým, ktorým záleží na zmierení. Sledovaním budete vedieť o ďalších putovaniach. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1884 => [
            'name' => 'Donacia.sk',
            'address' => [
                'street' => 'Štefánikova 46',
                'postcode' => '917 01',
                'municipality_id' => 3596,
                'latitude' => 48.3780604,
                'longitude' => 17.5850727,
            ],
            'body' => [
                0 => 'Donacia.sk je platforma na darovanie dobročinným projektom. Umožňuje ľahko a rýchlo podporiť dobré dielo. Sídli v Trnave.',
                1 => 'Pripravuje webináre, napríklad o jednoduchom (ko)financovaní dobročinných projektov. Je určený ľuďom, ktorí realizujú prospešné projekty alebo o nich uvažujú. Webinár prebieha online.',
                2 => 'Kanál je určený organizáciám a aktívnym ľuďom. Sledovaním budete vedieť o ďalších webinároch. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1918 => [
            'name' => 'Biznis raňajky Poprad',
            'body' => [
                0 => 'Biznis raňajky Poprad sú pravidelné ranné stretnutia podnikateľov. Konajú sa v Coworkingu Poprad na Námestí sv. Egídia. Spájajú vzdelávanie a networking.',
                1 => 'Na raňajkách vystupujú hostia s praktickými témami, napríklad kňaz Andrej Branický o budovaní komunity na sociálnych sieťach. Stretnutie trvá dve hodiny. Je otvorené pre podnikateľov a profesionálov.',
                2 => 'Kanál je určený podnikateľom z Popradu a okolia. Sledovaním budete vedieť o ďalších hosťoch. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1919 => [
            'name' => 'Nadácia rodiny Ulmových SOAR',
            'body' => [
                0 => 'Nadácia rodiny Ulmových SOAR nesie odkaz blahoslavenej poľskej rodiny Ulmovcov. Podporuje umenie a duchovné iniciatívy. Pôsobí v Ríme.',
                1 => 'Spolu s Nadáciou Jána Pavla II. pripravila festival Dávidových žalmov Žalmy s Jánom Pavlom II. – Žalmy pokoja. Koncert sa konal v Auditorium Conciliazione v Ríme. Pripomenul výročie pontifikátu Jána Pavla II.',
                2 => 'Kanál je určený všetkým, ktorým je blízky odkaz Jána Pavla II. Sledovaním budete vedieť o ďalších podujatiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1931 => [
            'name' => 'Spoločenstvo Vykroč',
            'body' => [
                0 => 'Spoločenstvo Vykroč je kresťanské spoločenstvo z Boroviec pri Piešťanoch. Pripravuje celodenné stretnutia s chválami. Ponúka priestor na zastavenie a načerpanie síl.',
                1 => 'Organizuje Day by the Well – Deň pri studni v kultúrnom dome v Borovciach. Program trvá od rána do večera a spája chvály, slovo a modlitbu. Je otvorený pre všetkých.',
                2 => 'Kanál je určený veriacim z Boroviec, Piešťan a okolia. Sledovaním budete vedieť o ďalších stretnutiach. Miesto a čas nájdete vždy v detaile podujatia.',
            ],
        ],
        1934 => [
            'name' => 'OZ LEVADA',
            'body' => [
                0 => 'OZ LEVADA je občianske združenie z Banskej Bystrice. Pripravuje duchovné programy. Ponúka priestor na zamyslenie nad vlastným životom.',
                1 => 'Pripravilo duchovnú prechádzku Aby srdce mohlo byť slobodné. Program vedie k zastaveniu a pohľadu do vlastného vnútra. Je otvorený pre jednotlivcov.',
                2 => 'Kanál je určený ľuďom, ktorí hľadajú duchovné povzbudenie. Sledovaním budete vedieť o ďalších programoch. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1940 => [
            'name' => 'Nadácia Slovakia Christiana',
            'address' => [
                'street' => 'Kvetinárska 31',
                'postcode' => '821 06',
                'municipality_id' => 242,
                'latitude' => 48.1293728,
                'longitude' => 17.200832,
            ],
            'body' => [
                0 => 'Nadácia Slovakia Christiana podporuje kresťanskú kultúru a vzdelávanie na Slovensku. Pripravuje konferencie a odborné stretnutia. Sídli v Bratislave.',
                1 => 'Organizovala konferenciu Panna Mária v pláne spásy alebo Kde sú hranice mariánskej úcty? v Centre Salvator na Jakubovom námestí. Konferencia trvala celý deň. Spájala teológiu a kultúru.',
                2 => 'Kanál je určený veriacim a všetkým, ktorých zaujíma teológia. Sledovaním budete vedieť o ďalších konferenciách. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1942 => [
            'name' => 'Worshipster',
            'body' => [
                0 => 'Worshipster je kresťanský web s obchodom a blogom. Jeho heslo je Krása, ktorá chváli Boha. Spája umelcov a tvorcov, ktorých spája viera.',
                1 => 'Organizuje Worship market v Dome Quo Vadis v Bratislave. Na jednom mieste sa stretávajú umelci, podnikatelia a tvorcovia z kresťanského prostredia. Podujatie má už niekoľko ročníkov.',
                2 => 'Kanál je určený rodinám a priaznivcom kresťanskej tvorby. Sledovaním budete vedieť o ďalšom ročníku. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1945 => [
            'name' => 'Rodina sv. Šarbela',
            'body' => [
                0 => 'Rodina sv. Šarbela je spoločenstvo, ktoré sa snaží žiť spiritualitu sv. Šarbela. Pôsobí v Česku aj na Slovensku. Šíri úctu k tomuto libanonskému svätcovi.',
                1 => 'Pripravuje stretnutia s Raymondom Naderom, libanonským katolíkom spojeným s úctou k sv. Šarbelovi. Jedno z nich sa konalo v Katedrále Božského Spasiteľa v Ostrave. Stretnutia spájajú svedectvo a modlitbu.',
                2 => 'Kanál je určený veriacim, ktorým je blízky sv. Šarbel. Sledovaním budete vedieť o ďalších stretnutiach. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1947 => [
            'name' => 'Chválové spoločenstvo',
            'body' => [
                0 => 'Chválové spoločenstvo zo Senca pripravuje pravidelné večery chvál. Stretáva sa v Kostole sv. Mikuláša v Senci. Spája hudbu, modlitbu a slovo.',
                1 => 'Organizuje piatkové chvály so slovom, ktoré vedie Lucia Zambojová. Večery sa konajú v piatok večer. Sú otvorené pre všetkých.',
                2 => 'Kanál je určený veriacim zo Senca a okolia. Sledovaním budete vedieť o ďalších večeroch chvál. Miesto a čas nájdete vždy v detaile podujatia.',
            ],
        ],
    ];

    public function up(): void
    {
        $emails = Schema::hasTable('canal_emails') ? app(CanalEmails::class) : null;
        $nationwide = \App\Models\Municipality::nationwideId();

        foreach (self::PROFILES as $id => $profile) {
            $canal = Canal::withTrashed()->find($id);

            if (! $canal || $canal->trashed() || $canal->merged_into_id !== null || $canal->claimed_at !== null
                || $canal->name !== $profile['name']) {
                continue;
            }

            $changes = [];

            if (isset($profile['body']) && str_contains((string) $canal->body, self::PLACEHOLDER)) {
                $changes['body'] = implode('', array_map(fn (string $p) => '<p>'.e($p).'</p>', $profile['body']));
            }

            foreach (['website', 'phone'] as $field) {
                if (isset($profile[$field]) && trim((string) $canal->$field) === '') {
                    $changes[$field] = $profile[$field];
                }
            }

            if (isset($profile['address']) && in_array($canal->coordinates_source, ['municipality', 'nationwide', 'ai', null], true)) {
                $a = $profile['address'];
                $municipalityOk = $canal->municipality_id === $a['municipality_id']
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

            if ($emails && ! empty($profile['emails'])) {
                $emails->discover($canal->fresh(), $profile['emails'], 'manual');
                $emails->refresh($canal->fresh());
            }
        }
    }

    public function down(): void
    {
        // Doplnené údaje sa nevracajú.
    }
};
