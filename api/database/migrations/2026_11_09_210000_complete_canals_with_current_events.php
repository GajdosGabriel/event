<?php

use App\Models\Canal;
use App\Services\Canals\CanalEmails;
use App\Services\SystemLog\Recorder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Štvrtá dávka (november 2026): kanály s aktuálnou akciou — dorovnanie
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
        9 => [
            'name' => 'Rada pre mládež a univerzity KBS',
            'phone' => '+421 909 250 936',
        ],
        20 => [
            'name' => 'OZ Rodinné spoločenstvo ECAV na Slovensku',
            'address' => [
                'street' => 'Hlavná 137',
                'postcode' => '080 01',
                'municipality_id' => 2822,
                'latitude' => 48.9989119,
                'longitude' => 21.2393984,
            ],
        ],
        36 => [
            'name' => 'Spišská diecéza',
            'phone' => '+421 944 311 154',
        ],
        38 => [
            'name' => 'Misijná škola Karola Wojtylu',
            'address' => [
                'street' => 'Svoradova 13',
                'postcode' => '811 03',
                'municipality_id' => 242,
                'latitude' => 48.1453803,
                'longitude' => 17.1005793,
            ],
        ],
        42 => [
            'name' => 'MEA GRATI',
            'phone' => '+421 915 333 707',
        ],
        48 => [
            'name' => 'Prešovská archieparchia',
            'phone' => '0909 250 462',
        ],
        93 => [
            'name' => 'Ekumenické spoločenstvo cirkví a náboženských spoločností na území mesta Košice',
            'address' => [
                'street' => 'Hlavná 91',
                'postcode' => '040 01',
                'municipality_id' => 1565,
                'latitude' => 48.7245141,
                'longitude' => 21.2563683,
            ],
        ],
        106 => [
            'name' => 'Spolok svätého Vojtecha',
            'phone' => '0911 670 143',
            'address' => [
                'street' => 'Radlinského 5',
                'postcode' => '917 01',
                'municipality_id' => 3596,
                'latitude' => 48.3768533,
                'longitude' => 17.5839892,
            ],
        ],
        152 => [
            'name' => 'Informačné centrum Mir Medjugorje CZ',
            'body' => [
                0 => 'Informačné centrum Mir Medjugorje CZ je české centrum, ktoré prináša informácie o pútnickom mieste Medžugorie. Zverejňuje novinky, posolstvá a ponuku pútí. Jeho činnosť sleduje aj mnoho veriacich zo Slovenska.',
                1 => 'Podieľa sa na príprave českého Mladifestu, stretnutia mladých v atmosfére Medžugoria na Morave. Ponúka aj púte a duchovné podujatia spojené s Medžugorím. Program spája modlitbu, sväté omše a svedectvá.',
                2 => 'Kanál je určený pútnikom a mladým, ktorým je Medžugorie blízke. Sledovaním budete vedieť o nových stretnutiach a pútiach. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        162 => [
            'name' => 'Exercičný dom svätého Ignáca z Loyoly',
            'address' => [
                'street' => 'Pod Kalváriou 81',
                'postcode' => '080 01',
                'municipality_id' => 2822,
                'latitude' => 48.9894458,
                'longitude' => 21.2272701,
            ],
        ],
        171 => [
            'name' => 'Kongregácia sestier Najsvätejšieho Spasiteľa - Centrum Salvator',
            'phone' => '+421 950 507 258',
        ],
        206 => [
            'name' => 'Školské sestry sv. Františka v Žiline',
            'address' => [
                'street' => 'J. M. Hurbana 44',
                'postcode' => '010 01',
                'municipality_id' => 4194,
                'latitude' => 49.2260062,
                'longitude' => 18.7357412,
            ],
        ],
        232 => [
            'name' => 'Milosrdné sestry Svätého kríža',
            'phone' => '0915 961 752',
        ],
        243 => [
            'name' => 'Pápežská nadácia ACN – Pomoc trpiacej Cirkvi',
            'address' => [
                'street' => 'Sládkovičova 7',
                'postcode' => '811 06',
                'municipality_id' => 242,
                'latitude' => 48.1493329,
                'longitude' => 17.1057573,
            ],
        ],
        278 => [
            'name' => 'Dom Quo Vadis',
            'address' => [
                'street' => 'Veterná 1',
                'postcode' => '811 03',
                'municipality_id' => 242,
                'latitude' => 48.1462729,
                'longitude' => 17.1067602,
            ],
        ],
        319 => [
            'name' => 'Sestry Karmelitánky Dieťaťa Ježiša',
            'body' => [
                0 => 'Sestry Karmelitánky Dieťaťa Ježiša sú rehoľné spoločenstvo s komunitou v Nitre. Žijú karmelitánsku spiritualitu, ktorá kladie dôraz na modlitbu. Svoju modlitbu otvárajú aj veriacim.',
                1 => 'Pozývajú na modlitbové stretnutia, napríklad o svätom Jánovi z Kríža. Stretnutia približujú karmelitánskych svätých a ich cestu k Bohu. Konajú sa v priestoroch sestier v Nitre.',
                2 => 'Kanál je určený veriacim z Nitry, ktorým je blízka modlitba a karmelitánska spiritualita. Sledovaním budete vedieť o ďalších stretnutiach. Miesto a čas nájdete vždy v detaile podujatia.',
            ],
        ],
        321 => [
            'name' => 'Pápežská komisia na ochranu maloletých',
            'body' => [
                0 => 'Pápežská komisia na ochranu maloletých je orgán Svätej stolice. Radí pápežovi v otázkach ochrany detí a zraniteľných osôb v Cirkvi. Spolupracuje s biskupskými konferenciami a reholami.',
                1 => 'Pripravuje medzinárodné stretnutia o prevencii, napríklad stretnutie o prevencii v zasvätenom živote v Ríme. Na stretnutia sa registrujú zástupcovia reholí a cirkevných inštitúcií. Venujú sa bezpečnému prostrediu v cirkevných komunitách.',
                2 => 'Kanál je určený predstaveným reholí, pracovníkom s deťmi a všetkým, ktorí sa venujú ochrane maloletých. Sledovaním budete vedieť o nových stretnutiach a výzvach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        331 => [
            'name' => 'Rád bosých karmelitánov',
            'address' => [
                'street' => 'Dobrovičova 2',
                'postcode' => '811 02',
                'municipality_id' => 242,
                'latitude' => 48.1415122,
                'longitude' => 17.1148012,
            ],
        ],
        336 => [
            'name' => 'Milujúca náruč, n.o',
            'body' => [
                0 => 'Milujúca náruč je nezisková organizácia, ktorá sprevádza ľudí v bolestných životných situáciách. Venuje sa najmä bezdetným manželom a ľuďom zraneným stratou dieťaťa. Ponúka im duchovnú oporu a uzdravenie.',
                1 => 'Organizuje duchovné cvičenia pre bezdetných manželov a pre ľudí zranených potratom. Pripravila aj konferenciu o podstate a hodnote človeka Vidieť seba vo svetle. Cvičenia sa konajú najmä v Rehoľno-formačnom centre v Spišskej Novej Vsi.',
                2 => 'Kanál je určený manželom a jednotlivcom, ktorí hľadajú duchovné sprevádzanie. Sledovaním budete vedieť o nových termínoch cvičení. Prihlasovanie nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        364 => [
            'name' => 'Bazilika Staré Hory',
            'body' => [
                0 => 'Bazilika Navštívenia Panny Márie v Starých Horách je známe mariánske pútnické miesto v Banskobystrickej diecéze. Nad bazilikou sa nachádza Studnička s prameňom. Púte sem prichádzajú počas celého roka.',
                1 => 'Pri bazilike sa konajú pravidelné púte, napríklad počas prvých sobôt v mesiaci. Na tieto dni bazilika hľadá dobrovoľníkov, ktorí pomáhajú pri službe pútnikom. Program spája sväté omše, modlitbu a spoločenstvo.',
                2 => 'Kanál je určený pútnikom a dobrovoľníkom. Sledovaním budete vedieť o pútiach a príležitostiach pomôcť. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        368 => [
            'name' => 'Trnavská univerzita v Trnave',
            'address' => [
                'street' => 'Hornopotočná 23',
                'postcode' => '918 43',
                'municipality_id' => 3596,
                'latitude' => 48.379698,
                'longitude' => 17.58686,
            ],
        ],
        373 => [
            'name' => 'Univerzitné kolégium Antona Neuwirtha',
            'address' => [
                'street' => 'Námestie padlých hrdinov 7',
                'postcode' => '900 28',
                'municipality_id' => 1231,
                'latitude' => 48.19063,
                'longitude' => 17.2587549,
            ],
        ],
        380 => [
            'name' => 'Gréckokatolícka teologická fakulta v Prešove',
            'body' => [
                0 => 'Gréckokatolícka teologická fakulta je súčasťou Prešovskej univerzity v Prešove. Pripravuje kňazov, katechétov a ďalších odborníkov v byzantskej tradícii. Venuje sa aj vedeckej práci.',
                1 => 'Organizuje odborné konferencie, napríklad o Homiletickom direktóriu ako kľúči k evanjelizácii. Konferencie spájajú teológov, kňazov a študentov. Konajú sa v Prešove.',
                2 => 'Kanál je určený študentom, kňazom a všetkým, ktorých zaujíma teológia. Sledovaním budete vedieť o ďalších konferenciách. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        419 => [
            'name' => 'Konfederácia politických väzňov Slovenska',
            'address' => [
                'street' => 'Košická 5590/56',
                'postcode' => '821 08',
                'municipality_id' => 242,
                'latitude' => 48.1521004,
                'longitude' => 17.1363,
            ],
        ],
        468 => [
            'name' => 'Gréckokatolícki redemptoristi na Slovensku',
            'body' => [
                0 => 'Gréckokatolícki redemptoristi sú rehoľa, ktorá na Slovensku pôsobí v byzantskom obrade. Venujú sa ľudovým misiám, duchovným obnovám a pastorácii. Majú viacero kláštorov na východe Slovenska.',
                1 => 'Pripravujú aj netradičné iniciatívy, napríklad darovanie krvi v troch svojich kláštoroch. Organizujú duchovné obnovy a púte. Ich podujatia sú otvorené pre veriacich všetkých obradov.',
                2 => 'Kanál je určený veriacim z východného Slovenska a všetkým, ktorým je blízka redemptoristická spiritualita. Sledovaním budete vedieť o ďalších podujatiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        475 => [
            'name' => 'saleziánska rodina',
            'address' => [
                'street' => 'Miletičova 7',
                'postcode' => '821 08',
                'municipality_id' => 242,
                'latitude' => 48.1557119,
                'longitude' => 17.1344389,
            ],
        ],
        482 => [
            'name' => 'Štátna vedecká knižnica',
            'body' => [
                0 => 'Štátna vedecká knižnica v Banskej Bystrici je vedecká a regionálna knižnica. Sprístupňuje knižničné fondy, vzácne tlače a odbornú literatúru. Pripravuje aj výstavy a kultúrne podujatia.',
                1 => 'Pripravila napríklad výstavu, ktorá predstavuje bratislavské antifonáre zo Slovenského národného archívu. Výstavy približujú vzácne rukopisy a dejiny knižnej kultúry. Sú otvorené pre verejnosť.',
                2 => 'Kanál je určený čitateľom, študentom a milovníkom histórie. Sledovaním budete vedieť o nových výstavách a podujatiach. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        524 => [
            'name' => 'Televízia LUX',
            'address' => [
                'street' => 'Prepoštská 5',
                'postcode' => '811 01',
                'municipality_id' => 242,
                'latitude' => 48.1430008,
                'longitude' => 17.1056412,
            ],
        ],
        675 => [
            'name' => 'Rímskokatolícka cyrilometodská bohoslovecká fakulta UK – Kňazský seminár sv. Gorazda',
            'address' => [
                'street' => 'Kapitulská 26',
                'postcode' => '814 58',
                'municipality_id' => 242,
                'latitude' => 48.1424656,
                'longitude' => 17.1048446,
            ],
        ],
        686 => [
            'name' => 'katRande o.z',
            'append' => 'Kanál je určený slobodným katolíkom, ktorí hľadajú partnera s rovnakými hodnotami. Okrem online platformy pripravuje aj víkendovky a dovolenky pre slobodných, napríklad vo Vysokých Tatrách či na Spiši. Termíny nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
        ],
        757 => [
            'name' => 'Vincentská rodina',
            'body' => [
                0 => 'Vincentská rodina združuje spoločenstvá, ktoré žijú odkazom sv. Vincenta de Paul. Na Slovensku pomáha chudobným a ľuďom v núdzi. Spája rehoľníkov, sestry aj laikov.',
                1 => 'Každoročne organizuje celoslovenskú verejnú zbierku Boj proti hladu, ktorá má už viac ako dvadsať ročníkov. Výťažok pomáha chudobným na Slovensku, na Haiti, v Hondurase či na Ukrajine. Na záver zbierky sa ďakuje na spoločnej svätej omši.',
                2 => 'Kanál je určený darcom, dobrovoľníkom a všetkým, ktorí chcú pomáhať. Sledovaním budete vedieť o termíne zbierky a jej výsledkoch. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        775 => [
            'name' => 'Univerzitné pastoračné centrum (UPeCe) sv. Jozefa Freinademetza',
            'append' => 'Kanál je určený vysokoškolákom v Bratislave. Centrum pripravuje napríklad trojdňové duchovné obnovy a slávnostné otvorenie akademického roka svätou omšou. Miesto a čas nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
        ],
        802 => [
            'name' => 'Pápežský slovenský ústav a kolégium sv. Cyrila a Metoda',
            'body' => [
                0 => 'Pápežský slovenský ústav a kolégium sv. Cyrila a Metoda sídli v Ríme. Je miestom formácie slovenských kňazov a študentov v Ríme. Slúži aj ako centrum slovenskej komunity vo Večnom meste.',
                1 => 'Pripravuje program slovenských bohoslužieb v Ríme, napríklad počas Veľkonočných sviatkov. Pripomína si aj výročia, ako bola návšteva sv. Jána Pavla II. v ústave. Ponúka aj online vzdelávacie kurzy.',
                2 => 'Kanál je určený Slovákom v Ríme a pútnikom, ktorí Rím navštívia. Sledovaním budete vedieť o slovenských bohoslužbách a podujatiach. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        808 => [
            'name' => 'SLUHA o. z',
            'body' => [
                0 => 'SLUHA o. z. je občianske združenie zo Starej Turej. Podieľa sa na kultúrnych a spoločenských podujatiach v meste. Jeho akcie zverejňuje aj Evanjelická cirkev a. v.',
                1 => 'Spolupripravuje Novembrové oslavy na Starej Turej. Oslavy sa konajú každoročne v novembri. Sú otvorené pre obyvateľov mesta aj hostí.',
                2 => 'Kanál je určený obyvateľom Starej Turej a okolia. Sledovaním budete vedieť o ďalších podujatiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        815 => [
            'name' => 'Katolícke spoločenstvo Cor et Lumen Christi',
            'body' => [
                0 => 'Cor et Lumen Christi je katolícka charizmatická a kontemplatívna komunita s eucharistickým srdcom. Spája hlboký život modlitby s aktívnym apoštolátom. Na Slovensku má zázemie v Medzilaborciach.',
                1 => 'Organizuje dvojdňové konferencie katolíckej komunity v Medzilaborciach. Pripravuje aj online konferencie, napríklad Výzbroj svetla. Program spája prednášky, chvály a adoráciu.',
                2 => 'Kanál je určený veriacim, ktorí hľadajú duchovnú obnovu. Sledovaním budete vedieť o ďalších konferenciách. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        850 => [
            'name' => 'Fórum života o.z',
            'phone' => '+421 903 533 946',
            'address' => [
                'street' => 'Estónska 14',
                'postcode' => '821 07',
                'municipality_id' => 242,
                'latitude' => 48.142175,
                'longitude' => 17.201688,
            ],
            'body' => [
                0 => 'Fórum života je občianske združenie, ktoré buduje spoločnosť, v ktorej môže každý človek žiť plnohodnotne od počatia po prirodzenú smrť. Pomáha tehotným ženám a rodinám v ťažkých situáciách. Organizuje celoslovenské kampane.',
                1 => 'Pripravuje Deň počatého dieťaťa a novembrové zapaľovanie sviečok za nenarodené deti. Kampane sa konajú v mnohých mestách Slovenska. Spájajú verejnosť, farnosti a školy.',
                2 => 'Kanál je určený všetkým, ktorým záleží na ochrane života. Sledovaním budete vedieť o nových kampaniach a podujatiach. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        870 => [
            'name' => 'Rodinné centrum Drobček',
            'phone' => '0915 818 987',
            'body' => [
                0 => 'Rodinné centrum Drobček pôsobí v Námestove. Je miestom stretávania rodičov a detí. Pripravuje programy pre celé rodiny.',
                1 => 'Každoročne organizuje lampiónový sprievod sv. Martina v Námestove. Pripravuje aj podujatia ako Míľa pre mamu ku Dňu matiek. Akcie sú určené rodinám s deťmi.',
                2 => 'Kanál je určený rodinám z Námestova a okolia. Sledovaním budete vedieť o ďalších podujatiach. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        873 => [
            'name' => 'Familiaris občianske združenie',
            'address' => [
                'street' => 'SNP 145/9',
                'postcode' => '059 21',
                'municipality_id' => 3370,
                'latitude' => 49.0573216,
                'longitude' => 20.1991246,
            ],
            'body' => [
                0 => 'Familiaris je občianske združenie zo Svitu. Venuje sa rodinám a pôsobí v Podtatranskej oblasti. Prevádzkuje aj sociálnu poradňu.',
                1 => 'Organizuje organové koncerty a adventné koncerty, napríklad k sviatku Nepoškvrneného počatia Panny Márie. Pripravuje aj aktivity pre rodiny, ako sú výlety a letné programy. Podujatia sú otvorené verejnosti.',
                2 => 'Kanál je určený rodinám a obyvateľom Svitu a okolia. Sledovaním budete vedieť o ďalších koncertoch a podujatiach. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        954 => [
            'name' => 'Rehoľa bratov kazateľov (bratia dominikáni)',
            'body' => [
                0 => 'Rehoľa bratov kazateľov, dominikáni, je súčasťou Katolíckej cirkvi. Podľa túžby sv. Dominika je jej hlavným cieľom kázať pre spásu duší. Na Slovensku má kláštor aj v Košiciach.',
                1 => 'Pripravuje Akadémiu sv. Tomáša Akvinského pre stredoškolákov aj pre dospelých, napríklad na tému O šťastí alebo kristologickú akadémiu. Organizuje aj prednášky, napríklad o Pier Giorgiovi Frassatim. Program spája vzdelávanie a duchovný život.',
                2 => 'Kanál je určený mladým a dospelým, ktorí chcú hlbšie poznať vieru. Sledovaním budete vedieť o nových akadémiách a prednáškach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        972 => [
            'name' => 'komunita Taizé',
            'body' => [
                0 => 'Komunita Taizé je ekumenické spoločenstvo bratov v Burgundsku vo Francúzsku. Prijíma mladých aj návštevníkov všetkých vekových kategórií z celého sveta. Známa je svojou modlitbou a spevmi.',
                1 => 'Každý rok na prelome rokov organizuje európske stretnutie mladých, napríklad v poľskej Łodzi. Prihlásiť sa naň môžu aj mladí zo Slovenska. Stretnutie spája modlitbu, spev a pohostinnosť rodín.',
                2 => 'Kanál je určený mladým, ktorí chcú zažiť ekumenické spoločenstvo. Sledovaním budete vedieť o prihlasovaní na ďalšie stretnutia. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        973 => [
            'name' => 'Rímskokatolícka farnosť Bratislava – Prievoz',
            'address' => [
                'street' => 'Tomášikova 8',
                'postcode' => '821 03',
                'municipality_id' => 255,
                'latitude' => 48.1526149,
                'longitude' => 17.1648172,
            ],
            'body' => [
                0 => 'Rímskokatolícka farnosť Bratislava – Prievoz pôsobí v mestskej časti Ružinov. Zabezpečuje bohoslužby, vysluhovanie sviatostí a pastoráciu. Pripravuje aj formačné stretnutia.',
                1 => 'Ponúka pravidelné večery ohlasovania evanjelia a katechéz. Stretnutia sa konajú v priestoroch farnosti. Sú otvorené pre všetkých, ktorí chcú prehĺbiť svoju vieru.',
                2 => 'Kanál je určený veriacim z Prievozu a Ružinova. Sledovaním budete vedieť o katechézach a podujatiach farnosti. Miesto a čas nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        995 => [
            'name' => 'Úrad Ekumenickej pastoračnej služby Ministerstva vnútra SR',
            'body' => [
                0 => 'Úrad Ekumenickej pastoračnej služby zabezpečuje duchovnú službu v rezorte Ministerstva vnútra SR. Ide o ekumenickú službu viacerých cirkví. Slúži príslušníkom a zamestnancom rezortu.',
                1 => 'Pripravuje Svetový deň pamiatky obetí dopravných nehôd s ekumenickou bohoslužbou. Spoluorganizoval aj ekumenický koncert pre ukrajinské polosiroty a vdovy v Košiciach. Podujatia spájajú modlitbu a solidaritu.',
                2 => 'Kanál je určený príslušníkom rezortu a všetkým, ktorí si chcú uctiť obete nehôd. Sledovaním budete vedieť o ďalších podujatiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1012 => [
            'name' => 'Sestry Congregatio Jesu',
            'append' => 'Sestry pripravujú aj adventné duchovné obnovy, napríklad (NE)OBYČAJNÁ RADOSŤ, a víkendy tanečnej meditácie. Kanál je určený všetkým, ktorí hľadajú stíšenie a duchovný rast. Prihlasovanie nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
        ],
        1056 => [
            'name' => 'Slovenský spevácky zbor ADOREMUS',
            'body' => [
                0 => 'Slovenský spevácky zbor ADOREMUS je zbor zameraný na sakrálnu hudbu. Sídli vo Vrábľoch. Koncertuje doma aj v ďalších mestách Slovenska.',
                1 => 'Uvádza hudobno-poetické dielo Proglas – Slovanská óda, napríklad v kostole vo Vrábľoch a na adventnom koncerte. Pripravuje aj vianočné koncerty s hosťami, napríklad v bratislavskej katedrále. Pravidelne hľadá nových spevákov.',
                2 => 'Kanál je určený milovníkom zborového spevu a sakrálnej hudby. Sledovaním budete vedieť o ďalších koncertoch. Termín a miesto nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1134 => [
            'name' => 'Úrad pre apoštolát ochrany života v Košickej arcidiecéze',
            'address' => [
                'street' => 'Hlavná 89',
                'postcode' => '040 01',
                'municipality_id' => 1565,
                'latitude' => 48.7243096,
                'longitude' => 21.2570178,
            ],
            'body' => [
                0 => 'Úrad pre apoštolát ochrany života pôsobí v Košickej arcidiecéze. Spolupracuje s Arcidiecéznym centrom pre rodinu v Košiciach. Venuje sa hodnote života a rodinám v ťažkých situáciách.',
                1 => 'Pripravuje víkendové oázy pre bezdetných manželov, ktoré majú už niekoľko ročníkov. Organizuje aj bioetickú konferenciu pre farmaceutov Aj lekárnik má dušu. Program spája odborné témy a duchovnú oporu.',
                2 => 'Kanál je určený manželom, zdravotníkom a farmaceutom. Sledovaním budete vedieť o nových oázach a konferenciách. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1300 => [
            'name' => 'Hnutie kresťanských rodín na Slovensku',
            'address' => [
                'street' => 'Francisciho 3',
                'postcode' => '811 08',
                'municipality_id' => 242,
                'latitude' => 48.147502,
                'longitude' => 17.1190923,
            ],
            'body' => [
                0 => 'Hnutie kresťanských rodín na Slovensku spája rodiny, ktoré chcú žiť vieru v každodennom živote. Pripravuje formačné programy pre manželov. Pôsobí po celom Slovensku.',
                1 => 'Organizuje duchovné cvičenia pre manželov Originálne manželské rekolekcie. Konajú sa viackrát do roka. Pomáhajú manželom obnoviť vzťah a postaviť ho na Bohu.',
                2 => 'Kanál je určený manželským párom. Sledovaním budete vedieť o nových termínoch rekolekcií. Prihlasovanie nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1346 => [
            'name' => 'Fórum kresťanských inštitúcií, o.z',
            'body' => [
                0 => 'Fórum kresťanských inštitúcií je občianske združenie, ktoré spája kresťanské organizácie na Slovensku. Venuje sa verejnému životu, rodine a hodnotám. Prináša aj projekt Kresťan na vidieku.',
                1 => 'Pripravuje Deň rodiny, pred ktorým chce počuť rodiny, čím dnes žijú. Organizuje debaty, napríklad o odkaze Nežnej revolúcie v Košiciach. Pripravuje aj stretnutie Vy ste svetlo sveta.',
                2 => 'Kanál je určený rodinám a všetkým, ktorých zaujíma verejný život. Sledovaním budete vedieť o nových debatách a podujatiach. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1359 => [
            'name' => 'katRande.org',
            'body' => [
                0 => 'katRande.org je katolícka zoznamovacia platforma pre slobodných. Okrem webu pripravuje aj stretnutia naživo. Pomáha slobodným spoznať ľudí s podobnými hodnotami.',
                1 => 'Organizuje speed dating v rôznych mestách, napríklad v Trenčíne, Nitre a Žiline. Pripravuje aj Katarínsku zábavu pre slobodných. Na podujatia je potrebná registrácia.',
                2 => 'Kanál je určený slobodným katolíkom. Sledovaním budete vedieť o ďalších stretnutiach. Registráciu nájdete vždy v detaile podujatia.',
            ],
        ],
        1371 => [
            'name' => 'Gréckokatolícka evanjelizačná škola sv. Mikuláša',
            'body' => [
                0 => 'Gréckokatolícka evanjelizačná škola sv. Mikuláša pôsobí v Prešove. Pripravuje duchovné a formačné programy. Venuje sa najmä rodičom a rodinám.',
                1 => 'Organizuje Kurz Samuel, duchovnú obnovu pre rodičov s o. Jozefom Marettom. Kurz sa venuje výchove detí vo viere a v rodine. Koná sa opakovane počas roka.',
                2 => 'Kanál je určený rodičom, ktorí chcú vychovávať deti vo viere. Sledovaním budete vedieť o nových termínoch kurzu. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1387 => [
            'name' => 'Katolícke biblické dielo na Slovensku',
            'phone' => '+421 915 909 914',
            'body' => [
                0 => 'Katolícke biblické dielo na Slovensku podporuje čítanie a štúdium Svätého písma. Pripravuje kurzy, webináre a biblické školy. Pôsobí z Bratislavy pre celé Slovensko.',
                1 => 'Pravidelne ponúka biblické webináre, napríklad cyklus o Jozefovi Egyptskom či o premieňajúcich slovách vzkrieseného Krista. V lete organizuje Letnú biblickú školu v Rodinkove. Program je určený laikom aj kňazom.',
                2 => 'Kanál je určený všetkým, ktorí chcú lepšie poznať Bibliu. Sledovaním budete vedieť o nových webinároch a kurzoch. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1450 => [
            'name' => 'Donum vitae',
            'body' => [
                0 => 'Donum vitae je slovenská mimovládna organizácia, ktorá sa venuje ochrane a hodnote života. Jej zakladateľom bol lekár Miroslav Mikolášik. Venuje sa aj prirodzenému plánovaniu rodičovstva.',
                1 => 'Pripravuje kurzy a prednášky, napríklad Láska, sexualita a plodnosť. Učí aj Billingsovu ovulačnú metódu. Kurzy sú určené manželom, snúbencom a mladým.',
                2 => 'Kanál je určený párom a všetkým, ktorí chcú lepšie porozumieť plodnosti. Sledovaním budete vedieť o nových kurzoch. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1460 => [
            'name' => 'Kongregácia sestier dominikánok bl. Imeldy',
            'address' => [
                'street' => 'Parková 27',
                'postcode' => '821 05',
                'municipality_id' => 242,
                'latitude' => 48.1445445,
                'longitude' => 17.1691731,
            ],
            'body' => [
                0 => 'Kongregácia sestier dominikánok bl. Imeldy je rehoľné spoločenstvo. Jej poslaním je kontemplovať, čerpať pravdu v načúvaní Bohu a darovať druhým ovocie kontemplácie. Sestry pôsobia na viacerých miestach Slovenska.',
                1 => 'Ponúkajú mladým ženám kurz Povolaným nablízku na rozlišovanie povolania. Kurz pomáha hľadať odpoveď na otázku, k čomu ma Boh volá. Otvára sa opakovane.',
                2 => 'Kanál je určený mladým ženám, ktoré hľadajú svoje povolanie. Sledovaním budete vedieť o otvorení nového kurzu. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1876 => [
            'name' => 'Slovenská provincia Spoločnosti Ježišovej',
            'append' => 'Jezuiti dnes pripravujú aj pravidelné modlitby a spevy z Taizé a pozývajú veriacich k modlitbe, napríklad pred provinčnou kongregáciou. Kanál je určený všetkým, ktorým je blízka ignaciánska spiritualita. Miesto a čas nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
        ],
        1877 => [
            'name' => 'tím SingleKatolici.sk',
            'append' => 'Kanál je určený slobodným katolíkom z celého Slovenska. Tím pripravuje napríklad sväté omše za bezdetných manželov a za slobodných spojené so spoločenskými hrami či online stretnutia. Termíny nájdete vždy v detaile podujatia.',
        ],
        1878 => [
            'name' => 'Združenie Chemin Neuf',
            'append' => 'Kanál je určený manželským párom a všetkým, ktorí hľadajú spoločenstvo s ekumenickým rozmerom. Najbližšie termíny Kány a ďalších víkendov nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
        ],
        1883 => [
            'name' => 'Kongregácia sestier Služobníčok Najsvätejšej Panny Márie Nepoškvrnene počatej',
            'body' => [
                0 => 'Kongregácia sestier Služobníčok Najsvätejšej Panny Márie Nepoškvrnene počatej je rehoľné spoločenstvo. Na Slovensku má viacero komunít. Venuje sa aj mladým ženám, ktoré hľadajú povolanie.',
                1 => 'Organizuje duchovné víkendy pre dievčatá so záujmom o duchovné povolanie, napríklad Pripravme cestu Pánovi. Víkend ponúka modlitbu, rozhovor a spoznanie života sestier. Koná sa v adventnom období.',
                2 => 'Kanál je určený dievčatám a mladým ženám. Sledovaním budete vedieť o ďalších víkendoch. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1896 => [
            'name' => 'Coworking Poprad',
            'address' => [
                'street' => 'Námestie svätého Egídia 11/25',
                'postcode' => '058 01',
                'municipality_id' => 2763,
                'latitude' => 49.055997,
                'longitude' => 20.303851,
            ],
        ],
        1906 => [
            'name' => 'Slovenská a česká provincia Spoločnosti Božieho Slova',
            'address' => [
                'street' => 'Kalvária 3',
                'postcode' => '949 01',
                'municipality_id' => 2333,
                'latitude' => 48.2982161,
                'longitude' => 18.0916092,
            ],
            'body' => [
                0 => 'Slovenská a česká provincia Spoločnosti Božieho Slova združuje misionárov verbistov na Slovensku a v Česku. Život zasvätený misiám je pre nich vzácny. Provincia má centrum na Kalvárii v Nitre.',
                1 => 'Na Kalvárii v Nitre slávi obnovu a skladanie sľubov seminaristov. Pripomenula si aj 150 rokov Spoločnosti Božieho Slova. Pripravuje aj iniciatívy pre rodiny, ako bola výzva Postav miesto pre Ježiša.',
                2 => 'Kanál je určený priaznivcom misií a veriacim z Nitry. Sledovaním budete vedieť o slávnostiach a misijných podujatiach. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1912 => [
            'name' => 'Farnosť Spišské Bystré',
            'phone' => '0904 355 904',
            'address' => [
                'street' => 'Michalská 1/3',
                'postcode' => '059 18',
                'municipality_id' => 3244,
                'latitude' => 48.9920657,
                'longitude' => 20.2432164,
            ],
            'body' => [
                0 => 'Farnosť Spišské Bystré je rímskokatolícka farnosť v Spišskej diecéze. Zabezpečuje bohoslužby a vysluhovanie sviatostí v obci. Jej farským kostolom je Kostol sv. Michala archanjela.',
                1 => 'Farnosť obnovila svoj kostol a pripravila jeho slávnostnú konsekráciu. Slávnosť spojila farníkov aj hostí z okolia. Pripomína význam kostola pre život obce.',
                2 => 'Kanál je určený veriacim zo Spišského Bystrého a okolia. Sledovaním budete vedieť o slávnostiach a podujatiach farnosti. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1916 => [
            'name' => 'Vydavateľstvo Wojtyla.sk',
            'address' => [
                'street' => 'Športovcov 1445/6',
                'postcode' => '054 01',
                'municipality_id' => 1831,
                'latitude' => 49.0289385,
                'longitude' => 20.5952952,
            ],
        ],
        1920 => [
            'name' => 'Chrámový spevácky zbor Cantica Sacra Tyrnaviae',
            'body' => [
                0 => 'Chrámový spevácky zbor Cantica Sacra Tyrnaviae je trnavský zbor zameraný na chrámovú hudbu. Spieva pri bohoslužbách a cirkevných slávnostiach. Jeho názov odkazuje na Trnavu.',
                1 => 'Zbor oslavuje 50 rokov od obnovenia svojej činnosti. Pri tejto príležitosti pripravil slávnostný program. Na podujatie sa možno prihlásiť vopred.',
                2 => 'Kanál je určený milovníkom sakrálnej hudby z Trnavy a okolia. Sledovaním budete vedieť o koncertoch a slávnostiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1922 => [
            'name' => 'Miestny organizačný výbor Svetových dní mládeže v Soule 2027',
            'body' => [
                0 => 'Miestny organizačný výbor Svetových dní mládeže v Soule 2027 pripravuje celosvetové stretnutie katolíckej mládeže s pápežom. Stretnutie sa uskutoční v Južnej Kórei. Výbor koordinuje registráciu a program.',
                1 => 'Spustil oficiálnu registráciu mladých na SDM v Soule 2027. Vyhlásil aj súťaž o hymnu stretnutia, do ktorej sa môžu zapojiť mladí z celého sveta. Postupne zverejňuje informácie o programe.',
                2 => 'Kanál je určený mladým a ich vedúcim, ktorí sa chystajú do Soulu. Sledovaním budete vedieť o novinkách k stretnutiu. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1928 => [
            'name' => 'Filozofická fakulta Katolíckej univerzity v Ružomberku',
            'address' => [
                'street' => 'Hrabovská cesta 1B',
                'postcode' => '034 01',
                'municipality_id' => 3053,
                'latitude' => 49.0825123,
                'longitude' => 19.280265,
            ],
            'body' => [
                0 => 'Filozofická fakulta Katolíckej univerzity v Ružomberku ponúka štúdium humanitných a spoločenských vied. Kladie dôraz na osobnostný rozvoj a individuálny prístup. Jej heslo je Formujeme mysle a srdcia.',
                1 => 'Organizuje odborné konferencie, napríklad k 250. výročiu vzniku troch slovenských diecéz. Konferencie spájajú historikov, teológov a študentov. Konajú sa v Ružomberku.',
                2 => 'Kanál je určený študentom, uchádzačom a odbornej verejnosti. Sledovaním budete vedieť o konferenciách a podujatiach fakulty. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1930 => [
            'name' => 'Centrum pre rodinu- Malé Rodinkovo a Congregatio Jesu',
            'body' => [
                0 => 'Centrum pre rodinu – Malé Rodinkovo v Malinove vytvára priestor pre ľudí a rodiny. Spolupracuje so sestrami Congregatio Jesu. Pripravuje duchovné a formačné programy.',
                1 => 'Organizuje adventné duchovné obnovy pre ženy, napríklad Pozvané bdieť. Obnova ponúka stíšenie a prípravu na Vianoce. Koná sa v Malinove.',
                2 => 'Kanál je určený ženám a rodinám z Bratislavy a okolia. Sledovaním budete vedieť o ďalších obnovách. Prihlasovanie nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1933 => [
            'name' => 'Bratislava Mozart Festival',
            'body' => [
                0 => 'Bratislava Mozart Festival prináša nové pohľady na tvorbu Mozarta a jeho súčasníkov. Ponúka kvalitné hudobné produkcie domácich aj zahraničných umelcov. Koná sa v Bratislave.',
                1 => 'Festival otvára napríklad Mozartovo Requiem v Katedrále sv. Martina. Koncerty sa konajú v historických priestoroch mesta. Program sa venuje klasickej hudbe 18. storočia.',
                2 => 'Kanál je určený milovníkom klasickej hudby. Sledovaním budete vedieť o programe ďalšieho ročníka. Termíny a miesta nájdete vždy v detaile podujatia.',
            ],
        ],
        1937 => [
            'name' => 'Záhorské osvetové stredisko',
            'body' => [
                0 => 'Záhorské osvetové stredisko je kultúrno-osvetová inštitúcia so sídlom v Senici. Pôsobí v regióne Záhorie. Podporuje záujmovú umeleckú činnosť a kultúrny život.',
                1 => 'Pripravuje výstavy, súťaže a vzdelávacie aktivity. Usporiadalo napríklad výstavu, ktorá približuje kňaza Antona Srholca prostredníctvom fotografií. Výstavy sú otvorené verejnosti.',
                2 => 'Kanál je určený obyvateľom Záhoria a milovníkom kultúry. Sledovaním budete vedieť o nových výstavách. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1944 => [
            'name' => 'Diecézna charita Rožňava',
            'body' => [
                0 => 'Diecézna charita Rožňava je charitatívna organizácia Rožňavskej diecézy. Pomáha ľuďom v núdzi a poskytuje sociálne služby. Pôsobí v mnohých obciach južného a stredného Slovenska.',
                1 => 'Rozširuje svoje služby, napríklad otvorením denného stacionára v Kokave nad Rimavicou. Stacionár rozšíri sociálne služby v regióne. Otvorenie je spojené so slávnosťou.',
                2 => 'Kanál je určený klientom, dobrovoľníkom a podporovateľom charity. Sledovaním budete vedieť o nových službách a podujatiach. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
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
