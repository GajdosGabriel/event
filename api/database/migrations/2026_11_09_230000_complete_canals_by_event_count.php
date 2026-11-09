<?php

use App\Models\Canal;
use App\Services\Canals\CanalEmails;
use App\Services\SystemLog\Recorder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Piata dávka (november 2026): 200 kanálov podľa počtu akcií — dorovnanie
 * všetkého, čo obsahuje formulár kanála. Všeobecné jednoodsekové popisy
 * (staršie AI texty bez overených faktov) sa prepíšu na tri odseky podľa
 * podujatí a webu; bohatým dvojodsekovým popisom sa len pridá tretí odsek.
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
    /** @var array<int, array<string, mixed>> */
    private const PROFILES = [
        19 => [
            'name' => 'Ekumenický výbor ECAV na Slovensku',
            'address' => [
                'street' => 'Palisády 46',
                'postcode' => '811 06',
                'municipality_id' => 242,
                'latitude' => 48.148712,
                'longitude' => 17.105441,
            ],
        ],
        29 => [
            'name' => 'Bratislavská eparchia',
            'phone' => '0911 755 577',
        ],
        32 => [
            'name' => 'Farnosť Nitra – Kalvária',
            'address' => [
                'street' => 'Kalvária 1',
                'postcode' => '949 01',
                'municipality_id' => 2333,
                'latitude' => 48.2986753,
                'longitude' => 18.0918021,
            ],
        ],
        67 => [
            'name' => 'Košická eparchia',
            'address' => [
                'street' => 'Dominikánske námestie 2/A',
                'postcode' => '040 01',
                'municipality_id' => 1565,
                'latitude' => 48.7208139,
                'longitude' => 21.2532964,
            ],
        ],
        73 => [
            'name' => 'Občianske združenie Katarínka',
            'phone' => '0910 542 053',
        ],
        74 => [
            'name' => 'Sociálna subkomisia Teologickej komisie KBS',
            'address' => [
                'street' => 'Kapitulská 11',
                'postcode' => '814 99',
                'municipality_id' => 242,
                'latitude' => 48.143312,
                'longitude' => 17.1052033,
            ],
        ],
        79 => [
            'name' => 'Občianske združenie Národný pochod za život',
            'address' => [
                'street' => 'Svoradova 13',
                'postcode' => '811 03',
                'municipality_id' => 242,
                'latitude' => 48.1453803,
                'longitude' => 17.1005793,
            ],
        ],
        80 => [
            'name' => 'Mesto Bardejov – oddelenie kultúry',
            'address' => [
                'street' => 'Radničné námestie 21',
                'postcode' => '085 01',
                'municipality_id' => 80,
                'latitude' => 49.2917001,
                'longitude' => 21.2764029,
            ],
        ],
        85 => [
            'name' => 'Katolícka univerzita v Ružomberku',
            'emails' => [
                0 => [
                    'email' => 'info@ku.sk',
                    'found_on' => 'http://www.ku.sk',
                ],
            ],
            'address' => [
                'street' => 'Hrabovská cesta 1A',
                'postcode' => '034 01',
                'municipality_id' => 3053,
                'latitude' => 49.0824185,
                'longitude' => 19.2795441,
            ],
        ],
        89 => [
            'name' => 'Farnosť Gaboltov',
            'emails' => [
                0 => [
                    'email' => 'gaboltov@abuke.sk',
                    'found_on' => 'https://gaboltov.rimkat.sk',
                ],
            ],
        ],
        97 => [
            'name' => 'Košická arcidiecéza',
            'address' => [
                'street' => 'Hlavná 28',
                'postcode' => '041 83',
                'municipality_id' => 1565,
                'latitude' => 48.7197688,
                'longitude' => 21.2574497,
            ],
        ],
        99 => [
            'name' => 'Rádio Mária Slovensko',
            'phone' => '0901 909 919',
            'address' => [
                'street' => 'Mlynské nivy 73',
                'postcode' => '821 05',
                'municipality_id' => 242,
                'latitude' => 48.1450603,
                'longitude' => 17.1550548,
            ],
        ],
        135 => [
            'name' => 'M-Aréna',
            'address' => [
                'street' => 'Švábska 22',
                'postcode' => '080 05',
                'municipality_id' => 2822,
                'latitude' => 48.9748736,
                'longitude' => 21.2619971,
            ],
        ],
        138 => [
            'name' => 'farnosť Ladce',
            'address' => [
                'street' => 'Farská 151/1',
                'postcode' => '018 63',
                'municipality_id' => 1769,
                'latitude' => 49.0359934,
                'longitude' => 18.2879554,
            ],
        ],
        146 => [
            'name' => 'Rímskokatolícka farnosť sv. Mikuláša',
            'address' => [
                'street' => 'Hlavná 81',
                'postcode' => '080 01',
                'municipality_id' => 2822,
                'latitude' => 48.9979574,
                'longitude' => 21.2390791,
            ],
        ],
        149 => [
            'name' => 'IKONY OZ',
            'address' => [
                'street' => 'M. R. Štefánika 33',
                'postcode' => '010 01',
                'municipality_id' => 4194,
                'latitude' => 49.2230027,
                'longitude' => 18.7478826,
            ],
        ],
        150 => [
            'name' => 'Centrum pomoci pre rodinu - Trnava',
            'address' => [
                'street' => 'Štefánikova 46',
                'postcode' => '917 01',
                'municipality_id' => 3596,
                'latitude' => 48.3780604,
                'longitude' => 17.5850727,
            ],
        ],
        151 => [
            'name' => 'Občianske združenie Nenápadní hrdinovia',
            'emails' => [
                0 => [
                    'email' => 'info@november89.eu',
                    'found_on' => 'http://www.november89.eu/kontakt/',
                ],
            ],
            'address' => [
                'street' => 'Robotnícka 7',
                'postcode' => '831 03',
                'municipality_id' => 242,
                'latitude' => 48.1615799,
                'longitude' => 17.1289368,
            ],
        ],
        157 => [
            'name' => 'Slovenský skauting',
            'phone' => '0948 310 100',
            'address' => [
                'street' => 'Mokrohájska cesta 6',
                'postcode' => '841 04',
                'municipality_id' => 242,
                'latitude' => 48.1751285,
                'longitude' => 17.0694284,
            ],
        ],
        161 => [
            'name' => 'Rehoľa menších bratov (františkáni)',
            'address' => [
                'street' => 'Františkánska 1',
                'postcode' => '917 01',
                'municipality_id' => 3596,
                'latitude' => 48.3771347,
                'longitude' => 17.5834414,
            ],
        ],
        167 => [
            'name' => 'Farnosť Rajecká Lesná',
            'phone' => '+421 909 266 251',
        ],
        195 => [
            'name' => 'Komunita Blahoslavenstiev',
            'phone' => '0948 447 425',
        ],
        200 => [
            'name' => 'Trenčín - Centrum pre rodinu',
            'address' => [
                'street' => 'Farská 12',
                'postcode' => '911 01',
                'municipality_id' => 3592,
                'latitude' => 48.8939785,
                'longitude' => 18.041562,
            ],
        ],
        227 => [
            'name' => 'Rožňavská diecéza',
            'address' => [
                'street' => 'Námestie baníkov 20',
                'postcode' => '048 01',
                'municipality_id' => 3014,
                'latitude' => 48.6626556,
                'longitude' => 20.5339558,
            ],
        ],
        264 => [
            'name' => 'Sestry saleziánky',
            'phone' => '+421 902 336 805',
            'address' => [
                'street' => 'Kremnická 17',
                'postcode' => '851 01',
                'municipality_id' => 242,
                'latitude' => 48.126324,
                'longitude' => 17.0924856,
            ],
        ],
        266 => [
            'name' => 'Rímskokatolícky farský úrad v Topoľčiankach',
            'address' => [
                'street' => 'Hlavná 139',
                'postcode' => '951 93',
                'municipality_id' => 3560,
                'latitude' => 48.4200539,
                'longitude' => 18.4118694,
            ],
        ],
        269 => [
            'name' => 'Bratislavská arcidiecézna charita',
            'address' => [
                'street' => 'Krasinského 6',
                'postcode' => '821 04',
                'municipality_id' => 251,
                'latitude' => 48.1663554,
                'longitude' => 17.1621764,
            ],
        ],
        277 => [
            'name' => 'Arcidiecézne centrum pre rodinu v Košiciach',
            'phone' => '0918 855 208',
            'address' => [
                'street' => 'Hlavná 89',
                'postcode' => '040 01',
                'municipality_id' => 1565,
                'latitude' => 48.7243096,
                'longitude' => 21.2570178,
            ],
        ],
        279 => [
            'name' => 'Farnosť Levoča',
            'address' => [
                'street' => 'Námestie Majstra Pavla 52',
                'postcode' => '054 01',
                'municipality_id' => 1831,
                'latitude' => 49.026695,
                'longitude' => 20.5885575,
            ],
        ],
        290 => [
            'name' => 'Ekumenická rada cirkví v SR',
            'address' => [
                'street' => 'Palisády 48',
                'postcode' => '811 06',
                'municipality_id' => 242,
                'latitude' => 48.1486843,
                'longitude' => 17.1056785,
            ],
        ],
        301 => [
            'name' => 'Arcidiecézna charita Košice',
            'address' => [
                'street' => 'Bočná 2',
                'postcode' => '040 01',
                'municipality_id' => 1565,
                'latitude' => 48.7194722,
                'longitude' => 21.2567313,
            ],
        ],
        302 => [
            'name' => 'Spoločenstvo Extrémnej krížovej cesty (EKC) na Slovensku',
            'address' => [
                'street' => 'SNP 528',
                'postcode' => '086 33',
                'municipality_id' => 4095,
                'latitude' => 49.3714089,
                'longitude' => 21.3085998,
            ],
        ],
        328 => [
            'name' => 'Gréckokatolícka cirkev v Sabinove',
            'address' => [
                'street' => 'Prešovská 1910/2A',
                'postcode' => '083 01',
                'municipality_id' => 3066,
                'latitude' => 49.0998895,
                'longitude' => 21.1037115,
            ],
        ],
        347 => [
            'name' => 'Nitrianske biskupstvo',
            'address' => [
                'street' => 'Samova 14',
                'postcode' => '949 01',
                'municipality_id' => 2333,
                'latitude' => 48.3160188,
                'longitude' => 18.0882523,
            ],
        ],
        349 => [
            'name' => 'Rodinné spoločenstvo FATIMA, o.z',
            'phone' => '0903 608 231',
        ],
        437 => [
            'name' => 'Občianske združenie Dobré srdce – Good heart',
            'phone' => '+421 903 600 477',
            'address' => [
                'street' => 'Krakovská 7',
                'postcode' => '040 11',
                'municipality_id' => 1565,
                'latitude' => 48.7027631,
                'longitude' => 21.2556997,
            ],
        ],
        439 => [
            'name' => 'sestry redemptoristky',
            'phone' => '0908 644 234',
            'address' => [
                'street' => 'Kláštorná 4',
                'postcode' => '060 01',
                'municipality_id' => 1421,
                'latitude' => 49.1383033,
                'longitude' => 20.4391573,
            ],
        ],
        441 => [
            'name' => 'Ordinariát Ozbrojených síl a ozbrojených zborov SR',
            'phone' => '+421 960 303 087',
            'address' => [
                'street' => 'Námestie generála Viesta 2',
                'postcode' => '832 47',
                'municipality_id' => 242,
                'latitude' => 48.1654301,
                'longitude' => 17.1303043,
            ],
        ],
        467 => [
            'name' => 'Tlačová kancelária Konferencie biskupov Slovenska (TK KBS)',
            'address' => [
                'street' => 'Kapitulská 11',
                'postcode' => '814 99',
                'municipality_id' => 242,
                'latitude' => 48.143312,
                'longitude' => 17.1052033,
            ],
        ],
        498 => [
            'name' => 'Slovenská katolícka misia v Ríme',
            'phone' => '+421 904 856 377',
        ],
        522 => [
            'name' => 'Diecézny katechetický úrad Spišskej diecézy',
            'phone' => '+421 909 250 261',
            'address' => [
                'street' => 'Levočská 10',
                'postcode' => '052 01',
                'municipality_id' => 3240,
                'latitude' => 48.9455607,
                'longitude' => 20.5691556,
            ],
        ],
        551 => [
            'name' => 'Farnosť Bošany',
            'phone' => '0909 250 370',
        ],
        568 => [
            'name' => 'Trnavská arcidiecéza',
            'address' => [
                'street' => 'Jána Hollého 10',
                'postcode' => '917 01',
                'municipality_id' => 3596,
                'latitude' => 48.3789292,
                'longitude' => 17.5891326,
            ],
        ],
        573 => [
            'name' => 'Arcidiecézne centrum pre mládež (ACM)',
            'address' => [
                'street' => 'Rooseveltova 12',
                'postcode' => '040 01',
                'municipality_id' => 1565,
                'latitude' => 48.7188077,
                'longitude' => 21.2616,
            ],
        ],
        593 => [
            'name' => 'spoločenstvo Angelus',
            'address' => [
                'street' => 'Jezuitská 143/6',
                'postcode' => '010 01',
                'municipality_id' => 4194,
                'latitude' => 49.2231857,
                'longitude' => 18.7382253,
            ],
        ],
        611 => [
            'name' => 'Taliansky kultúrny inštitút',
            'address' => [
                'street' => 'Kapucínska 7',
                'postcode' => '811 03',
                'municipality_id' => 242,
                'latitude' => 48.1445923,
                'longitude' => 17.1044111,
            ],
        ],
        627 => [
            'name' => 'Bratia Augustiniáni',
            'phone' => '+421 948 257 086',
            'address' => [
                'street' => 'Herlianska 52',
                'postcode' => '040 14',
                'municipality_id' => 1565,
                'latitude' => 48.7291412,
                'longitude' => 21.2937723,
            ],
            'body' => [
                0 => 'Bratia augustiniáni sú rehoľníci, ktorí neúnavne hľadajú Boha spoločne. Žijú v modlitbe, priateľstve a službe Cirkvi podľa ideálu sv. Augustína. Na Slovensku pôsobia aj v Košiciach.',
                1 => 'Organizujú festival Augfest a tradičnú pešiu púť z Prešova do Gaboltova. V Košiciach pozývajú na odpustovú slávnosť ku cti sv. Rity z Cascie. Podujatia spájajú modlitbu, spoločenstvo a putovanie.',
                2 => 'Kanál je určený veriacim, pútnikom a mladým. Sledovaním budete vedieť o pútiach a slávnostiach augustiniánov. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        635 => [
            'name' => 'Misijné dielo detí',
            'address' => [
                'street' => 'Lazaretská 32',
                'postcode' => '811 09',
                'municipality_id' => 242,
                'latitude' => 48.1441735,
                'longitude' => 17.1207912,
            ],
            'append' => 'Dielo pripravuje aktivity pre deti, napríklad Májové kvetinky, a pozýva do komunity misijných samaritánov. Kanál je určený deťom, katechétom a farnostiam. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
        ],
        639 => [
            'name' => 'Komisia pre mládež Bratislavskej arcidiecézy',
            'address' => [
                'street' => 'Špitálska 7',
                'postcode' => '814 92',
                'municipality_id' => 242,
                'latitude' => 48.1455898,
                'longitude' => 17.1132962,
            ],
            'append' => 'Komisia pripravuje diecézne stretnutie mládeže a programy vo Vinici, napríklad Veľkú noc pre mladých. Kanál je určený mladým a animátorom z Bratislavskej arcidiecézy. Registráciu a podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
        ],
        643 => [
            'name' => 'Farnosť Banská Bystrica-mesto',
            'append' => 'Farnosť pripravuje aj koncerty a spomienkové podujatia, napríklad k 200. výročiu narodenia Jána Egryho. Kanál je určený veriacim z Banskej Bystrice a návštevníkom katedrály. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
        ],
        647 => [
            'name' => 'Farnosť Kluknava',
            'body' => [
                0 => 'Farnosť Kluknava je rímskokatolícka farnosť v obci Kluknava na Spiši. Zabezpečuje bohoslužby a vysluhovanie sviatostí. Je známa tradičnou púťou k sv. Anne.',
                1 => 'Každoročne v júli pozýva na púť k sv. Anne, ktorá spája celé generácie. Púť sa koná pri farskom kostole a láka veriacich z okolia. Súčasťou sú sväté omše a modlitby.',
                2 => 'Kanál je určený veriacim z Kluknavy a okolia. Sledovaním budete vedieť o termíne púte a ďalších slávnostiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        652 => [
            'name' => 'Trnavská arcidiecézna charita',
            'body' => [
                0 => 'Trnavská arcidiecézna charita pomáha ľuďom v núdzi na území Trnavskej arcidiecézy. Prevádzkuje sociálne služby a centrá pomoci. Zapája do pomoci aj dobrovoľníkov.',
                1 => 'Pripravuje štedrú večeru pre osamelých a ľudí bez domova v Trnave. Organizuje bazáre oblečenia a hračiek pre klientov a otvára dvere pri Svetovom dni sociálnej práce. Podujatia spájajú pomoc a stretnutie.',
                2 => 'Kanál je určený darcom, dobrovoľníkom a všetkým, ktorí chcú pomáhať. Sledovaním budete vedieť o zbierkach a akciách charity. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        669 => [
            'name' => 'Depaul Slovensko',
            'address' => [
                'street' => 'Kapitulská 308/18',
                'postcode' => '814 14',
                'municipality_id' => 242,
                'latitude' => 48.1431837,
                'longitude' => 17.1050069,
            ],
            'append' => 'Depaul pripravuje aj verejné podujatia, napríklad charitatívne otužovanie Dobroborci či výstavu fotografií Nebývam, ale žijem. Kanál je určený všetkým, ktorí chcú pomôcť ľuďom bez domova. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
        ],
        682 => [
            'name' => 'Farnosť Sedembolestnej Panny Márie',
            'body' => [
                0 => 'Farnosť Sedembolestnej Panny Márie v Záhradnom je rímskokatolícka farnosť v Prešovskom okrese. Zabezpečuje bohoslužby a vysluhovanie sviatostí. Jej kostol je zasvätený Sedembolestnej Panne Márii.',
                1 => 'Každoročne slávi odpustovú slávnosť Sedembolestnej Panny Márie. Pripravuje aj bohatý program na Turíce s Dňom odprosenia a turíčnou vigíliou. Slávnosti spájajú farníkov aj hostí.',
                2 => 'Kanál je určený veriacim zo Záhradného a okolia. Sledovaním budete vedieť o slávnostiach farnosti. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        688 => [
            'name' => 'Spoločenstvo Ladislava Hanusa',
            'append' => 'Spoločenstvo pripravuje festivaly Hanusove dni v Bratislave, Košiciach a Ružomberku a podieľa sa aj na Národnom pochode za život. Kanál je určený mladým, študentom a všetkým, ktorých zaujíma kresťanstvo vo verejnom živote. Program nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
        ],
        695 => [
            'name' => 'Farnosť Považská Bystrica-Rozkvet (Saletíni)',
            'body' => [
                0 => 'Farnosť Považská Bystrica – Rozkvet spravujú misionári saletíni. Pôsobí na sídlisku Rozkvet v Považskej Bystrici. Ponúka bohoslužby aj duchovné programy.',
                1 => 'Saletíni pripravujú novény pred Zoslaním Ducha Svätého so zamysleniami. Organizujú aj pôstne obnovy, napríklad o Ježišových blahoslavenstvách. Zamyslenia sú dostupné aj online.',
                2 => 'Kanál je určený veriacim z Považskej Bystrice a priaznivcom saletínov. Sledovaním budete vedieť o obnovách a novénach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        697 => [
            'name' => 'Národný trust, n.o',
            'phone' => '0907 296 724',
            'address' => [
                'street' => 'Hlboká cesta 9',
                'postcode' => '811 04',
                'municipality_id' => 242,
                'latitude' => 48.1556426,
                'longitude' => 17.1019257,
            ],
        ],
        703 => [
            'name' => 'Nadácia Danice Olexovej – Baboon',
            'address' => [
                'street' => 'Malokrasňanská 10195/6',
                'postcode' => '831 54',
                'municipality_id' => 242,
                'latitude' => 48.1921648,
                'longitude' => 17.1362638,
            ],
            'append' => 'Nadácia pripravuje africký piknik v Bratislave, aukcie umeleckých diel a udeľuje ocenenie Skutočný človek. Kanál je určený všetkým, ktorí chcú podporiť vzdelávanie v Afrike. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
        ],
        717 => [
            'name' => 'Banskobystrické biskupstvo',
            'address' => [
                'street' => 'Námestie SNP 19',
                'postcode' => '975 90',
                'municipality_id' => 73,
                'latitude' => 48.7359372,
                'longitude' => 19.1447913,
            ],
            'body' => [
                0 => 'Banskobystrické biskupstvo spravuje Banskobystrickú diecézu. V roku 2026 si pripomína 250 rokov od jej vzniku. Pripravuje celodiecézne slávnosti a púte.',
                1 => 'K 250. výročiu diecézy pripravilo púť rodín na Staré Hory, koncert a novú knihu. Pozýva aj na Ankinu sobotu s relikviami sv. Františka vo Vysokej nad Uhom. Podujatia spájajú veriacich z celej diecézy.',
                2 => 'Kanál je určený veriacim Banskobystrickej diecézy. Sledovaním budete vedieť o diecéznych slávnostiach a pútiach. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        726 => [
            'name' => 'Farnosť Kežmarok',
            'body' => [
                0 => 'Farnosť Kežmarok je rímskokatolícka farnosť v Spišskej diecéze. Pôsobí v historickom meste Kežmarok. Okrem bohoslužieb ponúka aj programy pre mladých.',
                1 => 'Pripravuje napríklad duchovnú obnovu na rozlišovanie povolania. Slávila aj ďakovnú svätú omšu za pontifikát pápeža Františka. Zapája mladých do celosvetových stretnutí, ako boli Svetové dni mládeže.',
                2 => 'Kanál je určený veriacim z Kežmarku a okolia. Sledovaním budete vedieť o obnovách a slávnostiach farnosti. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        727 => [
            'name' => 'OZ Slobodní 28+',
            'body' => [
                0 => 'OZ Slobodní 28+ je občianske združenie pre slobodných kresťanov vo veku 28 a viac rokov. Pripravuje spoločné dovolenky a pobyty. Spája oddych so spoločenstvom.',
                1 => 'Organizuje každoročné Chorvátske leto vo Vodiciach. Pobyt trvá približne desať dní a spája more, výlety a duchovný program. Prihlasovanie sa otvára na jar.',
                2 => 'Kanál je určený slobodným nad 28 rokov. Sledovaním budete vedieť o nových pobytoch a termínoch. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        728 => [
            'name' => 'farnosť Kráľovnej rodiny',
            'body' => [
                0 => 'Farnosť Kráľovnej rodiny je rímskokatolícka farnosť v Bratislave. Okrem bohoslužieb otvára aj dialóg Cirkvi s umením. Pripravuje stretnutia pre veriacich aj umelcov.',
                1 => 'Hostila diskusie z cyklu o Cirkvi a umení, napríklad o pravdivosti v sakrálnom umení či o tvorivosti s biskupom Trstenským. Konalo sa tu aj diecézne večeradlo Mariánskeho kňazského hnutia. Stretnutia sú otvorené verejnosti.',
                2 => 'Kanál je určený veriacim z Bratislavy a záujemcom o umenie. Sledovaním budete vedieť o ďalších diskusiách a stretnutiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        732 => [
            'name' => 'Dom kultúry v Námestove',
            'body' => [
                0 => 'Dom kultúry v Námestove je kultúrne centrum mesta. Pripravuje koncerty, divadlá a výstavy. Organizuje aj celoslovenské súťaže.',
                1 => 'Vyhlasuje celoslovenskú výtvarnú súťaž detí a mládeže Gorazdovo výtvarné Námestovo, ktorá má už tridsať ročníkov. Súťaž je inšpirovaná odkazom sv. Gorazda. Výstava ocenených prác sa koná v Námestove.',
                2 => 'Kanál je určený obyvateľom Oravy, školám a mladým výtvarníkom. Sledovaním budete vedieť o nových ročníkoch súťaže a podujatiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        745 => [
            'name' => 'Castellum, n. o.',
            'address' => [
                'street' => 'Nám. Jána Pavla II. 1012/7',
                'postcode' => null,
                'municipality_id' => 2333,
                'latitude' => 48.3184475,
                'longitude' => 18.0863993,
            ],
            'body' => [
                0 => 'Castellum, n. o. je nezisková organizácia spojená s Nitrianskym hradom. Sprístupňuje hrad a jeho pamiatky návštevníkom. Spolupracuje s Nitrianskym biskupstvom.',
                1 => 'Na hrade pripravuje výstavy, napríklad kópiu Turínskeho plátna v Bazilike sv. Emeráma. Podieľa sa na Cyrilo-metodskej národnej púti do Nitry. Pozýva aj na púte katedrálneho archidiakonátu.',
                2 => 'Kanál je určený návštevníkom Nitrianskeho hradu a pútnikom. Sledovaním budete vedieť o výstavách a slávnostiach. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        752 => [
            'name' => 'Inštitút Communio',
            'append' => 'Inštitút pripravuje Týždeň kresťanskej kultúry a výstavy v Diecéznom centre v Žiline, napríklad o nenápadných hrdinoch. Kanál je určený veriacim Žilinskej diecézy a všetkým, ktorých zaujíma kresťanská kultúra. Podrobnosti nájdete vždy v detaile podujatia.',
        ],
        755 => [
            'name' => 'Nadácia PRO PATRIA',
            'body' => [
                0 => 'Nadácia PRO PATRIA sa venuje slovenským dejinám a národnému povedomiu. Pripravuje pietne a spomienkové podujatia. Vydáva a predstavuje knihy o histórii.',
                1 => 'Organizovala spomienkovú slávnosť na sv. Metoda v Žiline a pietnu spomienku na Srbskom vojenskom cintoríne. Predstavila aj knihu Antona Hrnka Prechádzky slovenskými dejinami. Podujatia sú otvorené verejnosti.',
                2 => 'Kanál je určený všetkým, ktorých zaujímajú slovenské dejiny. Sledovaním budete vedieť o ďalších spomienkach a prezentáciách. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        767 => [
            'name' => 'Farnosť Teplicka',
            'address' => [
                'street' => 'Dr. Karola Kmeťku 86/44',
                'postcode' => '013 01',
                'municipality_id' => 3527,
                'latitude' => 49.2257531,
                'longitude' => 18.7976355,
            ],
            'append' => 'Farnosť hostí aj diskusné stretnutia z cyklu Umenie a Cirkev, napríklad na tému osamelosti. Kanál je určený veriacim z Tepličky nad Váhom a Mojša aj záujemcom o umenie. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
        ],
        770 => [
            'name' => 'Gymnázium sv. Františka z Assisi',
            'phone' => '0951 806 567',
            'address' => [
                'street' => 'J. M. Hurbana 44',
                'postcode' => '010 01',
                'municipality_id' => 4194,
                'latitude' => 49.2260062,
                'longitude' => 18.7357412,
            ],
            'body' => [
                0 => 'Gymnázium sv. Františka z Assisi je cirkevná stredná škola v Žiline. Vzdeláva a vychováva študentov v kresťanskom duchu. Pripravuje aj súťaže a dni otvorených dverí.',
                1 => 'Vyhlásilo napríklad súťaž k Chválospevu stvorenia sv. Františka. Každoročne pozýva uchádzačov na Deň otvorených dverí. Podujatia približujú život školy.',
                2 => 'Kanál je určený žiakom, rodičom a uchádzačom o štúdium. Sledovaním budete vedieť o dňoch otvorených dverí a súťažiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        771 => [
            'name' => 'Kongregácia Milosrdných sestier sv. Vincenta – Satmárok a Rodina Nepoškvrnenej',
            'phone' => '0917 350 111',
            'body' => [
                0 => 'Kongregácia Milosrdných sestier sv. Vincenta – Satmárok je rehoľné spoločenstvo. K jej duchovnej rodine patrí aj Rodina Nepoškvrnenej. Sestry si pripomínajú svoje významné osobnosti.',
                1 => 'Pripravili spomienky na sestru M. Bernadetu Pánčiovú, vrátane premietania filmu v Ružomberku k jej storočnici. Organizujú aj kultúrne podujatia, napríklad divadelné predstavenie Piaty mušketier. Podujatia sú otvorené verejnosti.',
                2 => 'Kanál je určený priaznivcom sestier a veriacim. Sledovaním budete vedieť o spomienkach a podujatiach kongregácie. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        773 => [
            'name' => 'Farnosť Saletíni',
            'append' => 'Farnosť pozýva napríklad na Saletínsky hodový týždeň a k modlitbovej podpore Adoptuj si kňaza. Kanál je určený veriacim z Považskej Bystrice a priaznivcom saletínov. Podrobnosti nájdete vždy v detaile podujatia.',
        ],
        782 => [
            'name' => 'Gréckokatolícka cirkev na Slovensku',
            'append' => 'Gréckokatolícka cirkev pozýva veriacich k Veľkému pôstu – Štyridsiatnici a k modlitbám za jednotu kresťanov. Kanál je určený gréckokatolíckym veriacim a všetkým, ktorým je blízka byzantská tradícia. Podrobnosti nájdete vždy v detaile podujatia.',
        ],
        794 => [
            'name' => 'Cirkevné konzervatórium v Bratislave',
            'address' => [
                'street' => 'Beňadická 16',
                'postcode' => '851 06',
                'municipality_id' => 242,
                'latitude' => 48.098929,
                'longitude' => 17.1073457,
            ],
            'body' => [
                0 => 'Cirkevné konzervatórium v Bratislave vzniklo 27. apríla 1992. Ponúka študijné odbory hudobno-dramatické umenie, hudba a spev. Pripravuje mladých hudobníkov a hercov.',
                1 => 'Každoročne pozýva uchádzačov na Deň otvorených dverí. Pripravuje koncerty a večery hudby a umeleckého prednesu. Na podujatiach vystupujú študenti a pedagógovia.',
                2 => 'Kanál je určený uchádzačom o štúdium a milovníkom hudby. Sledovaním budete vedieť o koncertoch a dňoch otvorených dverí. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        800 => [
            'name' => 'pezinský chrámový zbor Ad Una Corda',
            'body' => [
                0 => 'Ad Una Corda je chrámový spevácky zbor z Pezinka. Spieva pri liturgii aj na koncertoch. Venuje sa aj benefičnej činnosti.',
                1 => 'Každoročne pripravuje benefičný koncert Cesty k sebe, ktorý má už vyše dvadsať ročníkov a podporuje napríklad detskú psychiatriu. Spieval aj na koncerte Tóny pre Ukrajinu v Kostole Najsvätejšej Trojice. Koncerty spájajú hudbu a pomoc.',
                2 => 'Kanál je určený milovníkom zborovej hudby z Pezinka a okolia. Sledovaním budete vedieť o koncertoch. Termín a miesto nájdete vždy v detaile podujatia.',
            ],
        ],
        809 => [
            'name' => 'Slavistický ústav Jána Stanislava SAV, v.v.i.',
            'emails' => [
                0 => [
                    'email' => 'slavust@savba.sk',
                    'found_on' => 'http://www.slavu.sav.sk',
                ],
            ],
            'address' => [
                'street' => 'Dúbravská cesta 9',
                'postcode' => '841 04',
                'municipality_id' => 242,
                'latitude' => 48.1699512,
                'longitude' => 17.0689236,
            ],
            'body' => [
                0 => 'Slavistický ústav Jána Stanislava SAV je vedecké pracovisko Slovenskej akadémie vied. Venuje sa slovanským jazykom a kultúram. Skúma aj dejiny biblických prekladov.',
                1 => 'Organizuje vedecké podujatia o biblických štúdiách, napríklad v Bardejovských Kúpeľoch. Pripomenul si aj 30. výročie vydania Svätého písma na východe Slovenska. Na podujatiach vystupujú odborníci zo Slovenska aj zo zahraničia.',
                2 => 'Kanál je určený odborníkom, študentom a záujemcom o Bibliu a slavistiku. Sledovaním budete vedieť o konferenciách. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        823 => [
            'name' => 'Katolícke pedagogické a katechetické centrum, n.o',
            'body' => [
                0 => 'Katolícke pedagogické a katechetické centrum (KPKC) podporuje učiteľov náboženstva a katechétov. Pripravuje vzdelávanie a súťaže. Pôsobí pre celé Slovensko.',
                1 => 'Organizuje Letnú miniškolu pre učiteľov katolíckeho náboženstva, celoslovenské kolo biblickej olympiády a výtvarnú súťaž Biblia tvojimi očami. Súťaže sú určené žiakom základných a stredných škôl. Vzdelávanie prebieha v rôznych mestách.',
                2 => 'Kanál je určený učiteľom náboženstva, katechétom a školám. Sledovaním budete vedieť o kurzoch a súťažiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        849 => [
            'name' => 'Vydavateľstvo DON BOSCO',
            'phone' => '0903 962 029',
            'address' => [
                'street' => 'Miletičova 7',
                'postcode' => '821 08',
                'municipality_id' => 242,
                'latitude' => 48.1557119,
                'longitude' => 17.1344389,
            ],
            'append' => 'Vydavateľstvo pripravuje prezentácie kníh, napríklad večer s misionárom pri knihe V službe Šuarom. Kanál je určený čitateľom, rodičom a vychovávateľom. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
        ],
        851 => [
            'name' => 'Centrum konkrétnej pomoci Femina, n.o',
            'phone' => '0910 842 184',
            'address' => [
                'street' => 'Perecká 43',
                'postcode' => '934 05',
                'municipality_id' => 1829,
                'latitude' => 48.2063939,
                'longitude' => 18.6076916,
            ],
            'body' => [
                0 => 'Centrum konkrétnej pomoci Femina pomáha ženám a dievčatám, ktoré sa ocitli v ťažkej životnej situácii. V poradni si riešenia vyberá klientka sama. Pôsobí v Leviciach.',
                1 => 'Pripravuje webináre, napríklad o domácom násilí či o prirodzenom plánovaní rodičovstva. Webináre sú dostupné z celého Slovenska. Prinášajú odborné informácie aj praktické rady.',
                2 => 'Kanál je určený ženám a všetkým, ktorí pomáhajú ženám v núdzi. Sledovaním budete vedieť o nových webinároch. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        856 => [
            'name' => 'Maltézska pomoc Slovensko – centrum Nitra',
            'body' => [
                0 => 'Maltézska pomoc Slovensko – centrum Nitra je súčasťou dobrovoľníckej organizácie Maltézskej pomoci. Pomáha ľuďom v núdzi a pripravuje charitatívne akcie. Pôsobí v Nitre.',
                1 => 'Pripravuje charitatívny punč a stánok na vianočných trhoch. Pozýva aj posielať úmysly do Lúrd, kam Maltézska pomoc každoročne putuje. Výťažok akcií podporuje ľudí v núdzi.',
                2 => 'Kanál je určený dobrovoľníkom a podporovateľom z Nitry. Sledovaním budete vedieť o charitatívnych akciách. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        893 => [
            'name' => 'KZ Sprevádzajúci – pútnické bratstvo',
            'body' => [
                0 => 'KZ Sprevádzajúci je kresťanské pútnické bratstvo z Bratislavy. Pripravuje púte a pomáha pri starostlivosti o Bratislavskú Kalváriu. Spája pútnikov všetkých vekových kategórií.',
                1 => 'Pravidelne organizuje pešiu púť Bratislava – Kačín – Marianka. Púť vedie do najstaršieho mariánskeho pútnického miesta na Slovensku. Spája modlitbu a putovanie prírodou.',
                2 => 'Kanál je určený pútnikom z Bratislavy a okolia. Sledovaním budete vedieť o termínoch pútí. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        902 => [
            'name' => 'Edukačno-misijné centrum ECAV',
            'append' => 'Centrum pripravuje napríklad Školu cirkevných hudobníkov, rozhovory troch generácií či oslavy výročí osobností, ako bol Svetozár Hurban Vajanský. Kanál je určený evanjelickým zborom aj všetkým, ktorí hľadajú vzdelávanie a duchovný rast. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
        ],
        903 => [
            'name' => 'Spoločenstvo kresťanských fotografov Človek a Viera',
            'append' => 'Spoločenstvo pripravuje fotografické workshopy a pravidelne hľadá nových fotografov. Kanál je určený fotografom a farnostiam, ktoré chcú zachytiť život Cirkvi. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
        ],
        926 => [
            'name' => 'Konferencia biskupov Slovenska – Rada pre vedu, vzdelanie a kultúru',
            'body' => [
                0 => 'Rada pre vedu, vzdelanie a kultúru je jednou z rád Konferencie biskupov Slovenska. Zastrešuje spoluprácu Cirkvi s akademickou a kultúrnou obcou. Udeľuje ceny Fides et Ratio a Fra Angelico.',
                1 => 'Prináša aj informácie o dianí v Konferencii biskupov Slovenska, napríklad o plenárnych zasadnutiach či pastierskych listoch. Organizuje stretnutia s ľuďmi z vedy a kultúry. Pripravuje dokumenty k týmto oblastiam.',
                2 => 'Kanál je určený ľuďom z vedy, školstva a kultúry. Sledovaním budete vedieť o oceneniach a stretnutiach. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        940 => [
            'name' => 'MIVA Slovensko',
            'address' => [
                'street' => 'Lazaretská 32',
                'postcode' => '811 09',
                'municipality_id' => 242,
                'latitude' => 48.1441735,
                'longitude' => 17.1207912,
            ],
            'body' => [
                0 => 'MIVA Slovensko je humanitárna organizácia Katolíckej cirkvi. Pomáha misionárom zabezpečiť dopravné prostriedky. Šíri myšlienku vďaky za bezpečné cesty.',
                1 => 'Každoročne v júli pripravuje Národný týždeň sv. Krištofa. Vodiči môžu za každý bezpečne prejdený kilometer prispieť na vozidlá pre misie. Súčasťou sú aj požehnania vodičov a vozidiel.',
                2 => 'Kanál je určený vodičom, farnostiam a priaznivcom misií. Sledovaním budete vedieť o Týždni sv. Krištofa. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        998 => [
            'name' => 'Bazilika Sedembolestnej Panny Márie v Šaštíne',
            'phone' => '0903 160 904',
            'emails' => [
                0 => [
                    'email' => 'spravca@bazilika.sk',
                    'found_on' => 'https://www.bazilika.sk/kontakt/bazilika',
                ],
            ],
            'body' => [
                0 => 'Bazilika Sedembolestnej Panny Márie v Šaštíne-Strážach je národnou svätyňou Slovenska. Patrónka Slovenska tu má svoje hlavné pútnické miesto. Baziliku spravujú saleziáni.',
                1 => 'Každoročne v septembri sa tu koná Národná púť k Sedembolestnej Panne Márii. Konajú sa tu aj ďalšie púte, napríklad Turíčna púť a púte ružencových bratstiev. Program spája sväté omše, modlitbu a spoločenstvo.',
                2 => 'Kanál je určený pútnikom z celého Slovenska. Sledovaním budete vedieť o programe pútí. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1004 => [
            'name' => 'farnosť Dubová',
            'body' => [
                0 => 'Farnosť Dubová je rímskokatolícka farnosť. Okrem bohoslužieb pripravuje programy pre mladých a rodiny. Ponúka aj duchovné obnovy.',
                1 => 'Pripravila víkendovú duchovnú obnovu pre mladých Dubovský Lisabon nad textami pápeža Františka. Organizuje aj prednášky o svätých manželoch a rodičoch, napríklad o sv. Gianne a bl. Karolovi Habsburskom. Program je otvorený aj pre hostí.',
                2 => 'Kanál je určený mladým a rodinám z Dubovej a okolia. Sledovaním budete vedieť o obnovách a prednáškach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1010 => [
            'name' => 'Katedra hudby na Pedagogickej fakulte Katolíckej univerzity v Ružomberku',
            'body' => [
                0 => 'Katedra hudby Pedagogickej fakulty Katolíckej univerzity v Ružomberku pripravuje učiteľov hudby a cirkevných hudobníkov. Rozvíja aj koncertnú činnosť. Pôsobí v Ružomberku.',
                1 => 'Organizuje letný kurz pre chrámových organistov a kantorov. Pedagógovia a študenti koncertujú aj mimo Ružomberka, napríklad u jezuitov v Bratislave. Kurzy sú určené aj hudobníkom z farností.',
                2 => 'Kanál je určený organistom, kantorom a študentom hudby. Sledovaním budete vedieť o kurzoch a koncertoch. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1039 => [
            'name' => 'Tím speed dating katRande',
            'body' => [
                0 => 'Tím speed dating katRande pripravuje zoznamovacie večery pre slobodných katolíkov. Patrí ku katolíckej zoznamovacej platforme katRande. Pôsobí v rôznych mestách Slovenska.',
                1 => 'Organizuje speed dating napríklad v Bratislave, Trenčíne a Prešove. Večery sú rozdelené podľa vekových kategórií, napríklad 26 – 39 rokov. Na podujatia je potrebná registrácia.',
                2 => 'Kanál je určený slobodným katolíkom. Sledovaním budete vedieť o ďalších večeroch. Registráciu nájdete vždy v detaile podujatia.',
            ],
        ],
        1053 => [
            'name' => 'GRÉCKOKATOLÍCKE SPOLOČENSTVO VO FARNOSTI SEDLISKÁ',
            'body' => [
                0 => 'Gréckokatolícke spoločenstvo vo farnosti Sedliská pôsobí v okrese Vranov nad Topľou. Pripravuje pútnické zájazdy pre veriacich. Púte organizuje viackrát do roka.',
                1 => 'Organizuje mariánske púte do Medžugoria, Ostrihomu, Zadaru a Visegrádu, do Máriapócsu v Maďarsku aj silvestrovské púte. Púte spájajú modlitbu, sväté omše a spoznávanie miest. Sú otvorené aj pre veriacich z okolia.',
                2 => 'Kanál je určený pútnikom z východného Slovenska. Sledovaním budete vedieť o nových pútiach. Cenu a program nájdete vždy v detaile podujatia.',
            ],
        ],
        1055 => [
            'name' => 'Teatro Colorato',
            'phone' => '0948 017 816',
        ],
        1072 => [
            'name' => 'Katolícke Rádio LUMEN',
            'phone' => '0901 909 901',
            'address' => [
                'street' => 'Kapitulská 2',
                'postcode' => '974 01',
                'municipality_id' => 73,
                'latitude' => 48.7353654,
                'longitude' => 19.1465553,
            ],
            'body' => [
                0 => 'Katolícke Rádio LUMEN je rádio pre celú rodinu. Vysiela z Banskej Bystrice pre celé Slovensko. Prináša duchovné programy, prenosy a spravodajstvo.',
                1 => 'Pripravuje rozhlasové púte, napríklad do Krakova, a Deň modlitieb a spoločenstva na Starých Horách. Prináša prenosy z významných udalostí, ako je Národný pochod za život. Poslucháči sa môžu podujatí zúčastniť osobne aj cez vysielanie.',
                2 => 'Kanál je určený poslucháčom a priaznivcom rádia. Sledovaním budete vedieť o pútiach a prenosoch. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1078 => [
            'name' => 'Poradenské centrum pre rodiny Family Garden',
            'address' => [
                'street' => 'Pavlovičova 3',
                'postcode' => '821 04',
                'municipality_id' => 242,
                'latitude' => 48.1659768,
                'longitude' => 17.1665101,
            ],
            'body' => [
                0 => 'Family Garden je poradenské centrum pre rodiny v Bratislave. Pomáha rodinám v náročných situáciách. Ponúka kurzy a vzdelávanie.',
                1 => 'Pripravuje kurz ASAP – Ako sprevádzať a pomáhať. Organizuje aj stretnutia, napríklad Mám doma Sväté písmo – a čo ďalej? Kurzy sú určené rodičom aj pomáhajúcim profesiám.',
                2 => 'Kanál je určený rodinám a ľuďom, ktorí sprevádzajú iných. Sledovaním budete vedieť o nových kurzoch. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1086 => [
            'name' => 'Rada KBS pre históriu',
            'body' => [
                0 => 'Rada KBS pre históriu je poradným orgánom Konferencie biskupov Slovenska. Venuje sa cirkevným dejinám a ich pripomínaniu. Spolupracuje s historikmi a diecézami.',
                1 => 'Pripravila konferenciu v Nitre k nedožitej storočnici kardinála Jána Chryzostoma Korca. Konferencie spájajú historikov, teológov a pamätníkov. Približujú osobnosti cirkevných dejín.',
                2 => 'Kanál je určený historikom a všetkým, ktorých zaujímajú cirkevné dejiny. Sledovaním budete vedieť o konferenciách. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1124 => [
            'name' => 'Františkánska rodina na Slovensku',
            'body' => [
                0 => 'Františkánska rodina na Slovensku spája rehole a spoločenstvá, ktoré žijú odkazom sv. Františka z Assisi. Patria do nej bratia, sestry aj laici. Pripomína si františkánske jubileá.',
                1 => 'Pripomína si 800. výročie smrti sv. Františka, ktoré vrcholí v Assisi aj na Slovensku. Spolupracovala na konferencii V šľapajach svätého Františka na Katolíckej univerzite. Podujatia približujú františkánsku spiritualitu.',
                2 => 'Kanál je určený františkánskej rodine a jej priateľom. Sledovaním budete vedieť o jubilejných podujatiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1138 => [
            'name' => 'Farnosť Korňa',
            'body' => [
                0 => 'Farnosť Korňa je rímskokatolícka farnosť na Kysuciach v Žilinskej diecéze. Na jej území je pútnické miesto Živčáková. Púte sem prichádzajú z celého Slovenska.',
                1 => 'Pripravuje jesenné a víkendové púte na Živčákovú pri Turzovke. Oslávila aj 30. výročie posviacky kaplnky na hore. Púte spájajú sväté omše, modlitbu a putovanie.',
                2 => 'Kanál je určený pútnikom a veriacim z Kysúc. Sledovaním budete vedieť o termínoch pútí. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1147 => [
            'name' => '40 dní za život',
            'body' => [
                0 => '40 dní za život je najväčšia medzinárodne koordinovaná pro-life mobilizácia. Spája modlitbu, pôst a pokojnú prítomnosť. Na Slovensku prebieha vo viacerých mestách.',
                1 => 'Kampaň trvá 40 dní na jar a na jeseň. Účastníci sa modlia vo vigíliách a zapájajú sa do pôstu za ochranu života. Na Slovensku sa koná napríklad v dvoch mestách súčasne.',
                2 => 'Kanál je určený všetkým, ktorí sa chcú zapojiť do modlitby za život. Sledovaním budete vedieť o termínoch kampane. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1163 => [
            'name' => 'Školský výbor ECAV',
            'body' => [
                0 => 'Školský výbor ECAV sa venuje cirkevnému školstvu Evanjelickej cirkvi a. v. na Slovensku. Podporuje evanjelické školy a ich pedagógov. Pripravuje konferencie a vzdelávanie.',
                1 => 'Organizuje školské konferencie Hodnoty vo vzdelávaní. Pripravuje aj vzdelávanie pre pedagógov, napríklad na tému nenásilnej komunikácie. Podujatia sú určené učiteľom a vedeniu škôl.',
                2 => 'Kanál je určený pedagógom evanjelických škôl. Sledovaním budete vedieť o konferenciách a vzdelávaní. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1168 => [
            'name' => 'Cor Sancti Martini, n.o',
            'body' => [
                0 => 'Cor Sancti Martini je nezisková organizácia, ktorá sa venuje kultúrnemu a duchovnému dedičstvu Spiša. Spolupracuje so Spišskou Kapitulou a diecéznym múzeom. Pripravuje kultúrne aj pútnické podujatia.',
                1 => 'Pripravila divadelnú cestu dejinami Spišskej Kapituly a oslavu 360 rokov Kaplnky sv. Rozálie v Spišskom diecéznom múzeu. Organizuje aj dvojdňovú jesennú púť do Turzovky. Podujatia spájajú históriu a vieru.',
                2 => 'Kanál je určený priaznivcom Spiša a jeho pamiatok. Sledovaním budete vedieť o nových podujatiach. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1187 => [
            'name' => 'Mary’s Meals Prešov',
            'body' => [
                0 => 'Mary’s Meals Prešov je skupina dobrovoľníkov, ktorá podporuje organizáciu Mary’s Meals. Tá zabezpečuje jedlo deťom v škole v najchudobnejších krajinách sveta. Dobrovoľníci pôsobia v Prešove.',
                1 => 'Pripravujú benefičné akcie, napríklad ŠteFANskú zábavu v Prešove alebo charitatívnu knižnú burzu Čítaj a daruj. Výťažok putuje na školské jedlo pre deti. Akcie spájajú zábavu a pomoc.',
                2 => 'Kanál je určený podporovateľom z Prešova a okolia. Sledovaním budete vedieť o ďalších benefičných akciách. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1208 => [
            'name' => 'Nádej - spoločenstvo slobodných',
            'body' => [
                0 => 'Nádej je spoločenstvo slobodných, ktorí túžia po manželstve. Pripravuje modlitbové a formačné stretnutia. Pôsobí vo viacerých mestách Slovenska.',
                1 => 'Organizuje duchovno-formačné programy pre slobodných, napríklad v Kovarciach. Pripravuje aj ples pre slobodných tuSom. Stretnutia spájajú modlitbu a spoločenstvo.',
                2 => 'Kanál je určený slobodným, ktorí hľadajú spoločenstvo. Sledovaním budete vedieť o nových stretnutiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1218 => [
            'name' => 'Spoločnosť Andreja Hlinku',
            'body' => [
                0 => 'Spoločnosť Andreja Hlinku si pripomína odkaz kňaza a politika Andreja Hlinku. Pripravuje pietne a spomienkové podujatia. Pôsobí po celom Slovensku.',
                1 => 'Každoročne v auguste pripravuje pietnu spomienku pri výročí úmrtia Andreja Hlinku v Bratislave. Spomienka spája modlitbu a pripomenutie jeho života. Je otvorená verejnosti.',
                2 => 'Kanál je určený všetkým, ktorých zaujímajú slovenské dejiny. Sledovaním budete vedieť o spomienkových podujatiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1243 => [
            'name' => 'Biskupský úrad VD ECAV na Slovensku',
            'phone' => '0918 828 379',
            'body' => [
                0 => 'Biskupský úrad Východného dištriktu ECAV na Slovensku riadi život evanjelických zborov dištriktu. Spolupracuje s cirkevnými zbormi a seniorátmi. Pripravuje podujatia pre duchovných aj veriacich.',
                1 => 'Organizuje pastorálne konferencie, napríklad Návrat k Prvej láske, a Deň farárskych rodín. Zapája sa aj do Noci kostolov v Banskej Štiavnici. Podujatia posilňujú spoločenstvo v cirkvi.',
                2 => 'Kanál je určený evanjelickým duchovným a veriacim. Sledovaním budete vedieť o konferenciách a stretnutiach. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1248 => [
            'name' => 'Katolícke spoločenstvo EMfest tím',
            'phone' => '0907 579 742',
            'body' => [
                0 => 'EMfest tím je katolícke spoločenstvo, ktoré pripravuje festival EMfest. Festival nesie atmosféru Medžugoria. Koná sa na Starých Horách.',
                1 => 'EMfest má už viac ako pätnásť ročníkov a koná sa v lete. Program spája sväté omše, adoráciu, chvály a svedectvá. Prichádzajú naň najmä mladí a rodiny.',
                2 => 'Kanál je určený mladým a rodinám, ktoré hľadajú duchovný festival. Sledovaním budete vedieť o ďalšom ročníku. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1249 => [
            'name' => 'Komisia pre mládež v Spišskej diecéze',
            'body' => [
                0 => 'Komisia pre mládež v Spišskej diecéze pripravuje programy pre mladých na Spiši. Spája prácu s mládežou vo farnostiach. Organizuje akcie v prírode aj na pútnických miestach.',
                1 => 'Pripravuje diecézne výstupy mladých, napríklad na Veľké Hincovo pleso či na Téryho chatu. Organizuje aj mládežnícky program Boh to vidí inak na Mariánskej hore v Levoči. Podujatia spájajú vieru a dobrodružstvo.',
                2 => 'Kanál je určený mladým zo Spišskej diecézy. Sledovaním budete vedieť o výstupoch a stretnutiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1271 => [
            'name' => 'rímskokatolícka farnosť v Šaštíne',
            'body' => [
                0 => 'Rímskokatolícka farnosť v Šaštíne pôsobí pri Národnej svätyni Sedembolestnej Panny Márie. Spravujú ju saleziáni. Stará sa o veriacich aj pútnikov.',
                1 => 'Pozýva na Národnú púť k Sedembolestnej Panne Márii, patrónke Slovenska. Púť sa koná každoročne v septembri. Jej program trvá niekoľko dní.',
                2 => 'Kanál je určený farníkom a pútnikom. Sledovaním budete vedieť o programe púte. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1284 => [
            'name' => 'V.I.A.C. - Inštitút pre podporu a rozvoj mládeže',
            'phone' => '+421 948 849 922',
            'emails' => [
                0 => [
                    'email' => 'office@ozviac.sk',
                    'found_on' => 'https://www.ozviac.sk',
                ],
            ],
            'body' => [
                0 => 'V.I.A.C. – Inštitút pre podporu a rozvoj mládeže pracuje s mladými na Orave. Je spojený s Oravským centrom mládeže. Pripravuje tábory a formačné programy.',
                1 => 'Organizuje letný Camp Vyvolený. Pripravuje aj prednášky, napríklad Ako čítať Bibliu so Scottom Hahnom. Programy spájajú zážitky, vzdelávanie a vieru.',
                2 => 'Kanál je určený mladým a ich rodičom. Sledovaním budete vedieť o nových táboroch a programoch. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1320 => [
            'name' => 'Slovenské národné múzeum (SNM) – Historické múzeum',
            'body' => [
                0 => 'Historické múzeum je súčasťou Slovenského národného múzea. Sídli na Bratislavskom hrade. Pripravuje výstavy o dejinách Slovenska.',
                1 => 'Pripravilo napríklad výstavu Dary pápežov Slovensku na Bratislavskom hrade. Na Trenčianskom hrade predstavilo výber najkrajších a najcennejších betlehemov. Výstavy približujú aj cirkevné dejiny.',
                2 => 'Kanál je určený návštevníkom múzeí a milovníkom histórie. Sledovaním budete vedieť o nových výstavách. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1337 => [
            'name' => 'Katolícke hnutie žien Slovenska',
            'phone' => '0903 781 786',
            'body' => [
                0 => 'Katolícke hnutie žien Slovenska spája ženy, ktoré chcú žiť vieru v rodine aj v spoločnosti. Je známe pôstnou akciou Podeľme sa. Pôsobí po celom Slovensku.',
                1 => 'V pôste organizuje pôstne polievky, ktoré podávajú aj biskupi, napríklad v Košiciach a v Bratislave. Výťažok akcie Podeľme sa podporuje projekty v rozvojových krajinách. Akcie spájajú solidaritu a pôst.',
                2 => 'Kanál je určený ženám a všetkým, ktorí chcú pomáhať. Sledovaním budete vedieť o pôstnych akciách. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1339 => [
            'name' => 'Spoločenstvo evanjelických žien Bratislavského seniorátu',
            'body' => [
                0 => 'Spoločenstvo evanjelických žien Bratislavského seniorátu spája evanjelické ženy z Bratislavy a okolia. Pripravuje biblické hodiny a diskusie. Otvára aj odvážne témy.',
                1 => 'Organizuje biblické hodiny, napríklad o postave Lilith, a diskusie, napríklad o ceste k ordinácii žien na Slovensku. Stretnutia spájajú štúdium Písma a rozhovor. Sú otvorené pre ženy aj mužov.',
                2 => 'Kanál je určený ženám a všetkým, ktorých zaujíma Biblia. Sledovaním budete vedieť o ďalších stretnutiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1382 => [
            'name' => 'Západný dištrikt ECAV na Slovensku',
            'body' => [
                0 => 'Západný dištrikt ECAV na Slovensku spája evanjelické cirkevné zbory na západe Slovenska. Podporuje vzdelávanie duchovných aj laikov. Pripravuje celodištriktuálne podujatia.',
                1 => 'Organizuje semináre pre neordinovaných pracovníkov, Festival chrámovej piesne a online ekumenické modlitby. Podujatia spájajú zbory dištriktu. Mnohé prebiehajú aj online.',
                2 => 'Kanál je určený evanjelickým zborom a veriacim. Sledovaním budete vedieť o seminároch a festivaloch. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1386 => [
            'name' => 'Centrum pre duchovné povolania RKFÚ sv. Mikuláša',
            'body' => [
                0 => 'Centrum pre duchovné povolania pôsobí pri Rímskokatolíckom farskom úrade sv. Mikuláša v Prešove. Pomáha mladým rozlišovať povolanie. Pripravuje formačné programy.',
                1 => 'Organizuje duchovno-formačné pobyty v Ríme pre mladých. Prihlasovanie sa uzatvára niekoľko mesiacov vopred. Pobyt spája modlitbu, spoznávanie Ríma a rozhovory.',
                2 => 'Kanál je určený mladým, ktorí hľadajú svoje povolanie. Sledovaním budete vedieť o nových pobytoch. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1389 => [
            'name' => 'Národný týždeň manželstva, o.z',
            'phone' => '0904 529 824',
            'body' => [
                0 => 'Národný týždeň manželstva je občianske združenie, ktoré pripravuje celoslovenskú kampaň na podporu manželstva. Každý ročník má vlastnú tému. Kampaň začína okolo sviatku sv. Valentína.',
                1 => 'Počas týždňa sa po celom Slovensku konajú stretnutia, prednášky a večery pre manželov. Témou jedného ročníka bolo napríklad slovo Odkaz. Do kampane sa zapájajú farnosti, mestá aj organizácie.',
                2 => 'Kanál je určený manželským párom a organizátorom podujatí. Sledovaním budete vedieť o téme a programe ďalšieho ročníka. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1395 => [
            'name' => 'Pezinok - Centrum pre rodinu',
            'body' => [
                0 => 'Centrum pre rodinu v Pezinku podporuje manželov a rodičov. Pripravuje kurzy a stretnutia. Pomáha rodinám v každodennom živote.',
                1 => 'Organizuje dvojdňový kurz Cesta efektívnej komunikácie a prednášky, napríklad Sexualita vo výchove. Pripravuje aj Manželské večery. Programy sú otvorené pre páry z okolia.',
                2 => 'Kanál je určený rodinám z Pezinka a okolia. Sledovaním budete vedieť o nových kurzoch. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1406 => [
            'name' => 'Koinonia Mladi',
            'body' => [
                0 => 'Koinonia Mladi je spoločenstvo mladých. Pripravuje stretnutia a víkendy pre mladých. Pôsobí na Slovensku.',
                1 => 'Organizuje modlitbové stretnutia, napríklad v Novom Smokovci, a večery Ježiš uzdravuje. Pripravuje aj víkendové stretnutia pre mladých, niektoré cez ZOOM. Program spája chvály, slovo a modlitbu.',
                2 => 'Kanál je určený mladým, ktorí hľadajú živé spoločenstvo. Sledovaním budete vedieť o ďalších stretnutiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1417 => [
            'name' => 'Musica Cordis',
            'body' => [
                0 => 'Musica Cordis je spevácke teleso, ktoré koncertuje v obciach Nitrianskeho kraja. Spieva duchovnú a adventnú hudbu. Vystupuje najmä v kostoloch.',
                1 => 'Pripravuje sériu adventných koncertov, napríklad v Lovciach, Hostí a Kostoľanoch pod Tribečom. Koncerty pomáhajú naladiť sa na Vianoce. Sú otvorené pre verejnosť.',
                2 => 'Kanál je určený milovníkom duchovnej hudby. Sledovaním budete vedieť o koncertoch. Termín a miesto nájdete vždy v detaile podujatia.',
            ],
        ],
        1427 => [
            'name' => 'Hospitálska rehoľa sv. Jána z Boha – Milosrdní bratia',
            'body' => [
                0 => 'Hospitálska rehoľa sv. Jána z Boha – Milosrdní bratia sa venuje chorým a núdznym. V Bratislave spravuje nemocnicu a Kostol Navštívenia Panny Márie. Pripravuje aj duchovné programy.',
                1 => 'Organizuje stretnutia pre mužov vo Vysokých Tatrách, ktoré majú už desať ročníkov. V pôste pozýva na pravidelné krížové cesty. Pripomína si aj sviatok svojho patróna.',
                2 => 'Kanál je určený veriacim a mužom, ktorí hľadajú spoločenstvo. Sledovaním budete vedieť o stretnutiach a pobožnostiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1432 => [
            'name' => 'Deti sv. Alžbety - Aliancia za život, o.z',
            'body' => [
                0 => 'Deti sv. Alžbety – Aliancia za život je občianske združenie z Košíc. Venuje sa ochrane života od počatia. Podporuje rodiny a tehotné matky.',
                1 => 'Pripravuje celodennú spomienku na potratené deti a stretnutia všetkých, ktorým záleží na úcte k životu. Organizuje aj sväté omše s požehnaním tehotných mamičiek a ich rodín. Podujatia sa konajú v Košiciach.',
                2 => 'Kanál je určený rodinám a všetkým, ktorým záleží na ochrane života. Sledovaním budete vedieť o ďalších podujatiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1446 => [
            'name' => 'Spoločenstvo Cleopas, o.z',
            'body' => [
                0 => 'Spoločenstvo Cleopas je občianske združenie, ktoré sa venuje Svätému písmu. Vydáva časopis Studia Biblica Slovaca. Pripravuje biblické víkendy.',
                1 => 'Organizuje adventné a ďalšie víkendy s Bibliou, napríklad Otcovia v Biblii – príbehy a inšpirácie alebo Sila slabých a slabosť silných v Biblii. Víkendy sa konajú napríklad v Badíne. Spájajú výklad Písma a modlitbu.',
                2 => 'Kanál je určený všetkým, ktorí chcú hlbšie poznať Bibliu. Sledovaním budete vedieť o ďalších víkendoch. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1455 => [
            'name' => 'Iniciatíva Kresťan na vidieku',
            'body' => [
                0 => 'Kresťan na vidieku je iniciatíva, ktorá podporuje život kresťanov a obcí na vidieku. Je projektom Fóra kresťanských inštitúcií. Zbiera a šíri inšpiratívne príbehy z vidieka.',
                1 => 'Pripravuje semináre, napríklad Formovať budúcnosť vidieka, a sériu diskusných večerov. Prináša aj projekt inšpiratívnych príbehov z obcí. Podujatia spájajú samosprávy, farnosti a aktívnych ľudí.',
                2 => 'Kanál je určený ľuďom z vidieka, starostom a farnostiam. Sledovaním budete vedieť o seminároch a diskusiách. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1459 => [
            'name' => 'Oáza - nádej pre nový život, n.o',
            'body' => [
                0 => 'Oáza – nádej pre nový život je nezisková organizácia z Košíc. Stojí za modlitbovými stretnutiami Nádej. Venuje sa slobodným, ktorí túžia po manželstve.',
                1 => 'Organizuje modlitbové stretnutia slobodných túžiacich po manželstve, napríklad v Košiciach a v Nitre. Stretnutia sa konajú pravidelne. Spájajú modlitbu a spoločenstvo.',
                2 => 'Kanál je určený slobodným katolíkom. Sledovaním budete vedieť o najbližších stretnutiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1519 => [
            'name' => 'Tripy mladých',
            'body' => [
                0 => 'Tripy mladých sú projekt výletov a pútí pre mladých. Spájajú turistiku s duchovným programom. Pripravujú ich spolu s diecéznymi centrami mládeže.',
                1 => 'Organizovali napríklad výstup na horu Butkov, trip do Dubinného a pešiu púť s Arcidiecéznym centrom pre mládež v Košiciach. Programy spájajú prírodu, modlitbu a spoločenstvo. Sú určené mladým.',
                2 => 'Kanál je určený mladým, ktorí radi chodia do prírody. Sledovaním budete vedieť o ďalších tripoch. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1549 => [
            'name' => 'Exercičný dom kalvaria',
            'body' => [
                0 => 'Exercičný dom Kalvária v Nitre slúži duchovným cvičeniam a obnovám. Prijíma jednotlivcov aj skupiny. Ponúka programy pre rôzne skupiny.',
                1 => 'Pripravuje duchovné cvičenia, napríklad „Aké milé sú na horách nohy posla“, aj cvičenia pre rodinných príslušníkov závislých. Organizuje aj biblické kurzy, napríklad o víne v Biblii. Programy prebiehajú v tichu a modlitbe.',
                2 => 'Kanál je určený všetkým, ktorí hľadajú duchovnú obnovu. Sledovaním budete vedieť o nových termínoch. Prihlasovanie nájdete vždy v detaile podujatia.',
            ],
        ],
        1560 => [
            'name' => 'Dominikánske mariánske centrum - DMC',
            'address' => [
                'street' => 'Mäsiarska 6',
                'postcode' => '040 01',
                'municipality_id' => 1565,
                'latitude' => 48.7211312,
                'longitude' => 21.2551846,
            ],
            'body' => [
                0 => 'Dominikánske mariánske centrum založila Slovenská provincia Rehole dominikánov v roku 1994. Podporuje a rozvíja ružencové bratstvá na Slovensku. Sídli v Košiciach.',
                1 => 'Organizuje celoslovenské stretnutia ružencových bratstiev a ich púte do Národnej baziliky v Šaštíne, ktoré majú už takmer dvadsať ročníkov. Púte spájajú modlitbu ruženca, sväté omše a spoločenstvo. Zúčastňujú sa na nich bratstvá z celého Slovenska.',
                2 => 'Kanál je určený členom ružencových bratstiev a ctiteľom ruženca. Sledovaním budete vedieť o pútiach a stretnutiach. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1562 => [
            'name' => 'Bratia Dominikáni – farnosť Bratislava Kalvária',
            'body' => [
                0 => 'Farnosť Bratislava – Kalvária spravujú bratia dominikáni. Na Kalvárskom vrchu sa nachádza historická krížová cesta. Farnosť sa o ňu stará spolu s ďalšími spoločenstvami.',
                1 => 'Pripravuje pravidelné krížové cesty na Kalvárskom vrchu a Kalvárske utorky s meditáciami nad mariánskou mozaikou. Slávi aj Nedeľu Božieho milosrdenstva s procesiou. Program je otvorený pre veriacich z celej Bratislavy.',
                2 => 'Kanál je určený veriacim z Bratislavy. Sledovaním budete vedieť o pobožnostiach a slávnostiach na Kalvárii. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1580 => [
            'name' => 'Godzone Academy',
            'body' => [
                0 => 'Godzone Academy je vzdelávacia vetva hnutia Godzone. Pripravuje kurzy a konferencie. Spolupracuje s ďalšími spoločenstvami.',
                1 => 'V spolupráci s Komunitou Emanuel vytvorila nový kurz. Podieľala sa aj na ženskej konferencii Nespútaná a spoločnej modlitbe spoločenstiev za mier. Programy prebiehajú aj online.',
                2 => 'Kanál je určený mladým a vedúcim spoločenstiev. Sledovaním budete vedieť o nových kurzoch. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1625 => [
            'name' => 'Kolednícka akcia Dobrá novina',
            'body' => [
                0 => 'Kolednícka akcia Dobrá novina je najväčšia solidárna akcia detí a mladých na Slovensku. Pripravuje ju hnutie eRko. Koledníci počas Vianoc prinášajú do domácností radostnú zvesť.',
                1 => 'Spolupracuje s Pápežskými misijnými dielami na Trojkráľovej kolede. Výťažok podporuje rozvojové projekty v Afrike. Vyslanie koledníkov vysiela aj TV LUX.',
                2 => 'Kanál je určený koledníkom, farnostiam a darcom. Sledovaním budete vedieť o termínoch a programe koledy. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1631 => [
            'name' => 'Slovenské evanjelizačné stredisko',
            'body' => [
                0 => 'Slovenské evanjelizačné stredisko (EVS) je kresťanská organizácia, ktorá sa venuje evanjelizácii. Pripravuje konferencie a večery pre mladých aj dospelých. Pôsobí po celom Slovensku.',
                1 => 'Organizuje online konferencie, napríklad Dobrý Boh, a minikonferencie. Pripravuje aj večery Chcemviac. Program spája slovo, chvály a inšpiráciu.',
                2 => 'Kanál je určený všetkým, ktorí chcú rásť vo viere. Sledovaním budete vedieť o konferenciách a večeroch. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1747 => [
            'name' => 'Evanjelická diakonia ECAV na Slovensku',
            'body' => [
                0 => 'Evanjelická diakonia ECAV na Slovensku poskytuje sociálne služby a pomoc ľuďom v núdzi. Spolupracuje s evanjelickými cirkevnými zbormi. Pôsobí po celom Slovensku.',
                1 => 'Pripravuje webináre, napríklad o návštevnej službe v postcovidovej dobe či o starnutí. Organizovala aj komunitné stretnutia priateľov z Ukrajiny na Orave. Podujatia spájajú pomoc a vzdelávanie.',
                2 => 'Kanál je určený pracovníkom diakonie, dobrovoľníkom a zborom. Sledovaním budete vedieť o webinároch a stretnutiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1783 => [
            'name' => 'Veľvyslanectvo Poľskej republiky na Slovensku',
            'body' => [
                0 => 'Veľvyslanectvo Poľskej republiky na Slovensku podporuje poľsko-slovenské kultúrne vzťahy. Pripomína si aj odkaz sv. Jána Pavla II. Pripravuje výstavy a premietania.',
                1 => 'Pripravilo výstavu Ján Pavol II. a Slovensko, ktorú predstavilo na Mariánskej hore v Levoči aj v Bazilike sv. Emeráma v Nitre. K výstave patrilo aj premietanie filmu. Podujatia sú otvorené verejnosti.',
                2 => 'Kanál je určený všetkým, ktorým je blízky odkaz Jána Pavla II. Sledovaním budete vedieť o ďalších výstavách. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1849 => [
            'name' => 'Teologická fakulta KU v Košiciach',
            'address' => [
                'street' => 'Hlavná 89',
                'postcode' => '041 21',
                'municipality_id' => 1565,
                'latitude' => 48.7243731,
                'longitude' => 21.256988,
            ],
            'body' => [
                0 => 'Teologická fakulta Katolíckej univerzity so sídlom v Košiciach vznikla v roku 2003. Nadväzuje na odkaz Košickej univerzity založenej v roku 1657. Ponúka štúdium katolíckej teológie, sociálnej práce a cirkevnej hudby.',
                1 => 'Organizuje konferencie a diskusie, napríklad o náboženskom extrémizme, o ochrane detí v cirkevnom prostredí či o modernom umení v kostole. Okrem formácie kňazov pripravuje aj laických odborníkov. Podujatia sú otvorené verejnosti.',
                2 => 'Kanál je určený študentom, uchádzačom a odbornej verejnosti. Sledovaním budete vedieť o konferenciách a diskusiách. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1892 => [
            'name' => 'M-Aréna a spoločenstvo MaranaTha',
            'body' => [
                0 => 'Marana Tha je katolícke spoločenstvo v Prešove, Bardejove a Košiciach. Je miestom stretnutí, modlitieb, chvál a evanjelizácie. M-Aréna v Prešove je priestorom jeho podujatí.',
                1 => 'Pripravuje konferenciu Oheň, modlitbové stretnutia mužov a kurz Manželské večery. Podujatia sú otvorené pre mladých, manželov aj rodiny. Program spája chvály, slovo a spoločenstvo.',
                2 => 'Kanál je určený veriacim z Prešova a okolia. Sledovaním budete vedieť o konferenciách a stretnutiach. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
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

            // Prepíše sa len popis, ktorý ešte nemá tri odseky — upravený text ostane.
            $paragraphs = substr_count((string) $canal->body, '<p');

            if (isset($profile['body']) && $paragraphs !== 3) {
                $changes['body'] = implode('', array_map(fn (string $p) => '<p>'.e($p).'</p>', $profile['body']));
            } elseif (isset($profile['append']) && $paragraphs === 2) {
                $changes['body'] = rtrim((string) $canal->body).'<p>'.e($profile['append']).'</p>';
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
