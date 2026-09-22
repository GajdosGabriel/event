<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pokračovanie 2026_09_22_130000_backfill_contact_info_on_organization_canals.php
 * — doplní web/email/telefón/adresu pre ďalších 28 neosobných kanálov
 * (organizátorov), zistené z ich oficiálnych webov 22. 9. 2026. Rovnaký
 * guard, rovnaká štruktúra dát a rovnaký jednosmerný prepis `body` — pozri
 * dokumentáciu v predchádzajúcej migrácii.
 *
 * Kanály id=815 (Katolícke spoločenstvo Cor et Lumen Christi) a id=833
 * (Fraternity Jeruzalem) sa nedopĺňajú vôbec — nenašla sa žiadna overená
 * väzba na Slovensko (len medzinárodné sídlo v UK, resp. žiadny potvrdený
 * slovenský kontakt), rovnako ako pri kanáli id=70 v predošlej migrácii.
 *
 * Ďalšie duplicity tej istej reálnej organizácie: id 169/356/698 = OZ
 * Bratislavská Kalvária (698 doplnený už predtým), id 94/218 = Biskupstvo
 * Nitra, id 193/524 = Televízia LUX, id 373/72 = Kolégium Antona
 * Neuwirtha — dostávajú tie isté overené údaje, zlúčenie nie je súčasťou
 * tejto migrácie. Kanál id=719 má v DB zjavne zlúčený/nejasný názov
 * (viacero nesúvisiacich mien); kontaktné údaje patria organizácii ZKSM,
 * ktorej sídlo sa zhoduje s pôvodnou obcou záznamu — pozri komentár v
 * `body`.
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
        80 => [
            'name' => 'Mesto Bardejov – oddelenie kultúry',
            'website' => [null, 'https://kulturabardejov.sk'],
            'email' => [null, 'bardejov.cultura@gmail.com'],
            'phone' => [null, '054 472 30 13'],
            'street' => [null, 'Radničné námestie 21'], 'postcode' => [null, '085 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Oddelenie kultúry Mestského úradu Bardejov koordinuje a organizuje kultúrne podujatia v meste, spravuje mestský kultúrny kalendár a spolupracuje s kultúrnymi zariadeniami mesta.</p>',
        ],
        92 => [
            'name' => 'Mary’s Meals',
            'municipality' => [663, 1891], // Dolný Kubín -> Liptovský Michal (skutočné sídlo)
            'website' => [null, 'https://marysmeals.sk'],
            'email' => [null, 'info@marysmeals.sk'],
            'phone' => [null, '0951 126 267'],
            'street' => [null, 'Liptovský Michal 39'], 'postcode' => [null, '034 83'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Mary’s Meals je slovenská pobočka medzinárodného charitatívneho hnutia, ktoré zabezpečuje jedno jedlo denne pre deti v najchudobnejších krajinách sveta priamo v mieste vzdelávania. Vďaka nízkym réžijným nákladom a práci dobrovoľníkov stačí 22 € na celoročné stravovanie jedného dieťaťa; globálne hnutie takto dosahuje vyše 3 miliónov detí v 16 krajinách.</p>',
        ],
        94 => [
            // rovnaká inštitúcia ako kanál id=218 "Biskupstvo Nitra" (duplicitný kanál, mimo rozsahu tejto migrácie)
            'name' => 'Nitrianska diecéza',
            'website' => [null, 'https://www.biskupstvo-nitra.sk'],
            'email' => [null, 'nitra@kbs.sk'],
            'phone' => [null, '+421 37 772 17 47'],
            'street' => [null, 'Nám. Jána Pavla II. 7'], 'country' => [null, 'Slovensko'], // PSČ sa medzi zdrojmi líši, neuvádza sa (rovnako ako pri id=218)
            'body' => '<p>Biskupstvo Nitra je diecéznym úradom Rímskokatolíckej cirkvi, ktorý spravuje farnosti, cirkevné školy a pastoračné centrá na území Nitrianskej diecézy. Zabezpečuje aj charitatívnu činnosť a správu historických cirkevných objektov.</p>',
        ],
        97 => [
            'name' => 'Košická arcidiecéza',
            'website' => [null, 'https://ke-arcidieceza.sk'],
            'phone' => [null, '055/6828 111'],
            'street' => [null, 'Hlavná 28'], 'postcode' => [null, '041 83'], 'country' => [null, 'Slovensko'],
            // email sa medzi zdrojmi líšil (abukosice@kbs.sk vs abukosice@abuke.sk), neuvádza sa
            'body' => '<p>Košická arcidiecéza je rímskokatolícka arcidiecéza pre východné Slovensko, ktorá spravuje farnosti, seminár, nemocnice, mládežnícke centrá, rodinné a charitatívne služby a pútnické miesta.</p>',
        ],
        111 => [
            'name' => 'Rodinkovo',
            'municipality' => [383, 119], // Celulózka -> Beluša (skutočné sídlo)
            'website' => ['https://program-amoris.webnode.sk', 'https://rodinkovo.sk'],
            'email' => [null, 'info@rodinkovo.sk'],
            'phone' => [null, '+421 911 911 780'],
            'street' => [null, 'Belušské Slatiny 1941'], 'postcode' => [null, '018 61'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Rodinkovo je dom prijatia pre rodiny v Belušských Slatinách, ktorý poskytuje ubytovanie, stravovanie a duchovno-formačné pobyty inšpirované podobným centrom v talianskej obci Caresto. Organizuje víkendové aj týždňové pobyty a programy ako Program AMORIS pre manželov.</p>',
        ],
        150 => [
            'name' => 'Centrum pomoci pre rodinu - Trnava',
            'website' => [null, 'https://cppr.sk'],
            'email' => [null, 'cppr@cppr.sk'],
            'phone' => [null, '+421 948 262 912'],
            'street' => [null, 'Štefánikova 46'], 'postcode' => [null, '917 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Centrum pomoci pre rodinu v Trnave je poradenské a preventívne centrum poskytujúce bezplatné sociálno-právne, psychologické, pastoračné a rodinné poradenstvo, najmä pre rodičov a deti. Prevádzkuje aj projekt Eko šatník a integračné centrum OPORA, pôsobí v Trnave aj Piešťanoch.</p>',
        ],
        159 => [
            'name' => 'Ústav pamäti národa',
            'website' => [null, 'https://upn.gov.sk'],
            'email' => [null, 'info@upn.gov.sk'],
            'phone' => [null, '02/593 00 311'],
            'street' => [null, 'Miletičova 21'], 'postcode' => [null, '820 18'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Ústav pamäti národa je štátna inštitúcia skúmajúca obdobie neslobody 1939 – 1945 a 1945 – 1989 na Slovensku. Spravuje rozsiahly archív, publikuje historický výskum a spomienky a realizuje vzdelávacie programy pre školy.</p>',
        ],
        169 => [
            // rovnaká organizácia ako kanály id=356 a id=698 (duplicitné kanály, mimo rozsahu tejto migrácie)
            'name' => 'OZ Bratislavská Kalvária',
            'municipality' => [2119, 242], // Martin -> Bratislava (skutočné sídlo)
            'website' => [null, 'https://bratislavskakalvaria.sk'],
            'email' => [null, 'info@bratislavskakalvaria.sk'],
            'street' => [null, 'Oravská 1264/18'], 'postcode' => [null, '821 09'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Občianske združenie Bratislavská Kalvária, založené v roku 2019, sa venuje obnove a revitalizácii Bratislavskej Kalvárie — krížovej cesty z roku 1694. Koordinuje reštaurátorské a záchranné práce na jednotlivých zastaveniach.</p>',
        ],
        187 => [
            'name' => 'Rád bosých bratov Preblahoslavenej Panny Márie',
            // pôvodný web bol zdroj importu (hlascirkvi.sk), nie vlastná stránka rádu
            'website' => ['https://hlascirkvi.sk', 'https://bosikarmelitani.sk'],
            // rád nemá jednotné národné sídlo (viacero kláštorov), preto sa adresa/kontakt nedopĺňa
            'body' => '<p>Rád bosých karmelitánov je kontemplatívno-apoštolská rehoľná komunita spájajúca vnútornú modlitbu so službou podľa potrieb Cirkvi, siahajúca tradíciou k hore Karmel a reformovaná sv. Teréziou z Ávily a sv. Jánom z Kríža. Na Slovensku pôsobí vo viacerých kláštoroch (napr. Staré Hory, Banská Bystrica, Košice-Lorinčík) bez jedného centrálneho sídla.</p>',
        ],
        193 => [
            // rovnaká organizácia ako kanál id=524 "Televízia LUX" (duplicitný kanál, mimo rozsahu tejto migrácie)
            'name' => 'TV LUX',
            // pôvodný web bol zdroj importu (hlascirkvi.sk), nie vlastná stránka TV LUX
            'website' => ['https://hlascirkvi.sk', 'https://www.tvlux.sk'],
            'email' => [null, 'tvlux@tvlux.sk'],
            'phone' => [null, '+421 2 212 955 55'],
            'street' => [null, 'Prepoštská 5'], 'postcode' => [null, '811 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>TV LUX je slovenská katolícka televízia zameraná na duchovné a ľudské hodnoty. Vysiela náboženské a spravodajské relácie, dokumenty a diskusné programy s kresťanskou tematikou a prevádzkuje aj e-shop s náboženskou literatúrou a médiami.</p>',
        ],
        200 => [
            'name' => 'Trenčín - Centrum pre rodinu',
            'municipality' => [4209, 3592], // Celé Slovensko -> Trenčín (skutočné sídlo)
            // pôvodný web bol zdroj importu (hlascirkvi.sk), nie vlastná stránka centra
            'website' => ['https://hlascirkvi.sk', 'https://www.cprtrencin.sk'],
            'email' => [null, 'info@cprtrencin.sk'],
            'phone' => [null, '0903 440 132'],
            'street' => [null, 'Farská 12'], 'postcode' => [null, '911 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Centrum pre rodinu v Trenčíne je občianske združenie rodín, ktoré prevádzkuje komunitný priestor s kaviarňou a záhradou pri farských schodoch. Ponúka bezplatné poradenstvo, formačné a rekreačné programy pre manželov, rodiny, deti aj mladých vďaka zázemiu vyše stovky dobrovoľníkov.</p>',
        ],
        202 => [
            // reálny názov organizácie je "CYRILOMETODIADA" (DB má preklep "CYRILOMETODODIA"); názov záznamu sa touto migráciou nemení, len kontakty
            'name' => 'CYRILOMETODODIA, o. z.',
            'municipality' => [3698, 251], // Vaďovce -> Bratislava - Petržalka (skutočné sídlo)
            // pôvodný web bol zdroj importu (hlascirkvi.sk), nie vlastná stránka združenia
            'website' => ['https://hlascirkvi.sk', 'https://www.cyrilometodiada.sk'],
            'email' => [null, 'cyrilometodiada@cyrilometodiada.sk'],
            'phone' => [null, '+421 907 817 323'],
            'street' => [null, 'Vlastenecké námestie 1186/10'], 'postcode' => [null, '851 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Občianske združenie inšpirované odkazom sv. Cyrila a Metoda, kráľa Rastislava a ďalších osobností, ktoré sa venuje pripomínaniu kresťanských, kultúrnych a spoločenských udalostí slovenského národa s cieľom obnovovať a upevňovať kresťanské a národné korene Slovákov doma i v zahraničí. Vydáva aj historický časopis Cyrilometodiáda.</p>',
        ],
        232 => [
            'name' => 'Milosrdné sestry Svätého kríža',
            'municipality' => [4209, 3596], // Celé Slovensko -> Trnava (skutočné sídlo provincialátu)
            'website' => [null, 'https://sestrysvkriza.sk'],
            'email' => [null, 'provincialat@sestrysvkriza.sk'],
            'phone' => [null, '+421 33 5536 360'],
            // presné číslo domu a PSČ sa medzi zdrojmi rozchádzali, adresa sa preto nedopĺňa
            'body' => '<p>Milosrdné sestry Svätého kríža sú rímskokatolícka ženská kongregácia založená v roku 1856 vo Švajčiarsku. Slovenská provincia má komunity po celom Slovensku a venuje sa najmä školstvu, zdravotníctvu, sociálnej a pastoračnej službe a starostlivosti o seniorov, chorých, deti a mládež.</p>',
        ],
        315 => [
            'name' => 'Klub kresťanských lekárov a zdravotníkov Bratislava',
            'website' => [null, 'https://kklz.org'],
            'email' => [null, 'kklzba@gmail.com'],
            // klub nemá vlastnú registrovanú adresu (neformálne združenie), adresa sa nedopĺňa
            'body' => '<p>Klub kresťanských lekárov a zdravotníkov je neformálne združenie pôsobiace od roku 2011, ktoré združuje zdravotníkov z Bratislavy a okolia. Organizuje mesačné stretnutia so svätou omšou a odbornou prednáškou a zúčastňuje sa na celoslovenských podujatiach kresťanských zdravotníkov.</p>',
        ],
        331 => [
            'name' => 'Rád bosých karmelitánov (OCD) a Svetský rád bosých karmelitánov (OCDS) v Bratislave',
            'website' => [null, 'https://ocds.sk'],
            'email' => [null, 'ocdsbratislava@gmail.com'],
            'phone' => [null, '+421 948 555 778'],
            'street' => [null, 'Dobrovičova 2'], 'postcode' => [null, '811 02'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Rád bosých karmelitánov a Svetský rád bosých karmelitánov je komunita veriacich žijúcich podľa duchovnosti sv. Terézie z Ávily a sv. Jána z Kríža, s dôrazom na modlitbu, chudobu a poslušnosť v rámci vlastného životného stavu. Bratislavské spoločenstvo sv. Jána od Kríža vzniklo v roku 1994 a má celoslovenskú pôsobnosť.</p>',
        ],
        356 => [
            // rovnaká organizácia ako kanály id=169 a id=698 (duplicitné kanály, mimo rozsahu tejto migrácie)
            'name' => 'Občianske združenie Bratislavská Kalvária a Kresťanské spoločenstvo Sprevádzajúci',
            'website' => [null, 'https://bratislavskakalvaria.sk'],
            'email' => [null, 'info@bratislavskakalvaria.sk'],
            'street' => [null, 'Oravská 1264/18'], 'postcode' => [null, '821 09'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Občianske združenie Bratislavská Kalvária, založené v roku 2019, sa venuje obnove a revitalizácii Bratislavskej Kalvárie — krížovej cesty z roku 1694. Koordinuje reštaurátorské a záchranné práce na jednotlivých zastaveniach. Tento kanál eviduje aj spoluprácu s Kresťanským spoločenstvom Sprevádzajúci.</p>',
        ],
        360 => [
            'name' => 'Saleziáni, Domka a spoločenstvo Tymian',
            'website' => [null, 'https://www.domka.sk'],
            'email' => [null, 'sekretariat@domka.sk'],
            'phone' => [null, '+421 903 296 526'],
            'street' => [null, 'Miletičova 7'], 'postcode' => [null, '821 08'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Domka – Združenie saleziánskej mládeže je občianske združenie zastrešujúce saleziánsku mládežnícku prácu na Slovensku, ktoré združuje vyše 8400 členov. Venuje sa výchove a vzdelávaniu mládeže cez voľnočasové, vzdelávacie a pastoračné aktivity v saleziánskych strediskách. Tento kanál eviduje aj neformálne miništrantské spoločenstvo Tymian, pôsobiace v spolupráci so Saleziánmi a Domkou.</p>',
        ],
        373 => [
            // rovnaká inštitúcia ako kanál id=72 "Kolégium Antona Neuwirtha" (duplicitný kanál, mimo rozsahu tejto migrácie)
            'name' => 'Univerzitné kolégium Antona Neuwirtha',
            'website' => [null, 'https://kolegium.org'],
            'email' => [null, 'info@kolegium.org'],
            'street' => [null, 'Námestie padlých hrdinov 7'], 'postcode' => [null, '900 28'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Kolégium Antona Neuwirtha je nezávislá vzdelávacia inštitúcia založená v roku 2009, ktorá formuje mladých ľudí v duchu kresťanských hodnôt a kultúrneho dedičstva západnej civilizácie. Ponúka vzdelávacie programy pre deti, stredoškolákov, vysokoškolákov aj širokú verejnosť vrátane internátneho kolégia v Ivanke pri Dunaji.</p>',
        ],
        405 => [
            'name' => 'Jezuiti a spoločenstvá Magis a CVX',
            'website' => [null, 'https://jezuiti.sk'],
            'email' => [null, 'svkprov@jezuiti.sk'],
            'phone' => [null, '+421 2 5920 0400'],
            'street' => [null, 'Panská 11'], 'postcode' => [null, '814 99'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Slovenská provincia Spoločnosti Ježišovej (jezuiti) pôsobí v štyroch celosvetových apoštolských prioritách: duchovné cvičenia a rozlišovanie, kráčanie s chudobnými, sprevádzanie mladých a starostlivosť o stvorenstvo. Prevádzkuje exercičné domy a viaceré farnosti a kostoly. Tento kanál eviduje aj spoluprácu so spoločenstvami Magis (mladí 18 – 30 rokov žijúci ignaciánsku spiritualitu) a CVX (Spoločenstvo kresťanského života).</p>',
        ],
        415 => [
            'name' => 'Farnosť Štefanová',
            'website' => [null, 'https://mojakomunita.sk/web/farnost-stefanova'],
            'email' => [null, 'stefanova@ba.ecclesia.sk'],
            'phone' => [null, '+421 33 6446 314'],
            'street' => [null, 'Štefanová 102'], 'postcode' => [null, '900 86'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Rímskokatolícka farnosť Štefanová (okres Pezinok, Bratislavská arcidiecéza) spravuje kostol sv. Štefana Uhorského, posvätený v roku 1500, s farnosťou doloženou od roku 1397. Súčasťou farnosti je aj kaplnka sv. Rozálie a každoročná farská púť 19. októbra.</p>',
        ],
        466 => [
            'name' => 'spoločenstva Nádej a portálu SingleKatolici.sk',
            'website' => [null, 'https://singlekatolici.sk'],
            'body' => '<p>SingleKatolici.sk je zastrešujúci portál spájajúci katolícke projekty pastorácie slobodných na Slovensku, ktorý informuje o formačných, duchovných a spoločenských podujatiach pre nezadaných. Vznikol počas pandémie z iniciatívy kňaza Martina Šalamona. Tento kanál eviduje aj modlitbové spoločenstvo Nádej, ktoré organizuje nedeľné stretnutia slobodných v Košiciach a Nitre.</p>',
        ],
        467 => [
            'name' => 'Tlačová kancelária Konferencie biskupov Slovenska (TK KBS)',
            'website' => [null, 'https://www.tkkbs.sk'],
            'email' => [null, 'reporter@tkkbs.sk'],
            'phone' => [null, '02/5920 6510'],
            'street' => [null, 'Kapitulská 11'], 'postcode' => [null, '814 99'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Tlačová kancelária Konferencie biskupov Slovenska sleduje domácu a zahraničnú tlač, rozhlas a televíziu, vydáva spravodajský servis zo života Cirkvi doma i vo svete, organizuje tlačové konferencie a vydáva elektronický bulletin Život Cirkvi.</p>',
        ],
        481 => [
            'name' => 'Hnutie na pomoc rozvedeným kresťanom',
            'email' => [null, 'slavka.kolesarova1@gmail.com'],
            'phone' => [null, '0905 288 845'],
            'body' => '<p>Hnutie na pomoc rozvedeným kresťanom je pastoračné hnutie pri Gréckokatolíckej cirkvi v Prešove, ktoré poskytuje duchovnú a komunitnú podporu rozvedeným formou pravidelných mesačných stretnutí (svätá omša, duchovné slovo, spoločenstvo). Pôsobí už vyše 10 rokov a jeho aktivity sa šíria aj do iných farností.</p>',
        ],
        493 => [
            // primárne dáta Farnosti Skačany (Pápežské misijné diela sú vedené samostatne, pozri kanál id=63)
            'name' => 'Pápežské misijné diela a Farnosť Skačany',
            'municipality' => [242, 3138], // Bratislava -> Skačany (skutočné sídlo)
            'website' => [null, 'https://skacany.nrb.sk'],
            'email' => [null, 'skacany@nrb.sk'],
            'phone' => [null, '038/7488 194'],
            'street' => [null, 'Školská 1'], 'postcode' => [null, '958 53'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Rímskokatolícka farnosť Skačany (okres Partizánske) zabezpečuje bohoslužby, sviatosti a duchovnú starostlivosť pre farnosť Skačany a filiálky Hradište a Partizánske – Návojovce. Tento kanál eviduje aj spoluprácu s Pápežskými misijnými dielami na Slovensku, ktoré majú vlastný samostatný kanál.</p>',
        ],
        568 => [
            'name' => 'Trnavská arcidiecéza',
            'website' => [null, 'https://abu.sk'],
            'email' => [null, 'abu@abu.sk'],
            'phone' => [null, '033/5912 111'],
            'street' => [null, 'Ulica Jána Hollého 10'], 'postcode' => [null, '917 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Trnavská arcidiecéza je rímskokatolícka metropolitná arcidiecéza so sídlom Arcibiskupského úradu v Trnave, riadiaca farnosti a pastoračné aktivity v regióne.</p>',
        ],
        680 => [
            // primárne dáta KPVS (Spoločnosť Ježišova/jezuiti je vedená samostatne, pozri kanál id=405)
            'name' => 'Konfederácia politických väzňov Slovenska a Spoločnosť Ježišova',
            'website' => [null, 'https://kpvs.forma.sk'],
            'email' => [null, 'kpvs.kpvs@gmail.com'],
            'phone' => [null, '02 5244 2321'],
            'street' => [null, 'Košická 5590/56'], 'postcode' => [null, '821 08'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Konfederácia politických väzňov Slovenska je občianske združenie založené v roku 1999, ktoré združuje bývalých politických väzňov a občanov postihnutých komunistickým režimom. Usiluje sa o odškodnenie krívd, zachovanie pamiatky nespravodlivo odsúdených a osádzanie pamätných tabúľ. Tento kanál eviduje aj spoluprácu so Spoločnosťou Ježišovou (jezuiti), ktorá má vlastný samostatný kanál.</p>',
        ],
        719 => [
            // zlúčený/nejasný záznam (viacero nesúvisiacich mien); kontaktné údaje patria ZKSM, ktorej sídlo sa zhoduje s pôvodnou obcou záznamu
            'name' => 'Strapar, Aslanov stôl, Godzone projekt, ZKSM, YES, WE CAN platforma',
            'website' => [null, 'https://zksm.sk'],
            'email' => [null, 'zksm@zksm.sk'],
            'phone' => [null, '+421 917 350 164'],
            'street' => [null, 'Kostolná-Záriečie 8'], 'postcode' => [null, '913 04'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Tento kanál v databáze zlučuje viacero nesúvisiacich mien (Strapar, Aslanov stôl, Godzone projekt, ZKSM, YES, WE CAN platforma) a jeho pôvod je nejasný. Kontaktné údaje patria organizácii ZKSM – Združenie kresťanských spoločenstiev mládeže, ktorej sídlo sa zhoduje s pôvodnou obcou záznamu; združuje stovky komunít vo všetkých slovenských diecézach a ponúka neformálne vzdelávanie, tábory, festivaly a pravidelné stretká pre deti a mládež.</p>',
        ],
        845 => [
            // primárne dáta Saleziánov (KPVS je vedená samostatne, pozri kanál id=680)
            'name' => 'Saleziáni dona Bosca a Konfederácia politických väzňov Slovenska',
            'website' => [null, 'https://saleziani.sk'],
            'email' => [null, 'sekretariat@saleziani.sk'],
            'phone' => [null, '+421 2 554 22 800'],
            'street' => [null, 'Miletičova 7'], 'postcode' => [null, '821 08'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Slovenská provincia Saleziánov dona Bosca je katolícka rehoľná komunita venujúca sa výchove mládeže cez sieť saleziánskych stredísk s výchovno-vzdelávacími, športovými, kultúrnymi, sociálnymi a duchovnými aktivitami pre mládež, rodiny aj seniorov. Tento kanál eviduje aj spoluprácu s Konfederáciou politických väzňov Slovenska, ktorá má vlastný samostatný kanál.</p>',
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
