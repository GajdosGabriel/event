<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pokračovanie predošlých backfill_contact_info_on_*_organization_canals.php
 * migrácií — doplní web/email/telefón/adresu pre ďalších 28 neosobných
 * kanálov, zistené z ich oficiálnych webov 22. 9. 2026. Rovnaký guard,
 * rovnaká štruktúra dát a rovnaký jednosmerný prepis `body`.
 *
 * Kanál id=367 (Tím RADOSTNÁ) sa nedopĺňa vôbec — nenašla sa žiadna
 * overená informácia o jeho existencii ani náplni.
 *
 * Kanály id=498 a id=557 sú zahraničné slovenské misie (Rím, Brusel) —
 * dopĺňajú sa im web/email/telefón/adresa v zahraničí, ale
 * `municipality_id` sa nemení, keďže tabuľka obcí obsahuje len
 * slovenské obce a niet čím ho nahradiť správne.
 *
 * Opäť veľa duplicít tej istej reálnej organizácie: id 207 = OCD/OCDS
 * Bratislava (duplicita id=331/679), id 355 = katRande (duplicita
 * id=686), id 414/820 = klub ISKRA (duplicita id=707/617/941 — už
 * 5. výskyt), id 577 = ZKSM (duplicita id=719), id 837 = Pápežské
 * misijné diela (duplicita id=63), id 938 = Cyrilometodiáda (duplicita
 * id=202 — správne napísaný názov), id 269 = Bratislavská arcidiecézna
 * charita (už opísaná v `body` kanála id=789), id 584 = KBS Rada pre
 * vedu (duplicita id=926). Dostávajú tie isté overené údaje, zlúčenie
 * kanálov nie je súčasťou tejto migrácie.
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
        207 => [
            // duplicita id=331/679 "Rád bosých karmelitánov (OCD) a Svetský rád bosých karmelitánov (OCDS) v Bratislave"
            'name' => 'Rád bosých karmelitánov (OCD) na Slovensku a Svetský rád bosých karmelitánov (OCDS) v Bratislave',
            'website' => [null, 'https://ocds.sk'],
            'email' => [null, 'ocdsbratislava@gmail.com'],
            'phone' => [null, '+421 948 555 778'],
            'street' => [null, 'Dobrovičova 2'], 'postcode' => [null, '811 02'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Rád bosých karmelitánov a Svetský rád bosých karmelitánov je komunita veriacich žijúcich podľa duchovnosti sv. Terézie z Ávily a sv. Jána z Kríža. Bratislavské spoločenstvo sv. Jána od Kríža vzniklo v roku 1994 a má celoslovenskú pôsobnosť.</p>',
        ],
        355 => [
            // duplicita id=686 "katRande o.z"
            'name' => 'katRande tím',
            'website' => [null, 'https://katrande.org'],
            'email' => [null, 'office@katrande.org'],
            'phone' => [null, '+421 910 911 686'],
            'street' => [null, 'Chrobákova 2693/22'], 'postcode' => [null, '841 02'], 'country' => [null, 'Slovensko'],
            'body' => '<p>KatRande.org je jediná katolícka zoznamovacia stránka na Slovensku, súčasť medzinárodnej platformy kathTreff. Okrem klasickej zoznamky organizuje aj speed-dating podujatia pre slobodných katolíkov.</p>',
        ],
        414 => [
            // duplicita id=707/617/941 "klub ISKRA" (rovnaká Facebook stránka)
            'name' => 'Voľnočasový kresťanský klub ISKRA',
            'body' => '<p>Klub ISKRA je kresťanské ekumenické voľnočasové spoločenstvo v Bratislave, ktoré vytvára priestor pre mladých pracujúcich ľudí bez ohľadu na vek či rodinný stav. Program je rozdelený do pravidelných „podklubov" — biblický update, spoločenské hry, knižný klub, bedminton, športový, dobrodružný, herný, tanečný a kultúrny klub — a klub tiež organizuje verejné akcie ako spoločné turistické výstupy či tanečné večery. Komunikuje predovšetkým cez Facebook a WhatsApp skupinu, vlastnú webovú stránku nemá.</p>',
        ],
        820 => [
            // duplicita id=707/617/941 "klub ISKRA" (rovnaká Facebook stránka)
            'name' => 'Kresťanský voľnočasový klub mladých pracujúcich ISKRA',
            'body' => '<p>Klub ISKRA je kresťanské ekumenické voľnočasové spoločenstvo v Bratislave, ktoré vytvára priestor pre mladých pracujúcich ľudí bez ohľadu na vek či rodinný stav. Program je rozdelený do pravidelných „podklubov" — biblický update, spoločenské hry, knižný klub, bedminton, športový, dobrodružný, herný, tanečný a kultúrny klub — a klub tiež organizuje verejné akcie ako spoločné turistické výstupy či tanečné večery. Komunikuje predovšetkým cez Facebook a WhatsApp skupinu, vlastnú webovú stránku nemá.</p>',
        ],
        577 => [
            // duplicita id=719 "...ZKSM..." (rovnaké združenie, vypísaný celý názov)
            'name' => 'Združenie kresťanských spoločenstiev mládeže',
            'municipality' => [4209, 1550], // Celé Slovensko -> Kostolná - Záriečie (skutočné sídlo)
            'website' => [null, 'https://zksm.sk'],
            'email' => [null, 'zksm@zksm.sk'],
            'phone' => [null, '+421 917 350 164'],
            'street' => [null, 'Kostolná-Záriečie 8'], 'postcode' => [null, '913 04'], 'country' => [null, 'Slovensko'],
            'body' => '<p>ZKSM – Združenie kresťanských spoločenstiev mládeže je občianske združenie pôsobiace v práci s deťmi a mládežou na kresťanských princípoch, vzniknuté po roku 1989 pod súčasným názvom od roku 1999. Združuje stovky komunít vo všetkých slovenských diecézach a ponúka neformálne vzdelávanie, tábory, festivaly a pravidelné stretká.</p>',
        ],
        837 => [
            // duplicita id=63 "Pápežské misijné diela na Slovensku"
            'name' => 'Pápežské misijné diela (PMD) na Slovensku',
            'website' => [null, 'https://www.misijnediela.sk'],
            'email' => [null, 'info@misijnediela.sk'],
            'phone' => [null, '02/529 64 916'],
            'street' => [null, 'Lazaretská 32'], 'postcode' => [null, '811 09'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Pápežské misijné diela na Slovensku koordinujú modlitebnú a finančnú podporu misijnej činnosti Katolíckej cirkvi vo svete v spolupráci s vatikánskym Dikastériom pre evanjelizáciu. Na Slovensku ich vedie národný riaditeľ menovaný na odporúčanie Konferencie biskupov Slovenska.</p>',
        ],
        938 => [
            // duplicita id=202 "CYRILOMETODODIA, o. z." (správne napísaný názov organizácie)
            'name' => 'CYRILOMETODIADA. o. z',
            'municipality' => [2643, 251], // Piešťany -> Bratislava - Petržalka (skutočné sídlo)
            'website' => [null, 'https://www.cyrilometodiada.sk'],
            'email' => [null, 'cyrilometodiada@cyrilometodiada.sk'],
            'phone' => [null, '+421 907 817 323'],
            'street' => [null, 'Vlastenecké námestie 1186/10'], 'postcode' => [null, '851 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Občianske združenie Cyrilometodiáda je inšpirované odkazom sv. Cyrila a Metoda, kráľa Rastislava a ďalších osobností a venuje sa pripomínaniu kresťanských, kultúrnych a spoločenských udalostí slovenského národa. Vydáva aj historický časopis Cyrilometodiáda.</p>',
        ],
        269 => [
            // podrobnejšie opísaná ako súčasť `body` kanála id=789 "Bratislavská arcidiecézna charita a Slovenská katolícka charita"
            'name' => 'Bratislavská arcidiecézna charita',
            'website' => [null, 'https://charitaba.sk'],
            'email' => [null, 'charitaba@charitaba.sk'],
            'phone' => [null, '02/442 50 374'],
            'street' => [null, 'Krasinského 6'], 'postcode' => [null, '821 04'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Bratislavská arcidiecézna charita je regionálna charita pôsobiaca v Bratislavskej arcidiecéze, súčasť siete Slovenskej katolíckej charity. Poskytuje sociálne, charitatívne a poradenské služby ľuďom v núdzi v Bratislave a okolí, s viacerými pracoviskami v meste.</p>',
        ],
        584 => [
            // duplicita id=926 "Konferencia biskupov Slovenska – Rada pre vedu, vzdelanie a kultúru"
            'name' => 'Rada Konferencie biskupov Slovenska pre vedu, vzdelanie a kultúru',
            'municipality' => [1565, 242], // Košice -> Bratislava (skutočné sídlo)
            'website' => [null, 'https://kultura.kbs.sk'],
            'email' => [null, 'tajomnik@kultura.kbs.sk'],
            'phone' => [null, '+421 2 5920 6501'],
            'street' => [null, 'Kapitulská 11'], 'postcode' => [null, '814 99'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Rada pre vedu, vzdelanie a kultúru je jedna z rád Konferencie biskupov Slovenska, ktorá zastrešuje spoluprácu Cirkvi s akademickou a kultúrnou obcou. Udeľuje ceny Fides et Ratio a Fra Angelica a organizuje stretnutia a dokumenty v tejto oblasti.</p>',
        ],
        903 => [
            'name' => 'Spoločenstvo kresťanských fotografov Človek a Viera',
            'website' => [null, 'https://www.clovekaviera.sk'],
            'body' => '<p>Spoločenstvo kresťanských fotografov Človek a Viera vzniklo v roku 2011 v Česku, keď traja fotografi začali rozvíjať cirkevnú fotografiu ako dovtedy zanedbávaný žáner a kultivovať správanie fotografov počas liturgických a cirkevných udalostí. Dnes má vyše 230 členov vo veku 15 až 84 rokov naprieč Českom, Slovenskom, Nemeckom, Maďarskom, Poľskom a Egyptom, pričom na Slovensku pôsobí od roku 2017 s približne 30 až 45 fotografmi.</p><p>Spoločenstvo prevádzkuje rozsiahlu online fotobanku s desaťtisícami galérií a stovkami tisíc fotografií z Česka aj Slovenska, ktorú využívajú farnosti, cirkevné médiá a publikácie; fotografi pracujú bezplatne ako služba Cirkvi. Je oficiálnym fotografom Katedrály svätého Víta v Prahe a na Slovensku organizuje vstupné workshopy pre nových fotografov a putovné výstavy počas adventu.</p>',
        ],
        798 => [
            // primárne dáta Nadácie Pro Patria; CYRILOMETODIADA má vlastný samostatný kanál id=202/938, Spolok Slovákov v Poľsku sa nepodarilo overiť
            'name' => 'Spolok Slovákov v Poľsku, CYRILOMETODIADA, o. z., Nadácia PRO PATRIA',
            'municipality' => [1829, 242], // Levice -> Bratislava (skutočné sídlo Nadácie Pro Patria)
            'website' => [null, 'https://propatria.sk'],
            'street' => [null, 'Martinengova 4880/26'], 'postcode' => [null, '811 02'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Tento kanál v databáze zlučuje tri rôzne subjekty — Spolok Slovákov v Poľsku, občianske združenie Cyrilometodiáda (ktoré má v databáze vlastný samostatný kanál) a Nadáciu Pro Patria. Nadáciu Pro Patria založil bývalý poslanec NR SR a historik Anton Hrnko s poslaním podporovať a uchovávať poznanie slovenských národných dejín a zdôrazňovať význam spoločnej minulosti pre budúcnosť Slovenska.</p><p>Nadácia podporuje výskum, vzdelávanie a kultúrne projekty spojené so slovenskou históriou a národným dedičstvom a spolupracuje so školami, múzeami, archívmi a inštitúciami. Jej vlajkovou aktivitou je každoročné udeľovanie Ceny nadácie Pro Patria osobnostiam, ktoré sa zaslúžili o šírenie poznania slovenských dejín a posilňovanie národnej identity.</p>',
        ],
        730 => [
            'name' => 'Rímskokatolícky farský úrad Branč',
            'phone' => [null, '037/656 51 04'],
            'street' => [null, 'Cetínska 44/1'], 'postcode' => [null, '951 13'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Obec Branč v okrese Nitra sa v písomných prameňoch spomína už v roku 1156, fara sa spomína v roku 1166 a hrad v roku 1241. Rímskokatolícky farský Kostol Navštívenia Panny Márie pochádza vo svojom jadre z konca 13. storočia, pričom farnosť bola obnovená v roku 1704.</p><p>V nedávnych rokoch prešiel kostol rozsiahlou rekonštrukciou, počas ktorej sa objavili archeologické nálezy z 13. storočia; obnovený kostol posvätil nitriansky biskup Viliam Judák. Farský úrad dnes zabezpečuje pastoráciu, nedeľné čítania, farské oznamy a slávenie svätých omší pre miestne katolícke spoločenstvo v rámci Nitrianskej diecézy.</p>',
        ],
        302 => [
            'name' => 'Spoločenstvo Extrémnej krížovej cesty (EKC) na Slovensku',
            'website' => [null, 'https://ekc.sk'],
            'email' => [null, 'ekc.slovensko@gmail.com'],
            'phone' => [null, '+421 907 695 490'],
            'body' => '<p>Extrémna krížová cesta (EKC) vznikla v Poľsku v roku 2009 z iniciatívy kňaza Jacka „Wiosna" Strycz­ka ako nočná, približne 30 až 40 kilometrov dlhá túra lesom a horami v tichu, kombinovaná so 14 zastaveniami krížovej cesty a meditáciami. Trasa začína svätou omšou obetovanou za pútnikov a končí požehnaním, s cieľom „siahnuť na dno svojich síl" a prostredníctvom fyzickej výzvy zažiť Božiu prítomnosť.</p><p>Z lokálnej iniciatívy sa stalo jedným z najrýchlejšie rastúcich katolíckych duchovných hnutí na svete — pôsobí v 16 krajinách na vyše 1420 trasách s vyše 100 000 účastníkmi ročne. Na Slovensku sa koná od roku 2018; v roku 2025 to bolo už 32 trás v 30 mestách s približne 7840 účastníkmi.</p>',
        ],
        377 => [
            'name' => 'Hudobná subkomisia Liturgickej komisie KBS',
            'municipality' => [4209, 242], // Celé Slovensko -> Bratislava (zdieľané sídlo s KBS)
            'website' => [null, 'https://hudba.kbs.sk'],
            'email' => [null, 'tajomnik@hudba.kbs.sk'],
            'street' => [null, 'Kapitulská 11'], 'postcode' => [null, '814 99'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Hudobná subkomisia Liturgickej komisie KBS je poradný orgán venovaný otázkam liturgickej a cirkevnej hudby na Slovensku, ktorého vlastný štatút platí od 7. marca 2023. Vedie ju Mons. Marek Forgáč spolu s Mons. Jánom Kubošom a pravidelne zasadá, okrem iného na Katolíckej univerzite v Ružomberku.</p><p>Hlavnou prebiehajúcou iniciatívou subkomisie je projekt Katolícky spevník — celoslovenský prieskum medzi organistami, kantormi, spevákmi, zbormajstrami, kňazmi a veriacimi o obľúbenosti a používaní jednotlivých piesní. Subkomisia tiež posudzuje nové spevníky a zbierky piesní, vydáva k nim schvaľovacie stanoviská pre ich liturgické používanie a organizuje púte pre cirkevných hudobníkov.</p>',
        ],
        973 => [
            // rovnaká farnosť ako kanál id=499 (duplicitný kanál, mimo rozsahu tejto migrácie); web tejto farnosti
            'name' => 'Farnosť Bratislava - Prievoz v Ružinove a katechisti neokatechumenátnej cesty',
            'website' => [null, 'https://fara-ba-prievoz.sk'],
            'email' => [null, 'rkfu@vdp.sk'],
            'phone' => [null, '02/43 41 51 58'],
            'street' => [null, 'Tomášikova 8'], 'postcode' => [null, '821 03'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Farnosť Bratislava-Prievoz pri Kostole sv. Vincenta de Paul spravuje Misijná spoločnosť sv. Vincenta de Paul a je uvádzaná ako najväčšia farnosť na Slovensku. Pri farnosti aktívne pôsobí aj spoločenstvo Neokatechumenátnej cesty — medzinárodné katolícke formačné hnutie založené v roku 1964 v Madride, na Slovensko prišlo v roku 1978 a dnes má tu približne 55 spoločenstiev v 29 farnostiach.</p><p>V Prievoze sa katechézy Neokatechumenátnej cesty konajú v Kaplnke Panny Márie Zázračnej medaily, počas pôstu tam bývajú aj ranné chvály vedené bratmi prvého spoločenstva. Hnutie sa snaží priviesť ľudí k bratskej jednote a k zrelej viere prostredníctvom dlhodobého katechumenátneho sprevádzania.</p>',
        ],
        775 => [
            'name' => 'Univerzitné pastoračné centrum (UPeCe) sv. Jozefa Freinademetza',
            'website' => [null, 'https://www.upcba.sk'],
            'email' => [null, 'contact@upcba.sk'],
            'phone' => [null, '+421 905 406 679'],
            'street' => [null, 'Staré Grunty 36'], 'postcode' => [null, '841 04'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Univerzitné pastoračné centrum svätého Jozefa Freinademetza v areáli vysokoškolských internátov Mlyny spravuje rehoľa Spoločnosť Božieho Slova (Verbisti); slúži predovšetkým študentom Univerzity Komenského a pôsobí už približne 27 rokov. Cieľom centra je sprevádzať študentov v duchovnom, kultúrnom a športovom rozvoji pod heslom „Ži život v plnosti".</p><p>Centrum denne, okrem soboty, keď sa slávi gréckokatolícka liturgia, ponúka svätú omšu v kaplnke alebo veľkej sále a je súčasťou Združenia kresťanských spoločenstiev mládeže. Okrem duchovného programu ponúka aj voľnočasové aktivity ako stolný tenis, bedminton a florbal.</p>',
        ],
        279 => [
            'name' => 'Farnosť Levoča',
            'website' => [null, 'https://www.farnostlevoca.sk'],
            'email' => [null, 'farnost.levoca@gmail.com'],
            'phone' => [null, '0948 439 089'],
            'street' => [null, 'Námestie Majstra Pavla 52'], 'postcode' => [null, '054 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Farnosť Levoča spravuje Baziliku svätého Jakuba na Námestí Majstra Pavla a súčasťou farnosti je aj Mariánska hora — jedno z najvýznamnejších mariánskych pútnických miest na Slovensku s vlastným pútnickým a rekolekčným domom a výročnou júlovou púťou. Vo farnosti pôsobí aj kláštor minoritov.</p><p>Administratívne teda Mariánska hora aj miestny kláštor patria pod farnosť Levoča, hoci majú vlastné samostatné kontakty pre pútnikov a návštevníkov jednotlivých pútnických a kláštorných aktivít.</p>',
        ],
        257 => [
            // obe zlúčené zložky majú vlastný samostatný kanál (id=377 a id=480/329)
            'name' => 'Hudobná subkomisia Liturgickej komisie Konferencie biskupov Slovenska a Spolok svätého Vojtecha',
            'body' => '<p>Tento kanál zlučuje Hudobnú subkomisiu Liturgickej komisie Konferencie biskupov Slovenska (samostatný kanál „Hudobná subkomisia Liturgickej komisie KBS") so Spolkom svätého Vojtecha (samostatný kanál „Spolok svätého Vojtecha a Dom Quo Vadis"). Subkomisia je poradný orgán venovaný liturgickej a cirkevnej hudbe na Slovensku, vedený Mons. Marekom Forgáčom, ktorého vlajkovým projektom je celoslovenský prieskum Katolícky spevník o obľúbenosti jednotlivých piesní.</p>',
        ],
        461 => [
            // lokálna trasa tej istej organizácie ako kanál id=302 "Spoločenstvo Extrémnej krížovej cesty (EKC) na Slovensku"
            'name' => 'Spoločenstvo EKC',
            'website' => [null, 'https://ekc.sk'],
            'email' => [null, 'ekc.slovensko@gmail.com'],
            'phone' => [null, '+421 907 695 490'],
            'body' => '<p>Extrémna krížová cesta (EKC) je celoslovenská sieť nočných pútnických túr kombinovaných so 14 zastaveniami krížovej cesty, ktorá vznikla v Poľsku v roku 2009 a na Slovensku sa koná od roku 2018. Tento kanál zastupuje konkrétnu trasu Zborov – Bardejov, jednu z najväčších na Slovensku — napríklad 11. apríla 2025 sa jej zúčastnilo 911 pútnikov na trase dlhej 32 až 42 kilometrov.</p>',
        ],
        1031 => [
            // Občianske združenie Bratislavská Kalvária má vlastný samostatný kanál (id=169/356/698); tu ide o dáta farnosti pri Kalvárii
            'name' => 'Bratia Dominikáni – Farnosť Bratislavská Kalvária, OZ Bratislavská Kalvária',
            'website' => [null, 'https://kalvaria.sk'],
            'email' => [null, 'kalvaria@kalvaria.sk'],
            'phone' => [null, '0908 090 799'],
            'street' => [null, 'Na Kalvárii 10'], 'postcode' => [null, '811 04'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Farnosť Bratislava-Kalvária vznikla 11. októbra 1933 vyčlenením z farnosti Bratislava-Nové Mesto; pôvodne ju spravovali františkáni, od roku 1990 ju vedú dominikáni, ktorí tu v novembri 1989 založili Konvent svätého Dominika. Farský Kostol Panny Márie Snežnej z roku 1943 stojí na mieste staršieho barokového kostola na kopci Kalvária, pútnickom mieste od 18. storočia, kde dodnes vedie 14 zastavení krížovej cesty.</p><p>Komunistický režim v roku 1959 zbúral pôvodnú 50-metrovú vežu kostola kvôli výhľadu na stavaný Slavín. Vo farnosti pôsobí detský spevácky zbor Dominik, ružencové a púťové bratstvo aj klub seniorov; v roku 2024 arcibiskup Stanislav Zvolenský posvätil nový organ a prebieha výstavba pastoračného centra. Tento kanál eviduje aj Občianske združenie Bratislavská Kalvária, ktoré má vlastný samostatný kanál.</p>',
        ],
        1012 => [
            'name' => 'Sestry Congregatio Jesu',
            'website' => [null, 'https://congregatiojesu.com'],
            'email' => [null, 'ruzomberokcj@gmail.com'],
            'phone' => [null, '+421 44 430 46 39'],
            'street' => [null, 'J. Sladkého 28'], 'country' => [null, 'Slovensko'], // presné PSČ sa nepodarilo overiť, neuvádza sa
            'body' => '<p>Congregatio Jesu (CJ), pôvodne Ústav blahoslavenej Panny Márie, založila Angličanka Mary Ward v roku 1609 v Saint-Omer. Na Slovensko boli sestry pozvané v roku 1882 biskupom Konštantínom Schusterom, prvý dom vznikol v Prešove a slovenská provincia bola erigovaná 5. apríla 1941.</p><p>Dnes má slovenská provincia 146 členiek pôsobiacich v Bratislave, Banskej Bystrici, Bardejove, Košiciach, Lučenci, Považskej Bystrici, Prešove, Ružomberku, Starej Ľubovni a Zborove, ako aj v komunitách v Česku, na Ukrajine a na Sibíri. V Ružomberku sa sestry venujú najmä pedagogickej činnosti na Katolíckej univerzite, zdravotníckej škole a základnej škole svätého Vincenta, ako aj duchovnému sprevádzaniu a ignaciánskym duchovným cvičeniam.</p>',
        ],
        // id=367 "Tím RADOSTNÁ" sa vynecháva — žiadne overené údaje sa nenašli
        557 => [
            // zahraničná misia (Brusel), municipality_id sa nemení — tabuľka obcí obsahuje len slovenské obce
            'name' => 'Slovenská katolícka misia v Bruseli',
            'website' => [null, 'https://skmbrussels.be'],
            'email' => [null, 'mail@skmbrussels.be'],
            'phone' => [null, '+32 499 240 071'],
            'street' => [null, 'Rue Jenneval 10'], 'postcode' => [null, '1000'], 'country' => [null, 'Belgicko'],
            'body' => '<p>Slovenská katolícka misia v Bruseli bola slávnostne uvedená 19. októbra 2005 a poskytuje pastoračnú a duchovnú starostlivosť Slovákom, čiastočne aj Čechom, žijúcim v Bruseli a okolí Belgicka. Bohoslužby sa konajú v Kostole Najsvätejšieho Srdca Ježišovho, ktorý misia zdieľa s francúzsky, poľsky a anglicky hovoriacimi farníkmi.</p><p>Slovenské sväté omše sú od pondelka do soboty o 19:00 a v nedeľu o 10:00, misia ponúka aj adorácie a sviatosť zmierenia. Okrem liturgického života organizuje kultúrne a spoločenské podujatia na udržiavanie slovenských tradícií medzi krajanmi v Belgicku.</p>',
        ],
        782 => [
            'name' => 'Gréckokatolícka cirkev na Slovensku',
            'website' => [null, 'https://grkat.sk'],
            'email' => [null, 'metropolia@grkatpo.sk'],
            'phone' => [null, '051/756 26 01'],
            'street' => [null, 'Hlavná 1'], 'postcode' => [null, '081 35'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Gréckokatolícka cirkev na Slovensku je metropolitná cirkev sui iuris zastrešujúca tri eparchie — Prešovskú archieparchiu a sufragánne eparchie v Bratislave a Košiciach. Portál grkat.sk slúži ako informačný a zastrešujúci web pre celú cirkev, so sídlom metropolitnej správy v Prešove.</p><p>Kontaktné údaje tohto kanála sú zhodné s Prešovskou archieparchiou (samostatný kanál), keďže grkat.sk nemá vlastné samostatné kontakty oddelené od archieparchiálneho úradu.</p>',
        ],
        498 => [
            // zahraničná misia (Rím), municipality_id sa nemení — tabuľka obcí obsahuje len slovenské obce
            'name' => 'Slovenská katolícka misia v Ríme',
            'website' => [null, 'https://www.skmrim.sk'],
            'email' => [null, 'katolickamisiarim@gmail.com'],
            'phone' => [null, '+421 904 856 377'],
            'street' => [null, 'San Girolamo della Carità, Via di Monserrato 62A'], 'postcode' => [null, '00186'], 'country' => [null, 'Taliansko'],
            'body' => '<p>Slovenskú katolícku misiu v Ríme predchádzal a s ňou úzko súvisí Slovenský ústav svätého Cyrila a Metoda, založený v roku 1960, ktorý pôsobil ako pastoračné centrum pre Slovákov v zahraničí ešte pred vznikom samotnej misie v roku 2005. Komunita Slovákov v Ríme sa formovala už od 50. rokov 20. storočia v dôsledku komunistickej emigrácie.</p><p>Poslaním misie je dávať odpoveď na otázky identity krajanov v zahraničí a poskytovať im náhradný domov, slovenskú atmosféru a ľudskú i duchovnú pomoc. Slovenská svätá omša sa slávi každú nedeľu o 17:00, okrem letných mesiacov a Vianoc a Veľkej noci; v roku 2025 misia oslávila 20 rokov pôsobenia.</p>',
        ],
        48 => [
            'name' => 'Prešovská archieparchia',
            'municipality' => [1899, 2822], // Litmanová (len pútnické miesto eparchie) -> Prešov (skutočné sídlo)
            'website' => [null, 'https://www.grkatpo.sk'],
            'email' => [null, 'abu@grkatpo.sk'],
            'phone' => [null, '051/75 62 601'],
            'street' => [null, 'Hlavná 1'], 'postcode' => [null, '081 35'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Prešovská archieparchia je metropolitná gréckokatolícka cirkevná provincia byzantského obradu na Slovensku, kánonicky zriadená ako samostatná eparchia 22. septembra 1818 pápežom Piom VII. a oddelená od Mukačevskej eparchie. Na metropolitnú archieparchiu ju povýšil pápež Benedikt XVI. 30. januára 2008, čím vznikla samostatná cirkevná provincia s dvoma sufragánnymi eparchiami — Bratislavskou a Košickou.</p><p>Katedrálnym chrámom je Katedrálny chrám svätého Jána Krstiteľa v Prešove. Podľa údajov z rokov 2018 – 2021 má archieparchia približne 165 farností, 114 401 veriacich, 284 diecéznych a 22 rehoľných kňazov, 3 trvalých diakonov a 75 rehoľných sestier. Súčasným arcibiskupom metropolitom je ThDr. Jonáš Maxim, MSU, vymenovaný 26. októbra 2023.</p>',
        ],
        266 => [
            'name' => 'Rímskokatolícky farský úrad v Topoľčiankach',
            'website' => [null, 'https://topolcianky.fara.sk'],
            'email' => [null, 'topolcianky@nrb.sk'],
            'phone' => [null, '037 630 11 46'],
            'street' => [null, 'Hlavná 139'], 'postcode' => [null, '951 93'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Farnosť svätej Kataríny Alexandrijskej v Topoľčiankach je zároveň významným mariánskym pútnickým miestom. Grófka Alžbeta Rákociová-Erdödyová, inšpirovaná úctou ku karmelitánskemu rádu, po púti do Svätej zeme požiadala o zriadenie Škapuliarskeho bratstva na svojom panstve; pápež Inocent XI. iniciatívu schválil a arcibiskup Juraj Szécsényi bratstvo formálne založil 16. júla 1686.</p><p>Dnešné pútnické miesto zahŕňa okrem pôvodnej kaplnky aj farský kostol, cintorín a ďalšie stavby vrátane Kalvárskej kaplnky posvätenej v roku 1933 a kaplnky svätej Anny z roku 1871. Osídlenie územia siaha do neolitu a slovanské opevnenie je doložené už zo 6. až 8. storočia.</p>',
        ],
        74 => [
            'name' => 'Sociálna subkomisia Teologickej komisie KBS',
            'website' => [null, 'https://social.kbs.sk'],
            'email' => [null, 'social@kbs.sk'],
            'phone' => [null, '0903 783 229'],
            'street' => [null, 'Kapitulská 11'], 'postcode' => [null, '814 99'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Sociálna subkomisia Teologickej komisie KBS je poradným orgánom Konferencie biskupov Slovenska pre sociálnu a charitatívnu činnosť Katolíckej cirkvi na Slovensku, ktorý vníma svoje poslanie ako „službu tým, ktorí pomáhajú" — teda prepájanie katolíckych charitatívnych štruktúr a podporu ich vzájomnej spolupráce. Predsedom subkomisie je Mons. Peter Rusnák, bratislavský eparcha.</p><p>Medzi hlavné aktivity patrí organizovanie konferencií o chudobe a ľudských právach, koordinácia regionálnych stretnutí „Poslovia nádeje" pre ľudí v sociálnych službách a podpora farských charít. Subkomisia každoročne udeľuje Cenu svätej Matky Terezy z Kalkaty a venuje sa aj témam dôstojného života ľudí bez domova a prevencie bezdomovectva.</p>',
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
