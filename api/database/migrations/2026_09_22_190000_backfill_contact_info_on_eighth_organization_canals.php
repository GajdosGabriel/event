<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pokračovanie predošlých backfill_contact_info_on_*_organization_canals.php
 * migrácií — doplní web/email/telefón/adresu pre ďalších 27 neosobných
 * kanálov, zistené z ich oficiálnych webov 22. 9. 2026. Rovnaký guard,
 * rovnaká štruktúra dát a rovnaký jednosmerný prepis `body`.
 *
 * Kanály id=757 (Vincentská rodina — zastrešujúci pojem bez vlastnej
 * právnej subjektivity ani kontaktu) a id=569 (Spoločenstvo Nová nádej
 * — nepodarilo sa spoľahlivo overiť, ktorý z viacerých rovnomenných
 * subjektov na Slovensku záznam predstavuje) sa nedopĺňajú vôbec.
 *
 * Ďalšie duplicity: id 354 = bratia kapucíni (dáta zdieľa s id=611),
 * id 738 = OZ Bratislavská Kalvária (duplicita id=169/356/698/720/922 —
 * už 6. výskyt), id 184 = Rímskokatolícka bohoslovecká fakulta UK
 * (duplicita id=675), id 581 = UPeCe sv. Jozefa Freinademetza
 * (duplicita id=775), id 317 = Dominikánske mariánske centrum
 * (duplicita id=887), id 28 = KPVS (duplicita id=680/845), id 261 =
 * Biskupstvo Banská Bystrica (duplicita id=717).
 */
