<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pokračovanie predošlých backfill_contact_info_on_*_organization_canals.php
 * migrácií — doplní web/email/telefón/adresu pre ďalších 25 neosobných
 * kanálov, zistené z ich oficiálnych webov 22. 9. 2026. Rovnaký guard,
 * rovnaká štruktúra dát a rovnaký jednosmerný prepis `body`.
 *
 * Kanály id=801 (Ruský dom v Bratislave — reálna organizácia (RCVK) má
 * nefunkčný web a Rusko jej činnosť pozastavilo, navyše existuje
 * rovnomenný, ale nesúvisiaci subjekt v Dúbravke — riziko zámeny) a
 * id=349 (Rodinné spoločenstvo FATIMA — jediný dohľadaný subjekt s týmto
 * menom sídli v inej obci, než je uvedená v DB, s neistotou, či ide
 * o ten istý subjekt) sa nedopĺňajú vôbec — rovnaká opatrnosť ako pri
 * id=70/815/833/59/684/230/294/727/367 v predošlých migráciách.
 *
 * Ďalšie duplicity: id 344 = Farnosť Bratislava-Kalvária (dáta zdieľa
 * s id=1031), id 213 = Teologická fakulta KU (duplicita id=566), id 912
 * = katRande (duplicita id=686/355), id 259 = Saleziáni (duplicita
 * id=845/533), id 104 = Človek a Viera (duplicita id=903), id 223 =
 * Školské sestry sv. Františka (duplicita id=206).
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
        344 => [
            // duplicita id=1031 "Bratia Dominikáni – Farnosť Bratislavská Kalvária, OZ Bratislavská Kalvária"
            'name' => 'Farnosť Bratislava Kalvária',
            'website' => [null, 'https://kalvaria.sk'],
            'email' => [null, 'kalvaria@kalvaria.sk'],
            'phone' => [null, '0908 090 799'],
            'street' => [null, 'Na Kalvárii 10'], 'postcode' => [null, '811 04'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Farnosť Bratislava-Kalvária vznikla v roku 1933 vyčlenením z farnosti Bratislava-Nové Mesto; od roku 1990 ju vedú dominikáni. Farský Kostol Panny Márie Snežnej stojí na kopci Kalvária, pútnickom mieste od 18. storočia, kde dodnes vedie 14 zastavení krížovej cesty.</p>',
        ],
        213 => [
            // duplicita id=566 "Teologická fakulta Katolíckej univerzity v Ružomberku – Katedra spoločenských vied"
            'name' => 'Teologická fakulta Katolíckej univerzity v Ružomberku',
            'municipality' => [3053, 1565], // Ružomberok -> Košice (skutočné sídlo fakulty)
            'website' => [null, 'https://www.ku.sk/fakulty-katolickej-univerzity/teologicka-fakulta'],
            'email' => [null, 'sekretariat.tf@ku.sk'],
            'phone' => [null, '+421 55 68 36 111'],
            'street' => [null, 'Hlavná 89'], 'postcode' => [null, '041 21'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Teologická fakulta je jednou zo štyroch fakúlt Katolíckej univerzity v Ružomberku, jej sídlo je však historicky viazané na Košice. Poskytuje akreditované študijné programy z katolíckej teológie a príbuzných odborov vrátane filozofie, sociálneho učenia Cirkvi a náuky o rodine.</p>',
        ],
        912 => [
            // duplicita id=686/355 "katRande" (podstránka pre speed-dating podujatia)
            'name' => 'Speed dating katRande tím',
            'website' => [null, 'https://speeddating.katrande.org'],
            'email' => [null, 'office@katrande.org'],
            'phone' => [null, '+421 910 911 686'],
            'body' => '<p>KatRande.org je jediná katolícka zoznamovacia stránka na Slovensku, súčasť medzinárodnej platformy kathTreff. Tento kanál zastupuje jej speed-dating podujatia pre slobodných katolíkov.</p>',
        ],
        259 => [
            // duplicita id=845/533 "Saleziáni dona Bosca"
            'name' => 'Saleziáni dona Bosca',
            'website' => [null, 'https://saleziani.sk'],
            'email' => [null, 'sekretariat@saleziani.sk'],
            'phone' => [null, '+421 2 554 22 800'],
            'street' => [null, 'Miletičova 7'], 'postcode' => [null, '821 08'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Saleziáni don Bosca prišli na Slovensko v septembri 1924 a v roku 2024 oslávili 100 rokov pôsobenia. Vedú výchovné a školské zariadenia, vydavateľské aktivity, mládežnícke organizácie Domka a misijnú činnosť medzi marginalizovanými komunitami.</p>',
        ],
        104 => [
            // duplicita id=903 "Spoločenstvo kresťanských fotografov Človek a Viera"
            'name' => 'združenie kresťanských fotografov Človek a Viera',
            'website' => [null, 'https://www.clovekaviera.sk'],
            'body' => '<p>Spoločenstvo kresťanských fotografov Človek a Viera vzniklo v roku 2011 v Česku a na Slovensku pôsobí od roku 2017 s približne 30 až 45 fotografmi. Prevádzkuje rozsiahlu online fotobanku, ktorú bezplatne využívajú farnosti, cirkevné médiá a publikácie.</p>',
        ],
        223 => [
            // duplicita id=206 "Školské sestry sv. Františka v Žiline" (KPVS je vedená samostatne, pozri kanál id=680)
            'name' => 'Konfederácia politických väzňov Slovenska a Kongregácia školských sestier sv. Františka',
            'municipality' => [242, 4194], // Bratislava -> Žilina (skutočné sídlo kongregácie)
            'website' => [null, 'https://skolskesestry.sk'],
            'email' => [null, 'provincialat@skolskesestry.sk'],
            'street' => [null, 'J. M. Hurbana 44'], 'postcode' => [null, '010 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Kongregáciu školských sestier svätého Františka založila v roku 1843 v rakúskom Grazi sestra Františka Antónia Lampelová, pôvodne ako spoločenstvo učiteliek. Slovenská provincia so sídlom v Žiline bola obnovene zriadená 1. marca 1990 a kongregácia na Slovensku prevádzkuje okrem iného Gymnázium svätého Františka z Assisi v Žiline. Tento kanál eviduje aj Konfederáciu politických väzňov Slovenska, ktorá má vlastný samostatný kanál.</p>',
        ],
        703 => [
            'name' => 'Nadácia Danice Olexovej – Baboon',
            'website' => [null, 'https://www.baboon.sk'],
            'email' => [null, 'baboon@baboon.sk'],
            'phone' => [null, '+421 905 849 408'],
            'street' => [null, 'Malokrasňanská 10195/6'], 'postcode' => [null, '831 54'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Nadáciu Danice Olexovej – Baboon založila v roku 2020 jej matka Anna Olexová spolu so skupinou Daničiných priateľov, rok po tom, čo Danica Olexová zahynula 10. marca 2019 pri páde lietadla Ethiopian Airlines ET302, ktoré si vyžiadalo životy všetkých 157 ľudí na palube vrátane štyroch Slovákov. Danica Olexová vyštudovala matematiku a geografiu na Univerzite Komenského, pôsobila ako učiteľka a koordinátorka vzdelávacích materiálov pre hnutie eRko a od roku 2010 pracovala ako rozvojová poradkyňa organizácie Horizont3000 v keňskom Nairobi.</p><p>Nadácia nadväzuje na jej poslanie a zameriava sa na podporu dôstojnosti a integrálneho rozvoja človeka na Slovensku aj v afrických krajinách, najmä na sociálnu pomoc, vzdelávanie a rozvoj životných a pracovných zručností. V roku 2021 vyšla kniha jej textov a fotografií Stories From Beneath The Acacia a vznikol dokumentárny film o jej živote; nadácia pravidelne organizuje spomienkové podujatia a udeľuje ocenenie Reálny človek za mimoriadnu humanitárnu angažovanosť.</p>',
        ],
        773 => [
            'name' => 'Farnosť Saletíni',
            'website' => [null, 'https://saletinirozkvet.webnode.sk'],
            'email' => [null, 'rozkvet@dcza.sk'],
            'phone' => [null, '042/432 68 71'],
            'street' => [null, 'Rozkvet 4897/207'], 'postcode' => [null, '017 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Farnosť Považská Bystrica – Rozkvet spravujú saletíni, ktorí na sídlisku Rozkvet pôsobia od roku 1990. Po páde komunizmu opravili poškodenú barokovú kaplnku, ktorá bola vysvätená v auguste 1991; samostatnú farnosť zriadil 1. januára 2009 žilinský biskup Tomáš Galis oddelením od farnosti Považská Bystrica-mesto a dnes slúži približne 8900 katolíkom.</p><p>Farnosť má dva kostoly — farský Kostol Panny Márie Lasaletskej vysvätený v roku 2018 a starší Kostol svätej Heleny z roku 1728, teda pôvodne opravenú barokovú kaplnku. Pravidelne organizuje duchovné aktivity ako novény pred Turícami a adventné výzvy.</p>',
        ],
        290 => [
            'name' => 'Ekumenická rada cirkví v SR',
            'municipality' => [4209, 242], // Celé Slovensko -> Bratislava (skutočné sídlo)
            'website' => [null, 'https://ekumena.sk'],
            'email' => [null, 'ekumena@ekumena.sk'],
            'phone' => [null, '+421 2 5443 3238'],
            'street' => [null, 'Palisády 48'], 'postcode' => [null, '811 06'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Ekumenická rada cirkví v Slovenskej republike vznikla v roku 1993 po rozdelení Československa a nadviazala na predchádzajúcu ekumenickú spoluprácu cirkví. Pri vzniku ju tvorilo šesť riadnych členov a štyria pozorovatelia vrátane Evanjelickej cirkvi augsburského vyznania, Reformovanej kresťanskej cirkvi, Pravoslávnej cirkvi, Bratskej jednoty baptistov a Evanjelickej metodistickej cirkvi; Rímskokatolícka cirkev bola spočiatku pozorovateľom, neskôr sa stala riadnym členom.</p><p>Poslanie rady má vnútorný rozmer — prekonávanie konfesijných rozdielov a posilňovanie kresťanskej jednoty — aj vonkajší, teda prehlbovanie občianskej angažovanosti cirkví. Medzi hlavné aktivity patria ekumenické bohoslužby, teologický dialóg, humanitárna pomoc a mládežnícke programy; rada tiež spravuje grantový program Guľatý stôl na podporu diakonických a mládežníckych projektov.</p>',
        ],
        439 => [
            'name' => 'sestry redemptoristky',
            'website' => [null, 'https://redemptoristky.sk'],
            'phone' => [null, '+421 52 452 2181'],
            'street' => [null, 'Kláštorná 4'], 'postcode' => [null, '060 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Kláštor sestier redemptoristiek v Kežmarku patrí ku kontemplatívnej rehoľi Najsvätejšieho Vykupiteľa, ktorú založila blahoslavená Mária Celesta Crostarosa v spolupráci so svätým Alfonzom Máriom de Liguori. Do Kežmarku prišlo prvých deväť sestier 17. mája 2005; kláštor získal kánonické schválenie Svätou stolicou 2. apríla 2005 a kostol zasvätený Ježišovi Kristovi Vykupiteľovi sveta bol posvätený 17. júla 2005.</p><p>Sestry sa venujú kontemplatívnemu apoštolátu sústredenému na liturgickú modlitbu, prijímanie hostí na duchovné cvičenia a duchovné sprevádzanie, ako aj praktickým činnostiam ako ručné práce, preklady a záhradníctvo. Spolupracujú s laickými združeniami MOST a Priatelia blahoslavenej Márie Celesty Crostarosa a pomáhajú aj pri príprave na birmovku.</p>',
        ],
        1017 => [
            // primárne dáta farnosti Nižná Šebastová (Oáza má registrované sídlo v Košiciach, nie v Prešove)
            'name' => 'farnosť Prešov – Nižná Šebastová, n. o. Oáza – nádej pre nový život, Park kultúry a oddychu v Prešove, mesto Prešov',
            'phone' => [null, '051 381 0045'],
            'street' => [null, 'Slanská 2434/21'], 'postcode' => [null, '080 06'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Farnosť Najsvätejšieho mena Ježiša a Márie v Prešove – Nižnej Šebastovej patrí do Košickej arcidiecézy. Je známa najmä spoluprácou na organizovaní Živej krížovej cesty v centre Prešova na Veľký piatok, ktorú spoluorganizuje s neziskovou organizáciou Oáza – nádej pre nový život (útulok pre ľudí bez domova so sídlom v Košiciach, ktorý od roku 2006 slúži približne 350 ľuďom), Parkom kultúry a oddychu v Prešove a mestom Prešov. Trasa vedie od Pilátovho súdu cez Hlavnú ulicu a Hurbanistov až po Záhradu umenia.</p><p>Tento kanál v databáze zlučuje tieto štyri subjekty; kontaktné údaje patria farnosti v Nižnej Šebastovej, keďže organizácia Oáza má registrované sídlo v Košiciach, nie v Prešove.</p>',
        ],
        301 => [
            'name' => 'Arcidiecézna charita Košice',
            'website' => [null, 'https://www.charita-ke.sk'],
            'email' => [null, 'arcidiecezna.charita@charita-ke.sk'],
            'phone' => [null, '055/625 53 17'],
            'street' => [null, 'Bočná 2'], 'postcode' => [null, '040 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Arcidiecézna charita Košice vznikla v roku 1992 ako zložka Slovenskej katolíckej charity a samostatnou právnickou osobou sa stala 1. januára 1996, po tom, čo pápež Ján Pavol II. v roku 1995 zriadil košickú cirkevnú provinciu. Jej mottom je „Byť blízko pri človeku" a poslaním poskytovať sociálne, zdravotnícke a charitatívne služby bez ohľadu na rasu, národnosť, náboženstvo či politické zmýšľanie.</p><p>Medzi hlavné oblasti činnosti patrí starostlivosť o seniorov, pomoc ľuďom bez domova vrátane nocľahární a krízového strediska pre matky s deťmi, zdravotná starostlivosť vrátane hospicu a domácej ošetrovateľskej starostlivosti, poradenstvo a komunitné centrá a výdaj stravy; od roku 2022 pomáha aj odídencom z Ukrajiny.</p>',
        ],
        745 => [
            // primárne dáta Castellum, n. o. (Biskupstvo Nitra je vedené samostatne, pozri kanál id=218)
            'name' => 'Castellum, n. o. a Nitrianske biskupstvo',
            'website' => [null, 'https://nitrianskyhrad.sk'],
            'email' => [null, 'info@nitrianskyhrad.sk'],
            'phone' => [null, '+421 910 842 991'],
            'street' => [null, 'Nám. Jána Pavla II. 1012/7'], 'country' => [null, 'Slovensko'], // PSČ neisté (P.O.Box má iné PSČ ako mesto), neuvádza sa
            'body' => '<p>Castellum, n. o. je nezisková organizácia, ktorej všeobecnoprospešnou činnosťou je tvorba, rozvoj, ochrana, obnova a prezentácia duchovných a kultúrnych hodnôt. Kľúčovou náplňou je záchrana a obnova Nitrianskeho hradu, národnej kultúrnej pamiatky a sídla Nitrianskeho biskupstva, ktorého kontakt vedie samostatný kanál.</p><p>Castellum n. o. zabezpečuje aj chod Diecézneho múzea Nitrianskeho biskupstva v areáli hradu a v rámci rozvoja turistickej infraštruktúry zriadila Castellum Cafe, kaviareň zabudovanú do svahu hradného kopca s výhľadom na mesto a odkryté stredoveké hradby.</p>',
        ],
        526 => [
            // duplicita id=602 "Pastoračné centrum Anny Kolesárovej – Domček"
            'name' => 'Pastoračné centrum Anny Kolesárovej',
            'website' => [null, 'https://domcek.org'],
            'email' => [null, 'domcek@domcek.org'],
            'phone' => [null, '+421 911 912 598'],
            'street' => [null, 'Vysoká nad Uhom 27'], 'postcode' => [null, '072 14'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Pastoračné centrum Anny Kolesárovej – Domček je pastoračno-mládežnícke centrum v rodisku Anny Kolesárovej, kde od roku 1999 prebiehajú Púte radosti a duchovné obnovy pre mládež, birmovancov, farnosti a školy. Centrum ročne navštívi vyše 4-tisíc ľudí pri jej hrobe.</p>',
        ],
        157 => [
            'name' => 'Slovenský skauting',
            'municipality' => [4209, 242], // Celé Slovensko -> Bratislava (sídlo ústredia)
            'website' => [null, 'https://skauting.sk'],
            'email' => [null, 'ustredie@skauting.sk'],
            'phone' => [null, '02/446 40 154'],
            'street' => [null, 'Mokrohájska cesta 6'], 'postcode' => [null, '841 04'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Korene slovenského skautingu siahajú do roku 1913, keď vznikli prvé skautské oddiely; po vzniku Československa sa v marci 1919 sformovali ďalšie oddiely a stál pri tom A. B. Svojsík. Skauting bol počas 20. storočia opakovane potláčaný totalitnými režimami — počas druhej svetovej vojny aj po komunistickom prevrate v roku 1948, s krátkym obnovením v rokoch 1968 – 1970 — a definitívne sa obnovil tesne po Nežnej revolúcii, keď boli 28. decembra 1989 schválené stanovy obnoveného Slovenského Junáka.</p><p>Na IV. Slovenskom junáckom sneme v Žiline v máji 1990 sa organizácia premenovala na Slovenský skauting. Dnes je jednou z najväčších výchovných organizácií pre deti a mládež na Slovensku, pôsobí naprieč šiestimi vekovými kategóriami od predškolákov po dospelých a podľa údajov z roku 2019 mal vyše 7000 členov v takmer 300 oddieloch po celom Slovensku. Medzi hlavné aktivity patria letné tábory a výpravy, pravidelné družinové stretnutia, medzinárodné výmeny a jamboree a získavanie odboriek.</p>',
        ],
        1011 => [
            // primárne dáta Áno pre život (Fórum života je vedené samostatne, pozri kanál id=406/854)
            'name' => 'Fórum života, o.z. v spolupráci s Áno pre život, n.o',
            'municipality' => [4209, 2905], // Celé Slovensko -> Rajecké Teplice (skutočné sídlo Áno pre život)
            'website' => [null, 'https://anoprezivot.sk'],
            'email' => [null, 'apz.vedenie@anoprezivot.sk'],
            'phone' => [null, '+421 903 534 894'],
            'street' => [null, 'Farská 543/2'], 'postcode' => [null, '013 13'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Áno pre život, n.o. vzniklo v roku 1998 a ako nezisková organizácia bolo zaregistrované 18. mája 2000, s krédom „založené ženami, pomáhajúce ženám, zamestnávajúce ženy". Jej poslaním je posilňovať úctu k ženám, materstvu a rodine a chrániť ľudský život od počatia po prirodzenú smrť.</p><p>Organizácia prevádzkuje azylový dom Gianna B. Molla, ktorý poskytuje núdzové ubytovanie a odbornú pomoc osamelým tehotným ženám a matkám s deťmi či obetiam domáceho násilia, spolu s akreditovaným sociálnym, psychologickým a právnym poradenstvom a terapeutickými programami. Za 27 rokov činnosti organizácia pomohla vyše 1150 ženám a deťom a v jej zariadení sa narodilo vyše 75 detí. Tento kanál eviduje aj spoluprácu s Fórom života, ktoré má vlastný samostatný kanál.</p>',
        ],
        172 => [
            'name' => 'Teologická fakulta Trnavskej univerzity',
            'municipality' => [3596, 242], // Trnava -> Bratislava (skutočné sídlo fakulty)
            'website' => [null, 'https://tf.truni.sk'],
            'email' => [null, 'lubomira.zaloudkova@truni.sk'],
            'phone' => [null, '+421 2 5277 5410'],
            'street' => [null, 'Kostolná 1'], 'postcode' => [null, '814 99'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Teologická fakulta Trnavskej univerzity sídli v Bratislave a bola formálne zriadená 23. októbra 1997, no nadväzuje na Teologický inštitút svätého Alojza, založený jezuitmi v roku 1941, ako aj na pôvodnú Teologickú fakultu Trnavskej univerzity, ktorá existovala v rokoch 1635 až 1777. Fakulta má štyri katedry — teológie, humanitných vied, biblických a historických vied a poradenstva — a samostatné centrum pre východnú kresťanskú spiritualitu v Košiciach.</p><p>Ponúka bakalárske programy kresťanskej filozofie, poradenstva a medzikultúrnej mediácie, magisterský program katolícka teológia a doktorandské štúdium. Je členom Asociácie jezuitských univerzít v Európe a vydáva vedecký časopis Studia Aloisiana.</p>',
        ],
        326 => [
            // primárne dáta Trenčianskeho samosprávneho kraja
            'name' => 'Trenčiansky samosprávny kraj, Spolok Srbov na Slovensku, Nadácia PRO PATRIA, občianske združenie CYRILOMETODIADA',
            'website' => [null, 'https://www.tsk.sk'],
            'email' => [null, 'info@tsk.sk'],
            'phone' => [null, '032/6555 111'],
            'street' => [null, 'K dolnej stanici 7282/20A'], 'postcode' => [null, '911 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Trenčiansky kraj vznikol na základe zákona č. 221/1996 Z. z. o územnom a správnom usporiadaní SR z roku 1996 a ako samosprávny kraj bol formálne ustanovený zákonom č. 302/2001 Z. z. Zlučuje deväť okresov — Trenčín, Bánovce nad Bebravou, Ilava, Myjava, Nové Mesto nad Váhom, Partizánske, Považská Bystrica, Prievidza a Púchov — s rozlohou 4502 km² a približne 600-tisíc obyvateľmi.</p><p>Ako orgán územnej samosprávy zabezpečuje kompetencie v oblasti regionálneho rozvoja, dopravy, sociálnych služieb, zdravotníctva vrátane zriaďovania nemocníc, školstva a kultúry na svojom území. Tento kanál v databáze eviduje aj Spolok Srbov na Slovensku (občianske združenie sídliace v Bratislave, ktoré podporuje kultúru srbskej menšiny), ako aj Nadáciu Pro Patria a občianske združenie Cyrilometodiáda, ktoré majú vlastné samostatné kanály.</p>',
        ],
        717 => [
            // primárne dáta Banskobystrickej diecézy
            'name' => 'Banskobystrické biskupstvo, Diecézne pastoračné centrum pre rodinu Banskobystrickej diecézy a Farnosť Staré Hory',
            'municipality' => [4209, 73], // Celé Slovensko -> Banská Bystrica (skutočné sídlo biskupského úradu)
            'website' => [null, 'https://bbdieceza.sk'],
            'email' => [null, 'sekretariat.bb@rcc.sk'],
            'phone' => [null, '048 472 08 00'],
            'street' => [null, 'Námestie SNP 19'], 'postcode' => [null, '975 90'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Banskobystrická diecéza vznikla 13. marca 1776, keď cisárovná Mária Terézia bulou Regalium principum spolu s pápežom Piom VI. zriadili nové biskupstvo (spolu so spišským a rožňavským v ten istý deň). Územie bolo predtým súčasťou rozsiahleho Ostrihomského arcibiskupstva, prvým banskobystrickým biskupom bol František Berchtold a diecéza pri vzniku mala 77 farností a okolo 290 filiálok.</p><p>Tento kanál eviduje aj Diecézne pastoračné centrum pre rodinu Banskobystrickej diecézy, ktoré poskytuje kurzy prirodzeného plánovania rodičovstva a prípravy snúbencov na manželstvo a organizuje obnovné víkendy pre bezdetné manželské páry, a farnosť Staré Hory — mariánske pútnické miesto s Bazilikou Navštívenia Panny Márie, ktorú od roku 2010 spravujú bosí karmelitáni.</p>',
        ],
        339 => [
            'name' => 'Gréckokatolícka teologická fakulta Prešovskej univerzity',
            'website' => [null, 'https://www.unipo.sk/greckokatolicka-teologicka-fakulta'],
            'email' => [null, 'gtfpu@unipo.sk'],
            'phone' => [null, '+421 51 77 25 166'],
            'street' => [null, 'Ulica biskupa Gojdiča 2'], 'postcode' => [null, '080 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Gréckokatolícka teologická fakulta Prešovskej univerzity bola založená v roku 1880 ako Gréckokatolícka bohoslovecká akadémia prešovským biskupom Mikulášom Tóthom; jej činnosť bola násilne ukončená v roku 1950 pri likvidácii gréckokatolíckej cirkvi v Československu. Štúdium teológie sa v Prešove obnovilo v roku 1990 a od 1. januára 1997 je fakulta súčasťou novozriadenej Prešovskej univerzity ako jej najstaršia súčasť.</p><p>Fakulta má štyri katedry — filozofie a európskych štúdií, sociálnych a humanitných vied, systematickej teológie a historických vied — a ponúka bakalárske, magisterské aj doktorandské štúdium teológie, spoločný magisterský program mediácia a probácia s Univerzitou Komenského, vlastnú kaplnku blahoslaveného Pavla Petra Gojdiča a zapája sa do programu Erasmus+.</p>',
        ],
        611 => [
            // primárne dáta Talianskeho kultúrneho inštitútu
            'name' => 'Taliansky kultúrny inštitút a bratia kapucíni',
            'website' => [null, 'https://iicbratislava.esteri.it'],
            'email' => [null, 'iicbratislava@esteri.it'],
            'phone' => [null, '+421 2 59 30 71 11'],
            'street' => [null, 'Kapucínska 7'], 'postcode' => [null, '811 03'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Taliansky kultúrny inštitút v Bratislave vznikol v roku 1922 ako Circolo Italiano, v roku 1924 bol premenovaný na Circolo Italiano di Cultura; v rokoch 1942 až 1946 bol zatvorený a po krátkom povojnovom pôsobení bol neaktívny až do roku 1999, keď obnovil činnosť v paláci neďaleko Dómu svätého Martina a Bratislavského hradu. Je oficiálnym orgánom talianskeho štátu — kultúrnym úradom Talianskeho veľvyslanectva — s poslaním šíriť taliansky jazyk a kultúru na Slovensku.</p><p>Inštitút organizuje kurzy taliančiny, skúšky CILS certifikátu Univerzity pre cudzincov v Siene, prevádzkuje knižnicu s knihami, CD, DVD a talianskou tlačou a spolupracuje s inštitúciami ako Slovenská filharmónia, Slovenská národná galéria a slovenské univerzity. Tento kanál eviduje aj bratov kapucínov, ktorí v Bratislave pôsobia od 17. storočia a majú vlastný kláštor a Kostol svätého Štefana na Župnom námestí.</p>',
        ],
        594 => [
            'name' => 'Komisia pre mládež Trnavskej arcidiecézy',
            'website' => [null, 'https://www.mladeztt.sk'],
            'email' => [null, 'centrum.archa@gmail.com'],
            'phone' => [null, '+421 948 549 511'],
            'street' => [null, 'Ulica Jána Hollého 384/10'], 'postcode' => [null, '917 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Komisia pre mládež Trnavskej arcidiecézy bola ako samostatný právny subjekt s vlastným štatútom ustanovená v auguste 2011. V tom istom roku vzniklo aj jej hlavné dielo — Diecézne centrum mládeže Archa v Bojničkách pri Hlohovci, vybudované z bývalého kláštora a slávnostne otvorené v októbri 2011.</p><p>Centrum s kapacitou približne 36 osôb slúži ako formačné a ubytovacie miesto pre mládež nielen z Trnavskej arcidiecézy — organizujú sa tu duchovné obnovy pre spoločenstvá aj birmovancov. Komisia tiež pripravuje animátorov vedúcich detských a mládežníckych spoločenstiev a organizuje podujatia ako každoročný mládežnícky ples.</p>',
        ],
        921 => [
            'name' => 'farský úrad Slovenská Ves',
            'website' => [null, 'https://rkcslovenskaves.sk'],
            'email' => [null, 'kancelaria@rkcslovenskaves.sk'],
            'phone' => [null, '052/459 31 05'],
            'street' => [null, 'Slovenská Ves 415'], 'postcode' => [null, '059 02'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Farským kostolom farnosti Slovenská Ves je Kostol Obetovania Pána — gotická stavba zo stredu 14. storočia s pozdĺžnym pôdorysom typickým pre dobovú architektúru. Farnosť patrí do Spišskej diecézy, Kežmarského dekanátu.</p><p>Okrem farského kostola spravuje farnosť aj dve filiálky — Kostol svätej Kataríny Alexandrijskej vo Vojňanoch a Kostol svätej Uršule vo Výbornej.</p>',
        ],
        663 => [
            // primárne dáta Obce Radava
            'name' => 'OZ Za krajšiu Radavu, Rímskokatolícky farský úrad Radava, Obec Radava, CYRILOMETODIADA, o. z., a Nadácia PRO PATRIA',
            'website' => [null, 'https://radava.sk'],
            'email' => [null, 'info@radava.sk'],
            'phone' => [null, '+421 35 6582331'],
            'street' => [null, 'Radava 444'], 'postcode' => [null, '941 47'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Obec Radava v okrese Nové Zámky je prvýkrát písomne doložená v roku 1237. Archeologické nálezy dokladajú osídlenie už z neolitu a doby bronzovej, zápis z roku 1319 spomína „tri poplužia zeme s kostolom svätého Petra". Obec zasiahol mor v roku 1349 a opakované turecké nájazdy po bitke pri Moháči v roku 1526; v roku 2007 si pripomenula 770. výročie prvej písomnej zmienky, dnes má 736 obyvateľov.</p><p>Tento kanál eviduje aj Rímskokatolícky farský úrad Radava (patriaci pod Nitriansku diecézu) a Občianske združenie Za krajšiu Radavu, ktoré spolu s obcou spoluorganizuje pravidelné podujatia ako vianočný koncert, fašiangovú kapustnicu, batôžkovú zábavu a súťaž vo výrobe vína Víno Radava.</p>',
        ],
        977 => [
            // primárne dáta Kongregácie Milosrdných sestier sv. Vincenta (ÚPN je vedený samostatne, pozri kanál id=159)
            'name' => 'Ústav pamäti národa a Kongregácia Milosdných sestier sv. Vincenta – Satmárok',
            'municipality' => [4209, 3949], // Celé Slovensko -> Vrícko (skutočné sídlo kongregácie)
            'website' => [null, 'https://satmarky.sk'],
            'email' => [null, 'satmarky@satmarky.sk'],
            'phone' => [null, '+421 43 4901 702'],
            'street' => [null, 'Vrícko 195'], 'postcode' => [null, '038 31'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Kongregáciu Milosrdných sestier svätého Vincenta, ľudovo nazývanú Satmárky, založil 29. augusta 1842 v Satu Mare biskup János Hám, na základe duchovnosti svätého Vincenta de Paul. Sestry prišli do Bratislavy v roku 1857, ich činnosť bola za komunizmu násilne potlačená a formálne obnovená 26. februára 1990; v roku 2022 kongregácia oslávila 180. výročie svojho vzniku.</p><p>Charizma sestier spočíva v nasledovaní Krista prostredníctvom charitatívnej a apoštolskej služby ľuďom — pôsobia ako zdravotné sestry v nemocniciach, v domovoch dôchodcov, ako učiteľky v školách a pomáhajú vo farnostiach najmä prostredníctvom katechézy. Kongregácia má približne 120 sestier v jedenástich komunitách na Slovensku. Tento kanál eviduje aj Ústav pamäti národa, ktorý má vlastný samostatný kanál.</p>',
        ],
        145 => [
            // primárne dáta OOCR Turiec
            'name' => 'OOCR Turiec, OOCR Malá Fatra, Rada pre kultúrnu cestu sv. Martina na Slovensku',
            'municipality' => [4209, 2119], // Celé Slovensko -> Martin (skutočné sídlo OOCR Turiec)
            'website' => [null, 'https://turiec.com'],
            'email' => [null, 'kancelaria@turiec.org'],
            'phone' => [null, '+421 915 551 377'],
            'street' => [null, 'Nám. S. H. Vajanského 1'], 'postcode' => [null, '036 49'], 'country' => [null, 'Slovensko'],
            'body' => '<p>OOCR Turiec je oficiálna oblastná organizácia cestovného ruchu pre región Turiec so sídlom v Martine, zriadená v roku 2012 na základe zákona č. 91/2010 Z. z. o podpore cestovného ruchu. Propaguje širší priestor Turčianskej kotliny vrátane miest Martin a Turany a údolí ako Valčianska či Jasenská dolina.</p><p>Jej aktivity zahŕňajú letnú turistiku (cykloturistika, pešia turistika, agroturistika, adrenalínové športy), zimnú turistiku (lyžiarske strediská, snežnicová turistika), organizovanie a propagáciu regionálnych podujatí, vedenie katalógu ubytovania a sprievodcov po kultúrnych a historických pamiatkach. Tento kanál eviduje aj OOCR Malá Fatra (regionálna organizácia cestovného ruchu so sídlom v Žiline, založená v roku 2012) a Radu pre kultúrnu cestu svätého Martina na Slovensku (občianske združenie so sídlom v Dolnom Štáli, ktoré od roku 2016 rozvíja slovenský úsek medzinárodnej pútnickej Cesty svätého Martina, certifikovanej Radou Európy).</p>',
        ],
        313 => [
            'name' => 'Katolícke noviny',
            'website' => [null, 'https://www.katolickenoviny.sk'],
            'email' => [null, 'posta@katolickenoviny.sk'],
            'phone' => [null, '02/5930 6911'],
            'street' => [null, 'Kapitulská 5'], 'country' => [null, 'Slovensko'], // PSČ neisté (uvedená len poštová schránka), neuvádza sa
            'body' => '<p>Katolícke noviny sú najstaršie slovenské katolícke periodikum, založené v roku 1849 s cieľom uchovávať katolícku a národnú identitu Slovákov. V obnovenej podobe vychádzajú od 7. júla 1870, keď sa ich vydavateľom stal Spolok svätého Vojtecha — vzťah, ktorý trvá dodnes.</p><p>Noviny vychádzajú ako týždenník a prinášajú cirkevné spravodajstvo, duchovné a pastoračné komentáre, aktuality z pohľadu katolíckej Cirkvi a reportáže; v poslednej dobe spustili aj digitálne predplatné. Historicky mali náklad okolo 65-tisíc výtlačkov.</p>',
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
