<?php

use App\Models\Canal;
use App\Services\SystemLog\Recorder;
use Illuminate\Database\Migrations\Migration;

/**
 * Popisy importovaných kanálov, ktoré mali len systémovú vetu
 * „… — organizátor podujatí. Podujatia sú preberané z verejných zdrojov."
 * (ImportedProfileDescriber bez AI; pri chýbajúcom názve dokonca „null — …").
 *
 * Texty sú napísané ručne v troch odsekoch ako ostatné popisy: kto organizácia
 * je, aké podujatia pripravuje (podľa jej podujatí a webu) a komu je kanál
 * určený. Bez vymyslených mien, dátumov a kontaktov.
 *
 * Prepíše sa len stále zástupný popis — text, ktorý medzitým upravil správca
 * či AI, ostane. Kanál musí sedieť aj názvom; prevzatý či zlúčený sa preskočí.
 * Kanálom s cudzím webom sa web opraví (`replace_website`), inak sa doplní
 * len do prázdneho poľa.
 */
return new class extends Migration
{
    private const PLACEHOLDER = 'Podujatia sú preberané z verejných zdrojov';

    /** @var array<int, array{name: string, body: array<int, string>, website?: string, replace_website?: bool}> */
    private const PROFILES = [
        1090 => [
            'name' => 'CK Cestovná kancelária NOE TRAVEL',
            'body' => [
                'CK NOE travel je cestovná kancelária z Piešťan, ktorá sa špecializuje na púte. Na svojej stránke pute.sk ponúka zájazdy na svetové aj slovenské pútnické miesta. Púte pripravuje autobusom aj letecky.',
                'Pravidelne organizuje púte do Medžugoria, napríklad k sviatku Nanebovzatia Panny Márie, a medzinárodnú duchovnú obnovu pre manželské páry. Ponúka aj púte do Ríma a Vatikánu, niekedy spojené s pobytom pri mori. Zájazdy sprevádza kňaz, ktorý vedie duchovný program.',
                'Kanál je určený pútnikom, rodinám a manželským párom, ktorí hľadajú organizovanú púť. Ak ho budete sledovať, budete mať prehľad o nových termínoch zájazdov. Program, cenu a miesto odchodu nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        276 => [
            'name' => 'CYRILOMETODIADA',
            'body' => [
                'CYRILOMETODIADA je občianske združenie, ktoré pestuje cyrilo-metodské dedičstvo a slovenskú kultúru. Spája duchovný rozmer s kultúrou a vzdelávaním. Pôsobí po celom Slovensku.',
                'Pripravuje prezentácie kníh, napríklad diela prof. Evy Fordinálovej O sile slovenského slova, a výstavy, ako boli Ikony sv. Mikuláša. Organizuje aj ďakovné sväté omše a predstavenie knihy o kňazovi Šebastiánovi Košútovi. Podujatia sa konajú v knižniciach, múzeách a kostoloch v rôznych mestách.',
                'Kanál odporúčame všetkým, ktorých zaujíma slovenská kultúra, história a duchovné tradície. Sledovaním budete vedieť o nových prezentáciách a výstavách. Miesto a čas konania nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1055 => [
            'name' => 'Teatro Colorato',
            'body' => [
                'Teatro Colorato je divadelná skupina z Bratislavy, ktorá vytvára predstavenia najmä pre deti a mládež. Hrá v priestoroch na Františkánskom námestí. Jej inscenácie spájajú divadlo, hudbu a obraz.',
                'Uviedla napríklad obrazovo-poetické predstavenie LOUIS BRAILLE, inscenáciu ANNA či hudobno-divadelnú show Bejby Bajbl Band o biblických zvieratkách a hrdinoch. Pripravuje aj vianočné koncerty. Predstavenia sú určené deťom aj celým rodinám.',
                'Kanál je určený rodinám s deťmi, školám a divákom, ktorí hľadajú divadlo s hodnotami. Ak ho budete sledovať, nezmeškáte nové predstavenia. Termín a miesto nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        684 => [
            'name' => 'Nadční fond CREDO',
            'body' => [
                'Nadační fond CREDO je česká nadácia, ktorá podporuje duchovné a evanjelizačné aktivity. Na Slovensku spolupracuje pri podujatiach s indickým katolíckym kňazom Jamesom Manjackalom. Jeho program sa sústreďuje na modlitbu a duchovnú obnovu.',
                'Pravidelne ponúka online modlitby za uzdravenie s Jamesom Manjackalom, zvyčajne v sobotu popoludní. Pripravila aj viacdňovú duchovnú obnovu s pátrom Manjackalom v Hlohovci. Online stretnutia umožňujú zapojiť sa z celého Slovenska.',
                'Kanál je určený veriacim, ktorí hľadajú modlitbu, povzbudenie a duchovnú obnovu. Ak ho budete sledovať, budete mať prehľad o termínoch online modlitieb aj stretnutí naživo. Podrobnosti a spôsob pripojenia nájdete vždy v detaile podujatia.',
            ],
        ],
        833 => [
            'name' => 'Fraternity Jeruzalem',
            'body' => [
                'Fraternity Jeruzalem sú mníšske spoločenstvá bratov a sestier, ktoré žijú uprostred miest. Ich modlitba je spojená s liturgiou a životom v meste. Na Slovensku sú známe najmä vďaka online duchovným inšpiráciám.',
                'Pripravujú online cyklus Notre Dame – adventné inšpirácie, ktorý trvá niekoľko večerov pred Vianocami. Jednotlivé večery sú krátke, trvajú približne pol hodiny a venujú sa témam ako nádej a radosť. Pomáhajú duchovne sa pripraviť na Vianoce.',
                'Kanál je určený všetkým, ktorí chcú prežiť advent a ďalšie obdobia cirkevného roka hlbšie. Sledovaním budete vedieť o nových cykloch inšpirácií. Čas a odkaz na pripojenie nájdete vždy v detaile podujatia.',
            ],
        ],
        697 => [
            'name' => 'Národný trust, n.o',
            'body' => [
                'Národný trust je nezisková organizácia, ktorá chráni a sprístupňuje kultúrne a prírodné dedičstvo Slovenska. Organizuje celoslovenský Víkend otvorených parkov a záhrad. Toto kultúrno-vzdelávacie podujatie otvára verejnosti historické parky a záhrady.',
                'Každoročne v júni sa do programu zapája aj Bratislavská Kalvária na Kalvárskom vrchu v Bratislave. Národný trust ho pripravuje v spolupráci s OZ Bratislavská Kalvária a združením Sprevádzajúci. Návštevníci môžu spoznať históriu, prírodu aj duchovný rozmer tohto miesta.',
                'Kanál odporúčame milovníkom prírody, histórie a pamiatok. Sledovaním budete vedieť o termínoch otvorených parkov a záhrad. Miesto a čas nájdete vždy v detaile podujatia.',
            ],
        ],
        765 => [
            'name' => 'Biskup Haľko',
            'body' => [
                'Mons. Jozef Haľko je pomocný biskup Bratislavskej arcidiecézy. Venuje sa aj pastoračnej starostlivosti o Slovákov žijúcich v zahraničí. Kanál prináša jeho pozvánky a duchovné iniciatívy.',
                'V pôste pozýva na sériu stretnutí „Pri studni“ v centre Bratislavy. Slávil aj slovenskú svätú omšu v chorvátskom Šibeniku a vydal sa na púť do Santiaga de Compostela s úmyslami veriacich. Prináša tiež duchovné zamyslenia, napríklad o oddychu počas prázdnin.',
                'Kanál je určený veriacim z Bratislavy aj Slovákom v zahraničí. Ak ho budete sledovať, budete mať prehľad o stretnutiach a bohoslužbách s biskupom. Miesto a čas konania nájdete vždy v detaile podujatia.',
            ],
        ],
        1343 => [
            'name' => 'Slobodni Krestania',
            'body' => [
                'Slobodní kresťania sú spoločenstvo, ktoré pripravuje spoločný program pre slobodných kresťanov. Spája duchovný život s pobytom v prírode a športom. Podujatia sa konajú v horských oblastiach Slovenska.',
                'Organizuje víkendové turistické výlety, napríklad na Sliezsky dom, lyžovačky a amatérske bežkovanie v Zuberci. Pripravuje aj Veľkonočné štvordnie vo Važci, kde sa prežíva vrchol Veľkého týždňa. Program spája spoločenstvo, modlitbu a oddych.',
                'Kanál je určený slobodným, ktorí hľadajú kresťanské spoločenstvo a aktívny voľný čas. Sledovaním budete vedieť o nových víkendoch a výletoch. Termín, cenu a miesto nájdete vždy v detaile podujatia.',
            ],
        ],
        668 => [
            'name' => 'Slovenský orol',
            'body' => [
                'Slovenský orol je kresťanská telovýchovná organizácia s dlhou tradíciou. Spája šport, výchovu a duchovné hodnoty. Pôsobí po celom Slovensku.',
                'Každoročne organizuje cyklistickú cyrilo-metodskú púť na sviatok sv. Cyrila a Metoda, ktorá má už viac ako dvadsať ročníkov. Cyklisti putujú napríklad do Nitry pod Zobor. Púť spája pohyb, spoločenstvo a uctenie si vierozvestcov.',
                'Kanál odporúčame cyklistom a všetkým, ktorí chcú prežiť sviatok aktívne. Sledovaním budete vedieť o termíne a trase ďalšieho ročníka. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        776 => [
            'name' => 'Liga pár páru',
            'body' => [
                'Liga pár páru je občianske združenie, ktoré učí páry porozumieť plodnosti. Vyučuje sympto-termálnu metódu a sprevádza manželov aj snúbencov. Plodnosť vníma ako dar, nie problém.',
                'Organizuje víkendové kurzy a víkendy s Ligou pár páru v Rodinkove v Beluši, ktoré spájajú vzdelávanie a odpočinok. Venujú sa manželskej intimite a orientovaniu sa v plodnosti. Ponúka aj online kurz Plodnosť nie je problém, ale dar.',
                'Kanál je určený manželským a snúbeneckým párom. Ak ho budete sledovať, budete vedieť o nových kurzoch a víkendoch. Miesto, čas a prihlasovanie nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        924 => [
            'name' => 'Rodina BB',
            'body' => [
                'Rodina BB je Diecézne pastoračné centrum pre rodinu Banskobystrickej diecézy. Sprevádza manželov a rodiny v rôznych životných situáciách. Venuje sa aj ľuďom, ktorí prežívajú bolesť a stratu.',
                'Organizuje duchovno-terapeutický víkendový program Ráchelina vinica pre ženy a mužov, ktorí smútia po strate dieťaťa. Pripravuje aj duchovné obnovy pre bezdetné manželské páry, napríklad na Donovaloch. Programy sa konajú v rôznych regiónoch Slovenska.',
                'Kanál je určený manželom a jednotlivcom, ktorí hľadajú duchovnú oporu a uzdravenie. Sledovaním budete vedieť o nových termínoch programov. Miesto a prihlasovanie nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        645 => [
            'name' => 'Betlehemské sestry',
            'body' => [
                'Betlehemské sestry sú kontemplatívne rehoľné spoločenstvo. Ich život je postavený na modlitbe, tichu a práci. Slovenky ich poznajú najmä z kláštora na Kinderalme v Rakúsku.',
                'Pozývajú mladé ženy na duchovné cvičenia na Kinderalme, zvyčajne vo veku od 20 do 35 rokov. Ponúkajú aj dobrovoľnícku pomoc v kláštore, pri ktorej ženy prežijú týždeň v rytme komunity. Programy dávajú priestor na ticho a rozlišovanie.',
                'Kanál je určený mladým ženám, ktoré hľadajú hlbší duchovný život. Sledovaním budete vedieť o nových termínoch cvičení a dobrovoľníckych pobytov. Podrobnosti a prihlasovanie nájdete vždy v detaile podujatia.',
            ],
        ],
        812 => [
            'name' => 'Spoločenstvo Joel',
            'body' => [
                'Spoločenstvo Joel je katolícke spoločenstvo z východného Slovenska. Zameriava sa na modlitbu chvál a život v Duchu Svätom. Stretnutia pripravuje v Prešovskom kraji.',
                'Organizuje víkendové kurzy Charizmy Sv. Ducha I. a II. v Drienici, ktoré sa venujú darom Ducha Svätého v každodennom živote. Pripravuje aj večery chvál s modlitbami za oslobodenie a uzdravenie, napríklad v Sabinove. Program spája prednášky, chvály a modlitbu.',
                'Kanál je určený veriacim, ktorí chcú prehĺbiť svoj duchovný život. Sledovaním budete vedieť o nových kurzoch a večeroch chvál. Miesto a čas nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1301 => [
            'name' => 'Rehoľné sestry z rôznych spoločenstiev',
            'body' => [
                'Rehoľné sestry z rôznych spoločenstiev spoločne pripravujú modlitby pre verejnosť v Bratislave. Spájajú sestry viacerých rehoľných komunít. Ich cieľom je spoločná modlitba s veriacimi.',
                'Pozývajú na modlitbu večerných chvál a vešpier, ktorá sa koná každý tretí štvrtok v mesiaci. Stretnutia bývajú v uršulínskom Kostole Loretánskej Panny Márie. Modlitba je otvorená pre všetkých.',
                'Kanál je určený veriacim z Bratislavy, ktorí sa chcú pripojiť k pravidelnej modlitbe. Sledovaním budete vedieť o najbližšom termíne. Miesto a čas nájdete vždy v detaile podujatia.',
            ],
        ],
        693 => [
            'name' => 'Nové svitanie',
            'body' => [
                'Nové svitanie je katolícka pútnická konferencia pre jednotlivcov aj celé rodiny. Koná sa každé leto na Skalke pri Trenčíne, na lúke pod starobylým kláštorom. Konferencia má už viac ako desať ročníkov.',
                'Niekoľkodňový program spája sväté omše, prednášky, modlitbu a čas pre rodiny. Je určený všetkým generáciám, od detí po seniorov. Účastníci prežívajú duchovnú obnovu v prostredí pútnického miesta.',
                'Kanál odporúčame rodinám a pútnikom, ktorí hľadajú letnú duchovnú obnovu. Sledovaním budete vedieť o termíne a programe ďalšieho ročníka. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        908 => [
            'name' => 'Kruciata oslobodenia človeka',
            'body' => [
                'Kruciáta oslobodenia človeka je spoločenstvo, ktoré sa venuje prevencii a pomoci pri závislostiach. Pôsobí v Levoči a je spojené s Hnutím Svetlo-Život. Vychádza z odkazu Božieho služobníka Františka Blachnického.',
                'Organizuje celoslovenské sympóziá o závislostiach a spoluzávislostiach v Levoči. Pripravuje aj duchovné cvičenia Vnútorné uzdravenie a púte k hrobu Františka Blachnického. Program spája odborný pohľad s duchovným rozmerom.',
                'Kanál je určený rodinám, pedagógom a všetkým, ktorých sa dotýka problém závislostí. Sledovaním budete vedieť o nových sympóziách a cvičeniach. Miesto a čas nájdete vždy v detaile podujatia.',
            ],
        ],
        914 => [
            'name' => 'Spoločenstvo Lachaj Roi',
            'body' => [
                'Spoločenstvo Lachaj Roi je katolícke modlitbové spoločenstvo. Jeho názov znamená „studňa Živého, ktorý ma vidí“. Pôsobí v Trnavskom kraji.',
                'Pravidelne organizuje večery Modlitby za uzdravenie v Kostole sv. Jána Krstiteľa v Seredi. Stretnutia sa konajú v sobotu večer a trvajú niekoľko hodín. Sú otvorené pre všetkých.',
                'Kanál je určený veriacim, ktorí hľadajú modlitbu a duchovnú oporu. Sledovaním budete vedieť o najbližších termínoch. Miesto a čas nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1195 => [
            'name' => 'Železničná spoločnosť Slovensko, a. s',
            'website' => 'https://www.zssk.sk',
            'replace_website' => true,
            'body' => [
                'Železničná spoločnosť Slovensko (ZSSK) je národný železničný dopravca. Pri veľkých pútnických podujatiach posilňuje vlakovú dopravu. Pútnikom tak uľahčuje cestu na pútnické miesta.',
                'Na Levočskú púť vypravuje desiatky vlakov na trati Spišská Nová Ves – Levoča. Pri slávnosti Sedembolestnej Panny Márie vypravuje mimoriadny pútnický vlak na národnú púť do Šaštína. Spoje sú zaradené v cestovnom poriadku.',
                'Kanál je užitočný pre pútnikov, ktorí cestujú vlakom. Sledovaním budete vedieť o mimoriadnych spojoch na púte. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1241 => [
            'name' => 'Žilinský samosprávny kraj',
            'body' => [
                'Žilinský samosprávny kraj je vyšší územný celok, ktorý podporuje kultúru, turistiku a tradície regiónu. Spolupracuje s obcami a kultúrnymi inštitúciami. Na podujatiach sa podieľa ako organizátor aj partner.',
                'Spoluorganizuje pamätný výstup na Veľký Rozsutec na počesť sv. Jána Pavla II. Spolu s Kysuckou knižnicou v Čadci a obcou Klokočov vyhlasuje súťaž duchovnej tvorby Jurinova jeseň. Podujatia spájajú prírodu, literatúru a duchovné dedičstvo.',
                'Kanál je určený obyvateľom regiónu, turistom a autorom. Sledovaním budete vedieť o nových ročníkoch výstupu a súťaže. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1308 => [
            'name' => 'EVS',
            'body' => [
                'Slovenské evanjelizačné stredisko (EVS) je kresťanská organizácia, ktorá sa venuje evanjelizácii a rozvoju lídrov. Pôsobí po celom Slovensku. Spolupracuje s cirkevnými zbormi a spoločenstvami.',
                'Organizuje konferencie, napríklad EVS konferenciu NAPLNO v Piešťanoch. Pripravuje večery Chcemviac a tréningy pre ľudí, ktorí vedú iných, ako bol tréning Odolný život. Program spája inšpiráciu, vzdelávanie a vieru.',
                'Kanál je určený mladým, lídrom a všetkým, ktorí chcú rásť vo viere. Sledovaním budete vedieť o nových konferenciách a tréningoch. Miesto a čas nájdete vždy v detaile podujatia.',
            ],
        ],
        1375 => [
            'name' => 'portál nm.sk',
            'body' => [
                'Portál nm.sk je kresťanský webový portál, ktorý zároveň vydáva knihy a časopis. Venuje sa aktuálnym témam viery a spoločnosti. Pripravuje aj vlastné podujatia.',
                'Organizuje diskusie a webináre, napríklad o otázke, či sa dá odpustiť všetko. Pri knižnej novinke Volanie po mieri pripravil diskusiu o mieri a vojne. Podujatia sa konajú v Bratislave aj online.',
                'Kanál je určený čitateľom a všetkým, ktorých zaujímajú témy viery a spoločnosti. Sledovaním budete vedieť o nových diskusiách a webinároch. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1394 => [
            'name' => 'združenie Laura',
            'body' => [
                'Laura, združenie mladých, je občianske združenie, ktoré pracuje s deťmi a mladými. Inšpiruje sa blahoslavenou Laurou Vicuñou. Ponúka aktivity naživo aj online.',
                'Organizuje tanečnú súťaž TeenDance s dlhoročnou tradíciou a projekt Meška. Pripravuje adventné zamyslenia a deviatnik k bl. Laure Vicuni na sociálnych sieťach. Projekty spájajú tvorivosť, pohyb a vieru.',
                'Kanál je určený mladým, rodičom a animátorom. Sledovaním budete vedieť o nových projektoch a súťažiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1504 => [
            'name' => 'RTVS',
            'body' => [
                'RTVS je verejnoprávne vysielanie Slovenska. Vo svojom programe uvádza aj dokumenty s náboženskou a historickou tematikou. Mnohé z nich vysiela na Dvojke.',
                'Uviedla dokumentárne filmy Konštantínov tajný kód, Monsterproces o procese s biskupmi a film o dobrovoľníčke Danici Olexovej. Pripravuje aj premiéry k významným výročiam. Filmy približujú osobnosti a udalosti cirkevných dejín.',
                'Kanál je určený divákom, ktorých zaujímajú dokumenty o viere a histórii. Sledovaním budete vedieť o termínoch premiér. Čas a stanicu nájdete vždy v detaile podujatia.',
            ],
        ],
        1725 => [
            'name' => 'Rožňavský biskup',
            'body' => [
                'Rožňavský biskup stojí na čele Rožňavskej diecézy. Kanál prináša jeho pozvania na modlitbu a duchovné iniciatívy. Obracia sa na veriacich diecézy aj celého Slovenska.',
                'Biskup Mons. Stanislav Stolárik pozýval napríklad na deviatnik k sv. Jozefovi a na májovú modlitbu ruženca podľa iniciatívy pápeža. Pripravil aj mariánsku pobožnosť na máj, ktorú sa veriaci modlili spolu. Iniciatívy pomáhajú duchovne sa zjednotiť.',
                'Kanál je určený veriacim Rožňavskej diecézy. Sledovaním budete vedieť o nových pozvaniach a slávnostiach. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1802 => [
            'name' => 'Spolok Martina Rázusa',
            'body' => [
                'Spolok Martina Rázusa pestuje odkaz kňaza, básnika a politika Martina Rázusa. Venuje sa slovenským dejinám a kultúre. Pripravuje kultúrne aj duchovné podujatia.',
                'Organizuje vedecko-popularizačné podujatie Sláva šľachetným o nedocenených postavách slovenských dejín. Pripravuje aj dvojdňovú jesennú púť na Horu Živčáková v Turzovke. Program spája históriu, kultúru a vieru.',
                'Kanál je určený všetkým, ktorých zaujíma slovenská história a jej osobnosti. Sledovaním budete vedieť o nových podujatiach a pútiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1896 => [
            'name' => 'Coworking Poprad',
            'body' => [
                'Coworking Poprad je zdieľaný pracovný priestor v centre Popradu na Námestí sv. Egídia. Ponúka miesto na prácu aj stretávanie. Organizuje aj verejné večery pre komunitu.',
                'Pripravuje sériu Stretnutí s otcom Andrejom Branickým, na ktoré pozýva hostí, napríklad Mareka Madra z IPčko. Rozhovory sa venujú viere, vzťahom a výchove, napríklad komunikácii s tínedžermi. Večery sú otvorené pre verejnosť.',
                'Kanál je určený obyvateľom Popradu a okolia. Sledovaním budete vedieť o ďalších hosťoch a termínoch. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        379 => [
            'name' => 'spoločenstvo Skala',
            'body' => [
                'Spoločenstvo Skala je kresťanské spoločenstvo z Prešova. Pripravuje konferencie pre mužov aj ženy. Zameriava sa na duchovný rast a vzťah s Bohom.',
                'Organizuje konferenciu pre mužov Spoznaj svoju silu v Centre pre rodinu Sigord. Pre ženy pripravuje konferenciu Hlbina, napríklad na tému Slobodná v Kristovi. Konferencie sú zamerané na duchovný rast a spoločenstvo.',
                'Kanál je určený mužom a ženám, ktorí chcú rásť vo viere. Sledovaním budete vedieť o nových ročníkoch konferencií. Miesto a prihlasovanie nájdete vždy v detaile podujatia.',
            ],
        ],
        786 => [
            'name' => 'Združenie Faustínum',
            'body' => [
                'Združenie Faustínum šíri úctu k Božiemu milosrdenstvu podľa odkazu sv. Faustíny. Pôsobí na východnom Slovensku. Venuje sa duchovnej formácii a vzdelávaniu.',
                'Pripravuje stretnutia Zemplínske Faustínum v Nižnom Hrušove. Organizuje duchovné cvičenia, napríklad v Bardejovských Kúpeľoch. Program je zameraný na prehlbovanie duchovného života.',
                'Kanál je určený veriacim, ktorým je blízka úcta k Božiemu milosrdenstvu. Sledovaním budete vedieť o nových stretnutiach a cvičeniach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        891 => [
            'name' => 'DC pre bezdetných manželov',
            'body' => [
                'Duchovné cvičenia pre bezdetných manželov pripravuje nezisková organizácia Milujúca náruč. Sprevádza manželov, ktorí túžia po dieťati. Ponúka im duchovnú oporu a uzdravenie.',
                'Cvičenia sa konajú v Rehoľno-formačnom centre v Spišskej Novej Vsi. Trvajú niekoľko dní a spájajú modlitbu, slovo a priestor na zdieľanie. Termíny sa opakujú viackrát do roka.',
                'Kanál je určený bezdetným manželským párom. Sledovaním budete vedieť o najbližších termínoch. Prihlasovanie nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1018 => [
            'name' => 'Rehoľno-formačné centrum',
            'body' => [
                'Rehoľno-formačné centrum pri Svätyni Božieho milosrdenstva je duchovný dom v Spišskej Novej Vsi. Slúži na duchovné cvičenia a formáciu. Hostí programy rôznych organizátorov.',
                'Konajú sa tu duchovné cvičenia Milujúca náruč pre ľudí zranených potratom, vrátane spontánneho potratu. Programy sú zamerané na uzdravenie a vnútorný pokoj. Prebiehajú v tichu a modlitbe.',
                'Kanál je určený tým, ktorí hľadajú duchovnú obnovu a uzdravenie. Sledovaním budete vedieť o nových termínoch. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1049 => [
            'name' => 'Vtelené Slovo Slovensko',
            'body' => [
                'Vtelené Slovo je rehoľná rodina, ktorá na Slovensku ponúka ignaciánske duchovné cvičenia. Vedie ľudí k modlitbe a rozlišovaniu. Programy sa konajú v tichu.',
                'Organizuje duchovné cvičenia podľa sv. Ignáca z Loyoly v Dome Xaver v Badíne. Cvičenia trvajú niekoľko dní a ich súčasťou je osobné sprevádzanie. Prihlasovanie prebieha vopred.',
                'Kanál je určený veriacim, ktorí chcú stráviť čas v tichu s Bohom. Sledovaním budete vedieť o nových termínoch cvičení. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1101 => [
            'name' => 'KKLZ Bratislava',
            'body' => [
                'KKLZ Bratislava je bratislavské spoločenstvo katolíckych lekárov a zdravotníkov. Spája zdravotníkov, ktorým záleží na kresťanských hodnotách v medicíne. Pravidelne sa stretáva.',
                'Organizuje mesačné stretnutia, ktoré sa venujú etickým otázkam v zdravotníctve. Pripravuje aj Krížovú cestu zdravotníkov v Kostole Navštívenia Panny Márie u Milosrdných bratov. Stretnutia spájajú vzdelávanie a modlitbu.',
                'Kanál je určený lekárom, sestrám a ďalším zdravotníkom. Sledovaním budete vedieť o najbližšom stretnutí. Miesto a čas nájdete vždy v detaile podujatia.',
            ],
        ],
        1194 => [
            'name' => 'Kurz Samuel',
            'body' => [
                'Kurz Samuel je formačný program pre mladých, ktorí hľadajú svoje životné povolanie. Je určený ľuďom vo veku od 18 do 35 rokov. V Bratislave prebieha v spolupráci s univerzitným pastoračným centrom.',
                'Ide o deväťmesačný cyklus stretnutí s prednáškami, modlitbou a duchovným sprevádzaním. Účastníci sa učia rozlišovať a prehlbovať svoj vzťah k Bohu. Nový cyklus sa otvára na jeseň.',
                'Kanál je určený mladým, ktorí stoja pred dôležitými rozhodnutiami. Sledovaním budete vedieť o otvorení nového ročníka. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1221 => [
            'name' => 'Chlapské víkendovky',
            'body' => [
                'Chlapské víkendovky sú duchovné víkendy pre mladých mužov. Sú určené mužom vo veku od 18 do 35 rokov. Spájajú vieru, fyzickú aktivitu a mužské priateľstvo.',
                'Každý ročník má vlastnú tému, napríklad Ako skala na Skalke pri Trenčíne alebo Muž z ocele v Lúčkach. Program sa venuje psychickej aj fyzickej odolnosti. Víkendovky majú už takmer desať ročníkov.',
                'Kanál je určený mužom, ktorí chcú rásť vo viere a charaktere. Sledovaním budete vedieť o ďalšej víkendovke. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1263 => [
            'name' => 'WorldPriest',
            'website' => 'https://www.worldpriest.com',
            'body' => [
                'WorldPriest je medzinárodná iniciatíva, ktorá spája kňazov a laikov v modlitbe. Organizuje celosvetovú štafetu modlitby ruženca za posvätenie kňazov. Zapájajú sa do nej pútnické miesta na celom svete.',
                'Na Slovensku sa do štafety každoročne pripája Mariánska hora v Levoči. Modlitba bolestného ruženca a svätá omša sa vysielajú naživo v TV LUX. Veriaci sa môžu pripojiť osobne aj cez vysielanie.',
                'Kanál je určený veriacim, ktorí sa chcú modliť za kňazov. Sledovaním budete vedieť o termíne ďalšej štafety. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1288 => [
            'name' => 'sociálne sestry',
            'body' => [
                'Spoločnosť sociálnych sestier je rehoľné spoločenstvo, ktoré sa venuje sociálnej a charitatívnej službe. Na Slovensku pôsobí aj v Bratislave – Podunajských Biskupiciach. Spoločnosť má už viac ako sto rokov.',
                'Sestry oslávili jubileum sto rokov od založenia s veriacimi farnosti Podunajské Biskupice. Pripravujú aj víkendy pre mladé ženy, napríklad Víkend so sestrou Sárou v Bacúrove. Program spája modlitbu, spoločenstvo a spoznávanie rehoľného života.',
                'Kanál je určený mladým ženám a všetkým, ktorí chcú spoznať život sestier. Sledovaním budete vedieť o nových stretnutiach. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1407 => [
            'name' => 'Konrad-Adenauer-Stiftung',
            'body' => [
                'Konrad-Adenauer-Stiftung je nemecká politická nadácia, ktorá pôsobí aj na Slovensku. Podporuje demokraciu, občiansku spoločnosť a dialóg. Spolupracuje pri kultúrnych a vzdelávacích podujatiach.',
                'Podporila uvedenie dokumentárneho filmu Slobodní o kresťanskom disente, ktorý premietali aj v Národnej rade SR. Spolupracovala aj na diskusii o pápežovi Františkovi a tradícii. Podujatia otvárajú témy slobody a hodnôt.',
                'Kanál je určený všetkým, ktorých zaujíma história a verejný život. Sledovaním budete vedieť o nových projekciách a diskusiách. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1477 => [
            'name' => 'GPS team',
            'body' => [
                'GPS team pripravuje kurz o rozlišovaní povolania pre dievčatá. Kurz pomáha mladým ženám hľadať smer v živote. Prebieha počas celého školského roka.',
                'Kurz GPS trvá jeden školský rok a prebieha online aj na spoločných stretnutiach. Účastníčky sa venujú svojej osobnosti, duchovnému životu a zmyslu života. Na začiatku roka býva úvodné stretnutie.',
                'Kanál je určený dievčatám, ktoré hľadajú svoje povolanie. Sledovaním budete vedieť o otvorení nového ročníka. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1497 => [
            'name' => 'Cyprián',
            'body' => [
                'Cyprián je organizátor letných duchovných aktivít v Červenom Kláštore. Program sa koná v areáli bývalého kartuziánskeho kláštora. Približuje život mníchov a ich spiritualitu.',
                'Duchovné aktivity vedie dominikán páter Rafael Marek Tresa, OP. Trvajú niekoľko týždňov v lete a ponúkajú modlitbu aj zamyslenia. Návštevníci môžu spoznať kláštor z netradičného pohľadu.',
                'Kanál je určený návštevníkom Pienin a veriacim, ktorí hľadajú letnú duchovnú inšpiráciu. Sledovaním budete vedieť o programe na ďalšie leto. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1510 => [
            'name' => 'Spoločenstvo Dvaja-traja',
            'body' => [
                'Spoločenstvo Dvaja-traja je kresťanské spoločenstvo, ktoré pripravuje biblické kurzy. Zameriava sa na praktický život z viery. Kurzy sa konajú na rôznych miestach Slovenska.',
                'Organizuje biblický kurz Jozue o duchovnom zápase o jednotlivé oblasti života, napríklad v Litmanovej. Pripravuje aj víkendový kurz Biblia a peniaze v Centre pre rodinu Sigord. Kurzy spájajú prednášky a modlitbu.',
                'Kanál je určený veriacim, ktorí chcú žiť podľa Písma aj v praktických veciach. Sledovaním budete vedieť o nových kurzoch. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1617 => [
            'name' => 'RTVS – Slovenská televízia',
            'body' => [
                'RTVS – Slovenská televízia je televízna zložka verejnoprávneho vysielania. Vysiela aj dokumenty s duchovnou a historickou tematikou. Mnohé z nich uvádza na Dvojke.',
                'Odvysielala dokument Vojtaššák režiséra Mareka Poláčeka o biskupovi Jánovi Vojtaššákovi. K stému výročiu narodenia Jána Pavla II. uviedla nový dokument o jeho návšteve Slovenska. Filmy približujú osobnosti cirkevných dejín.',
                'Kanál je určený divákom, ktorých zaujímajú dokumenty o viere a histórii. Sledovaním budete vedieť o termínoch vysielania. Čas a stanicu nájdete vždy v detaile podujatia.',
            ],
        ],
        1619 => [
            'name' => 'Spoločenstvo Klasy',
            'body' => [
                'Spoločenstvo Klasy je spoločenstvo slobodných katolíkov z Banskej Bystrice. Vytvára priestor na stretávanie a spoločnú vieru. Stretáva sa pravidelne.',
                'Pripravuje Stretko slobodných katolíkov v Misijnom dome J. G. Perboyra na Hurbanovej ulici. Stretnutia sa konajú opakovane počas roka. Sú príležitosťou spoznať ďalších slobodných veriacich.',
                'Kanál je určený slobodným katolíkom z Banskej Bystrice a okolia. Sledovaním budete vedieť o najbližšom stretku. Miesto a čas nájdete vždy v detaile podujatia.',
            ],
        ],
        1709 => [
            'name' => 'Združenie Amazi',
            'body' => [
                'Združenie Amazi je občianske združenie, ktoré podporuje projekty v rozvojových krajinách. Pomáha najmä ľuďom na africkom kontinente. Zapája do pomoci aj verejnosť na Slovensku.',
                'Organizuje 24-hodinovú pohybovú štafetu Pohybom pre dobro druhých. Účastníci sa môžu zapojiť odkiaľkoľvek a svojím pohybom podporiť chudobných v Afrike. Štafeta spája šport a solidaritu.',
                'Kanál je určený športovcom a všetkým, ktorí chcú pomáhať. Sledovaním budete vedieť o ďalších výzvach. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1752 => [
            'name' => 'GPS tím',
            'body' => [
                'GPS tím pripravuje kurz o rozlišovaní a prehĺbení cesty povolania. Kurz je určený dievčatám od 17 do 30 rokov. Funguje na Slovensku už niekoľko rokov.',
                'Kurz prebieha počas školského roka, aj online. Účastníčky sa zamýšľajú nad svojou budúcnosťou, vzťahom k Bohu a životným povolaním. Kurz kombinuje osobnú prácu a spoločné stretnutia.',
                'Kanál je určený mladým ženám, ktoré hľadajú svoje povolanie. Sledovaním budete vedieť o otvorení nového ročníka. Podrobnosti nájdete vždy v detaile podujatia.',
            ],
        ],
        1895 => [
            'name' => 'S klub',
            'body' => [
                'S klub je komunitný priestor na Svoradovej ulici v Bratislave. Spája kultúru a duchovný rozmer. Ponúka komorné večery pre mladých aj dospelých.',
                'Pripravuje sériu S klub Sessions, komorných koncertov spojených s rozhovorom s autormi. Hosťami boli napríklad Slávka Tkáčová so sestrou Lenkou či hudobník havrilla. Večery prepájajú autorskú hudbu a príbehy.',
                'Kanál je určený milovníkom hudby a rozhovorov. Sledovaním budete vedieť o ďalších večeroch. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1916 => [
            'name' => 'Vydavateľstvo Wojtyla.sk',
            'body' => [
                'Vydavateľstvo Wojtyla.sk je katolícke vydavateľstvo z Levoče. Vydáva diela sv. Jána Pavla II. v slovenskom preklade. Odvoláva sa na jeho návštevu Mariánskej hory v roku 1995.',
                'Pripravilo slávnostné uvedenie Teológie tela Jána Pavla II. v Kongresovej sále pri Bazilike sv. Jakuba v Levoči. Predstavilo aj nový preklad Rímskeho triptychu. Podujatia približujú myslenie pápeža širokej verejnosti.',
                'Kanál je určený čitateľom a všetkým, ktorým je blízky odkaz Jána Pavla II. Sledovaním budete vedieť o nových knihách a ich predstavení. Podrobnosti nájdete vždy v detaile podujatia. Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
    ];

    public function up(): void
    {
        foreach (self::PROFILES as $id => $profile) {
            $canal = Canal::withTrashed()->find($id);

            if (! $canal || $canal->trashed() || $canal->merged_into_id !== null || $canal->claimed_at !== null
                || $canal->name !== $profile['name']) {
                continue;
            }

            $changes = [];

            if (str_contains((string) $canal->body, self::PLACEHOLDER)) {
                $changes['body'] = implode('', array_map(fn (string $p) => '<p>'.e($p).'</p>', $profile['body']));
            }

            if (isset($profile['website']) && (($profile['replace_website'] ?? false) || trim((string) $canal->website) === '')) {
                $changes['website'] = $profile['website'];
            }

            if ($changes === []) {
                continue;
            }

            $canal->forceFill($changes)->save();
            Recorder::info('canals', 'profile_described', "Kanál {$canal->name}: doplnený popis".(isset($changes['website']) ? ' a web' : ''),
                status: 'ok', subject: $canal, context: ['fields' => array_keys($changes)]);
        }
    }

    public function down(): void
    {
        // Zástupná veta sa nevracia — nový popis je lepší v každom prípade.
    }
};
