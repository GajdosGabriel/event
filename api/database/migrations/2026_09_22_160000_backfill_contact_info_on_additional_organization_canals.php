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
 * Kanály id=230 (Cor et Lumen Christi — duplicita id=815, len UK sídlo
 * bez overenej väzby na Slovensko), id=727 (OZ Slobodní 28+ — žiadne
 * overené údaje, len zmienka na Facebooku) a id=294 (Dikastérium pre
 * evanjelizáciu — vatikánsky úrad bez slovenského sídla) sa nedopĺňajú
 * vôbec.
 *
 * Táto dávka má nezvyčajne veľa duplicít tej istej reálnej organizácie:
 * id 265 = Slovenská katolícka charita (duplicita id=789), id 679 =
 * OCD/OCDS Bratislava (duplicita id=331), id 720/922 = OZ Bratislavská
 * Kalvária (duplicity id=169/356/698 — táto organizácia je teda v DB už
 * 5-krát), id 941 = klub ISKRA (duplicita id=707/617 — 3. výskyt), id 450
 * = TK KBS (duplicita id=467), id 246 = Spišské biskupstvo (duplicita
 * id=36), id 854 = Fórum života (duplicita id=406), id 253 = Trnavská
 * arcidiecéza/Arcibiskupský úrad (duplicita id=568), id 533 = Saleziáni
 * don Bosca (duplicita id=845). Dostávajú tie isté overené údaje ako ich
 * náprotivky, zlúčenie kanálov nie je súčasťou tejto migrácie.
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
        265 => [
            // duplicita id=789 "Bratislavská arcidiecézna charita a Slovenská katolícka charita"
            'name' => 'Slovenská katolícka charita',
            'website' => [null, 'https://www.charita.sk'],
            'email' => [null, 'info@charita.sk'],
            'phone' => [null, '+421 2 5443 1506'],
            'street' => [null, 'Kapitulská 18'], 'postcode' => [null, '814 15'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Slovenská katolícka charita je celoslovenská charitatívna organizácia s mottom „Blízko pri človeku v núdzi", ktorá pomáha seniorom, rodinám s deťmi, ľuďom bez domova, migrantom a utečencom na Slovensku aj v zahraničných rozvojových projektoch (napr. Pôstna krabička, Adopcia na diaľku). Pôsobí prostredníctvom siete diecéznych charít.</p>',
        ],
        679 => [
            // duplicita id=331 "Rád bosých karmelitánov (OCD) a Svetský rád bosých karmelitánov (OCDS) v Bratislave"
            'name' => 'Bratislavský Rád bosých karmelitánov (OCD) a Svetský rád bosých karmelitánov (OCDS)',
            'website' => [null, 'https://ocds.sk'],
            'email' => [null, 'ocdsbratislava@gmail.com'],
            'phone' => [null, '+421 948 555 778'],
            'street' => [null, 'Dobrovičova 2'], 'postcode' => [null, '811 02'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Rád bosých karmelitánov a Svetský rád bosých karmelitánov je komunita veriacich žijúcich podľa duchovnosti sv. Terézie z Ávily a sv. Jána z Kríža, s dôrazom na modlitbu, chudobu a poslušnosť v rámci vlastného životného stavu. Bratislavské spoločenstvo sv. Jána od Kríža vzniklo v roku 1994 a má celoslovenskú pôsobnosť.</p>',
        ],
        720 => [
            // duplicita id=169/356/698 "OZ Bratislavská Kalvária"
            'name' => 'Bratislavský OZ Bratislavská Kalvária KZ',
            'website' => [null, 'https://bratislavskakalvaria.sk'],
            'email' => [null, 'info@bratislavskakalvaria.sk'],
            'street' => [null, 'Oravská 1264/18'], 'postcode' => [null, '821 09'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Občianske združenie Bratislavská Kalvária, založené v roku 2019, sa venuje obnove a revitalizácii Bratislavskej Kalvárie — krížovej cesty z roku 1694. Koordinuje reštaurátorské a záchranné práce na jednotlivých zastaveniach.</p>',
        ],
        922 => [
            // duplicita id=169/356/698 "OZ Bratislavská Kalvária"
            'name' => 'Bratislavský OZ Bratislavská Kalvária a KZ Sprevádzajúci s podporou Bratislavskej arcidiecézy',
            'website' => [null, 'https://bratislavskakalvaria.sk'],
            'email' => [null, 'info@bratislavskakalvaria.sk'],
            'street' => [null, 'Oravská 1264/18'], 'postcode' => [null, '821 09'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Občianske združenie Bratislavská Kalvária, založené v roku 2019, sa venuje obnove a revitalizácii Bratislavskej Kalvárie — krížovej cesty z roku 1694. Koordinuje reštaurátorské a záchranné práce na jednotlivých zastaveniach, v spolupráci s Kresťanským združením Sprevádzajúci a s podporou Bratislavskej arcidiecézy.</p>',
        ],
        941 => [
            // duplicita id=707/617 "klub ISKRA" (rovnaká Facebook stránka)
            'name' => 'kresťanské voľnočasové spoločenstvo ISKRA:h',
            'body' => '<p>Klub ISKRA je kresťanské ekumenické voľnočasové spoločenstvo v Bratislave, ktoré nie je viazané len na katolícke prostredie, ale vytvára priestor pre mladých pracujúcich ľudí bez ohľadu na vek či rodinný stav. Program je rozdelený do niekoľkých pravidelných „podklubov" — biblický update, spoločenské hry, knižný klub, bedminton, športový, dobrodružný, herný, tanečný a kultúrny klub — a klub tiež organizuje verejné akcie ako spoločné turistické výstupy či tanečné večery. Komunikuje predovšetkým cez Facebook a WhatsApp skupinu, vlastnú webovú stránku nemá.</p>',
        ],
        450 => [
            // duplicita id=467 "Tlačová kancelária Konferencie biskupov Slovenska (TK KBS)"
            'name' => 'Tlačová kancelária KBS',
            'municipality' => [4209, 242], // Celé Slovensko -> Bratislava
            'website' => [null, 'https://www.tkkbs.sk'],
            'email' => [null, 'reporter@tkkbs.sk'],
            'phone' => [null, '02/5920 6510'],
            'street' => [null, 'Kapitulská 11'], 'postcode' => [null, '814 99'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Tlačová kancelária Konferencie biskupov Slovenska sleduje domácu a zahraničnú tlač, rozhlas a televíziu, vydáva spravodajský servis zo života Cirkvi doma i vo svete, organizuje tlačové konferencie a vydáva elektronický bulletin Život Cirkvi.</p>',
        ],
        246 => [
            // duplicita id=36 "Spišská diecéza"
            'name' => 'Spišské biskupstvo',
            'municipality' => [4209, 3246], // Celé Slovensko -> Spišské Podhradie
            'website' => [null, 'https://kapitula.sk'],
            'email' => [null, 'biskupstvo@kapitula.sk'],
            'phone' => [null, '+421 53 454 11 36'],
            'street' => [null, 'Spišská Kapitula 661/9'], 'postcode' => [null, '053 04'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Spišská diecéza je rímskokatolícka diecéza so sídlom biskupského úradu v Spišskej Kapitule pri Spišskom Podhradí, ktorá spravuje farnosti a pastoračné centrá v regiónoch Spiša, Liptova a Oravy.</p>',
        ],
        854 => [
            // duplicita id=406 "Fórum života"
            'name' => 'Občianske združenie Fórum života',
            'municipality' => [4209, 242], // Celé Slovensko -> Bratislava
            'website' => [null, 'https://forumzivota.sk'],
            'email' => [null, 'kancelaria@forumzivota.sk'],
            'phone' => [null, '+421 903 533 946'],
            'street' => [null, 'Heydukova 14'], 'postcode' => [null, '811 08'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Fórum života je občianske združenie založené v roku 2003, ktoré dnes združuje okolo 51 členských organizácií, odborníkov a osobností s víziou spoločnosti rešpektujúcej ľudský život a dôstojnosť človeka od počatia po prirodzenú smrť. Je známe najmä kampaňami „Sviečka za nenarodené deti" a Národným pochodom za život, vydáva periodikum Spravodajca a je členom medzinárodnej siete Human Life International.</p>',
        ],
        253 => [
            // duplicita id=568 "Trnavská arcidiecéza" (Arcibiskupský úrad je jej administratívny orgán na tej istej adrese)
            'name' => 'Arcibiskupský úrad v Trnave',
            'website' => [null, 'https://abu.sk'],
            'email' => [null, 'abu@abu.sk'],
            'phone' => [null, '033/5912 111'],
            'street' => [null, 'Ulica Jána Hollého 10'], 'postcode' => [null, '917 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Arcibiskupský úrad je výkonným administratívnym orgánom Trnavskej arcidiecézy, zriadenej v roku 1977 pápežom Pavlom VI. ako metropolitné arcibiskupstvo slovenskej cirkevnej provincie. Zabezpečuje riadenie farností, kléru a hospodárenia arcidiecézy a zastrešuje viaceré oddelenia vrátane arcidiecézneho školského a katechetického úradu.</p>',
        ],
        533 => [
            // duplicita id=845 "Saleziáni dona Bosca a Konfederácia politických väzňov Slovenska"
            'name' => 'rehoľa Saleziáni don Bosca',
            'website' => [null, 'https://saleziani.sk'],
            'email' => [null, 'sekretariat@saleziani.sk'],
            'phone' => [null, '+421 2 554 22 800'],
            'street' => [null, 'Miletičova 7'], 'postcode' => [null, '821 08'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Saleziáni don Bosca prišli na Slovensko v septembri 1924 a v roku 1939 vznikla samostatná slovenská provincia; počas komunizmu boli rehoľníci prenasledovaní (medzi nimi neskôr svätorečený Titus Zeman), no tajná činnosť pokračovala až do roku 1990, keď sa rehoľa obnovila verejne. V roku 2024 oslávili 100 rokov pôsobenia na Slovensku a dnes vedú výchovné a školské zariadenia, vydavateľské aktivity, mládežnícke organizácie (Domka) a misijnú činnosť medzi marginalizovanými komunitami, napríklad na Luníku IX v Košiciach.</p>',
        ],
        686 => [
            'name' => 'katRande o.z',
            'website' => [null, 'https://katrande.org'],
            'email' => [null, 'office@katrande.org'],
            'phone' => [null, '+421 910 911 686'],
            'street' => [null, 'Chrobákova 2693/22'], 'postcode' => [null, '841 02'], 'country' => [null, 'Slovensko'],
            'body' => '<p>KatRande.org je jediná katolícka zoznamovacia stránka na Slovensku, ktorú založila Martina Brenčičová; portál spustil činnosť približne v roku 2015 a formálne občianske združenie bolo zaregistrované 23. augusta 2018. Názov je skratkou z francúzskeho „rendez-vous" a služba je súčasťou medzinárodnej platformy kathTreff, ktorá umožňuje hľadanie partnera medzi katolíkmi v Česku, nemecky hovoriacich krajinách, Maďarsku, Slovinsku, Chorvátsku, Lotyšsku, Litve, Portugalsku a USA.</p><p>Ide o platenú službu, čo podľa prevádzkovateľov priťahuje najmä ľudí s vážnym záujmom o vzťah — v minulosti mala platforma takmer deväťtisíc registrovaných členov, z toho päťtisíc žien. Okrem klasickej zoznamky organizuje katRande aj speed-dating podujatia pre slobodných katolíkov.</p>',
        ],
        183 => [
            'name' => 'Univerzitné pastoračné centrum Pavla Straussa',
            'website' => [null, 'https://upcnitra.sk'],
            'email' => [null, 'upcnitrasocial@gmail.com'],
            'phone' => [null, '0905 240 853'],
            'street' => [null, 'Dražovská cesta 4'], 'postcode' => [null, '949 74'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Univerzitné pastoračné centrum Pavla Straussa bolo otvorené v decembri 2008 v areáli Univerzity Konštantína Filozofa v Nitre s cieľom poskytovať pastoračnú starostlivosť a duchovnú pomoc študentom a zamestnancom nitrianskych vysokých škôl a internátov. Je pomenované po Pavlovi Straussovi (1912 – 1994), slovenskom lekárovi, spisovateľovi, esejistovi a prekladateľovi, ktorý dlhé roky pôsobil, aj tajne, v Nitrianskej nemocnici.</p><p>Centrum organizuje pravidelné formačné, liturgické, kultúrne a oddychové aktivity — duchovnú obnovu, doplnenie akademického vzdelania o kresťanský rozmer hodnôt, duchovné vedenie a poradenstvo a priestor pre stretávanie veriacich aj hľadajúcich študentov a zamestnancov. Ponúka aj tematické kurzy, napríklad o mužskej spiritualite či predmanželskú prípravu Ars Amandi, prednášky a diskusie z teológie, kultúry a iných disciplín, ako aj záverečné akademické bohoslužby Te Deum.</p>',
        ],
        887 => [
            'name' => 'Dominikánske mariánske centrum a Dominikánsky konvent',
            'website' => [null, 'https://dmc.sk'],
            'email' => [null, 'dmc@dmc.sk'],
            'phone' => [null, '(055) 623 01 37'],
            'street' => [null, 'Mäsiarska 6'], 'postcode' => [null, '040 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Dominikánsky konvent Nanebovzatia Panny Márie v Košiciach patrí medzi najstaršie kláštorné komplexy na Slovensku — prví dominikánski bratia prišli do Košíc pravdepodobne ešte pred tatárskym vpádom v roku 1241 a postavili tu gotický kostol, dnes najstaršiu stavbu v meste, s prvou písomnou zmienkou z roku 1303. Kláštor prešiel búrlivou históriou: v roku 1553 bol poškodený počas náboženských nepokojov a komunita naň čas zanikla, návrat dominikánov umožnila až cisárska listina z roku 1697, a v noci zo 14. na 15. apríla 1950 komunistický režim násilne zrušil činnosť rehole, ktorú bratia dostali späť do správy až po páde režimu.</p><p>V tomto kláštornom komplexe dnes sídli aj Dominikánske mariánske centrum, ktoré v roku 1994 založila Slovenská dominikánska provincia s cieľom koordinovať šírenie modlitby ruženca a prehlbovať vieru v Ježiša Krista. Centrum vedie kontakt s ruženčovými bratstvami na celom Slovensku, registruje ich členov, vydáva zakladajúce a potvrdzujúce listiny, pripravuje štvrťročný časopis Ruženec a organizuje duchovné obnovy vo farnostiach a duchovné cvičenia.</p>',
        ],
        225 => [
            'name' => 'Spišská katolícka charita',
            'municipality' => [1831, 3240], // Levoča -> Spišská Nová Ves (skutočné sídlo)
            'website' => [null, 'https://caritas.sk'],
            'email' => [null, 'caritas@caritas.sk'],
            'phone' => [null, '+421 53 442 45 00'],
            'street' => [null, 'Slovenská 1765/30'], 'postcode' => [null, '052 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Spišská katolícka charita nadväzuje na Diecéznu charitu založenú v Spišskej diecéze už v roku 1927; po páde komunizmu bola v roku 1991 obnovená v rámci siete Slovenskej katolíckej charity a ako samostatný právny subjekt vznikla v roku 1996. Jej poslaním je pomáhať ľuďom v núdzi bez ohľadu na náboženstvo či národnosť, na základe evanjeliových hodnôt bratskej lásky a úcty k ľudskej dôstojnosti, a pôsobí v regiónoch Oravy, Liptova a Spiša.</p><p>Charita poskytuje širokú škálu sociálnych, zdravotných a vzdelávacích služieb — krízovú intervenciu vrátane útulkov a núdzového bývania, pomoc seniorom a ľuďom so zdravotným postihnutím, rodinám v kríze a ľuďom bez domova, špecializované sociálne poradenstvo, domácu ošetrovateľskú starostlivosť a hospicové služby aj vzdelávacie zariadenia pre znevýhodnené deti. Prevádzkuje desiatky rezidenčných aj ambulantných zariadení, je označovaná za najväčšieho neverejného poskytovateľa sociálnych služieb na Slovensku a je súčasťou medzinárodnej siete Caritas Internationalis.</p>',
        ],
        499 => [
            // primárne dáta Vincentínov (celoslovenská provincia); Farnosť Bratislava-Prievoz je nimi spravovaná farnosť na inej adrese
            'name' => 'Misijná spoločnosť sv. Vincenta de Paul a Farnosť Bratislava-Prievoz',
            'website' => [null, 'https://vincentini.sk'],
            'email' => [null, 'provincial@vincentini.sk'],
            'phone' => [null, '+421 2 48 257 102'],
            'street' => [null, 'Sv. Vincenta 1'], 'country' => [null, 'Slovensko'], // PSČ sa nepodarilo s istotou overiť, neuvádza sa
            'body' => '<p>Misijnú spoločnosť sv. Vincenta de Paul (vincentínov, tiež lazaristov) založil sv. Vincent de Paul vo Francúzsku v roku 1625, pápež Urban VIII. ju schválil v roku 1632, s poslaním evanjelizovať chudobných prostredníctvom ľudových misií vo farnostiach a formácie budúcich kňazov. Na Slovensko prišla spoločnosť v roku 1918, v roku 1942 vznikla slovenská viceprovincia, ktorú po vojne potlačil komunistický režim, a samostatná slovenská provincia bola obnovená v roku 1990.</p><p>Provinciálny dom sídli v Bratislave, kde sa nachádza aj seminár pre formáciu budúcich kňazov a bratov pri farnosti Bratislava-Prievoz s Kostolom sv. Vincenta de Paul, ktorá je uvádzaná ako najväčšia farnosť na Slovensku. Slovenská provincia má okrem Bratislavy komunity aj v Banskej Bystrici, Bijacovciach a Lučenci-Rúbanisku; medzinárodne pôsobí spoločnosť vo viac než 40 krajinách s vyše 3500 členmi a koordinuje desať zložiek takzvanej Vincentskej rodiny — rehoľné sestry, laické združenia aj mládežnícke skupiny.</p>',
        ],
        41 => [
            // primárne dáta Farského úradu Gaboltov (najbohatšie overené); Centrum rómskej misie aj Rada KBS pre Rómov sídlia v Košiciach/Bratislave bez vlastnej overenej adresy
            'name' => 'Rada KBS pre Rómov a menšiny, Centrum rómskej misie Košickej arcidiecézy, Farský úrad Gaboltov',
            'municipality' => [1565, 806], // Košice -> Gaboltov (skutočné sídlo farského úradu)
            'website' => [null, 'https://gaboltov.rimkat.sk'],
            'email' => [null, 'gaboltov@abuke.sk'],
            'phone' => [null, '054/47 941 33'],
            'street' => [null, 'Gaboltov 28'], 'postcode' => [null, '086 02'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Farnosť Gaboltov je pútnické miesto so svätyňou zasvätenou sv. Vojtechovi, ktorého prvá písomná zmienka pochádza z roku 1247. Súčasný farský kostol spája gotické prvky zo 14. – 15. storočia s barokovou prestavbou z roku 1715; okolo roku 1706 tu z iniciatívy karmelitánov vzniklo bratstvo Karmelskej Panny Márie a pútnická tradícia sa postupne stala súčasťou duchovného života farnosti. Arcibiskup Alojz Tkáč sem v roku 2001 pozval redemptoristov, aby spravovali rozrastajúcu sa svätyňu, a v roku 2011 bol Gaboltov vyhlásený za diecéznu mariánsku svätyňu Košickej arcidiecézy; na hlavnú júlovú púť ročne príde približne 30 000 pútnikov, konajú sa tu aj samostatná rómska a mužská púť.</p><p>Tento kanál eviduje aj Centrum rómskej misie Košickej arcidiecézy, ktoré arcibiskup zriadil 1. júla 2020 na podporu duchovnej služby medzi Rómami, a Radu Konferencie biskupov Slovenska pre Rómov a menšiny, ktorá vydáva smernice pre rómsku pastoráciu a organizuje celoslovenské iniciatívy — obe sídlia mimo Gaboltova, bez vlastnej samostatne overenej adresy.</p>',
        ],
        566 => [
            'name' => 'Teologická fakulta Katolíckej univerzity v Ružomberku – Katedra spoločenských vied',
            'municipality' => [3053, 1565], // Ružomberok -> Košice (skutočné sídlo fakulty)
            'website' => [null, 'https://www.ku.sk/fakulty-katolickej-univerzity/teologicka-fakulta'],
            'email' => [null, 'sekretariat.tf@ku.sk'],
            'phone' => [null, '+421 55 68 36 111'],
            'street' => [null, 'Hlavná 89'], 'postcode' => [null, '041 21'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Teologická fakulta je jednou zo štyroch fakúlt Katolíckej univerzity v Ružomberku, jej sídlo je však historicky viazané na Košice, kde nadväzuje na starší kňazský seminár a teologickú prípravu v meste. Poslaním fakulty je hlbšie porozumenie katolíckeho učenia vychádzajúceho z Božieho zjavenia a jeho systematický výklad, s dôrazom na formáciu uchádzačov o kňazstvo a prípravu na osobitné cirkevné služby.</p><p>Fakulta ponúka akreditované študijné programy z katolíckej teológie a príbuzných odborov vrátane filozofie, sociálneho učenia Cirkvi, náuky o rodine a učiteľstva. Katedra spoločenských vied v rámci fakulty zabezpečuje okrem iného výučbu smerom k sociálnej práci a príbuzným spoločenskovedným odborom.</p>',
        ],
        114 => [
            // rovnaká celoslovenská organizácia ako CVX v kanáli id=405 "Jezuiti a spoločenstvá Magis a CVX"; samostatný prešovský kontakt sa nenašiel
            'name' => 'Spoločenstvo kresťanského života CVX',
            'website' => [null, 'https://cvx.sk'],
            'body' => '<p>CVX (Communitas vitae christianae) je laické spoločenstvo kresťanov — mužov a žien, dospelých aj mladých ľudí zo všetkých spoločenských vrstiev —, ktorí chcú vernejšie nasledovať Ježiša Krista a spolupracovať s ním na budovaní Božieho kráľovstva; členstvo v spoločenstve chápu ako svoje konkrétne povolanie v Cirkvi. Jeho korene siahajú k mariánskym kongregáciám iniciovaným Jeanom Leunisom SJ, prvýkrát schváleným pápežom Gregorom XIII. v roku 1584, a nadväzuje na laické skupiny inšpirované sv. Ignácom z Loyoly; charakteristickým zdrojom spirituality sú duchovné cvičenia sv. Ignáca.</p><p>Formačný program CVX v duchu ignaciánskej spirituality prebieha na Slovensku okrem Bratislavy a Žiliny aj v Prešove, samostatný prešovský kontakt však oficiálna stránka neuvádza — ide o ten istý celoslovenský CVX ako kanál „Jezuiti a spoločenstvá Magis a CVX".</p>',
        ],
        138 => [
            'name' => 'farnosť Ladce',
            // pôvodný web patril Nadácii AGAPA (skalné sanktuárium Kríž Butkov), nie farnosti
            'website' => ['https://krizbutkov.sk', 'https://ladce.fara.sk'],
            'email' => [null, 'ladce@fara.sk'],
            'phone' => [null, '042/462 10 6'],
            'street' => [null, 'Farská 151/1'], 'postcode' => [null, '018 63'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Farnosť Ladce bola zriadená v roku 2011 žilinským biskupom Tomášom Galisom v rámci Žilinskej diecézy. Základný kameň nového Kostola Božieho milosrdenstva bol posvätený 15. marca 2014 a chrám bol konsekrovaný 8. októbra 2016 počas Roka milosrdenstva. Farnosť sa venuje bežnej sviatostnej a liturgickej činnosti a kladie osobitný dôraz na úctu k Božiemu milosrdenstvu — novénu a ruženec Božieho milosrdenstva podľa zjavení sv. Faustíny.</p><p>S farnosťou úzko súvisí neďaleké Skalné sanktuárium Božieho milosrdenstva na hore Butkov — veľký kríž a areál kaplniek Božieho milosrdenstva, sv. Faustíny a pamätníkov Jána Pavla II., ktorý od roku 2013 buduje Nadácia AGAPA. Konajú sa tam púte, sväté omše, koncerty a krížové cesty, často v spojení s bohoslužbami vo farskom kostole v Ladcoch.</p>',
        ],
        329 => [
            // primárne dáta Spolku svätého Vojtecha (rovnaké ako kanál id=480); Vydavateľstvo Nové mesto opísané v body
            'name' => 'Spolok svätého Vojtecha a Vydavateľstvo Nové mesto',
            'municipality' => [4209, 3596], // Celé Slovensko -> Trnava (skutočné sídlo SSV)
            'website' => [null, 'https://www.ssv.sk'],
            'email' => [null, 'ssv@ssv.sk'],
            'phone' => [null, '033 590 77 11'],
            'street' => [null, 'Radlinského 5'], 'postcode' => [null, '917 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Spolok svätého Vojtecha je najstaršia a najväčšia katolícka organizácia na Slovensku, založená v roku 1870. Vydáva duchovnú a náboženskú literatúru, prevádzkuje knižnicu a archív a spravuje sieť miestnych odbočiek po celom Slovensku.</p><p>Tento kanál eviduje aj Vydavateľstvo Nové mesto, ktoré na slovenskom trhu pôsobí od roku 1994 a vydáva časopis Nové mesto — slovenskú mutáciu talianskeho magazínu Città Nuova, založeného v roku 1956 hnutím Fokoláre zakladateľky Chiary Lubichovej; slovenská verzia vychádza od roku 1998 ako jedna z 36 jazykových mutácií po celom svete. Vydavateľstvo prevádzkuje aj portál nm.sk so štyrmi rubrikami — Spoločnosť, Rodina, Viera, Zo života —, podcasty, e-shop s knihami a newsletter, s poslaním šíriť „kultúru dialógu, vzájomného porozumenia a začlenenia".</p>',
        ],
        564 => [
            'name' => 'Farnosť sv. Martina',
            'website' => [null, 'https://dom.fara.sk'],
            'email' => [null, 'ba-sv-martina@ba.ecclesia.sk'],
            'phone' => [null, '+421 2 544 334 30'],
            'street' => [null, 'Kapitulská 9'], 'postcode' => [null, '811 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Farský Dóm svätého Martina je pôvodne gotická sakrálna stavba v historickom centre Bratislavy, najvýznamnejší a najväčší kostol v meste, postavený na mieste románskeho kostola sv. Salvátora doloženého už v roku 1221. Centrálna loď dnešnej halovej stavby bola vysvätená v roku 1452 a stredoveký vývoj uzavrela neskorogotická predsieň okolo roku 1510.</p><p>Najslávnejším obdobím Dómu sú roky 1563 – 1830, keď bol korunovačným chrámom Uhorského kráľovstva — konalo sa tu 19 korunovácií vrátane Márie Terézie. Farnosť dnes poskytuje bežnú sviatostnú a liturgickú činnosť, pravidelné aj spievané omše, prehliadky katedrály, duchovnú hudbu a koncerty a katechézy pod vedením biskupa.</p>',
        ],
        639 => [
            'name' => 'Komisia pre mládež Bratislavskej arcidiecézy',
            'website' => [null, 'https://www.mladezba.sk'],
            'email' => [null, 'info@mladezba.sk'],
            'phone' => [null, '0903 982 638'],
            'street' => [null, 'Špitálska 7'], 'postcode' => [null, '814 92'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Komisia pre mládež Bratislavskej arcidiecézy vznikla v roku 2009 ako cirkevná nezisková organizácia fungujúca na dobrovoľníckom princípe, s poslaním byť „miestom prijatia" pre mladých hľadajúcich spoločenstvo, duchovnú obnovu a vzťah s Bohom. Kľúčovým projektom je diecézne centrum mládeže Vinica v Bratislave-Rači, ktoré slúži na duchovné obnovy, teambuildingy aj bežné stretávanie sa, a Bratislavská animátorská škola pripravujúca mladých vo veku 16 – 30 rokov na službu animátorov vo farnostiach.</p><p>Od roku 2013 komisia organizuje aj celodiecézne stretnutie mládeže Cliptime a sieťovací projekt Lifenet, ktorý prepája spoločenstvá mládeže naprieč arcidiecézou.</p>',
        ],
        669 => [
            'name' => 'Depaul Slovensko',
            'website' => [null, 'https://depaul.sk'],
            'email' => [null, 'info@depaul.sk'],
            'phone' => [null, '+421 2 5443 2128'],
            'street' => [null, 'Kapitulská 308/18'], 'postcode' => [null, '814 14'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Depaul Slovensko vznikla 22. mája 2006 ako reakcia na krízovú situáciu, keď počas jednej zimy zomrelo na ulici viacero ľudí bez domova; 21. decembra 2006 otvorila nízkoprahovú nocľaháreň na Ivanskej ceste v Bratislave-Ružinove. Je slovenskou pobočkou medzinárodnej siete Depaul International, ktorá vznikla v roku 1989 z iniciatívy kardinála Basila Huma na pomoc ľuďom spiacim na uliciach Londýna, pod mottom „Ulica nie je domov".</p><p>Organizácia prevádzkuje ošetrovňu, terénnu prácu, útulok s opatrovateľskou službou a nocľaháreň, kde poskytuje stravu, prístrešie, hygienu, zdravotné ošetrenie a sociálne poradenstvo vrátane pomoci s dokladmi, prácou, bývaním a dlhmi. Za rok 2025 tím vyše 100 zamestnancov zaznamenal 2 473 klientov, 87 049 nocí v bezpečí, 185 049 podaných jedál a vyše štyritisíc sociálnych poradenstiev.</p>',
        ],
        688 => [
            'name' => 'Spoločenstvo Ladislava Hanusa',
            'website' => [null, 'https://www.slh.sk'],
            'email' => [null, 'kancelaria@slh.sk'],
            'phone' => [null, '0904 806 696'],
            'street' => [null, 'Pavlovova 20'], 'country' => [null, 'Slovensko'], // presné PSČ sa nepodarilo overiť, neuvádza sa
            'body' => '<p>Spoločenstvo Ladislava Hanusa je kresťanské akademické spoločenstvo pomenované po slovenskom katolíckom kňazovi a esejistovi Ladislavovi Hanusovi, ktoré vzniklo 7. októbra 2002, keď sa piati študenti prvýkrát stretli s vysokoškolským pedagógom nad textami najväčších mysliteľov západnej civilizácie. Dnes má vyše 500 členov a pôsobí v Bratislave aj Košiciach s poslaním byť „inšpiratívnym spoločenstvom vzdelaných a angažovaných kresťanov, ktorí budú obohacovať kultúru a verejný život na Slovensku".</p><p>Činnosť stojí na dvoch pilieroch: formačno-akademický program pre mladých ľudí doplnený o pokročilé kurzy z teológie, filozofie, politológie a histórie, a verejná angažovanosť prostredníctvom každoročných festivalov Bratislavské a Košické Hanusove dni, spoluorganizovania Národných pochodov za život, publikačnej činnosti a komentárov k legislatíve.</p>',
        ],
        93 => [
            'name' => 'Ekumenické spoločenstvo cirkví a náboženských spoločností na území mesta Košice',
            'website' => [null, 'https://ekumenake.rimkat.sk'],
            'email' => [null, 'ekumenake@gmail.com'],
            'phone' => [null, '+421 55 68 36 141'],
            'street' => [null, 'Hlavná 91'], 'postcode' => [null, '040 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Ekumenické spoločenstvo v Košiciach vzniklo v roku 1994 na podnet viacročných stretávaní predstaviteľov cirkví pri Mestskom zastupiteľstve a od roku 2000 má samostatnú právnu subjektivitu. Združuje osem kresťanských cirkví pôsobiacich v meste — Apoštolskú cirkev, Bratskú jednotu baptistov, Cirkev bratskú, Československú cirkev husitskú, Evanjelickú cirkev, Gréckokatolícku cirkev, Pravoslávnu cirkev, Reformovanú kresťanskú cirkev a Rímskokatolícku cirkev —, pričom Židovská náboženská obec má štatút pozorovateľa.</p><p>Medzi tradičné každoročné podujatia patria Ekumenická bohoslužba slova koncom januára, Pašiový sprievod na Veľký piatok, ďakovná bohoslužba za úrodu, Veni Sancte pre vysoké školy a ekumenický program prípravy na Vianoce. V roku 1998 dostalo spoločenstvo od mesta Košice Cenu mesta „za utváranie dobrých vzťahov medzi cirkvami".</p>',
        ],
        264 => [
            'name' => 'Sestry saleziánky',
            'website' => [null, 'https://salezianky.sk'],
            'email' => [null, 'sekretariat@salezianky.sk'],
            'phone' => [null, '+421 902 336 805'],
            'street' => [null, 'Kremnická 17'], 'postcode' => [null, '851 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Inštitút dcér Márie Pomocnice, sestry saleziánky (FMA), je ženská rehoľa patriaca do saleziánskej rodiny, založená don Bosconom a sv. Máriou Dominikou Mazzarellovou. V júni 2025 si sestry pripomenuli 85. výročie svojho príchodu na Slovensko. Poslaním sestier je výchova a evanjelizácia mladých ľudí, najmä dievčat a mladých žien, v duchu preventívneho výchovného systému don Bosca s dôrazom na rozum, náboženstvo a láskavosť.</p><p>Sestry pôsobia vo viacerých komunitách po celom Slovensku — okrem provinciálneho domu v Bratislave napríklad v Košiciach, Rožňave či Banskej Bystrici —, kde vedú školy, strediská pre voľný čas mládeže, farské a katechetické aktivity, ako aj sprevádzanie mladých pri hľadaní povolania. Súčasťou saleziánskej rodiny na Slovensku je aj spolupráca s laickými spolupracovníkmi a dobrovoľníkmi.</p>',
        ],
        593 => [
            'name' => 'spoločenstvo Angelus',
            'municipality' => [2469, 4194], // Okoličné -> Žilina (skutočné registrované sídlo)
            'website' => [null, 'https://angelus.sk'],
            'street' => [null, 'Jezuitská 143/6'], 'postcode' => [null, '010 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Spoločenstvo Angelus je modlitbové spoločenstvo mladých ľudí, ktoré vzniklo v roku 2007 z iniciatívy piatich vysokoškolských študentov s poslaním „šíriť kultúru povolania" — pomáhať mladým rozoznať v živote povolanie k svätosti a robiť konkrétne životné rozhodnutia ako odpoveď naň. Členovia sa spájajú v každodennej modlitbe Anjel Pána za tých, ktorí hľadajú svoju životnú cestu, a spoločenstvo pôsobí pod patronátom Podkomisie pre pastoráciu povolaní pri KBS.</p><p>Podľa dostupných zdrojov sa v hnutí momentálne angažuje vyše tisíc „hľadajúcich" mladých ľudí a stovky „patrónov" — najmä rehoľných sestier, kňazov a manželských párov —, ktorí sa denne modlia za konkrétneho pridereného mladého človeka počas jeho obdobia rozlišovania. Medzi aktivity patria online registrácia a sprievod, pravidelný newsletter, väčšie celoslovenské púte hľadajúcich, regionálne jednodňové stretnutia a duchovné obnovy.</p>',
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
