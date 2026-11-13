<?php

use App\Models\Canal;
use App\Models\Municipality;
use App\Services\SystemLog\Recorder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Deviata dávka (november 2026): ďalších 60 importovaných kanálov v poradí
 * (#1119 – #1192). Kanály so všeobecným AI popisom alebo len „organizátor
 * podujatí" dostanú tri odseky podľa podujatí kanála a verejne známych faktov.
 *
 * Opravy: farnosť Obišovce, Podunajské Biskupice, spišský biskup a Biskupský
 * úrad boli „Celé Slovensko", #1124 Františkánska rodina mala ako web upn.gov.sk.
 *
 * Kanál musí sedieť ID aj názvom; prevzatý, zlúčený či zmazaný sa preskočí.
 */
return new class extends Migration
{
    private const CLOSING = 'Podrobnosti nájdete vždy v detaile podujatia.';

    /** @var array<int, array<string, mixed>> */
    private const PROFILES = [
        1192 => [
            'name' => 'Spoločenstvo Človek a Viera',
            'body' => [
                0 => 'Spoločenstvo Človek a viera je spoločenstvo fotografov, ktoré dokumentuje náboženský život na Slovensku a v zahraničí.',
                1 => 'Pripravuje výstavy veľkoformátových fotografií, napríklad výstavu z návštevy pápeža Františka na Slovensku v Spišskej Kapitule alebo vernisáž v Dome Quo Vadis v Bratislave.',
                2 => 'Kanál je určený priateľom dokumentárnej fotografie. Sledovaním budete vedieť o nových výstavách. '.self::CLOSING,
            ],
        ],
        1190 => [
            'name' => 'Rodisko blahoslavenej Anny Kolesárovej',
            'body' => [
                0 => 'Rodisko blahoslavenej Anny Kolesárovej je vo Vysokej nad Uhom v okrese Michalovce. Anna Kolesárová zomrela v roku 1944, keď sa bránila vojakovi. Blahorečili ju v Košiciach v roku 2018.',
                1 => 'Každý rok sa tu koná Púť radosti s mladými a biskupmi. Pútnici navštevujú rodný dom a hrob blahoslavenej.',
                2 => 'Kanál je určený pútnikom a mladým. Sledovaním budete vedieť o púťach do Vysokej nad Uhom. '.self::CLOSING,
            ],
        ],
        1189 => [
            'name' => 'farnosť Obišovce',
            'municipality_id' => 2452,
            'body' => [
                0 => 'Farnosť Obišovce v okrese Košice-okolie patrí do Košickej arcidiecézy. Kostol je sanktuáriom Ružencovej Panny Márie s milostivým obrazom.',
                1 => 'Každý rok v októbri sa tu koná odpustová slávnosť k Ružencovej Panne Márii. Prichádzajú tisíce pútnikov a púť prenáša TV LUX.',
                2 => 'Kanál je určený pútnikom a veriacim z okolia Košíc. Sledovaním budete vedieť o púťach do Obišoviec. '.self::CLOSING,
            ],
        ],
        1188 => [
            'name' => 'Teologická fakulta Sliezskej univerzity v Katoviciach',
            'body' => [
                0 => 'Teologická fakulta Sliezskej univerzity v Katoviciach je katolícka fakulta v Poľsku. Spolupracuje s teologickými fakultami na Slovensku.',
                1 => 'Spolupracuje na vedeckých konferenciách, napríklad na konferencii Ženy v dnešnej Cirkvi a spoločnosti v Prešove.',
                2 => 'Kanál je určený teológom a študentom. Sledovaním budete vedieť o konferenciách. '.self::CLOSING,
            ],
        ],
        1184 => [
            'name' => 'Gospeltalent Junior',
            'body' => [
                0 => 'Gospeltalent Junior je národná súťaž detských spevákov vo veku od 4 do 14 rokov. Je detskou vetvou súťaže Gospeltalent.',
                1 => 'Súťaž má verejné hlasovanie a finále s porotou, kde deti spievajú gospelové a kresťanské piesne.',
                2 => 'Kanál je určený deťom, rodičom a učiteľom spevu. Sledovaním budete vedieť o prihláškach a finále. '.self::CLOSING,
            ],
        ],
        1183 => [
            'name' => 'Arcidiecéza Buenos Aires',
            'body' => [
                0 => 'Arcidiecéza Buenos Aires je katolícka arcidiecéza v hlavnom meste Argentíny. Jej arcibiskupom bol do roku 2013 Jorge Mario Bergoglio, neskorší pápež František.',
                1 => 'Pripravovala modlitby za pápeža Františka, napríklad počas jeho choroby vo februári 2025.',
                2 => 'Kanál prináša správy o modlitbových iniciatívach arcidiecézy. '.self::CLOSING,
            ],
        ],
        1181 => [
            'name' => 'Dobry Pastier',
            'body' => [
                0 => 'Dobrý Pastier je katolícky portál a spoločenstvo, ktoré prináša duchovné články a pozvánky pre mladých.',
                1 => 'Pozýva na púte a stretnutia, napríklad na cestu mladých do 35 rokov do Taizé.',
                2 => 'Kanál je určený mladým. Sledovaním budete vedieť o cestách a stretnutiach. '.self::CLOSING,
            ],
        ],
        1180 => [
            'name' => 'Deviatnik saleziánskej rodiny k Panne Márii Pomocnici',
            'body' => [
                0 => 'Deviatnik saleziánskej rodiny k Panne Márii Pomocnici je modlitbová príprava na jej sviatok 24. mája. Spájajú sa v ňom saleziáni, saleziánky a ich spolupracovníci.',
                1 => 'Pripravuje stretnutia a podujatia počas deviatnika, napríklad stretnutie Byť deťmi.',
                2 => 'Kanál je určený saleziánskej rodine a jej priateľom. Sledovaním budete vedieť o programe deviatnika. '.self::CLOSING,
            ],
        ],
        1179 => [
            'name' => 'Kostol povýšenia Svätého kríža',
            'body' => [
                0 => 'Kostol Povýšenia Svätého Kríža je farský kostol v Dolnom Kubíne. Je známy kvalitným organom.',
                1 => 'Každý rok sa tu koná Kubínsky organový festival s koncertmi slovenských a zahraničných organistov.',
                2 => 'Kanál je určený milovníkom organovej hudby. Sledovaním budete vedieť o koncertoch festivalu. '.self::CLOSING,
            ],
        ],
        1178 => [
            'name' => 'Mariánski ctitelia',
            'body' => [
                0 => 'Mariánski ctitelia sú skupina veriacich z Bratislavy, ktorá šíri úctu k Panne Márii.',
                1 => 'Organizujú pešie púte, napríklad púť z Bratislavy cez Kačín do Marianky.',
                2 => 'Kanál je určený pútnikom. Sledovaním budete vedieť o termínoch peších pútí. '.self::CLOSING,
            ],
        ],
        1176 => [
            'name' => 'Spišský biskup Mons. František Trstenský',
            'municipality_id' => 3246,
            'body' => [
                0 => 'Mons. František Trstenský je spišský diecézny biskup od roku 2020. Sídli v Spišskej Kapitule.',
                1 => 'Pozýva na diecézne slávnosti, napríklad na oslavu sviatosti manželstva pre manželské páry zo Spiša.',
                2 => 'Kanál je určený veriacim Spišskej diecézy. Sledovaním budete vedieť o pozvaniach biskupa. '.self::CLOSING,
            ],
        ],
        1175 => [
            'name' => 'Biskupský úrad Spišskej diecézy',
            'municipality_id' => 3246,
            'body' => [
                0 => 'Biskupský úrad Spišskej diecézy sídli v Spišskej Kapitule pri Spišskom Podhradí. Spravuje diecézu, ktorá zahŕňa Spiš, Oravu a Liptov.',
                1 => 'Oznamuje diecézne iniciatívy, napríklad vznik miesta ticha a modlitby pri Spišskej Kapitule.',
                2 => 'Kanál je určený veriacim Spišskej diecézy. '.self::CLOSING,
            ],
        ],
        1173 => [
            'name' => 'Združenie mariánskej mládeže',
            'body' => [
                0 => 'Združenie mariánskej mládeže je katolícke hnutie mladých pri Misijnej spoločnosti sv. Vincenta de Paul. Má spoločenstvá v mnohých mestách Slovenska.',
                1 => 'Šíri úctu k blahoslavenému Jánovi Havlíkovi z Duboviec, ktorý bol členom ZMM. Organizuje stretnutia a tábory.',
                2 => 'Kanál je určený mladým. Sledovaním budete vedieť o spomienkach na bl. Jána Havlíka. '.self::CLOSING,
            ],
        ],
        1172 => [
            'name' => 'Cirkvi bratskej',
            'body' => [
                0 => 'Cirkev bratská je evanjelikálna protestantská cirkev. Má zbory v Bratislave a ďalších mestách Slovenska.',
                1 => 'Zapája sa do ekumenických podujatí, napríklad Svetového dňa modlitieb žien.',
                2 => 'Kanál je určený členom a priateľom Cirkvi bratskej. '.self::CLOSING,
            ],
        ],
        1171 => [
            'name' => 'Inštitút duchovného sprevádzania',
            'body' => [
                0 => 'Inštitút duchovného sprevádzania je česká vzdelávacia iniciatíva so sídlom v Olomouci.',
                1 => 'Pripravuje viacročný Kurz duchovného sprevádzania pre kňazov, rehoľníkov a laikov.',
                2 => 'Kanál je určený tým, ktorí sa chcú vzdelávať v duchovnom sprevádzaní. '.self::CLOSING,
            ],
        ],
        1170 => [
            'name' => 'Občianske združenie Benediktíni v Krásnej',
            'body' => [
                0 => 'Občianske združenie Benediktíni v Krásnej v Košiciach pripomína benediktínsky kláštor v Krásnej. Šíri povedomie o jeho histórii.',
                1 => 'Pripravuje spomienkové podujatia, napríklad k výročiu narodenia kňaza a morálneho teológa Juraja Semivana.',
                2 => 'Kanál je určený veriacim z Krásnej a priateľom histórie. '.self::CLOSING,
            ],
        ],
        1169 => [
            'name' => 'Vinárstvo MEA GRATI',
            'body' => [
                0 => 'Vinárstvo MEA GRATI je slovenské rodinné vinárstvo.',
                1 => 'Pripravuje degustácie a podujatia, napríklad večer IN VINO GRATITUDO pri jubileu diecézy.',
                2 => 'Kanál je určený milovníkom vína. Sledovaním budete vedieť o degustáciách. '.self::CLOSING,
            ],
        ],
        1167 => [
            'name' => 'Farnosť v Novom Meste nad Váhom',
            'body' => [
                0 => 'Farnosť v Novom Meste nad Váhom patrí do Nitrianskej diecézy. Farským kostolom je Kostol Povýšenia sv. Kríža.',
                1 => 'Každý rok v septembri pripravuje Novomestskú púť k sviatku Sedembolestnej Panny Márie.',
                2 => 'Kanál je určený veriacim z Nového Mesta nad Váhom a pútnikom. '.self::CLOSING,
            ],
        ],
        1165 => [
            'name' => 'Farnosť Bratislava-Podunajské Biskupice',
            'municipality_id' => 252,
            'body' => [
                0 => 'Farnosť Bratislava-Podunajské Biskupice patrí do Bratislavskej arcidiecézy. Farským kostolom je Kostol sv. Mikuláša.',
                1 => 'Pripravuje farské púte, napríklad púť po stopách svätých v Taliansku.',
                2 => 'Kanál je určený veriacim z Podunajských Biskupíc. '.self::CLOSING,
            ],
        ],
        1162 => [
            'name' => 'Kancelária Národnej rady SR',
            'body' => [
                0 => 'Kancelária Národnej rady SR zabezpečuje činnosť parlamentu. Sídli na Bratislavskom hrade.',
                1 => 'Pripravuje podujatia na hrade, napríklad slávnostné odhalenie súsošia sv. Cyrila, Metoda a Gorazda v septembri 2023.',
                2 => 'Kanál prináša oznamy o podujatiach v priestoroch Národnej rady. '.self::CLOSING,
            ],
        ],
        1161 => [
            'name' => 'Nová kresťanská škola Ako Doma',
            'body' => [
                0 => 'Nová kresťanská škola Ako Doma je základná škola v Devínskej Novej Vsi v Bratislave. Vyučuje v malých triedach.',
                1 => 'Prijíma prvákov a pozýva rodičov na stretnutia a dni otvorených dverí.',
                2 => 'Kanál je určený rodičom, ktorí hľadajú kresťanskú školu. '.self::CLOSING,
            ],
        ],
        1158 => [
            'name' => 'Národné centrum pre pastoráciu rodín',
            'body' => [
                0 => 'Národné centrum pre pastoráciu rodín je inštitúcia Konferencie episkopátu Poľska. Podporuje rodiny a manželstvá.',
                1 => 'Organizovalo putovanie relikvií blahoslavenej rodiny Ulmovcov po poľských diecézach.',
                2 => 'Kanál prináša správy o pastorácii rodín. '.self::CLOSING,
            ],
        ],
        1157 => [
            'name' => 'Farnosť svätej Alžbety',
            'body' => [
                0 => 'Farnosť sv. Alžbety v Košiciach má farský kostol v Katedrále sv. Alžbety. Je to najväčší kostol na Slovensku a sídlo košického arcibiskupa.',
                1 => 'Pripravuje slávnosti a omše, napríklad poďakovanie za úrodu. Konajú sa tu aj koncerty.',
                2 => 'Kanál je určený veriacim z Košíc. Sledovaním budete vedieť o slávnostiach v Dóme. '.self::CLOSING,
            ],
        ],
        1156 => [
            'name' => 'farské spoločenstvo',
            'body' => [
                0 => 'Farské spoločenstvo v Kežmarku združuje veriacich z miestnej farnosti.',
                1 => 'Pripravuje prednášky a stretnutia, napríklad o význame Druhého vatikánskeho koncilu v jubilejnom roku.',
                2 => 'Kanál je určený veriacim z Kežmarku. '.self::CLOSING,
            ],
        ],
        1151 => [
            'name' => 'Farnosť Všetkých svätých vo Veľkej Lodine',
            'body' => [
                0 => 'Farnosť Všetkých svätých vo Veľkej Lodine v okrese Košice-okolie patrí do Košickej arcidiecézy. Spravuje aj veriacich z Malej Lodiny.',
                1 => 'Pripravuje spoločné podujatia s obcami, napríklad Deň rodákov v Malej Lodine.',
                2 => 'Kanál je určený veriacim z Lodín a rodákom. '.self::CLOSING,
            ],
        ],
        1150 => [
            'name' => 'kresťania dnes',
            'body' => [
                0 => 'Kresťania dnes je spoločenstvo veriacich z Gelnice.',
                1 => 'Pripravuje farské slávnosti, napríklad slávnosť Povýšenia sv. Kríža.',
                2 => 'Kanál je určený veriacim z Gelnice a okolia. '.self::CLOSING,
            ],
        ],
        1148 => [
            'name' => 'Košický Úrad pre apoštolát ochrany života',
            'body' => [
                0 => 'Úrad pre apoštolát ochrany života Košickej arcidiecézy podporuje rodiny a ochranu života od počatia.',
                1 => 'Pripravuje omše a pobyty, napríklad svätú omšu za zdravotníkov alebo víkendovú oázu pre bezdetné páry v Bardejovských Kúpeľoch.',
                2 => 'Kanál je určený rodinám a zdravotníkom. '.self::CLOSING,
            ],
        ],
        1146 => [
            'name' => 'Teologický inštitút Teologickej fakulty Katolíckej univerzity (KU) v Spišskej Kapitule-Spišskom Podhradí',
            'body' => [
                0 => 'Teologický inštitút Teologickej fakulty KU sídli v Spišskej Kapitule pri Spišskom Podhradí. Vzdeláva kandidátov kňazstva Spišskej diecézy.',
                1 => 'Každý rok pripravuje Dni kresťanskej kultúry s prednáškami o umení a literatúre.',
                2 => 'Kanál je určený študentom a záujemcom o kresťanskú kultúru. '.self::CLOSING,
            ],
        ],
        1145 => [
            'name' => 'Fara Miloslavov',
            'body' => [
                0 => 'Fara Miloslavov slúži veriacim v Miloslavove a Alžbetinom Dvore pri Bratislave.',
                1 => 'Pozýva na večery chvál a duchovné prednášky, napríklad na Lamačské chvály alebo prednášku pre slobodných.',
                2 => 'Kanál je určený veriacim z Miloslavova a okolia. '.self::CLOSING,
            ],
        ],
        1143 => [
            'name' => 'Farnosť v Pezinku',
            'body' => [
                0 => 'Farnosť v Pezinku patrí do Bratislavskej arcidiecézy. Farským kostolom je Kostol Nanebovzatia Panny Márie.',
                1 => 'Pripravuje modlitby a slávnosti, napríklad pripojenie k Ekumenickému modlitbovému bdeniu v Ríme.',
                2 => 'Kanál je určený veriacim z Pezinka. '.self::CLOSING,
            ],
        ],
        1140 => [
            'name' => 'Občianske združenie FILMSTORY',
            'body' => [
                0 => 'Občianske združenie FILMSTORY podporuje tvorbu krátkych hraných filmov.',
                1 => 'Vyhlasuje súťaž námetov na 20-minútové filmy. Víťazné námety pomáha natočiť.',
                2 => 'Kanál je určený scenáristom a filmárom. Sledovaním budete vedieť o súťaži. '.self::CLOSING,
            ],
        ],
        1139 => [
            'name' => 'Matej Bartoš',
            'municipality_id' => 663,
            'body' => [
                0 => 'Matej Bartoš je organista z Oravy.',
                1 => 'Stojí za medzinárodnou interpretačnou súťažou v hre na organe Orava Organ Prix v Dolnom Kubíne.',
                2 => 'Kanál je určený organistom a milovníkom organovej hudby. '.self::CLOSING,
            ],
        ],
        1137 => [
            'name' => 'Kláštor minoritov',
            'body' => [
                0 => 'Kláštor minoritov v Brehove v okrese Trebišov patrí reholi menších bratov konventuálov.',
                1 => 'Pripravuje duchovné cvičenia a exercície, napríklad kurz, ktorý vytvoril kňaz Rímskej diecézy.',
                2 => 'Kanál je určený tým, ktorí hľadajú duchovné cvičenia. '.self::CLOSING,
            ],
        ],
        1136 => [
            'name' => 'Saleziánske centrum prijatia, výchovy a vzdelávania',
            'body' => [
                0 => 'Saleziánske centrum prijatia, výchovy a vzdelávania je dielo sestier saleziánok v Bratislave.',
                1 => 'Pripravuje projekty pre mladých a rodiny, napríklad projekt Emauzy DO.MA, ktorý približuje Sväté písmo do života.',
                2 => 'Kanál je určený mladým a rodinám. '.self::CLOSING,
            ],
        ],
        1135 => [
            'name' => 'vedenie arcidiecézy',
            'body' => [
                0 => 'Vedenie Trnavskej arcidiecézy sídli v Trnave. Arcidiecézu vedie arcibiskup Ján Orosch.',
                1 => 'Pripravuje slávnosti arcidiecézy, napríklad oslavu 90. narodenín emeritného arcibiskupa Jána Sokola.',
                2 => 'Kanál je určený veriacim Trnavskej arcidiecézy. '.self::CLOSING,
            ],
        ],
        1133 => [
            'name' => 'Vatikánska bazilika',
            'body' => [
                0 => 'Vatikánska bazilika sv. Petra je hlavný kostol pápeža. Stojí nad hrobom apoštola Petra.',
                1 => 'Pripravuje liturgie a modlitby, napríklad každodenné modlitby počas zhromaždenia biskupskej synody v októbri 2023.',
                2 => 'Kanál prináša pozvánky na liturgie v Bazilike sv. Petra. '.self::CLOSING,
            ],
        ],
        1132 => [
            'name' => 'Rímskokatolícka farnosť Kolačkov',
            'body' => [
                0 => 'Rímskokatolícka farnosť Kolačkov v okrese Stará Ľubovňa patrí do Spišskej diecézy.',
                1 => 'Každú jeseň pripravuje ukončenie motorkárskej sezóny s požehnaním motorkárov.',
                2 => 'Kanál je určený motorkárom a veriacim z Kolačkova. '.self::CLOSING,
            ],
        ],
        1131 => [
            'name' => 'Rada pre mládež pri Konferencii biskupov Slovenska',
            'body' => [
                0 => 'Rada pre mládež Konferencie biskupov Slovenska koordinuje pastoráciu mladých v slovenských diecézach.',
                1 => 'Podporuje celoslovenské projekty, napríklad evanjelizačné koncerty Godzone tour.',
                2 => 'Kanál je určený mladým a animátorom. '.self::CLOSING,
            ],
        ],
        1130 => [
            'name' => 'o. Slavomír Palfi',
            'body' => [
                0 => 'O. Slavomír Palfi je gréckokatolícky kňaz Prešovskej archieparchie.',
                1 => 'Vedie kurzy a duchovné obnovy, napríklad kurz Služba oslobodenia v hoteli Javorná pri Drienici.',
                2 => 'Kanál je určený veriacim, ktorí hľadajú duchovné obnovy. '.self::CLOSING,
            ],
        ],
        1129 => [
            'name' => 'Kláštor Misijných sestier',
            'body' => [
                0 => 'Kláštor Misijných sestier Služobníc Ducha Svätého je v Ivanke pri Nitre.',
                1 => 'Pripravuje duchovné obnovy, napríklad obnovu pre dievčatá so sv. Teréziou z Lisieux.',
                2 => 'Kanál je určený dievčatám a ženám, ktoré hľadajú duchovné obnovy. '.self::CLOSING,
            ],
        ],
        1126 => [
            'name' => 'Samuel Brečka',
            'body' => [
                0 => 'Samuel Brečka je organizátor púte za kňazov v Šaštíne.',
                1 => 'Púť za kňazov sa koná v Národnej svätyni – Bazilike Sedembolestnej Panny Márie v Šaštíne.',
                2 => 'Kanál je určený pútnikom, ktorí sa modlia za kňazov. '.self::CLOSING,
            ],
        ],
        1125 => [
            'name' => 'Duchovné a formačné centrum Ivana Mastiliaka CSsR',
            'body' => [
                0 => 'Duchovné a formačné centrum Ivana Mastiliaka CSsR je pri kláštore redemptoristov v Stropkove. Nesie meno gréckokatolíckeho redemptoristu, ktorý trpel v komunistickom väzení.',
                1 => 'Pripravuje duchovné cvičenia, napríklad pre zasvätené osoby.',
                2 => 'Kanál je určený zasväteným aj laikom. '.self::CLOSING,
            ],
        ],
        1124 => [
            'name' => 'Františkánska rodina na Slovensku',
            'fix_website' => null,
        ],
        1122 => [
            'name' => 'Vydavateľstvo LÚČ',
            'body' => [
                0 => 'Vydavateľstvo LÚČ je katolícke vydavateľstvo z Bratislavy. Vydáva diela kardinála Jána Chryzostoma Korca.',
                1 => 'Každý rok v októbri pripravuje spomienku na kardinála Korca.',
                2 => 'Kanál je určený čitateľom kresťanskej literatúry. '.self::CLOSING,
            ],
        ],
        1120 => [
            'name' => 'Správa Tatranského národného parku',
            'body' => [
                0 => 'Správa Tatranského národného parku chráni prírodu Vysokých a Belianskych Tatier. Sídli v Tatranskej Štrbe.',
                1 => 'Spravuje Symbolický cintorín pri Popradskom plese a pripravuje spomienky na obete hôr.',
                2 => 'Kanál je určený návštevníkom Tatier. '.self::CLOSING,
            ],
        ],
        1119 => [
            'name' => 'Katedra náuky o rodine Teologickej fakulty Trnavskej univerzity',
            'body' => [
                0 => 'Katedra náuky o rodine pôsobí na Teologickej fakulte Trnavskej univerzity v Bratislave. Venuje sa manželstvu, rodine a pastorálnej teológii.',
                1 => 'Pripravuje konferencie, napríklad medzinárodnú konferenciu o counselingu v pastorálnej teológii.',
                2 => 'Kanál je určený teológom a študentom. '.self::CLOSING,
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
