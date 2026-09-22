<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pokračovanie 2026_09_22_130000/140000_backfill_contact_info_on_*_organization_canals.php
 * — doplní web/email/telefón/adresu pre ďalších 25 neosobných kanálov,
 * zistené z ich oficiálnych webov 22. 9. 2026. Rovnaký guard, rovnaká
 * štruktúra dát a rovnaký jednosmerný prepis `body` ako v predošlých
 * dvoch migráciách — tentokrát je `body` podrobnejší (viacero odsekov
 * s históriou, poslaním a rozsahom činnosti), na žiadosť používateľa.
 *
 * Kanály id=59 (Spoločenstvo Nádej, Kovarce) a id=684 (Nadční fond
 * CREDO — ide s vysokou pravdepodobnosťou o českú nadáciu CREDO CZ so
 * sídlom v Zlíne, nie o slovenský subjekt) sa nedopĺňajú vôbec, rovnako
 * ako id=70/815/833 v predošlých migráciách.
 *
 * Ďalšie duplicity tej istej reálnej organizácie: id 707/617 = klub
 * ISKRA (rovnaká Facebook stránka), id 915 = ďalší kanál inštitúcie
 * Kolégium Antona Neuwirtha (id 72/373), id 127 = konkrétny program
 * (kurz prípravy na manželstvo) kanála „Sigord - Centrum pre rodinu"
 * (id 196). Kanál id=517 „Bratia minoriti" je miestna komunita na tej
 * istej adrese ako celoštátna kustódia id=110. Zlúčené záznamy id=546,
 * 809, 675 dostávajú dáta hlavnej/identifikovateľnejšej organizácie
 * s poznámkou o druhej v `body` — pozri komentáre pri jednotlivých
 * záznamoch.
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
        707 => [
            // rovnaký klub ako id=617 (rovnaká Facebook stránka), žiadny vlastný web/kontakt sa nenašiel
            'name' => 'Voľnočasový kresťanský ekumenický klub ISKRA',
            'body' => '<p>Klub ISKRA je kresťanské ekumenické voľnočasové spoločenstvo v Bratislave, ktoré nie je viazané len na katolícke prostredie, ale vytvára priestor pre mladých pracujúcich ľudí bez ohľadu na vek či rodinný stav. Cieľom klubu je spájať ľudí okolo spoločných záujmov a hodnôt a budovať medzi nimi skutočné spoločenstvo presahujúce jednorazové stretnutia. Klub úzko spolupracuje s okruhom okolo projektu Single katolíci a portálu singlekatolici.sk.</p><p>Program je rozdelený do niekoľkých pravidelných „podklubov" s vlastným týždenným rytmom: pondelok patrí biblickému updatu, streda spoločenským hrám, štvrtok knižnému klubu a piatok bedmintonu spolu s takzvaným dňom komunity. Popri tom funguje aj športový klub, dobrodružný klub zameraný na turistiku a cykloturistiku, herný klub, tanečný klub a kultúrny klub, takže si každý účastník môže nájsť aktivitu podľa vlastného záujmu.</p><p>Klub okrem pravidelných stretnutí organizuje aj verejné akcie, napríklad spoločné výstupy na vrcholy ako Záruby alebo Vysoká od Zochovej chaty, tanečné večery a spoločenské či športové piknikové podujatia. Komunikuje predovšetkým cez Facebook a WhatsApp skupinu, keďže vlastnú webovú stránku zatiaľ nemá.</p>',
        ],
        573 => [
            'name' => 'Arcidiecézne centrum pre mládež (ACM)',
            'website' => [null, 'https://acmko.sk'],
            'email' => [null, 'premladez@gmail.com'],
            'street' => [null, 'Rooseveltova 12'], 'postcode' => [null, '040 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Arcidiecézne centrum pre mládež v Košiciach založil košický arcibiskup Alojz Tkáč 1. júla 2005 s cieľom podporovať pastoráciu mládeže vo farnostiach celej arcidiecézy a umožniť mladým ľuďom z ktorejkoľvek farnosti zažiť živé spoločenstvo veriacich rovesníkov. V roku 2025 si centrum pripomenulo 20. výročie svojej činnosti. Jeho poslaním je privádzať mladých ľudí k osobnému stretnutiu so živým Kristom prítomným v Cirkvi.</p><p>Činnosť centra stojí na troch pilieroch: koordinácia existujúcich aktivít mládeže v arcidiecéze, formácia budúcich vedúcich a animátorov a evanjelizácia prostredníctvom stretnutí mládeže, duchovných obnov a programov pre školy. Medzi konkrétne projekty patria škola slúžiacich sŕdc ADAŠ, program APka, ADSM, BirmOFFka pre birmovancov, futsalové, florbalové a volejbalové turnaje či celoarcidiecézne stretnutie miništrantov MIKE.</p><p>Vlajkovou akciou centra je Frekvencia — výročná pešia púť mládeže z Prešova do Levoče konaná koncom augusta, ktorá sa v roku 2025 konala už dvadsiaty raz. Centrum aktívne komunikuje s mladými aj cez Facebook, YouTube a Instagram.</p>',
        ],
        277 => [
            'name' => 'Arcidiecézne centrum pre rodinu v Košiciach',
            'website' => [null, 'https://rodinake.sk'],
            'email' => [null, 'rodina@abuke.sk'],
            'phone' => [null, '0908 844 384'],
            'street' => [null, 'Hlavná 79'], 'postcode' => [null, '040 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Arcidiecézne centrum pre rodinu v Košiciach zriadil košický arcibiskup Bernard Bober ako cirkevnoprávnickú osobu s účinnosťou od 19. marca 2018 na podporu pastorácie manželstva a rodiny na celom území Košickej arcidiecézy. Centrum pôsobí na základe kánonického práva, dokumentov Svätej stolice, dekrétov diecéznej synody a usmernení arcibiskupa a jeho cieľovými skupinami sú mladí pripravujúci sa na manželstvo, kresťanské rodiny, manželia v prvých rokoch spoločného života, rodiny v kríze, ale aj kňazi a laickí pracovníci v pastorácii rodín.</p><p>Medzi hlavné aktivity patrí príprava snúbencov na manželstvo formou kurzov, manželské a rodinné poradenstvo označované ako „Služba ucha", podpora veriacich po civilnom rozvode, kresťanský koučing a laktačné poradenstvo. Centrum sa venuje aj formácii pracovníkov pre pastoráciu rodín vrátane možnosti magisterského štúdia teológie manželstva a rodiny a organizuje celodiecézne podujatia ako Rodinný ples a Deň rodiny.</p><p>Popri priamej práci s rodinami sa centrum venuje aj osvetovej činnosti prostredníctvom prednášok, publikácií a vlastného webu.</p>',
        ],
        617 => [
            // rovnaký klub ako id=707 (rovnaká Facebook stránka), žiadny vlastný web/kontakt sa nenašiel
            'name' => 'Kresťanský voľnočasový klub ISKRA',
            'body' => '<p>Klub ISKRA je kresťanské ekumenické voľnočasové spoločenstvo v Bratislave, ktoré nie je viazané len na katolícke prostredie, ale vytvára priestor pre mladých pracujúcich ľudí bez ohľadu na vek či rodinný stav. Cieľom klubu je spájať ľudí okolo spoločných záujmov a hodnôt a budovať medzi nimi skutočné spoločenstvo presahujúce jednorazové stretnutia. Klub úzko spolupracuje s okruhom okolo projektu Single katolíci a portálu singlekatolici.sk.</p><p>Program je rozdelený do niekoľkých pravidelných „podklubov" s vlastným týždenným rytmom: pondelok patrí biblickému updatu, streda spoločenským hrám, štvrtok knižnému klubu a piatok bedmintonu spolu s takzvaným dňom komunity. Popri tom funguje aj športový klub, dobrodružný klub zameraný na turistiku a cykloturistiku, herný klub, tanečný klub a kultúrny klub, takže si každý účastník môže nájsť aktivitu podľa vlastného záujmu.</p><p>Klub okrem pravidelných stretnutí organizuje aj verejné akcie, napríklad spoločné výstupy na vrcholy ako Záruby alebo Vysoká od Zochovej chaty, tanečné večery a spoločenské či športové piknikové podujatia. Komunikuje predovšetkým cez Facebook a WhatsApp skupinu, keďže vlastnú webovú stránku zatiaľ nemá.</p>',
        ],
        437 => [
            'name' => 'Občianske združenie Dobré srdce – Good heart',
            'website' => [null, 'https://ozdobresrdce.sk'],
            'email' => [null, 'ozdobresrdce@gmail.com'],
            'phone' => [null, '+421 903 600 477'],
            'street' => [null, 'Krakovská 7'], 'postcode' => [null, '040 11'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Občianske združenie Dobré srdce – Good heart bolo oficiálne zaregistrované na Ministerstve vnútra SR 11. marca 2019 v Košiciach, s krédom „Vďaka Vám pomáhame tým, ktorí to sami nedokážu." Jeho filozofia stojí na presvedčení, že aj ľudia na okraji spoločnosti dokážu vytvárať niečo krásne a zmysluplne prispievať, a hlavnou cieľovou skupinou sú osamelé matky v chudobe a ľudia bez domova.</p><p>Združenie prevádzkuje tvorivé dielne, v ktorých klienti vyrábajú dekoratívne mydlá a ruženčeky, čím si zvyšujú sebestačnosť a získavajú príjem z vlastnej práce. Pre deti ponúka doučovanie a mimoškolské krúžky — matematický, spevácky, tanečný a neformálne hodiny angličtiny — spolu s motivačnými štipendiami podľa školského prospechu a dochádzky.</p><p>Každoročne k 17. októbru, Medzinárodnému dňu boja proti chudobe, organizuje celomestskú verejnú zbierku „Otvor srdce" a prevádzkuje aj projekt „Nehladuj, nakŕm dieťa" zameraný na zabezpečenie školských obedov pre deti v núdzi. Na činnosti sa ročne podieľa približne 30 dobrovoľníkov.</p>',
        ],
        546 => [
            // sídlo Bratstva Ježišovho aj Komunity Emanuel je tá istá adresa v obci Lehota
            'name' => 'Komunita Emanuel',
            'municipality' => [4209, 1802], // Celé Slovensko -> Lehota (registrované sídlo)
            'website' => [null, 'https://emanuel.sk'],
            'street' => [null, 'Lehota č. 776'], 'postcode' => [null, '951 36'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Medzinárodná Komunita Emanuel (Communauté de l\'Emmanuel) vznikla v roku 1972 vo Francúzsku, keď jej zakladatelia Pierre Goursat a Martine Laffitte po skúsenosti s charizmatickou obnovou založili modlitbovú skupinu, z ktorej sa postupne vyvinulo dnešné spoločenstvo. Názov je odvodený z biblického mena Emanuel — „Boh s nami". Ide o verejné združenie veriacich uznané Svätou stolicou, ktoré združuje slobodných, manželské páry, kňazov aj zasvätené osoby.</p><p>Podľa údajov z roku 2019 malo spoločenstvo celosvetovo vyše 11 500 členov v 60 krajinách na piatich kontinentoch, z toho 275 kňazov a 225 zasvätených osôb, pôsobí v 70 farnostiach a prevádzkuje sedem evanjelizačných škôl, ktorými ročne prejde približne 100 mladých ľudí. Duchovnosť komunity stojí na štyroch pilieroch: modlitba chvál, adorácia, súcit a milosrdenstvo a evanjelizácia.</p><p>Na Slovensku komunita organizuje národné projekty ako víkendové stretnutie mládeže „On je živý", deväťmesačnú večernú evanjelizačnú školu „Láska a poslanie" a duchovné obnovy pre rodiny „Láska a pravda"; medzi nedávne aktivity patria kurzy v Prievidzi a duchovné podujatia v Bardejove. Slovenská vetva úzko spolupracuje s Bratstvom Ježišovým, ktoré sídli na tej istej adrese v obci Lehota.</p>',
        ],
        67 => [
            'name' => 'Košická eparchia',
            'municipality' => [1455, 1565], // Klokočov (len pútnické miesto eparchie) -> Košice (skutočné sídlo)
            'website' => [null, 'https://www.grkatke.sk'],
            'email' => [null, 'eparchia@grkatke.sk'],
            'phone' => [null, '+421 940 985 460'],
            'street' => [null, 'Dominikánske námestie 2/A'], 'postcode' => [null, '040 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Gréckokatolícku Košickú eparchiu zriadil pápež Ján Pavol II. 21. februára 1997 ako Apoštolský exarchát pre veriacich byzantského obradu so sídlom v Košiciach, vyčlenený z dovtedy jedinej gréckokatolíckej eparchie na Slovensku so sídlom v Prešove. Prvým apoštolským exarchom sa stal Milan Chautur, CSsR, ktorý bol slávnostne uvedený do funkcie 13. apríla 1997 za účasti apoštolského nuncia a biskupov zo Slovenska i zahraničia. Územne exarchát, dnešná eparchia, zodpovedá Košickému kraju s rozlohou 6 753 km².</p><p>Dňa 30. januára 2008 pápež Benedikt XVI. povýšil exarchát na riadnu eparchiu a zaradil ju pod novozriadenú Prešovskú metropolitnú archieparchiu. Od roku 2021 eparchiu vedie arcibiskup Cyril Vasiľ, SJ, po tom, čo dovtedy pôsobiaci Milan Chautur viedol eparchiu od jej vzniku v roku 1997. Eparchia mala v roku 2018 sedem protopresbyterátov a 95 farností a podľa údajov z roku 2021 okolo 74 240 veriacich, 161 eparchiálnych a 16 reholných kňazov, 40 rehoľných sestier a jedného trvalého diakona.</p><p>Liturgia sa slávi byzantským obradom v slovenčine, cirkevnej slovančine a maďarčine, patrónmi eparchie sú svätí Cyril a Metod. Medzi významné pútnické miesta patria Sečovce, Klokočov, Michalovce a samotné Košice s katedrálou Narodenia Presvätej Bohorodičky.</p>',
        ],
        406 => [
            'name' => 'Fórum života',
            'municipality' => [4209, 242], // Celé Slovensko -> Bratislava (skutočné sídlo)
            'website' => [null, 'https://forumzivota.sk'],
            'email' => [null, 'kancelaria@forumzivota.sk'],
            'phone' => [null, '+421 903 533 946'],
            'street' => [null, 'Heydukova 14'], 'postcode' => [null, '811 08'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Fórum života bolo založené 19. decembra 2003 ako združenie občianskych organizácií a jednotlivcov spojených víziou demokratickej spoločnosti, ktorá chráni ľudský život a dôstojnosť od počatia po prirodzenú smrť. Jeho misiou je budovať spoločnosť, v ktorej môže každý žiť plnohodnotný život, a podporovať rodinu ako optimálne miesto vzniku a rozvoja ľudského života. Združenie funguje ako zastrešujúca organizácia desiatok členských organizácií, iniciatív a individuálnych členov.</p><p>Činnosť stojí na štyroch pilieroch: advokácia, teda pripomienkovanie legislatívy a členstvo vo viacerých poradných orgánoch štátu, prevencia formou osvety o dôstojnosti človeka vo všetkých fázach života, konkrétna pomoc prostredníctvom deviatich projektov priamej pomoci s vyše 1 200 klientmi, a dialóg cez bioetické podcasty, eseje a sledovanie médií.</p><p>Organizácia je známa najmä kampaňami „Sviečka za nenarodené deti", ktorá sa koná každoročne v októbri, a „25. marec – Deň počatého dieťaťa". Vydáva periodikum Spravodaj OZ Fórum života, je členom domáceho Fóra kresťanských inštitúcií a medzinárodnej siete Human Life International.</p>',
        ],
        661 => [
            'name' => 'Slovensko na kolenách',
            'website' => [null, 'https://www.slovenskonakolenach.sk'],
            'email' => [null, 'slovenskonakolenach@gmail.com'],
            'body' => '<p>Modlitebnú iniciatívu Slovensko na kolenách v roku 2021 inicioval duchovný Mário Brezňan po tom, čo sa myšlienka zrodila počas jeho návštevy Medžugoria; prvé modlitby sa uskutočnili začiatkom apríla 2021. Iniciatívu podporujú viacerí kňazi a laici pôsobiaci v Spišskej diecéze, konkrétne kňazi Ján Buc a Branislav Kožuch, spoločenstvo Rieka života a chválová kapela LCH Live, a funguje pod patronátom správcu Spišskej diecézy Mons. Jána Kuboša.</p><p>Cieľom iniciatívy je prebudiť v ľuďoch ducha pokánia, aby sa spoločne dokázali postaviť v pravde a učiť sa takzvanému zastupiteľnému pokániu ako spoločenstvo, ktorému záleží na životoch druhých; odvoláva sa pritom na biblický sľub z Druhej knihy kroník 7,14.</p><p>Modlitby sa konajú pravidelne každý druhý štvrtok v mesiaci o 20:00 a sú vysielané naživo z Konkatedrály Sedembolestnej Panny Márie v Poprade cez webstránku a YouTube kanál iniciatívy, kde je k dispozícii aj archív desiatok predchádzajúcich modlitbových stretnutí.</p>',
        ],
        809 => [
            // primárne dáta Slavistického ústavu SAV; Centrum pre štúdium biblického a blízkovýchodného sveta sídli samostatne v Košiciach (bez vlastného DB záznamu)
            'name' => 'Slavistický ústav Jána Stanislava SAV, v.v.i. a Centrum pre štúdium biblického a blízkovýchodného sveta',
            'municipality' => [1565, 242], // Košice -> Bratislava (skutočné sídlo Slavistického ústavu)
            'website' => [null, 'https://slavu.sav.sk'],
            'email' => [null, 'slavust@savba.sk'],
            'phone' => [null, '+421 2 59 209 411'],
            'street' => [null, 'Dúbravská cesta 9'], 'postcode' => [null, '841 04'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Slavistický ústav Jána Stanislava Slovenskej akadémie vied vznikol 1. marca 1995 ako Slavistický kabinet SAV a rozhodnutím predsedníctva SAV bol s účinnosťou od 1. januára 2005 premenovaný na dnešný názov. V roku 2025 si pripomenul 30. výročie svojho vzniku. Ide o verejnú výskumnú inštitúciu a interdisciplinárne pracovisko slovenskej slavistiky, ktoré sa nevenuje len jazykovede, ale aj historiografii, etnografii, umenovede a folkloristike.</p><p>Výskumné oblasti ústavu zahŕňajú inštitucionálne dejiny slovenskej slavistiky, jazykovednú a literárnu slavistiku, historiografiu a folkloristiku, vrátane bádania o ukrajinistike, bielorusistike, rusistike, cyrilometodskej tematike a skúmania ikon. Ústav spolupracuje s univerzitami a výskumnými inštitúciami po celom Slovensku a podieľa sa aj na doktorandskom štúdiu.</p><p>Tento kanál eviduje aj Centrum pre štúdium biblického a blízkovýchodného sveta, ktoré vzniklo v roku 2016 a pôsobí samostatne v Košiciach pod vedením prof. Róberta Lapka; jeho vlajkovým projektom je Letná škola biblických jazykov zameraná na biblickú hebrejčinu, gréčtinu, sýrčinu, latinčinu, staroslovienčinu a akkadčinu.</p>',
        ],
        151 => [
            'name' => 'Občianske združenie Nenápadní hrdinovia',
            'municipality' => [4209, 242], // Celé Slovensko -> Bratislava (skutočné sídlo)
            'website' => [null, 'https://www.nenapadnihrdinovia.sk'],
            'email' => [null, 'info@nenapadnihrdinovia.sk'],
            'street' => [null, 'Robotnícka 7'], 'postcode' => [null, '831 03'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Občianske združenie Nenápadní hrdinovia vzniklo z prípravného výboru 3. novembra 2010 a na Ministerstve vnútra SR bolo registrované 18. novembra 2010 ako dobrovoľné, nepolitické a neziskové záujmové združenie fyzických osôb; predsedom je historik a publicista František Neupauer. Jeho poslaním je pripomínať odvahu a obete ľudí prenasledovaných počas totality, najmä komunizmu, a odovzdávať túto pamäť mladej generácii v duchu hodnôt prirodzeného práva a spravodlivosti ako prevencie vzniku nových totalitných režimov.</p><p>Hlavnou aktivitou je celoročný študentský projekt a súťaž „Nenápadní hrdinovia v zápase s komunizmom", ktorý prebieha od marca (výročie Sviečkovej manifestácie) do novembra (Deň boja za slobodu a demokraciu) v spolupráci s Konfederáciou politických väzňov Slovenska — študenti v ňom vyhľadávajú a dokumentujú príbehy ľudí prenasledovaných komunistickým režimom.</p><p>Združenie sa venuje aj spracovaniu a šíreniu informácií o obdobiach neslobody na Slovensku aj vo svete, vydavateľskej a edičnej činnosti o komunistickom totalitarizme, ochrane národného kultúrneho dedičstva a podpore obetí komunizmu vrátane dlhodobo chorých a zdravotne postihnutých; presadzuje tiež vznik múzea zločinov komunizmu na Slovensku a organizuje konferencie, prednášky a semináre.</p>',
        ],
        485 => [
            'name' => 'Rada KBS pre rodinu',
            'municipality' => [4209, 1565], // Celé Slovensko -> Košice (odtiaľ reálne funguje sekretariát)
            'website' => [null, 'https://rodina.kbs.sk'],
            'email' => [null, 'rodina@kbs.sk'],
            'phone' => [null, '+421 55 727 19 27'],
            'street' => [null, 'Dominikánske námestie 2/A'], 'postcode' => [null, '043 43'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Rada Konferencie biskupov Slovenska pre rodinu je poradný a koordinačný orgán KBS pre oblasť pastorácie rodín. Jej súčasným predsedom je Mons. prof. ThDr. ICODr. Cyril Vasiľ, SJ, PhD., košický eparcha, tajomníkom je Mgr. Bc. Richard Kucharčík, M.A., PhD. Rada je zložená z delegátov jednotlivých slovenských diecéz a eparchií, zvyčajne kňaza a laika za každú z nich, a spolupracuje aj s vatikánskym Dikastériom pre laikov, rodinu a život.</p><p>Medzi hlavné činnosti rady patrí tvorba a pilotovanie novej metodiky prípravy na manželstvo v pilotných farnostiach, podpora a koordinácia siete diecéznych centier pre rodinu na Slovensku a podpora rodinných spoločenstiev pôsobiacich vo farnostiach, mestách i menších obciach.</p><p>Rada tiež organizuje rodinné tábory na posilnenie vedomia rodiny ako celku a budovanie komunity medzi rodinami a venuje sa aj pastorácii seniorov; pravidelne sa stretáva, hodnotí národné a diecézne stretnutia a rieši implementáciu nových pastoračných usmernení pre prípravu na manželstvo.</p>',
        ],
        915 => [
            // rovnaká právnická osoba ako kanály id=72 a id=373 "Kolégium Antona Neuwirtha" (duplicitné kanály, mimo rozsahu tejto migrácie)
            'name' => 'Kolégium ONLINE',
            'website' => [null, 'https://kolegium.org'],
            'email' => [null, 'info@kolegium.org'],
            'body' => '<p>Kolégium Antona Neuwirtha vzniklo v roku 2009 v Ivanke pri Dunaji ako nezávislá vzdelávacia inštitúcia s cieľom formovať mladých ľudí v duchu kresťanských hodnôt a kultúrneho dedičstva západnej civilizácie, pod heslom „Pravda, dobro a krása". Postupne okolo seba vybudovalo viacero vzdelávacích programov pre rôzne vekové skupiny — od základnej školy Citadela a gymnázia cez rezidenčný študijný program Kolégium až po Akadémiu veľkých diel pre stredoškolákov a letné programy vrátane medzinárodného seminára Free society seminar.</p><p>Tento kanál zastupuje konkrétne projekt Kolégium ONLINE — e-learningovú platformu spustenú na jeseň 2020, ktorá ponúka živé diskusné semináre z filozofie, umenia, teológie a ďalších tém dostupné komukoľvek so záujmom o kvalitné vzdelávanie odkiaľkoľvek na Slovensku aj mimo neho.</p><p>Ide o rovnakú právnickú osobu ako kanály „Kolégium Antona Neuwirtha" a „Univerzitné kolégium Antona Neuwirtha", len pod iným, projektovým názvom pre online formát vzdelávania.</p>',
        ],
        311 => [
            'name' => 'Komunita Cenacolo',
            'website' => [null, 'https://www.cenacolo.sk'],
            'phone' => [null, '+421 245 901 009'],
            'street' => [null, 'Včelárska paseka 278'], 'postcode' => [null, '900 50'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Komunita Cenacolo je medzinárodné kresťanské spoločenstvo, ktoré v roku 1983 založila sestra Elvíra Petrozzi v talianskom Saluzzo s poslaním pomáhať mladým ľuďom zotaviť sa zo závislostí — od drog cez alkohol po iné návykové látky — prostredníctvom spoločného života, práce, modlitby a duchovnej formácie, bez medikamentóznej liečby a na princípe vzájomnej pomoci a znovuobjavenia zmyslu života cez vieru. Komunita má dnes približne 44 domov po celom svete.</p><p>Slovenský dom, nazvaný Dom svätého Cyrila a Metoda, bol otvorený v novembri 2007 v Kráľovej pri Senci na lokalite Včelárska paseka, kde prišli prví mladí muži, a bol navrhnutý s kapacitou približne 30 obyvateľov. Bratislavský arcibiskup Mons. Stanislav Zvolenský dom oficiálne požehnal a inauguroval v júni 2010 za účasti samotnej zakladateľky sestry Elvíry.</p><p>Slovenská komunita je registrovaná ako občianske združenie a financuje sa okrem iného z darov. Pravidelne organizuje verejné akcie, napríklad dni otvorených dverí a vianočné živé jasličky, ktorými buduje vzťah s okolitou komunitou a informuje verejnosť o svojej činnosti.</p>',
        ],
        517 => [
            // miestna komunita na tej istej adrese ako celoštátna kustódia rádu, kanál id=110 "Rád menších bratov konventuálov (minoriti)"
            'name' => 'Bratia minoriti',
            'website' => [null, 'https://minoriti.sk'],
            'email' => [null, 'sekretariat@minoriti-ba.sk'],
            'phone' => [null, '(02) 65440150'],
            'street' => [null, 'Nám. sv. Františka 4'], 'postcode' => [null, '841 04'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Minoriti, teda Rád menších bratov konventuálov, sú jednou z troch hlavných vetiev františkánskej rehoľnej rodiny, ktorú založil svätý František z Assisi a ktorú pápež Inocent III. ústne schválil v roku 1209. Na Slovensku majú bratia minoriti najstarší kláštor v Trnave z roku 1224 a do roku 1272 existovali ich kláštory aj v Bratislave, Nitre, Trenčíne, Slovenskej Ľupči a Čachticiach; dnes pôsobia v Bratislave-Karlovej Vsi, Levoči, Spišskom Štvrtku a Brehove.</p><p>Tento kanál zastupuje miestnu komunitu bratov minoritov v Bratislave-Karlovej Vsi, ktorá spravuje farnosť svätého Michala Archanjela s kostolmi svätého Michala a svätého Františka z Assisi. Bratislavský kláštor je od júla 2003 zároveň sídlom Slovenskej kustódie Nepoškvrneného počatia Panny Márie, teda správneho centra rádu na Slovensku (samostatný kanál „Rád menších bratov konventuálov (minoriti)").</p><p>Farský život v Karlovej Vsi zahŕňa pravidelné sväté omše, cyklus „Piatky so svätým Františkom", prípravu na sviatosti a farský časopis svätý Michal; na území farnosti pôsobí aj základná cirkevná škola svätého Františka z Assisi. Generálnym predstaveným celosvetového rádu je od roku 2007 brat Marco Tasca, 119. nástupca svätého Františka.</p>',
        ],
        162 => [
            'name' => 'Exercičný dom svätého Ignáca z Loyoly',
            'website' => [null, 'https://jezuiti.sk/duchovne-cvicenia/exercicny-dom-presov/'],
            'email' => [null, 'infopo@jezuiti.sk'],
            'phone' => [null, '+421 948 892 929'],
            'street' => [null, 'Pod Kalváriou 81'], 'postcode' => [null, '080 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Exercičný dom svätého Ignáca z Loyoly v Prešove je duchovný dom vedený jezuitmi, umiestnený na západnom okraji mesta v tichom prostredí pod Kalváriou, s vlastnou adresou a kontaktom oddeleným od celoslovenskej jezuitskej centrály. Dom disponuje 57 izbami s kapacitou približne 60 lôžok, piatimi kaplnkami, štyrmi prednáškovými miestnosťami, jedálňou s mini-bufetom, parkoviskom a rozľahlým parkom so záhradou svätého Ignáca.</p><p>Dom ponúka bohatý celoročný program duchovných cvičení vedených jezuitmi — individuálne vedené exercície v dĺžke tri až osem dní, tematické exercície, biblické meditácie, pôstne obnovy i špeciálne cvičenia pre kňazov zamerané na rozlišovanie povolania, vnútorné uzdravenie a prehĺbenie osobného vzťahu s Bohom, ako aj jednodňové duchovné obnovy.</p><p>Pobyt v dome je spoplatnený podľa typu programu, od jednodňovej obnovy až po viacdňové exercičné balíky, a dom prevádzkuje aj recepciu, ktorá zabezpečuje organizačný chod jednotlivých pobytov.</p>',
        ],
        29 => [
            'name' => 'Bratislavská eparchia',
            'website' => [null, 'https://www.grkatba.sk'],
            'email' => [null, 'eparchia@grkatba.sk'],
            'phone' => [null, '+421 2 52 622 081'],
            'street' => [null, 'Ul. 29. augusta 7'], 'postcode' => [null, '811 08'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Gréckokatolícku Bratislavskú eparchiu zriadil pápež Benedikt XVI. 30. januára 2008, v deň sviatku Troch svätých hierarchov vo východnej tradícii, oddelením územia od Prešovskej eparchie. V ten istý deň bol Prešov povýšený na metropolitné arcibiskupstvo a súčasne vznikla aj Košická eparchia, pričom obe nové eparchie sa stali sufragánnymi eparchiami novej Prešovskej gréckokatolíckej metropolie.</p><p>Územie Bratislavskej eparchie pokrýva západné a stredné Slovensko mimo Prešovského a Košického kraja. Prvým bratislavským eparchom bol Mons. Peter Rusnák, dnes emeritný biskup, súčasným eparchom je Mons. Milan Lach, SJ. Katedrálnym chrámom eparchie je Katedrála Povýšenia svätého Kríža v Bratislave.</p><p>Vznik eparchie nadväzuje na vyše 1100-ročnú misijnú tradíciu svätého Cyrila a Metoda medzi Slovanmi a na takmer 190-ročnú históriu Prešovskej eparchie, ktorá siaha do roku 1818, vrátane obdobia prenasledovania gréckokatolíkov v rokoch 1950 – 1968 počas komunizmu, keď boli biskupi aj kňazi väznení, no cirkev zostala verná Rímu. Eparchia má vybudovanú administratívnu štruktúru — eparchiálnu kúriu, prezbyterskú radu, kolégium konzultorov, školský a katechetický úrad — a venuje sa liturgickej službe, náboženskej výchove, charitatívnej činnosti, pastoračnej starostlivosti a pútnickym miestam.</p>',
        ],
        675 => [
            // primárne dáta fakulty (oficiálne sídlo Bratislava); Kňazský seminár sv. Gorazda v Nitre má vlastný kanál id=347
            'name' => 'Rímskokatolícka cyrilometodská bohoslovecká fakulta UK – Kňazský seminár sv. Gorazda',
            'municipality' => [2333, 242], // Nitra -> Bratislava (oficiálne sídlo fakulty)
            'website' => [null, 'https://frcth.uniba.sk'],
            'email' => [null, 'sd@frcth.uniba.sk'],
            'phone' => [null, '+421 2 32 777 120'],
            'street' => [null, 'Kapitulská 26'], 'postcode' => [null, '814 58'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Rímskokatolícka cyrilometodská bohoslovecká fakulta Univerzity Komenského píše svoju históriu od jesene 1936, keď prví bohoslovci prekročili jej brány; predtým fungovala ako bohoslovecké učilište a seminár. V rokoch 1946 – 1947 prešla organizačnými zmenami vo vzťahu k Univerzite Komenského a počas komunizmu bola až do roku 1990 jedinou inštitúciou na Slovensku, ktorá vzdelávala a formovala budúcich kňazov, čo jej dávalo kľúčové postavenie pri zachovaní katolíckej cirkevnej štruktúry v neslobodných podmienkach.</p><p>Fakulta dnes ponúka bakalárske, magisterské aj doktorandské štúdium katolíckej teológie, formuje seminaristov aj laických študentov a prevádzkuje viacero katedier; od akademického roka 2025/2026 otvára aj rozširujúce štúdium učiteľstva náboženskej výchovy. Jej poslaním je sprostredkovať kresťanskú teologickú reflexiu zrozumiteľnejším jazykom pre dnešného človeka a reagovať na etické otázky biomedicínskeho výskumu, technológií a globalizácie.</p><p>Fakulta úzko spolupracuje s Kňazským seminárom svätého Gorazda v Nitre (samostatný kanál), ktorý slúži ako jej pracovisko a internát pre bohoslovcov — od akademického roka 2023/2024 sa tam počas rekonštrukcie budovy fakulty v Bratislave fakticky realizuje aj samotná výučba.</p>',
        ],
        490 => [
            'name' => 'Spoločenstvo Pavol z Levoče',
            'website' => [null, 'https://spolpavol.sk'],
            'email' => [null, 'spolocenstvo.levoca@gmail.com'],
            'body' => '<p>Spoločenstvo Pavol je katolícke spoločenstvo v Levoči, ktoré sa opisuje ako miesto modlitby, formácie, priateľstva a služby pre rodiny aj jednotlivcov z regiónu hľadajúcich hlbší duchovný život. Jeho korene siahajú do roku 2005, keď skupina ľudí zažila hlboké stretnutia s Božou láskou, a meno Pavol prijalo spoločenstvo v roku 2008 podľa inšpirácie obrátením apoštola Pavla.</p><p>V roku 2019 prešlo generačnou zmenou — z pôvodne prevažne mládežníckeho zamerania sa preorientovalo na spoločenstvo zamerané na manželské páry s deťmi. Pôsobí pod patronátom bratov minoritov a v úzkej spolupráci s gréckokatolíckou farnosťou v Levoči, členovia sú prevažne absolventi Diecéznej animátorskej školy vo Važci.</p><p>Spoločenstvo je členom celoslovenskej siete CHARIS communia a súčasťou siete spoločenstiev Spišskej diecézy „Spišnet" pod dohľadom Komisie pre mládež Spišskej diecézy, ako aj Európskej siete spoločenstiev. Okrem interných stretnutí zameraných na modlitbu a duchovnú formáciu organizuje od roku 2014 aj verejné „Otvorené stretnutia" s hosťujúcimi rečníkmi.</p>',
        ],
        174 => [
            'name' => 'Hnutie Modlitby za kňazov',
            'municipality' => [242, 2253], // Bratislava -> Moravský Svätý Ján (skutočné sídlo)
            'website' => [null, 'https://www.mzk.sk'],
            'email' => [null, 'mzk@mzk.sk'],
            'street' => [null, 'Zámocká ul. 28'], 'postcode' => [null, '908 71'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Hnutie Modlitby za kňazov vzniklo koncom roka 2008 v okolí Baziliky Sedembolestnej v Šaštíne, keď sa niekoľkí muži z tohto regiónu začali pravidelne stretávať s rodinami na modlitbách za kňazov. Webová stránka hnutia bola oficiálne spustená 4. augusta 2009 pri príležitosti 150. výročia úmrtia svätého Jána Vianneyho, ktorý sa stal patrónom hnutia. Postupne vznikali ďalšie malé spoločenstvá, napríklad MZK Štefanov v roku 2009 a MZK Závod v roku 2012.</p><p>Hnutie má dve hlavné poslania: obnovu kňazstva prostredníctvom modlitby a praktickej podpory kňazov, rehoľníkov a nových povolaní, a novú evanjelizáciu prostredníctvom kurzu Objav Krista, ktorý beží od roku 2010. Kľúčovou aktivitou je celoslovenská štyridsaťdňová reťaz pôstu a modlitieb za kňazov od 15. septembra do 24. októbra, ktorá vyvrcholí celoslovenskou Púťou za kňazov v Národnej svätyni v Šaštíne.</p><p>Hnutie ďalej organizuje Púť vernosti, adventnú iniciatívu povzbudenia aspoň jedného starého alebo chorého kňaza, návštevy farností, týždenné modlitby cez Rádio Mária a mesačné modlitbové stretnutia v Šaštíne. Funguje ako občianske združenie prijímajúce finančné príspevky na svoju činnosť.</p>',
        ],
        19 => [
            'name' => 'Ekumenický výbor ECAV na Slovensku',
            'municipality' => [4209, 242], // Celé Slovensko -> Bratislava (sídlo Generálneho biskupského úradu ECAV)
            'website' => ['https://www.ecav.sk', 'https://www.ecav.sk/generalny-biskupsky-urad/ekumenicky-vybor'],
            'email' => [null, 'ekum.vybor@ecav.sk'],
            'phone' => [null, '02/59 201 220'],
            'street' => [null, 'Palisády 46'], 'postcode' => [null, '811 06'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Ekumenický výbor je orgánom Evanjelickej cirkvi augsburského vyznania na Slovensku, ktorý sa venuje ekumenickej spolupráci s ostatnými kresťanskými cirkvami. Jeho poslaním je hľadať cesty spoločného kresťanského svedectva s kresťanmi rôznych cirkví, presadzovať a pomáhať uvádzať do života výsledky ekumenického dialógu, informovať o dosiahnutom pokroku a odstraňovať predsudky s cieľom podporovať viditeľnú jednotu Cirkvi prostredníctvom spoločných bohoslužieb, vzdelávania a služby núdznym.</p><p>Výbor sa pravidelne stretáva — napríklad dvadsiate zasadnutie sa konalo v marci 2026 v Poprade — a plánuje pripomenúť 25. výročie vzájomného uznania krstu medzi evanjelikmi a katolíkmi. V rámci Týždňa modlitieb za jednotu kresťanov pripravuje videopozvánky, online ekumenické večery a zbierky, napríklad pre kresťanov v Arménsku.</p><p>Výbor sídli pri Generálnom biskupskom úrade ECAV v Bratislave a nemá vlastnú samostatnú adresu.</p>',
        ],
        146 => [
            'name' => 'Rímskokatolícka farnosť sv. Mikuláša',
            'website' => [null, 'https://presov.fara.sk'],
            'email' => [null, 'po.mesto@abuke.sk'],
            'phone' => [null, '051/77 33 500'],
            'street' => [null, 'Hlavná 81'], 'postcode' => [null, '080 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Farnosť svätého Mikuláša v Prešove patrí k najstarším a najvýznamnejším cirkevným inštitúciám v meste, viaže sa na Konkatedrálu svätého Mikuláša — najstaršiu stavbu a jedinú zachovanú gotickú sakrálnu pamiatku v Prešove. Počiatky kostola siahajú do predurbánneho obdobia, keď už v 13. storočí pravdepodobne stál na tomto mieste kostol nemeckej osady; samotná výstavba súčasnej stavby sa datuje do roku 1347, keď kráľovná Alžbeta povolila obyvateľom Prešova ťažiť kameň v šarišskom území na tento účel.</p><p>Ide o gotický trojloďový takzvaný halový kostol, typ rozšírený najmä v nemeckých oblastiach, s vonkajšími rozmermi 54,7 × 34,45 metra, vnútornými loďami vysokými 16 metrov a vežou so 71-metrovou výškou a 200 schodmi. Kostol má štatút konkatedrály.</p><p>Farnosť v súčasnosti poskytuje bežnú sviatostnú a pastoračnú službu — krsty, birmovky, sobáše aj nemocničnú kaplánsku službu — a vedie farskú kanceláriu s pravidelnými úradnými hodinami. Aktuálne prebieha rozsiahla oprava a reštaurovanie konkatedrály, na ktorú farnosť zbiera finančné príspevky od veriacich aj verejnosti.</p>',
        ],
        127 => [
            // rovnaký subjekt ako kanál id=196 "Sigord - Centrum pre rodinu" (duplicitný kanál, mimo rozsahu tejto migrácie); web tohto konkrétneho programu je už v DB správny
            'name' => 'Centrum pre rodinu Sigord',
            'email' => [null, 'informacie@pripravadomanzelstva.sk'],
            'phone' => [null, '+421 903 983 316'],
            'street' => [null, 'Sigord 134'], 'postcode' => [null, '082 52'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Tento kanál zastupuje konkrétny program Centra pre rodinu Sigord — Kurz prípravy na manželstvo, ktorý má vlastnú webovú stránku aj kontakt, no patrí pod ten istý subjekt ako kanál „Sigord - Centrum pre rodinu". Ide o trojdňový víkendový pobytový kurz pre páry, ktoré sa pripravujú na prijatie sviatosti manželstva, prebiehajúci priamo v centre s možnosťou ubytovania účastníkov a večernými rozhovormi.</p><p>Kurz vedie deväť manželských párov ako lektorov s dĺžkou manželstva od 7 do 34 rokov, ktorí odovzdávajú desať tematických okruhov zameraných na manželský život kombináciou prednášok, praktických cvičení, otázok a sebareflexných testov. Jedinečnosťou kurzu je, že témy prezentujú samotné manželské páry na základe vlastnej skúsenosti, vrátane krásnych aj náročných situácií, ktorými prešli.</p>',
        ],
        201 => [
            'name' => 'Family Garden',
            'municipality' => [4209, 242], // Celé Slovensko -> Bratislava (skutočné sídlo)
            // pôvodný web bol zdroj importu (hlascirkvi.sk), nie vlastná stránka centra
            'website' => ['https://hlascirkvi.sk', 'https://familygarden.sk'],
            'email' => [null, 'bratislava@familygarden.sk'],
            'phone' => [null, '+421 903 821 321'],
            'street' => [null, 'Pavlovičova 3'], 'postcode' => [null, '821 04'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Family Garden je poradenské centrum pre rodiny registrované ako občianske združenie so sídlom v Bratislave-Trnávke, ktoré bolo podľa dostupných zdrojov registrované 8. októbra 2014. Zakladateľkou je Katarína Baginová, ktorá filozofiu centra opisuje ako snahu naučiť ľudí „opraviť, čo je pokazené", namiesto opúšťania vzťahov; centrum sa primárne zameriava na manželské páry, keďže zdravé a zrelé manželské vzťahy podľa neho vytvárajú podmienky pre zdravú a fungujúcu rodinu.</p><p>Aktivity sú určené aj snúbencom, jednotlivcom a opusteným — všetkým, ktorým záleží na fungujúcich vzťahoch. Centrum pravidelne ponúka svedectvá párov, odborné prednášky, stretnutia s kresťanskými koučmi, prípravu pre snúbencov a poradenstvo v oblasti plodnosti a neplodnosti, ktoré otvorilo koncom roka 2015.</p><p>Vzdelávacie kurzy centra sú určené viacerým cieľovým skupinám — párom, zasväteným osobám, pedagógom, športovým trénerom a mentorom — s témami ako efektívna komunikácia, riešenie konfliktov, kľúčové aspekty manželského života, umenie rodičovstva v dnešnom svete a práca s emóciami. Centrum tiež pripravovalo televízny program o komunikácii „Dá sa to aj inak" na TV Lux a spolupracovalo s katolíckymi rehoľnými spoločenstvami.</p>',
        ],
        522 => [
            'name' => 'Diecézny katechetický úrad Spišskej diecézy',
            'website' => [null, 'https://www.dkuspis.sk'],
            'email' => [null, 'dkuspis@dkuspis.sk'],
            'phone' => [null, '+421 909 250 261'],
            'street' => [null, 'Levočská 10'], 'postcode' => [null, '052 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Diecézny katechetický úrad Spišskej diecézy je hlavným orgánom, prostredníctvom ktorého diecézny biskup usmerňuje a riadi katechetickú činnosť v Spišskej diecéze. Úrad bol založený 1. septembra 1997 a v súčasnosti zamestnáva približne tri až štyri pracovníkov; je samostatnou organizačnou zložkou Biskupského úradu Spišskej diecézy s právnou subjektivitou, so sídlom v Spišskej Novej Vsi, odlišným od sídla biskupského úradu v Spišskej Kapitule.</p><p>Poslaním úradu je pomáhať komunikovať vieru zrozumiteľným, tvorivým a verným spôsobom — v škole, vo farnosti aj v bežnom živote — a geograficky pokrýva regióny Orava, Liptov a Spiš. Medzi hlavné činnosti patrí podpora učiteľov náboženskej výchovy a náboženstva, organizovanie farskej katechézy, sprevádzanie katolíckych škôl a formácia katechétov a učiteľov.</p><p>Úrad tvorí vzdelávacie materiály a projekty, organizuje biblické olympiády, semináre a ďalšie náboženské podujatia a vydáva odborný katechetický časopis Katechetické ozveny, ktorý slúži ako metodická a formačná pomôcka pre katechétov v diecéze.</p>',
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