return new class extends Migration
{
    /**
     * @var array<int, array{
     *   name: string,
     *   municipality?: array{0:int,1:int},
     *   website?: array{0:?string,1:?string},
     *   email?: array{0:?string,1:string},
     *   phone?: array{0:?string,1:string},
     *   street?: array{0:?string,1:string},
     *   postcode?: array{0:?string,1:string},
     *   country?: array{0:?string,1:string},
     *   body?: string,
     * }>
     */
    private const CANALS = [
        354 => [
            // duplicita id=611 "Taliansky kultúrny inštitút a bratia kapucíni" (kapucínska časť)
            'name' => 'Bratia kapucíni',
            'website' => [null, 'https://kapucini.sk'],
            'email' => [null, 'bratislava@kapucin.sk'],
            'phone' => [null, '+421 2 59 30 38 00'],
            'street' => [null, 'Župné námestie 10'], 'postcode' => [null, '814 99'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Kapucíni prišli do Bratislavy v 17. storočí — v júli 1676 požiadal cisár Leopold I. o ich pozvanie. Kláštor a Kostol svätého Štefana na Župnom námestí postavili v rokoch 1708 – 1711 s podporou kniežaťa Imricha Esterházyho; pre pokles základov museli byť v rokoch 1735 – 1737 opravené.</p><p>Bratislavský kláštor je dnes najväčším miestom modlitby, služby a bratského spoločenstva provincie — pôsobia tu tri komunity: rehoľná komunita, študentát s postnoviciátom a provinciálna kúria.</p>',
        ],
        738 => [
            // duplicita id=169/356/698/720/922 "OZ Bratislavská Kalvária"
            'name' => 'Občianske združenie Bratislavská Kalvária a KZ Sprevádzajúci',
            'website' => [null, 'https://bratislavskakalvaria.sk'],
            'email' => [null, 'info@bratislavskakalvaria.sk'],
            'street' => [null, 'Oravská 1264/18'], 'postcode' => [null, '821 09'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Občianske združenie Bratislavská Kalvária, založené v roku 2019, sa venuje obnove a revitalizácii Bratislavskej Kalvárie — krížovej cesty z roku 1694. Koordinuje reštaurátorské a záchranné práce na jednotlivých zastaveniach v spolupráci s Kresťanským združením Sprevádzajúci.</p>',
        ],
        184 => [
            // duplicita id=675 "Rímskokatolícka cyrilometodská bohoslovecká fakulta UK – Kňazský seminár sv. Gorazda"
            'name' => 'Rímskokatolícka bohoslovecká fakulta Univerzity Komenského v Bratislave',
            'municipality' => [2333, 242], // Nitra -> Bratislava (skutočné sídlo fakulty)
            'website' => [null, 'https://frcth.uniba.sk'],
            'email' => [null, 'sd@frcth.uniba.sk'],
            'phone' => [null, '+421 2 32 777 120'],
            'street' => [null, 'Kapitulská 26'], 'postcode' => [null, '814 58'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Rímskokatolícka cyrilometodská bohoslovecká fakulta Univerzity Komenského píše svoju históriu od jesene 1936, keď prví bohoslovci prekročili jej brány, a počas komunizmu bola až do roku 1990 jedinou inštitúciou na Slovensku, ktorá vzdelávala a formovala budúcich kňazov. Fakulta dnes ponúka bakalárske, magisterské aj doktorandské štúdium katolíckej teológie a prevádzkuje viacero katedier.</p>',
        ],
        581 => [
            // duplicita id=775 "Univerzitné pastoračné centrum (UPeCe) sv. Jozefa Freinademetza"
            'name' => 'Univerzitné pastoračné centrum sv. Jozefa Freinademetza',
            'website' => [null, 'https://www.upcba.sk'],
            'email' => [null, 'contact@upcba.sk'],
            'phone' => [null, '+421 905 406 679'],
            'street' => [null, 'Staré Grunty 36'], 'postcode' => [null, '841 04'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Univerzitné pastoračné centrum svätého Jozefa Freinademetza v areáli vysokoškolských internátov Mlyny spravuje rehoľa Spoločnosť Božieho Slova (Verbisti) a slúži predovšetkým študentom Univerzity Komenského pod heslom „Ži život v plnosti". Centrum denne ponúka svätú omšu a voľnočasové aktivity ako stolný tenis, bedminton a florbal.</p>',
        ],
        317 => [
            // duplicita id=887 "Dominikánske mariánske centrum a Dominikánsky konvent"
            'name' => 'Dominikánske mariánske centrum',
            'website' => [null, 'https://dmc.sk'],
            'email' => [null, 'dmc@dmc.sk'],
            'phone' => [null, '(055) 623 01 37'],
            'street' => [null, 'Mäsiarska 6'], 'postcode' => [null, '040 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Dominikánske mariánske centrum sídli v kláštornom komplexe Dominikánskeho konventu Nanebovzatia Panny Márie v Košiciach, jednom z najstarších na Slovensku. Centrum založila v roku 1994 Slovenská dominikánska provincia s cieľom koordinovať šírenie modlitby ruženca — vedie kontakt s ruženčovými bratstvami na celom Slovensku, vydáva štvrťročný časopis Ruženec a organizuje duchovné obnovy vo farnostiach.</p>',
        ],
        28 => [
            // duplicita id=680/845 "Konfederácia politických väzňov Slovenska"
            'name' => 'Konfederácia politických väzňov Slovenska',
            'website' => [null, 'https://kpvs.forma.sk'],
            'email' => [null, 'kpvs.kpvs@gmail.com'],
            'phone' => [null, '02 5244 2321'],
            'street' => [null, 'Košická 5590/56'], 'postcode' => [null, '821 08'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Konfederácia politických väzňov Slovenska je občianske združenie založené v roku 1999, ktoré združuje bývalých politických väzňov a občanov postihnutých komunistickým režimom. Usiluje sa o odškodnenie krívd, zachovanie pamiatky nespravodlivo odsúdených a osádzanie pamätných tabúľ.</p>',
        ],
        261 => [
            // duplicita id=717 "Banskobystrické biskupstvo, Diecézne pastoračné centrum pre rodinu Banskobystrickej diecézy a Farnosť Staré Hory"
            'name' => 'Biskupstvo Banská Bystrica',
            'website' => [null, 'https://bbdieceza.sk'],
            'email' => [null, 'sekretariat.bb@rcc.sk'],
            'phone' => [null, '048 472 08 00'],
            'street' => [null, 'Námestie SNP 19'], 'postcode' => [null, '975 90'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Banskobystrická diecéza vznikla 13. marca 1776, keď cisárovná Mária Terézia bulou Regalium principum spolu s pápežom Piom VI. zriadili nové biskupstvo. Prvým banskobystrickým biskupom bol František Berchtold, diecéza pri vzniku mala 77 farností a okolo 290 filiálok a jej patrónom je svätý František Xaverský, ktorému je zasvätená aj katedrála v centre mesta.</p>',
        ],
        32 => [
            'name' => 'Farnosť Nitra – Kalvária',
            'website' => [null, 'https://kalvaria.verbisti.sk/www/'],
            'email' => [null, 'nitrakalvaria@svd.sk'],
            'phone' => [null, '0950 233 140'],
            'street' => [null, 'Kalvária 1'], 'postcode' => [null, '949 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Kostol Panny Márie na nitrianskej Kalvárii pravdepodobne pochádza z 15. storočia, prvá písomná zmienka je z roku 1506 a miesto sa stalo známym pútnickým miestom povestným zázračnými uzdraveniami. Po veľkej barokovej prestavbe v 18. storočí sem v roku 1765 prišli mnísi-nazaréni, ktorých rád Mária Terézia zrušila dekrétom v roku 1770.</p><p>Od roku 1859 tu pôsobil beneficiát a 8. septembra 1925 miesto prevzali verbisti, ktorí v roku 1928 postavili novú misijnú budovu. Počas komunizmu boli 3. – 4. mája 1950 kňazi a bratia násilne odvlečení do internačného tábora; samostatná farnosť bola kanonicky zriadená až 1. júla 1997 a dnes je miesto aktívnym pútnickým a pastoračným centrom.</p>',
        ],
        445 => [
            'name' => 'Františkáni - Slovensko',
            'website' => [null, 'https://frantiskani.sk/filakovo/'],
            'email' => [null, 'klastorfil@frantiskani.sk'],
            'phone' => [null, '+421 47 438 10 16'],
            'street' => [null, 'Koháryho nám. 1'], 'postcode' => [null, '986 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Františkánsky kláštor a farnosť Nanebovzatia Panny Márie vo Fiľakove je jedným zo skutočných pôsobísk rádu na Slovensku. Pôvodný kostol zničili Turci v roku 1544, súčasná jednoloďová stavba s presbytériom s polygonálnym uzáverom vznikla v rokoch 1694 – 1727.</p><p>Kláštor priliehajúci ku kostolu zo severozápadu je dvojposchodová budova s centrálnym nádvorím a krížovou chodbou a patrí medzi kultúrne pamiatky Slovenska. Komunita dnes slúži slovenskej aj maďarsky hovoriacej časti veriacich a sprevádza aj turistov prehliadkami kostola a pokladnice.</p>',
        ],
        849 => [
            'name' => 'Vydavateľstvo DON BOSCO',
            'website' => [null, 'https://www.donbosco.sk'],
            'email' => [null, 'donbosco@donbosco.sk'],
            'phone' => [null, '02 5557 2226'],
            'street' => [null, 'Miletičova 7'], 'postcode' => [null, '821 08'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Vydavateľstvo DON BOSCO bolo založené v roku 1994 v Bratislave ako súčasť saleziánskeho diela s poslaním šíriť evanjeliové hodnoty prostredníctvom kvalitných kníh pre kresťanských čitateľov, rodičov, vychovávateľov a deti. Od roku 2011 vydáva edíciu Viera do vrecka a od roku 2020 prevzalo vydávanie časopisu REBRÍK, jediného slovenského kresťanského časopisu pre deti vo veku 6 až 10 rokov.</p><p>Medzi najznámejšie tituly patria Príbehy pre potešenie duše talianskeho saleziánskeho kňaza Bruna Ferrera s vyše 400-tisíc predanými kusmi a kniha MISIA, ktorá získala ocenenie Najkrajšia kniha Slovenska 2010. Od roku 2015 vydavateľstvo pripravuje aj pracovné zošity pre katolícku náboženskú výchovu a venuje sa audiovizuálnej tvorbe vrátane programov pre televíziu LUX.</p>',
        ],
        902 => [
            'name' => 'Edukačno-misijné centrum ECAV',
            'municipality' => [4209, 242], // Celé Slovensko -> Bratislava (sídlo na Generálnom biskupskom úrade)
            // pôvodný web bol len všeobecná stránka ECAV, nie centra samotného
            'website' => ['https://www.ecav.sk', 'https://www.edumiscentrum.sk'],
            'email' => [null, 'info@edumiscentrum.sk'],
            'phone' => [null, '+421 918 828 317'],
            'street' => [null, 'Palisády 46'], 'postcode' => [null, '811 06'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Edukačno-misijné centrum vzniklo v rámci Evanjelickej cirkvi augsburského vyznania na Slovensku ako reakcia na potreby vyplývajúce zo spoločenskej transformácie po roku 1989. Funguje ako organizačná zložka cirkvi bez samostatnej právnej subjektivity s tímom zloženým z riaditeľa, koordinátora vzdelávania a koordinátora misie.</p><p>Poslaním centra je rozširovať vedomosti a zručnosti osobnostného a duchovného charakteru prostredníctvom vzdelávania, školení a duchovných cvičení, a to nielen pre členov cirkvi. Ponuka zahŕňa tematické vzdelávacie kurzy, duchovné cvičenia, konferencie a webináre, podporu misijných programov, budovanie spoločenstiev biblického štúdia a online akadémiu s kurzami.</p>',
        ],
        267 => [
            'name' => 'Maltézska pomoc Slovensko',
            'website' => [null, 'https://www.maltezskapomoc.sk'],
            'email' => [null, 'office@maltezskapomoc.sk'],
            'phone' => [null, '+421 2 4319 3019'],
            'street' => [null, 'Jakubovo námestie 7'], 'postcode' => [null, '811 09'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Maltézska pomoc Slovensko vznikla 23. decembra 2005 ako výkonná charitatívna organizácia Maltézskeho rádu na Slovensku, s poslaním brániť vieru a slúžiť chudobným a núdznym bez ohľadu na náboženstvo či rasu. Pôsobí prostredníctvom šiestich regionálnych centier — Bratislava, Nitra, Topoľčany, Trenčín, Kežmarok a Košice.</p><p>Medzi hlavné aktivity patrí sociálna práca s rómskou komunitou v Topoľčanoch, rozvoz teplej stravy odkázaným seniorom vo viac ako 140 domácnostiach denne v Bratislave, pomoc ľuďom bez domova, zdravotnícke služby na podujatiach a humanitárna pomoc migrantom a utečencom vrátane pomoci ukrajinským utečencom od roku 2022. Od roku 2015 organizuje výročné vianočné zbierky a zúčastňuje sa pútí do Lúrd.</p>',
        ],
        790 => [
            'name' => 'katolícka iniciatíva Divine Renovation (Premena farnosti)',
            'website' => [null, 'https://promenafarnosti.cz'],
            'email' => [null, 'zuzana.palatova@divinerenovation.org'],
            'phone' => [null, '+420 731 259 420'],
            'body' => '<p>Hnutie Divine Renovation vzniklo na farnosti Saint Benedict Parish v kanadskom Halifaxe pod vedením kňaza Jamesa Mallona; jeho kniha Divine Renovation (2014) naštartovala celosvetové hnutie farskej obnovy stojace na troch pilieroch — otvorenosti pôsobeniu Ducha Svätého, primáte evanjelizácie a zdravom, službu rozvíjajúcom vedení farnosti.</p><p>V Česku aj na Slovensku iniciatívu pod názvom Premena farnosti spoločne koordinuje certifikovaná koučka Zuzana Palátová, ktorá ponúka kurzy pre kňazov a farské tímy, koučing, mentoring a online webináre; iniciatíva nemá na Slovensku samostatnú registrovanú organizáciu ani sídlo.</p>',
        ],
        635 => [
            'name' => 'Misijné dielo detí',
            'website' => [null, 'https://www.misijnediela.sk'],
            'email' => [null, 'info@misijnediela.sk'],
            'phone' => [null, '02/529 64 916'],
            'street' => [null, 'Lazaretská 32'], 'postcode' => [null, '811 09'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Pápežské misijné dielo detí, pôvodne Dielo Detinstva Ježišovho, založil francúzsky biskup Charles de Forbin-Janson v Paríži 20. júna 1843 s myšlienkou zapojiť deti do modlitby a materiálnej pomoci deťom v misijných krajinách pod heslom „Deti pomáhajú deťom". Je jednou zo štyroch vetiev Pápežských misijných diel, zjednotených rozhodnutím pápeža Pia XI.</p><p>Na Slovensku dielo funguje pod celoštátnou kanceláriou Pápežských misijných diel v Bratislave a realizuje programy pre deti a katechétov ako Misijné zrnko, Deň svätej Terezky, tvorivé kurzy Zachej, misijno-animačnú školu STUDŇA a celoslovenské stretnutia BUDÍK a OVEČKA.</p>',
        ],
        734 => [
            // primárne dáta Dekanátu Nitra (Biskupstvo Nitra je vedené samostatne, pozri kanál id=218)
            'name' => 'Nitrianske biskupstvo a farnosti mesta Nitra',
            'email' => [null, 'nitra.dm@nrb.sk'],
            'phone' => [null, '+421 37 652 2008'],
            'street' => [null, 'Farská 18'], 'postcode' => [null, '949 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Dekanát Nitra zastrešuje desať farností — osem v samotnom meste Nitra (Dolné mesto, Horné mesto, Chrenová, Kalvária, Klokočina, Zobor, Dražovce, Janíkovce) a dve okolité obce Dolné Krškany a Nitrianske Hrnčiarovce. Sídli pri farnosti Nitra – Dolné mesto.</p><p>Nitrianska diecéza samotná je najstarším doloženým biskupstvom medzi slovanskými národmi v strednej a východnej Európe — založil ju pápež Ján VIII. v roku 880 bulou Industriae tuae, prvým biskupom bol Viching, a nadväzuje na christianizáciu za kniežaťa Pribinu. Kontakt na biskupský úrad samotný vedie samostatný kanál.</p>',
        ],
        767 => [
            'name' => 'Farnosť Teplicka',
            'municipality' => [242, 3527], // Bratislava -> Teplička nad Váhom (skutočné sídlo)
            'email' => [null, 'teplickanadvahom@dcza.sk'],
            'phone' => [null, '041/598 21 40'],
            'street' => [null, 'Dr. Karola Kmeťku 86/44'], 'postcode' => [null, '013 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Farnosť Teplička nad Váhom bola zriadená 1. januára 1995 a patrí do dekanátu Varín v Žilinskej diecéze. Farským kostolom je Kostol svätého Martina, založený okolo roku 1300; filiálnou obcou je Mojš s Kaplnkou svätej Anny z roku 1925.</p><p>Farnosť má približne 4400 obyvateľov, z toho okolo 3800 rímskych katolíkov. K 4. júlu 2010 sa od farnosti oddelila filiálka Kotrčina Lúčka ako samostatná farnosť.</p>',
        ],
        537 => [
            'name' => 'Komunita Sant’Egidio',
            'website' => [null, 'https://www.santegidio.sk'],
            'email' => [null, 'info@santegidio.sk'],
            'street' => [null, 'Ul. 29. augusta 7'], 'postcode' => [null, '811 08'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Komunita Sant\'Egidio je medzinárodné katolícke laické spoločenstvo, ktoré v roku 1968 v Ríme založil vtedy 19-ročný Andrea Riccardi zhromaždením stredoškolákov k spoločnému čítaniu evanjelia. Dnes pôsobí vo viac ako 70 krajinách sveta s vyše 60-tisíc členmi a jej činnosť stojí na piatich pilieroch — modlitbe, ohlasovaní evanjelia, solidarite s chudobnými, ekumenizme a medzináboženskom dialógu.</p><p>Na Slovensku komunita pôsobí od septembra 2008, okrem Bratislavy aj v Topoľčanoch, Nitre, Banskej Bystrici a Košiciach. Venuje sa službe bezdomovcom a chudobným, starostlivosti o seniorov v domovoch dôchodcov, službe vo väzniciach, vzdelávaniu rómskych detí a podpore utečencov; všetci členovia sú dobrovoľníci.</p>',
        ],
        576 => [
            'name' => 'Kysucká knižnica',
            'website' => [null, 'https://www.kniznica-cadca.sk'],
            'phone' => [null, '+421 41 4334 616'],
            'street' => [null, 'Ul. 17. novembra 1258/6'], 'postcode' => [null, '022 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Kysucká knižnica má korene v roku 1919, keď spolok Čadčianska beseda založil svoju zbierku kníh; okolo roku 1922 vznikla mestská knižnica a v roku 1950 sa stala regionálnou. V roku 1940 knižnica ako prvá na Slovensku otvorila verejnú záhradnú čitáreň a dnešný názov nesie od roku 1995.</p><p>Ide o mestskú verejnú knižnicu pre Čadcu a zároveň regionálnu knižnicu pre okresy Čadca a Kysucké Nové Mesto, 100 % v majetku Žilinského samosprávneho kraja. Okrem klasických výpožičných služieb ponúka literárne kluby, čitateľské noci, projekt Kysucké rozprávkové kráľovstvo, e-knihy a audioknihy a plní úlohu koordinačného centra pre verejné knižnice v regióne Kysuce.</p>',
        ],
        20 => [
            // primárne dáta Východného dištriktu ECAV
            'name' => 'OZ Rodinné spoločenstvo ECAV na Slovensku a Východný dištrikt ECAV na Slovensku',
            'municipality' => [1890, 2822], // Liptovský Ján (len miesto konania konferencie) -> Prešov (skutočné sídlo)
            'website' => ['https://www.ecav.sk', 'https://www.vdecav.sk'],
            'email' => [null, 'sekretariat@vdecav.sk'],
            'phone' => [null, '051/772 25 15'],
            'street' => [null, 'Hlavná 137'], 'postcode' => [null, '080 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Východný dištrikt je jedným z dvoch dištriktov Evanjelickej cirkvi augsburského vyznania na Slovensku a zahŕňa šesť seniorátov — Gemerský, Košický, Liptovsko-oravský, Tatranský, Turčiansky a Šarišsko-zemplínsky. Na čele stojí dištriktuálny biskup a dozorca, úrad spravuje evanjelické cirkevné školy vo východoslovenskom regióne a prevádzkuje ubytovacie zariadenie Dom Janoš aj diakonické zariadenie ELIM.</p><p>Tento kanál eviduje aj Rodinné spoločenstvo ECAV, občianske združenie s rovnakým sídlom v Prešove, ktoré vyzdvihuje hodnotu rodiny založenej na manželstve a organizuje najmä výročné rodinné konferencie — jubilejná 20. konferencia sa konala v novembri 2025 v Liptovskom Jáne, čo je pôvod pôvodne uvedenej obce v databáze.</p>',
        ],
        469 => [
            'name' => 'Asociácia kresťanských koučov',
            'website' => [null, 'https://www.asociaciakk.sk'],
            'email' => [null, 'info@asociaciakk.sk'],
            'phone' => [null, '+421 903 743 961'],
            'body' => '<p>Asociácia kresťanských koučov vznikla v júli 2023 registráciou ako občianske združenie, no história kresťanského koučingu na Slovensku siaha do roku 2014, keď profesionálny kouč Marián Kubeš vytvoril prvý kurz kresťanského koučingu, pôvodne cez Rodinnú poradňu Záhradka podporovanú Saleziánmi don Bosca. Marián Kubeš je čestným prezidentom a zakladateľom kresťanského koučingu na Slovensku.</p><p>Poslaním asociácie je etablovať kresťanský koučing v spoločnosti a podporovať kresťanských koučov. Medzi aktivity patria webináre zamerané na odborný a osobnostný rast koučov, mesačné modlitbové stretnutia, duchovné semináre, každoročná konferencia kresťanských koučov a program Kresťanskí kouči pre manželstvá počas Národného týždňa manželstva.</p>',
        ],
        949 => [
            // primárne dáta Slovenského dohovoru za rodinu; Rytieri Nepoškvrnenej sa nedopĺňajú (nejednoznačný/nefunkčný kontakt)
            'name' => 'Slovenský dohovor za rodinu a Rytieri Nepoškvrnenej',
            'municipality' => [4209, 2822], // Celé Slovensko -> Prešov (skutočné sídlo)
            'website' => [null, 'https://slovenskydohovorzarodinu.sk'],
            'email' => [null, 'marianet.sdzr@gmail.com'],
            'street' => [null, 'Grešova 2'], 'postcode' => [null, '080 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Slovenský dohovor za rodinu je občianske združenie založené 20. júla 2020 so sídlom v Prešove ako reakcia na medzinárodnú zmluvu označovanú ako Istanbulský dohovor. Poslaním je obhajoba tradičnej rodiny, odmietanie takzvanej rodovej ideológie a ochrana života od počatia, v súlade s učením Katolíckej cirkvi.</p><p>Organizácia organizuje modlitbové akcie na národnej aj medzinárodnej úrovni — ružencové a pôstne reťaze, nonstop modlitby svätého ruženca naživo, online relácie s hosťami na duchovné témy a priame prenosy z pútnických miest. Tento kanál eviduje aj hnutie Rytieri Nepoškvrnenej, medzinárodné mariánske hnutie založené svätým Maximiliánom Kolbem v roku 1917.</p>',
        ],
        154 => [
            'name' => 'Musica aeterna o.z',
            'website' => [null, 'https://www.musica-aeterna.sk'],
            'email' => [null, 'musicaaeterna.ensemble@gmail.com'],
            'phone' => [null, '+421 948 400 840'],
            'body' => '<p>Súbor pre starú hudbu Musica aeterna vznikol v roku 1973 z iniciatívy profesora Jána Albrechta a medzinárodná kritika ho považuje za jeden z najvýznamnejších európskych súborov svojho druhu. Od roku 1989 hrá výhradne na dobových nástrojoch zo 17. a 18. storočia, so zameraním na stredoeurópsku a slovenskú hudbu tohto obdobia; v rokoch 1986 až 2005 bol administratívne súčasťou Slovenskej filharmónie.</p><p>Súbor pravidelne spolupracuje s Centrom barokovej hudby vo Versailles, vystupoval na festivaloch ako Holland Festival of Early Music Utrecht, Pražská jar či Berliner Bachtage, absolvoval tri koncertné turné v USA a opakovane vystupoval v Španielsku. Umeleckým vedúcim je huslista Peter Zajíček.</p>',
        ],
        643 => [
            'name' => 'Farnosť Banská Bystrica-mesto',
            'website' => [null, 'https://www.katedralabb.sk'],
            'email' => [null, 'bb.katedrala@fara.sk'],
            'phone' => [null, '048/412 45 31'],
            'street' => [null, 'Námestie Štefana Moysesa 1'], 'postcode' => [null, '974 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Farnosť Banská Bystrica-mesto, zasvätená Nanebovzatiu Panny Márie, si v roku 2026 pripomína 250 rokov života a služby v rámci Banskobystrickej diecézy. Farským, katedrálnym chrámom je Katedrála svätého Františka Xaverského z roku 1715; k farnosti patria aj Kostol svätej Alžbety z roku 1393 a Kalvársky kostol Povýšenia svätého Kríža z roku 1452.</p><p>Farnosti slúži deväť kňazov a v katedrále prebieha celodenná eucharistická adorácia každý pracovný deň. Medzi jej aktivity patrí detský spevácky zbor Úsmev, miništranti a náboženská výchova a príprava na sviatosti.</p>',
        ],
        241 => [
            'name' => 'Mesto Nitra',
            'website' => [null, 'https://nitra.sk'],
            'email' => [null, 'info@nitra.sk'],
            'phone' => [null, '037/65 02 111'],
            'street' => [null, 'Štefánikova trieda 60'], 'postcode' => [null, '950 06'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Nitra je najstaršie mesto na Slovensku; prvá písomná zmienka pochádza zo spisu o obrátení Bavorov a Korutáncov z rokov 871 – 873, a v období Veľkej Moravy patrila k najväčším sídliskovým aglomeráciám strednej Európy s prvým kresťanským kostolom na území dnešného Slovenska. Pápež Ján Pavol II. Nitru pri svojej návšteve v roku 1995 nazval „Betlehemom kresťanstva na Slovensku".</p><p>Mestský úrad Nitra ako výkonný orgán samosprávy poskytuje klientske služby prostredníctvom Klientskeho centra; k januáru 2026 malo mesto 74 548 obyvateľov.</p>',
        ],
        66 => [
            'name' => 'Pútnické miesto Živčáková – laické spoločenstvo MSSCC',
            'website' => [null, 'https://www.zivcakova.sk'],
            'email' => [null, 'misionari@msscc.sk'],
            'phone' => [null, '+421 911 551 028'],
            'street' => [null, 'Korňa 886'], 'postcode' => [null, '023 21'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Pútnické miesto Živčáková vzniklo na základe udalosti z júna 1958, keď lesný robotník Matúš Lašuta údajne zažil na vrchu Živčáková nad Korňou zjavenie Panny Márie. Napriek nedôvere cirkevných predstaviteľov aj odporu komunistického režimu na miesto naďalej prichádzali pútnici; po roku 1989 bola v rokoch 1992 – 1993 postavená kaplnka Panny Márie Kráľovnej pokoja.</p><p>V roku 2008 žilinský biskup Tomáš Galis rozhodol o výstavbe väčšieho Kostola Panny Márie Matky Cirkvi, ktorý bol slávnostne konsekrovaný 4. októbra 2015 za účasti približne 15-tisíc pútnikov. Duchovnú správu miesta od roku 2008 zabezpečuje rehoľná kongregácia Misionári Najsvätejších sŕdc Pána Ježiša a Panny Márie (MSSCC), ktorá na Slovensku pôsobí od roku 1994.</p>',
        ],
        752 => [
            'name' => 'Inštitút Communio',
            'website' => [null, 'https://icommunio.sk'],
            'street' => [null, 'Jána Kalinčiaka 1'], 'postcode' => [null, '010 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Inštitút Communio, n. o. pôsobí v rámci Žilinskej diecézy od svojho založenia 30. júna 2010. Jeho poslaním je prinášať do života diecézy aj širšej verejnosti inšpiráciu, vzdelávanie a duchovné hodnoty prostredníctvom kurzov, prednášok a besied pre deti, mládež aj dospelých.</p><p>Inštitút vydáva mesačník Naša Žilinská diecéza (od februára 2012, náklad približne 7-tisíc výtlačkov distribuovaných takmer do všetkých kostolov diecézy) a online teologický časopis Communio Missio, a je organizátorom Týždňa kresťanskej kultúry — ekumenického festivalu konaného tradične v poslednom májovom týždni už vyše 15 rokov.</p>',
        ],
        163 => [
            'name' => 'Farnosť Višňové',
            'website' => [null, 'https://visnove.fara.sk'],
            'email' => [null, 'visnove@dcza.sk'],
            'phone' => [null, '041/597 22 98'],
            'street' => [null, 'Višňové 70'], 'postcode' => [null, '013 23'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Farnosť Višňové zahŕňa obce Višňové a Turie a od 14. februára 2008 patrí do Rajeckého dekanátu Žilinskej diecézy. Farským kostolom je Kostol svätého Mikuláša biskupa vo Višňovom, ktorého výstavba sa začala v roku 1769 a bol konsekrovaný v roku 1783; filiálnou obcou je Turie s Kostolom svätého Michala Archanjela z roku 1595.</p><p>Farnosť má okolo 4600 obyvateľov, z toho približne 3900 rímskych katolíkov. Vo Višňovom sa historicky udržiava aj mariánska úcta — dodnes sa tu konajú pravidelné Fatimské slávnosti 13. deň každého mesiaca a hlavný farský sviatok Návšteva Panny Márie 2. júla je spojený s tradičnou púťou.</p>',
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('canals')) {
            return;
        }

        foreach (self::CANALS as $id => $row) {
            $this->applyContacts($id, $row, forward: true);
            $this->applyMunicipality($id, $row, forward: true);
            $this->applyBody($id, $row);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('canals')) {
            return;
        }

        foreach (self::CANALS as $id => $row) {
            $this->applyContacts($id, $row, forward: false);
            $this->applyMunicipality($id, $row, forward: false);
            // body sa zámerne nevracia späť (jednosmerný prepis)
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function applyContacts(int $id, array $row, bool $forward): void
    {
        $fields = ['website', 'email', 'phone', 'street', 'postcode', 'country'];
        $query = DB::table('canals')->where('id', $id)->where('name', $row['name']);
        $update = [];
        $touched = false;

        foreach ($fields as $field) {
            if (! isset($row[$field])) {
                continue;
            }

            [$old, $new] = $row[$field];
            $from = $forward ? $old : $new;
            $to = $forward ? $new : $old;

            $from === null
                ? $query->whereNull($field)
                : $query->where($field, $from);

            $update[$field] = $to;
            $touched = true;
        }

        if ($touched) {
            $update['updated_at'] = now();
            $query->update($update);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function applyMunicipality(int $id, array $row, bool $forward): void
    {
        if (! isset($row['municipality'])) {
            return;
        }

        [$old, $new] = $row['municipality'];
        $from = $forward ? $old : $new;
        $to = $forward ? $new : $old;

        DB::table('canals')->where('id', $id)->where('name', $row['name'])
            ->where('municipality_id', $from)
            ->update(['municipality_id' => $to, 'updated_at' => now()]);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function applyBody(int $id, array $row): void
    {
        if (! isset($row['body'])) {
            return;
        }

        DB::table('canals')->where('id', $id)->where('name', $row['name'])
            ->whereNotNull('body')
            ->update(['body' => $row['body'], 'updated_at' => now()]);
    }
};
