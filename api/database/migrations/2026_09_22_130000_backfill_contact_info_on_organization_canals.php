<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Doplní web/email/telefón/adresu pre 49 najvýznamnejších neosobných
 * kanálov (organizátorov, identity_mode=organization) a opraví `body`
 * a `municipality_id` tam, kde prieskum na oficiálnych weboch 22. 9. 2026
 * zistil iné sídlo, než mal kanál doteraz. Kanál id=70 (Katolícke
 * spoločenstvo Marana Tha, Košice) sa nedopĺňa vôbec — nenašli sa žiadne
 * overené údaje a hrozí zámena so samostatným kanálom id=135 (Prešov).
 *
 * Viacero kanálov v DB duplicitne predstavuje tú istú reálnu organizáciu
 * (id 86/124 = Spoločenstvo Martindom, id 65/197 = Spoločnosť Božieho
 * Slova/Verbisti, id 156/586 = eRko) — zlúčenie duplicitných kanálov nie
 * je súčasťou tejto migrácie, dostávajú len tie isté overené údaje.
 * Viacero kanálov v DB zas zlučuje dve reálne organizácie do jedného
 * záznamu (napr. id 543 = KBS + Ekumenická rada cirkví, id 789 =
 * Slovenská katolícka charita + Bratislavská arcidiecézna charita) —
 * kontaktné údaje patria prvej/hlavnej organizácii, druhá je spomenutá
 * v `body`.
 *
 * Riadok (kontaktné polia aj municipality_id) sa prepíše len vtedy, keď
 * súhlasí id + name + pôvodná hodnota — čo medzitým niekto upravil
 * ručne, ostane nedotknuté (rovnaký princíp ako
 * 2026_09_13_100000_backfill_venue_and_canal_coordinates.php). Prepis
 * `body` je zámerne jednosmerný — down() ho nevracia — nahrádza starý
 * AI-generovaný text z importu novým, z webu overeným popisom činnosti.
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
        // --- top 20 podľa počtu eventov (bez zberných kanálov vyveska.sk/tkkbs.sk/ecav.sk) ---
        135 => [
            'name' => 'M-Aréna a spoločenstvo MaranaTha',
            'website' => [null, 'https://maranathapo.sk'],
            'email' => [null, 'kancelaria@maranathapo.sk'],
            'street' => [null, 'Švábska 22'], 'postcode' => [null, '080 05'], 'country' => [null, 'Slovensko'],
        ],
        195 => [
            'name' => 'Komunita Blahoslavenstiev',
            // pôvodný web bol zdroj importu (hlascirkvi.sk), nie vlastná stránka komunity
            'website' => ['https://hlascirkvi.sk', 'https://blahoslavenstva.sk/sk'],
            'email' => [null, 'blahoslavenstva@gmail.com'],
        ],
        134 => [
            'name' => 'Godzone',
            'municipality' => [242, 3178], // Bratislava -> Sliač (skutočné sídlo)
            'website' => [null, 'https://godzone.sk'],
            'email' => [null, 'godzone@godzone.sk'],
            'phone' => [null, '+421 944 537 274'],
            'street' => [null, 'Jarná 13'], 'postcode' => [null, '962 31'], 'country' => [null, 'Slovensko'],
        ],
        110 => [
            'name' => 'Rád menších bratov konventuálov (minoriti)',
            'website' => [null, 'https://minoriti.sk'],
            // email/telefón/adresa neoverené pre obec Pečenice (rád má na Slovensku len 4 kláštory, Pečenice medzi nimi nie sú)
        ],
        98 => [
            'name' => 'Bratislavská arcidiecéza',
            'website' => [null, 'https://www.abuba.sk'],
            'email' => [null, 'podatelna@abuba.sk'],
            'phone' => [null, '+421 2 5720 0611'],
            'street' => [null, 'Špitálska 7'], 'postcode' => [null, '814 92'], 'country' => [null, 'Slovensko'],
        ],
        30 => [
            'name' => 'Žilinská diecéza',
            'website' => [null, 'https://dcza.sk'],
            'email' => [null, 'kuria@dcza.sk'],
            'phone' => [null, '+421 41 500 22 15'],
            'street' => [null, 'Jána Kalinčiaka 1'], 'postcode' => [null, '011 36'], 'country' => [null, 'Slovensko'],
        ],
        196 => [
            'name' => 'Sigord - Centrum pre rodinu',
            'municipality' => [3537, 3122], // Tešedíkovo -> Sigord (skutočné sídlo)
            'website' => [null, 'https://centrumsigord.sk'],
            'email' => [null, 'centrum.rodina@gmail.com'],
            'phone' => [null, '+421 903 983 316'],
            'street' => [null, 'Sigord č. 134'], 'postcode' => [null, '082 52'], 'country' => [null, 'Slovensko'],
        ],
        63 => [
            'name' => 'Pápežské misijné diela na Slovensku',
            'website' => [null, 'https://www.misijnediela.sk'],
            'email' => [null, 'info@misijnediela.sk'],
            'phone' => [null, '02/529 64 916'],
            'street' => [null, 'Lazaretská 32'], 'postcode' => [null, '811 09'], 'country' => [null, 'Slovensko'],
        ],
        243 => [
            'name' => 'Pápežská nadácia ACN – Pomoc trpiacej Cirkvi',
            'website' => [null, 'https://acnslovensko.sk'],
            'email' => [null, 'info@acnslovensko.sk'],
            'phone' => [null, '+421 2 222 001 25'],
            'street' => [null, 'Sládkovičova 7'], 'postcode' => [null, '811 06'], 'country' => [null, 'Slovensko'],
        ],
        161 => [
            'name' => 'Rehoľa menších bratov (františkáni)',
            'website' => [null, 'https://frantiskani.sk'],
            'email' => [null, 'klastortt@frantiskani.sk'],
            'phone' => [null, '+421 911 293 502'],
            'street' => [null, 'Františkánska 1'], 'postcode' => [null, '917 01'], 'country' => [null, 'Slovensko'],
        ],
        27 => [
            'name' => 'Františkáni',
            'website' => [null, 'https://frantiskani.sk/nove-zamky/'],
            'email' => [null, 'klastornz@frantiskani.sk'],
            'phone' => [null, '+421 35 640 06 27'],
            'street' => [null, 'Kostolná 129/1'], 'postcode' => [null, '940 01'], 'country' => [null, 'Slovensko'],
        ],
        75 => [
            'name' => 'Duchovné centrum Lukov dvor',
            'website' => [null, 'https://misionari.sk'],
            'email' => [null, 'dsjnitra@gmail.com'],
            'phone' => [null, '+421 951 935 005'],
            'street' => [null, 'Lukov dvor 2'], 'postcode' => [null, '949 01'], 'country' => [null, 'Slovensko'],
        ],
        86 => [
            'name' => 'Spoločenstvo Martindom',
            'municipality' => [2119, 250], // Martin -> Bratislava - Nové Mesto (skutočné sídlo)
            'website' => [null, 'https://www.martindom.sk'],
            'email' => [null, 'infocentrum@martindom.sk'],
            'phone' => [null, '02/555 71 397'],
            'street' => [null, 'Rožňavská 17'], 'postcode' => [null, '831 04'], 'country' => [null, 'Slovensko'],
        ],
        36 => [
            'name' => 'Spišská diecéza',
            'website' => [null, 'https://kapitula.sk'],
            'email' => [null, 'biskupstvo@kapitula.sk'],
            'phone' => [null, '+421 53 454 11 36'],
            'street' => [null, 'Spišská Kapitula 661/9'], 'postcode' => [null, '053 04'], 'country' => [null, 'Slovensko'],
        ],
        175 => [
            'name' => 'Rádio LUMEN',
            'municipality' => [3275, 73], // Staré Hory -> Banská Bystrica (skutočné sídlo)
            'website' => [null, 'https://www.lumen.sk'],
            'email' => [null, 'lumen@lumen.sk'],
            'phone' => [null, '048/471 08 10'],
            'street' => [null, 'Kapitulská 2'], 'postcode' => [null, '974 01'], 'country' => [null, 'Slovensko'],
        ],
        328 => [
            'name' => 'Gréckokatolícka cirkev v Sabinove',
            'website' => [null, 'https://www.greckokat-sabinov.sk'],
            'email' => [null, 'fu@greckokat-sabinov.sk'],
            'phone' => [null, '051/452 14 24'],
            'street' => [null, 'Prešovská 1910/2A'], 'postcode' => [null, '083 01'], 'country' => [null, 'Slovensko'],
        ],
        10 => [
            'name' => 'Rada pre ekumenizmus Bratislavskej arcidiecézy (Rímskokatolícka cirkev)',
            // pôvodný web (ecav.sk) je preukázateľne cudzí (Evanjelická cirkev), maže sa; dedikovaná stránka rady sa nenašla
            'website' => ['https://www.ecav.sk', null],
        ],
        99 => [
            'name' => 'Rádio Mária Slovensko',
            'website' => [null, 'https://www.radiomaria.sk'],
            'email' => [null, 'info.slo@radiomaria.org'],
            'phone' => [null, '+421 919 233 529'],
            'street' => [null, 'Mlynské nivy 73'], 'postcode' => [null, '821 05'], 'country' => [null, 'Slovensko'],
        ],
        206 => [
            'name' => 'Školské sestry sv. Františka v Žiline',
            'website' => [null, 'https://skolskesestry.sk'],
            'email' => [null, 'provincialat@skolskesestry.sk'],
            'street' => [null, 'J. M. Hurbana 44'], 'postcode' => [null, '010 01'], 'country' => [null, 'Slovensko'],
        ],

        // --- ďalších 30 neosobných kanálov podľa počtu eventov ---
        353 => [
            'name' => 'Fórum kresťanských inštitúcií',
            'website' => [null, 'https://fki.sk'],
            'email' => [null, 'info@fki.sk'],
            'phone' => [null, '+421 903 733 057'],
            'street' => [null, 'Gorkého 15'], 'postcode' => [null, '811 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Fórum kresťanských inštitúcií je občianske združenie (neformálne od roku 1996, registrované od 2004), ktoré združuje takmer 60 kresťanských organizácií na Slovensku a spolupracuje s ďalšími približne 50 inštitúciami. Jeho cieľom je posilňovať hlas kresťanov vo verejnom živote v spolupráci s Konferenciou biskupov Slovenska a je zapojené do medzinárodných sietí PRORURE a FIMARC.</p>',
        ],
        543 => [
            'name' => 'Konferencia biskupov Slovenska a Ekumenická rada cirkví na Slovensku',
            'website' => [null, 'https://www.kbs.sk'],
            'email' => [null, 'kbs@kbs.sk'],
            'phone' => [null, '02/59 20 65 01'],
            'street' => [null, 'Kapitulská 11'], 'postcode' => [null, '814 99'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Konferencia biskupov Slovenska je zastupiteľský orgán biskupov Katolíckej cirkvi na Slovensku, ktorý koordinuje činnosť cirkvi a diecéz na národnej úrovni, zastupuje cirkev navonok a vydáva spoločné pastierske listy. Tento kanál eviduje aj spoluprácu s Ekumenickou radou cirkví v SR (ekumena.sk), ktorá združuje cirkvi na nadkonfesnom základe a organizuje spoločné ekumenické podujatia.</p>',
        ],
        227 => [
            'name' => 'Rožňavská diecéza',
            'website' => [null, 'https://www.burv.sk'],
            'email' => [null, 'kancelaria@burv.sk'],
            'phone' => [null, '058/78 772 01'],
            'street' => [null, 'Nám. baníkov 20'], 'postcode' => [null, '048 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Rožňavská diecéza je rímskokatolícka diecéza zriadená v roku 1776 pápežom Piom VI. a Máriou Teréziou vyčlenením z Ostrihomskej arcidiecézy, pôvodne na území Gemera, Novohradu, Malohontu, Turne a časti Spiša. Biskupský úrad v Rožňave zabezpečuje pastoračnú, katechetickú, školskú a charitatívnu činnosť diecézy pod vedením diecézneho biskupa.</p>',
        ],
        124 => [
            // rovnaká organizácia ako kanál id=86 "Spoločenstvo Martindom" (duplicitný kanál, mimo rozsahu tejto migrácie)
            'name' => 'Spoločenstvo pri Dóme sv. Martina',
            'municipality' => [2119, 250], // Martin -> Bratislava - Nové Mesto (skutočné sídlo)
            'website' => [null, 'https://www.martindom.sk'],
            'email' => [null, 'infocentrum@martindom.sk'],
            'phone' => [null, '02/555 71 397'],
            'street' => [null, 'Rožňavská 17'], 'postcode' => [null, '831 04'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Spoločenstvo pri Dóme sv. Martina je kresťanské občianske združenie, ktoré vzišlo z aktivít podzemnej cirkvi ovplyvnenej disidentmi Silvestrom Krčmérym a Vladimírom Juklom. Približne 300 dospelých členov sa stretáva v malých spoločenstvách, organizuje pravidelné modlitbové stretnutia, kurzy Nový život a konferencie a vydáva časopis Nahlas.</p>',
        ],
        9 => [
            'name' => 'Rada pre mládež a univerzity KBS',
            'municipality' => [2763, 242], // Poprad -> Bratislava (skutočné sídlo; Poprad je len miesto konania stretnutia mládeže)
            'website' => [null, 'https://mladez.kbs.sk'],
            'email' => [null, 'mladez@kbs.sk'],
            'phone' => [null, '0940 985 377'],
            'street' => [null, 'Kapitulská 11'], 'postcode' => [null, '814 99'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Rada pre mládež a univerzity je orgán zriadený Konferenciou biskupov Slovenska pre pastoráciu mládeže a vysokoškolákov. Koordinuje celoslovenské podujatia ako Národné stretnutie mládeže, animátorské školy a prieskumy medzi mladými (napr. „Verím?", „Povedz!").</p>',
        ],
        480 => [
            'name' => 'Spolok svätého Vojtecha a Dom Quo Vadis',
            'municipality' => [242, 3596], // Bratislava -> Trnava (skutočné sídlo SSV)
            'website' => [null, 'https://www.ssv.sk'],
            'email' => [null, 'ssv@ssv.sk'],
            'phone' => [null, '033 590 77 11'],
            'street' => [null, 'Radlinského 5'], 'postcode' => [null, '917 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Spolok svätého Vojtecha je najstaršia a najväčšia katolícka organizácia na Slovensku, založená v roku 1870. Vydáva duchovnú a náboženskú literatúru, prevádzkuje knižnicu a archív a spravuje sieť miestnych odbočiek po celom Slovensku. Tento kanál eviduje aj Dom Quo Vadis v Bratislave (Veterná 1629/1), dobrovoľnícku kaviareň a evanjelizačné centrum v centre mesta.</p>',
        ],
        38 => [
            'name' => 'Misijná škola Karola Wojtylu',
            'website' => [null, 'https://mskw.sk'],
            'street' => [null, 'Svoradova 13'], 'postcode' => [null, '811 03'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Misijná škola Karola Wojtylu je občianske združenie (registrované 2016), ktoré sprístupňuje učenie Katolíckej cirkvi o láske a sexualite, najmä Teológiu tela Jána Pavla II., formou vzdelávania, modlitby a spoločenstva. Organizuje víkendové kurzy, semestrálne semináre a online kurzy na Slovensku, v Česku, Rakúsku a na Ukrajine.</p>',
        ],
        90 => [
            'name' => 'Konferencia biskupov Slovenska',
            'municipality' => [3411, 242], // Šaštín -> Bratislava (skutočné sídlo; Šaštín je mariánske pútnické miesto)
            'website' => [null, 'https://www.kbs.sk'],
            'email' => [null, 'kbs@kbs.sk'],
            'phone' => [null, '02/59 20 65 01'],
            'street' => [null, 'Kapitulská 11'], 'postcode' => [null, '814 99'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Konferencia biskupov Slovenska je zbor katolíckych biskupov pôsobiacich na Slovensku, zriadený na spoločné vykonávanie pastoračných úloh Cirkvi. Má plenárne zhromaždenie, stálu radu, generálny sekretariát, ekonomickú radu a viaceré odborné komisie.</p>',
        ],
        197 => [
            'name' => 'Verbisti',
            'municipality' => [4209, 2333], // Celé Slovensko -> Nitra (skutočné sídlo, ústredný dom)
            'email' => [null, null], // web už bol správny (verbisti.sk), email sa nepodarilo overiť
            'phone' => [null, '+421 37 77 69 411'],
            'street' => [null, 'Kalvária 3'], 'postcode' => [null, '949 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Spoločnosť Božieho Slova (Verbisti) je rehoľná misijná kongregácia založená Arnoldom Janssenom, venujúca sa hláseniu evanjelia prostredníctvom misijnej činnosti na Slovensku aj v zahraničí. Spravuje farnosti, formačné a duchovné centrá, misijné múzeum a vydavateľskú činnosť a podporuje misionárov po celom svete.</p>',
        ],
        789 => [
            'name' => 'Bratislavská arcidiecézna charita a Slovenská katolícka charita',
            'website' => [null, 'https://www.charita.sk'],
            'email' => [null, 'info@charita.sk'],
            'phone' => [null, '+421 2 5443 1506'],
            'street' => [null, 'Kapitulská 18'], 'postcode' => [null, '814 15'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Slovenská katolícka charita je celoslovenská charitatívna organizácia s mottom „Blízko pri človeku v núdzi", ktorá pomáha seniorom, rodinám s deťmi, ľuďom bez domova, migrantom a utečencom na Slovensku aj v zahraničných rozvojových projektoch (napr. Pôstna krabička, Adopcia na diaľku). Pôsobí prostredníctvom siete diecéznych charít vrátane Bratislavskej arcidiecéznej charity (charitaba.sk).</p>',
        ],
        85 => [
            'name' => 'Katolícka univerzita v Ružomberku',
            'municipality' => [2612, 3053], // Pečenice -> Ružomberok (skutočné sídlo)
            'website' => [null, 'https://www.ku.sk'],
            'email' => [null, 'info@ku.sk'],
            'phone' => [null, '044 4326 842'],
            'street' => [null, 'Hrabovská cesta 1A'], 'postcode' => [null, '034 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Katolícka univerzita v Ružomberku je verejná vysoká škola s katolíckym charakterom, založená zákonom v roku 2000. Má štyri fakulty — filozofickú, pedagogickú, teologickú a zdravotníctva — a študuje na nej približne 3600 študentov pod mottom „Formujeme myseľ a srdce".</p>',
        ],
        926 => [
            'name' => 'Konferencia biskupov Slovenska – Rada pre vedu, vzdelanie a kultúru',
            'website' => [null, 'https://kultura.kbs.sk'],
            'email' => [null, 'tajomnik@kultura.kbs.sk'],
            'phone' => [null, '+421 2 5920 6501'],
            'street' => [null, 'Kapitulská 11'], 'postcode' => [null, '814 99'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Rada pre vedu, vzdelanie a kultúru je jedna z rád Konferencie biskupov Slovenska, ktorá zastrešuje spoluprácu Cirkvi s akademickou a kultúrnou obcou. Udeľuje ceny Fides et Ratio a Fra Angelica a organizuje stretnutia a dokumenty v tejto oblasti.</p>',
        ],
        133 => [
            // popis (body) sa nepodarilo overiť z oficiálneho zdroja, preto sa nemení - len kontaktné údaje
            'name' => 'Misionári Najsvätejšieho Srdca Ježišovho',
            'website' => [null, 'https://misionari.sk'],
            'email' => [null, 'superior@misionari.sk'],
            'phone' => [null, '+421 37 693 00 31'],
            'street' => [null, 'Partizánska 56'], 'postcode' => [null, '949 01'], 'country' => [null, 'Slovensko'],
        ],
        586 => [
            // primárne dáta eRka (druhá zlúčená organizácia: Rada pre mládež a univerzity KBS, pozri kanál id=9)
            'name' => 'Rada pre mládež a univerzity Konferencie biskupov Slovenska a eRko – Hnutie kresťanských spoločenstiev detí',
            'municipality' => [2904, 242], // Rajecká Lesná -> Bratislava (skutočné sídlo eRka)
            'website' => [null, 'https://erko.sk'],
            'email' => [null, 'erko@erko.sk'],
            'phone' => [null, '+421 907 713 169'],
            'street' => [null, 'Miletičova 7'], 'postcode' => [null, '821 08'], 'country' => [null, 'Slovensko'],
            'body' => '<p>eRko – Hnutie kresťanských spoločenstiev detí je občianske združenie (od roku 1973, registrované od 1990), ktoré združuje vyše 7000 detí v mimoškolských spoločenstvách pri farnostiach po celom Slovensku. Organizuje tábory, výchovné kurzy, vydáva časopisy Rebrík a Lusk a najznámejšou aktivitou je celoslovenská koledovacia zbierka Dobrá novina. Tento kanál eviduje aj spoluprácu s Radou pre mládež a univerzity Konferencie biskupov Slovenska.</p>',
        ],
        309 => [
            'name' => 'Katolícke biblické dielo',
            'website' => [null, 'https://kbd.sk'],
            'email' => [null, 'kbd@kbd.sk'],
            'phone' => [null, '+421 915 909 914'],
            'street' => [null, 'Hrabovská cesta 1/A'], 'postcode' => [null, '034 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Katolícke biblické dielo je cirkevná organizácia zriadená Konferenciou biskupov Slovenska s celoslovenskou pôsobnosťou, ktorej poslaním je podporovať biblický apoštolát. Sprístupňuje Božie slovo prostredníctvom prekladov, vzdelávania, prednášok, kurzov pre lektorov a letných škôl.</p>',
        ],
        709 => [
            // primárne dáta Mesta Ružomberok (Spišská diecéza je vedená samostatne, pozri kanál id=36)
            'name' => 'Spišská diecéza a Mesto Ružomberok',
            'website' => [null, 'https://www.ruzomberok.sk'],
            'email' => [null, 'ruzomberok@ruzomberok.sk'],
            'phone' => [null, '+421 44 431 44 22'],
            'street' => [null, 'Námestie A. Hlinku 1098/1'], 'postcode' => [null, '034 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Mestský úrad Ružomberok je výkonným orgánom samosprávy mesta, ktorý zabezpečuje administratívnu agendu, prevádzkuje Klientske centrum pre občanov a spravuje mestské projekty a verejné služby. Tento kanál eviduje aj spoluprácu so Spišskou diecézou, ktorej sídlo (Spišská Kapitula) je vedené ako samostatný kanál.</p>',
        ],
        218 => [
            'name' => 'Biskupstvo Nitra',
            'website' => [null, 'https://www.biskupstvo-nitra.sk'],
            'email' => [null, 'nitra@kbs.sk'],
            'phone' => [null, '+421 37 772 17 47'],
            'street' => [null, 'Nám. Jána Pavla II. 7'], 'country' => [null, 'Slovensko'], // PSČ sa medzi zdrojmi líši (949 01 / 950 50), neuvádza sa
            'body' => '<p>Biskupstvo Nitra je diecéznym úradom Rímskokatolíckej cirkvi, ktorý spravuje farnosti, cirkevné školy a pastoračné centrá na území Nitrianskej diecézy. Zabezpečuje aj charitatívnu činnosť a správu historických cirkevných objektov.</p>',
        ],
        524 => [
            'name' => 'Televízia LUX',
            'website' => [null, 'https://www.tvlux.sk'],
            'email' => [null, 'tvlux@tvlux.sk'],
            'phone' => [null, '+421 2 212 955 55'],
            'street' => [null, 'Prepoštská 5'], 'postcode' => [null, '811 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>TV LUX je slovenská katolícka televízia zameraná na duchovné a ľudské hodnoty. Vysiela náboženské a spravodajské relácie, dokumenty a diskusné programy s kresťanskou tematikou a prevádzkuje aj e-shop s náboženskou literatúrou a médiami.</p>',
        ],
        198 => [
            'name' => 'Hnutie kresťanských rodín',
            'municipality' => [4209, 242], // Celé Slovensko -> Bratislava (skutočné sídlo)
            'email' => [null, 'hkrsr@hkrsr.sk'],
            'phone' => [null, '+421 905 892 213'],
            'street' => [null, 'Francisciho 3'], 'postcode' => [null, '811 08'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Hnutie kresťanských rodín na Slovensku je dobrovoľné laické kresťanské združenie pre manželov, rodičov a ich rodiny, ktoré chce budovať stabilný rodinný život na princípoch kresťanskej lásky, jednoty a služby. Organizuje najmä Originálne manželské rekolekcie — duchovné obnovy pre manželské páry, podporované Konferenciou biskupov Slovenska.</p>',
        ],
        171 => [
            'name' => 'Kongregácia sestier Najsvätejšieho Spasiteľa - Centrum Salvator',
            'website' => [null, 'https://www.centrumsalvator.sk'],
            'email' => [null, 'info@centrumsalvator.sk'],
            'phone' => [null, '+421 221 291 470'],
            'street' => [null, 'Jakubovo nám. 4-5'], 'postcode' => [null, '811 09'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Centrum Salvator je duchovné, konferenčné a ubytovacie centrum zriadené Kongregáciou sestier Najsvätejšieho Spasiteľa v centre Bratislavy. Ponúka ubytovanie, konferenčné priestory, stravovanie a duchovné programy.</p>',
        ],
        188 => [
            'name' => 'Komunita redemptoristov a laikov (Koral)',
            'municipality' => [4209, 1550], // Celé Slovensko -> Kostolná - Záriečie (skutočné sídlo)
            // pôvodný web bol zdroj importu (hlascirkvi.sk), nie vlastná stránka komunity
            'website' => ['https://hlascirkvi.sk', 'https://spolocenstvo-rl.sk'],
            'email' => [null, 'cssrkostolna@gmail.com'],
            'phone' => [null, '032/649 92 32'],
            'street' => [null, 'Kostolná-Záriečie 8'], 'postcode' => [null, '913 04'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Koral je laické spoločenstvo spolupracujúce s redemptoristami Bratislavsko-pražskej provincie, s približne 40 členmi. Členovia sa každoročne zaväzujú sľubom zotrvať v komunite a podieľajú sa na príprave ľudových misií, duchovných cvičení, obnov a seminárov v exercičnom dome pri kláštore v Kostolnej-Záriečí aj vo farnostiach.</p>',
        ],
        72 => [
            'name' => 'Kolégium Antona Neuwirtha',
            'municipality' => [2612, 1231], // Pečenice -> Ivanka pri Dunaji (skutočné sídlo)
            'website' => [null, 'https://kolegium.org'],
            'email' => [null, 'info@kolegium.org'],
            'street' => [null, 'Námestie padlých hrdinov 7'], 'postcode' => [null, '900 28'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Kolégium Antona Neuwirtha je nezávislá vzdelávacia inštitúcia založená v roku 2009, ktorá formuje mladých ľudí v duchu kresťanských hodnôt a kultúrneho dedičstva západnej civilizácie. Ponúka vzdelávacie programy pre deti, stredoškolákov, vysokoškolákov aj širokú verejnosť vrátane internátneho kolégia v Ivanke pri Dunaji.</p>',
        ],
        347 => [
            // vlastný kontakt seminára (odlišný od Biskupstva Nitra, kanál id=218)
            'name' => 'Nitrianske biskupstvo a Kňazský seminár sv. Gorazda',
            'website' => [null, 'https://ksnr.sk'],
            'email' => [null, 'rektorat@ksnr.sk'],
            'phone' => [null, '037/772 17 58'],
            'street' => [null, 'Samova 14'], 'postcode' => [null, '949 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Kňazský seminár svätého Gorazda v Nitre je medzidiecézny seminár pripravujúci bohoslovcov na kňazstvo pre Nitriansku, Žilinskú a Banskobystrickú diecézu, od roku 2023 aj pre Bratislavskú a Trnavskú arcidiecézu. Jeho činnosť obnovil v roku 1990 kardinál Ján Chryzostom Korec po období komunizmu.</p>',
        ],
        602 => [
            'name' => 'Pastoračné centrum Anny Kolesárovej – Domček',
            'website' => [null, 'https://domcek.org'],
            'email' => [null, 'domcek@domcek.org'],
            'phone' => [null, '+421 911 912 598'],
            'street' => [null, 'Vysoká nad Uhom 27'], 'postcode' => [null, '072 14'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Pastoračné centrum Anny Kolesárovej – Domček je pastoračno-mládežnícke centrum v rodisku Anny Kolesárovej, kde od roku 1999 prebiehajú Púte radosti a duchovné obnovy pre mládež, birmovancov, farnosti a školy. Centrum ročne navštívi vyše 4-tisíc ľudí pri jej hrobe.</p>',
        ],
        79 => [
            'name' => 'Občianske združenie Národný pochod za život',
            'website' => [null, 'https://pochodzazivot.sk'],
            'phone' => [null, '0901 702 299'],
            'body' => '<p>Občianske združenie Národný pochod za život organizuje každoročný pochod pod záštitou Konferencie biskupov Slovenska, ktorý presadzuje ochranu života od počatia po prirodzenú smrť, legislatívne zmeny na ochranu nenarodených detí a podporu matiek v ťažkých životných situáciách.</p>',
        ],
        73 => [
            'name' => 'Občianske združenie Katarínka',
            'municipality' => [2612, 242], // Pečenice -> Bratislava (korešpondenčná adresa; samotné ruiny sú pri Dechticiach/Naháči)
            'website' => [null, 'https://katarinka.sk'],
            'email' => [null, 'katarinka@katarinka.sk'],
            'street' => [null, 'Šándorova 8'], 'postcode' => [null, '821 03'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Občianske združenie Katarínka od roku 1994 dobrovoľnícky zachraňuje zrúcaninu kostola a kláštora sv. Kataríny Alexandrijskej pri Dechticiach. Organizuje dobrovoľnícke brigády, archeologický a geofyzikálny výskum, konzervačné práce a verejné podujatia.</p>',
        ],
        65 => [
            // rovnaká kongregácia ako kanál id=197 "Verbisti" (duplicitný kanál, mimo rozsahu tejto migrácie)
            'name' => 'Spoločnosť Božieho Slova',
            'website' => [null, 'https://www.verbisti.sk/nitra/'],
            'phone' => [null, '+421 37 77 69 411'],
            'street' => [null, 'Kalvária 3'], 'postcode' => [null, '949 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Misijný dom Matky Božej v Nitre je hlavný dom Spoločnosti Božieho Slova (Verbisti) na Slovensku a sídlo provinciála. Zabezpečuje misijnú animáciu, vydavateľstvo (časopis Hlasy z domova a z misií), kníhkupectvo Verbum, duchovné cvičenia a pastoráciu Kostola Nanebovzatia Panny Márie na Kalvárii.</p>',
        ],
        156 => [
            // rovnaká organizácia ako v kanáli id=586 (duplicitný kanál, mimo rozsahu tejto migrácie)
            'name' => 'eRko – Hnutie kresťanských spoločenstiev detí',
            'municipality' => [4209, 242], // Celé Slovensko -> Bratislava (skutočné sídlo)
            'website' => [null, 'https://erko.sk'],
            'email' => [null, 'erko@erko.sk'],
            'phone' => [null, '+421 907 713 169'],
            'street' => [null, 'Miletičova 7'], 'postcode' => [null, '821 08'], 'country' => [null, 'Slovensko'],
            'body' => '<p>eRko – Hnutie kresťanských spoločenstiev detí je občianske združenie (od roku 1973, registrované od 1990), ktoré združuje vyše 7000 detí v mimoškolských spoločenstvách pri farnostiach po celom Slovensku. Organizuje tábory a výchovné kurzy, vydáva časopisy Rebrík a Lusk a najznámejšou aktivitou je celoslovenská koledovacia zbierka Dobrá novina.</p>',
        ],
        471 => [
            'name' => 'Cestovná kancelária NOE TRAVEL',
            'municipality' => [4209, 2428], // Celé Slovensko -> Nové Mesto nad Váhom (sídlo firmy)
            'website' => [null, 'https://noetravel.sk'],
            'phone' => [null, '+421 902 057 999'],
            'street' => [null, 'Klčové 2088/36'], 'postcode' => [null, '915 01'], 'country' => [null, 'Slovensko'],
            'body' => '<p>NOE TRAVEL je cestovná kancelária, ktorá popri klasických dovolenkových zájazdoch prevádzkuje aj špecializovaný portál pútnických zájazdov, kombinujúc bežnú turistiku s katolíckymi púťami.</p>',
        ],
        698 => [
            'name' => 'Občianske združenie Bratislavská Kalvária a Kresťanské združenie Sprevádzajúci',
            'website' => [null, 'https://bratislavskakalvaria.sk'],
            'email' => [null, 'info@bratislavskakalvaria.sk'],
            'street' => [null, 'Oravská 1264/18'], 'postcode' => [null, '821 09'], 'country' => [null, 'Slovensko'],
            'body' => '<p>Občianske združenie Bratislavská Kalvária, založené v roku 2019, sa venuje obnove a revitalizácii Bratislavskej Kalvárie — krížovej cesty z roku 1694. Koordinuje reštaurátorské a záchranné práce na jednotlivých zastaveniach.</p>',
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
