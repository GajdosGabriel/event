<?php

use App\Models\Canal;
use App\Models\Municipality;
use App\Services\SystemLog\Recorder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tretia dávka (november 2026): ďalších 80 importovaných kanálov v poradí
 * (#1665 – #1783). Šesťdesiat z nich malo všeobecný AI popis bez overených
 * faktov alebo len „organizátor podujatí" — dostanú tri odseky podľa podujatí
 * kanála a verejne známych faktov. Ostatných dvadsať už trojodsekový popis má.
 *
 * Oprava: #1700 Farský úrad Bratislava-Devín mal web kežmarskej farnosti.
 *
 * Kanál musí sedieť ID aj názvom; prevzatý, zlúčený či zmazaný sa preskočí.
 */
return new class extends Migration
{
    private const CLOSING = 'Podrobnosti nájdete vždy v detaile podujatia.';

    /** @var array<int, array<string, mixed>> */
    private const PROFILES = [
        1782 => ['name' => 'OC Kresťanskodemokratického hnutia', 'body' => [
            'Obvodná organizácia Kresťanskodemokratického hnutia v Trnave združuje členov a sympatizantov KDH v meste. Hnutie sa hlási ku kresťanským hodnotám. Pripravuje spomienkové aj spoločenské podujatia.',
            'Spolu s ďalšími pripravuje spomienky na osobnosti slovenských dejín, napríklad na kňaza a národovca Andreja Hlinku pri výročí jeho narodenia v Bratislave a Trnave. Spomienky spájajú svätú omšu a kladenie vencov.',
            'Kanál je určený členom a priaznivcom hnutia z Trnavy. Sledovaním budete vedieť o spomienkových podujatiach. '.self::CLOSING,
        ]],
        1781 => ['name' => 'Farský úrad v Pohronskej Polhore', 'body' => [
            'Farnosť Pohronská Polhora patrí do Banskobystrickej diecézy. Farským kostolom je Kostol sv. Michala archanjela. Obec leží na hornom Pohroní.',
            'Spolu s obcou pripravuje aj benefičné podujatia, napríklad koncert v kostole, ktorého výnos dostala rodina, ktorej vyhorel dom. Farnosť sa stará o bohoslužby a život farského spoločenstva.',
            'Kanál je určený veriacim z Pohronskej Polhory a okolia. Sledovaním budete vedieť o podujatiach farnosti. '.self::CLOSING,
        ]],
        1780 => ['name' => 'Divadlo EXODUS', 'body' => [
            'Divadlo EXODUS je kresťanské divadelné zoskupenie z Prešova. Hrajú v ňom amatérski herci. Inscenácie sú inšpirované kresťanskými témami a literatúrou.',
            'Pripravuje divadelné predstavenia, napríklad hru Tajomný labyrint inšpirovanú dielom Jána Amosa Komenského, ktorú uviedlo na doskách Divadla Jonáša Záborského. Hrá aj na festivaloch kresťanského divadla.',
            'Kanál je určený milovníkom divadla z Prešova a okolia. Sledovaním budete vedieť o nových predstaveniach. '.self::CLOSING,
        ]],
        1779 => ['name' => 'Rímskokatolícka cirkev farnosť Vyšné Ružbachy', 'body' => [
            'Farnosť Vyšné Ružbachy patrí do Spišskej diecézy. Vyšné Ružbachy sú kúpeľnou obcou pod Levočskými vrchmi. Farnosť slúži domácim aj kúpeľným hosťom.',
            'Pripravuje ľudové misie, napríklad misie redemptoristov, a ďalšie duchovné podujatia. Zapája sa aj do celoslovenských iniciatív, ako je Týždeň modlitieb za jednotu kresťanov.',
            'Kanál je určený veriacim z Vyšných Ružbách a návštevníkom kúpeľov. Sledovaním budete vedieť o misiách a slávnostiach farnosti. '.self::CLOSING,
        ]],
        1778 => ['name' => 'Evanjelická materská škola', 'body' => [
            'Evanjelická materská škola vo Švábovciach je cirkevná škôlka evanjelického zboru. Vychováva deti v duchu kresťanských hodnôt. Spolupracuje s rodinami a cirkevným zborom.',
            'Pripravuje slávnosti pre deti, rodičov a zbor, napríklad slávnosť s výročím škôlky. Slávnosti spájajú služby Božie a program detí.',
            'Kanál je určený rodičom a deťom zo Šváboviec a okolia. Sledovaním budete vedieť o slávnostiach škôlky. '.self::CLOSING,
        ]],
        1776 => ['name' => 'Karmelitánska rodina', 'body' => [
            'Karmelitánska rodina na Slovensku spája bosých karmelitánov, mníšky karmelitánky a Svetský rád bosých karmelitánov. Žije spiritualitou sv. Terézie od Ježiša a sv. Jána z Kríža. Jej web je bosikarmelitani.sk.',
            'Pripravuje novény a slávnosti karmelitánskych svätcov, napríklad novénu k sv. Terézii Avilskej, ktorú sa modlí s klauzúrnymi karmelitánkami. Ponúka aj duchovné obnovy a prijímanie škapuliara.',
            'Kanál je určený všetkým, ktorým je blízka karmelitánska spiritualita. Sledovaním budete vedieť o novénach a slávnostiach. '.self::CLOSING,
        ]],
        1774 => ['name' => 'Úrad pre kultúru a univerzity rímskeho vikariátu', 'body' => [
            'Úrad pre kultúru a univerzity Rímskeho vikariátu sa stará o vzťah Cirkvi ku kultúre a univerzitám v Ríme. Rímsky vikariát spravuje Rímsku diecézu v mene pápeža.',
            'Pripravuje kultúrne iniciatívy, napríklad cyklus k 500. výročiu smrti maliara Raffaela Santiho Raffaello, Rím a ideál renesancie. Podujatia sú určené študentom aj verejnosti.',
            'Kanál je určený milovníkom umenia a kultúry. Sledovaním budete vedieť o ďalších iniciatívach. '.self::CLOSING,
        ]],
        1773 => ['name' => 'Vodkyne a skauti Európy', 'body' => [
            'Vodkyne a skauti Európy sú katolícke skautské hnutie, súčasť Únie medzinárodného skautingu Európy. Vychovávajú chlapcov a dievčatá v oddelených oddieloch. Spájajú skauting s duchovnou formáciou.',
            'Pripravujú tábory, výpravy a sväté omše pre skautov a ich rodiny, aj online. Stretávajú sa v oddieloch vo viacerých mestách.',
            'Kanál je určený deťom, mladým a rodičom, ktorých zaujíma katolícky skauting. Sledovaním budete vedieť o podujatiach hnutia. '.self::CLOSING,
        ]],
        1770 => ['name' => 'Farnosť Ružomberok', 'body' => [
            'Farnosť Ružomberok patrí do Spišskej diecézy. Farským kostolom je Kostol sv. Ondreja na Námestí Andreja Hlinku. Andrej Hlinka bol ružomberským farárom.',
            'Pripravuje modlitbové iniciatívy, napríklad modlitbovú reťaz posvätného ruženca, do ktorej sa môžu zapojiť aj veriaci iných farností. Organizuje aj misie a farské slávnosti.',
            'Kanál je určený veriacim z Ružomberka. Sledovaním budete vedieť o podujatiach farnosti. '.self::CLOSING,
        ]],
        1769 => ['name' => 'Kongregácia bratov tešiteľov a sestier tešiteliek', 'body' => [
            'Kongregácia bratov tešiteľov z Getsemani je rehoľa, ktorej poslaním je tešiť Krista v Getsemanskej záhrade a utešovať trpiacich. Na Slovensku pôsobia bratia aj sestry tešiteľky. Spravujú aj pútnické miesto Marianka.',
            'Pripravujú modlitbové iniciatívy, napríklad výzvu stať sa „anjelom útechy" modlitbou za chorých. Ponúkajú aj duchovné obnovy a sprevádzanie.',
            'Kanál je určený veriacim, ktorí chcú podporiť trpiacich modlitbou. Sledovaním budete vedieť o iniciatívach tešiteľov. '.self::CLOSING,
        ]],
        1765 => ['name' => 'farnosti a rehole v Prešove', 'body' => [
            'Farnosti a rehole v Prešove spoločne pripravujú programy pre mladých v meste. Zapájajú sa rímskokatolícke farnosti aj rehoľné komunity. Jedným z partnerov je farnosť Krista Kráľa.',
            'Pripravili napríklad Týždeň Cirkvi pre mládež, sériu stretnutí, modlitieb a programov v prešovských kostoloch. Programy sú otvorené pre všetkých mladých.',
            'Kanál je určený mladým z Prešova. Sledovaním budete vedieť o spoločných podujatiach. '.self::CLOSING,
        ]],
        1763 => ['name' => 'Miništranti z Považskej Bystrice', 'body' => [
            'Miništranti z Považskej Bystrice sú chlapci, ktorí slúžia pri oltári vo farnosti. Stretávajú sa aj mimo bohoslužieb. Spoločenstvo vedú kňazi farnosti.',
            'Pripravujú výzvy a aktivity pre deti, napríklad adventnú výzvu na duchovnú prípravu na Vianoce. Organizujú aj miništrantské stretnutia.',
            'Kanál je určený miništrantom a ich rodičom. Sledovaním budete vedieť o nových výzvach. '.self::CLOSING,
        ]],
        1762 => ['name' => 'Organizátori 52. medzinárodného eucharistického kongresu', 'body' => [
            '52. medzinárodný eucharistický kongres sa konal v septembri 2021 v Budapešti. Na jeho záver slúžil svätú omšu pápež František. Pripravovala ho Ostrihomsko-budapeštianska arcidiecéza.',
            'Organizátori pripravovali celosvetové adorácie a modlitby, do ktorých sa mohli zapojiť aj veriaci zo Slovenska. Kongres sprevádzal kríž kongresu, ktorý putoval aj po slovenských farnostiach.',
            'Kanál je určený veriacim, ktorých zaujímajú eucharistické kongresy. Sledovaním budete vedieť o podobných podujatiach. '.self::CLOSING,
        ]],
        1761 => ['name' => 'Združenie exallievov dona Bosca', 'body' => [
            'Združenie exallievov dona Bosca spája bývalých odchovancov saleziánov. Pokračuje v duchu sv. Jána Bosca aj v dospelosti. Pôsobí na celom Slovensku.',
            'Každý rok organizuje dobročinný projekt Darujme úsmev, zbierku pre deti a rodiny v núdzi. Zapája sa aj do saleziánskych slávností.',
            'Kanál je určený bývalým odchovancom saleziánov a podporovateľom. Sledovaním budete vedieť o zbierkach a stretnutiach. '.self::CLOSING,
        ]],
        1759 => ['name' => 'Univerzita Konštantína Filozofa v Nitre', 'body' => [
            'Univerzita Konštantína Filozofa v Nitre nesie meno sv. Konštantína-Cyrila. Je jednou z veľkých slovenských univerzít. Jej Filozofická fakulta má Katedru náboženských štúdií.',
            'Katedra pripravuje dni otvorených dverí a ponúka štúdium náboženskej výchovy. Univerzita organizuje aj konferencie a prednášky pre verejnosť.',
            'Kanál je určený uchádzačom o štúdium a odbornej verejnosti. Sledovaním budete vedieť o prijímaní a podujatiach. '.self::CLOSING,
        ]],
        1757 => ['name' => 'Rada KBS Iustitia et Pax', 'body' => [
            'Rada Iustitia et Pax je poradný orgán Konferencie biskupov Slovenska pre spravodlivosť a pokoj. Venuje sa sociálnej náuke Cirkvi. Upozorňuje na spoločenské témy.',
            'Pripravuje diskusie a online večery, napríklad večer Nádej v čase korony s hnutím eRko a Fórom kresťanských inštitúcií. Vydáva aj stanoviská k spoločenským otázkam.',
            'Kanál je určený všetkým, ktorých zaujíma sociálna náuka Cirkvi. Sledovaním budete vedieť o diskusiách rady. '.self::CLOSING,
        ]],
        1754 => ['name' => 'Pápežská rada na podporu jednoty kresťanov', 'body' => [
            'Pápežská rada na podporu jednoty kresťanov bola úradom Svätej stolice pre ekumenizmus. Od roku 2022 pokračuje ako Dikastérium na podporu jednoty kresťanov. Spolupracuje so Svetovou radou cirkví.',
            'Spolu so Svetovou radou cirkví pripravuje každoročne materiály na Týždeň modlitieb za jednotu kresťanov, ktorý sa slávi aj na Slovensku. Texty pripravujú vždy kresťania z inej krajiny.',
            'Kanál je určený všetkým, ktorým záleží na jednote kresťanov. Sledovaním budete vedieť o Týždni modlitieb. '.self::CLOSING,
        ]],
        1753 => ['name' => 'Spoločenstvo Efata', 'body' => [
            'Spoločenstvo Efata je kresťanské spoločenstvo z Považskej Bystrice. Venuje sa najmä ženám. Spája modlitbu, vzťahy a starostlivosť o seba.',
            'Každý rok pripravuje pôstnu výzvu pre ženy Exodus, deväťdesiatdňovú výzvu Naplň svoje telo, nakŕm svoju dušu. Výzva prebieha aj online.',
            'Kanál je určený ženám, ktoré hľadajú povzbudenie vo viere. Sledovaním budete vedieť o ďalších výzvach. '.self::CLOSING,
        ]],
        1750 => ['name' => 'Neformálne združenie cirkví pôsobiacich v Žiline Ekuza', 'body' => [
            'Ekuza je neformálne združenie cirkví pôsobiacich v Žiline. Spája katolíkov, evanjelikov a ďalšie cirkvi v meste. Podporuje ich spoluprácu.',
            'Pripravuje ekumenické bohoslužby, napríklad počas Týždňa modlitieb za jednotu kresťanov, aj online. Bohoslužby sa striedajú v chrámoch rôznych cirkví.',
            'Kanál je určený veriacim zo Žiliny. Sledovaním budete vedieť o ekumenických bohoslužbách. '.self::CLOSING,
        ]],
        1749 => ['name' => 'Generalát Spoločnosti sociálnych sestier', 'body' => [
            'Spoločnosť sociálnych sestier je rehoľné spoločenstvo, ktoré vzniklo v Maďarsku v 20. storočí. Sestry sa venujú sociálnej službe a pomoci ženám a rodinám. Generalát riadi spoločnosť.',
            'Pripravuje medzinárodné konferencie o sociálnej náuke Cirkvi, napríklad online konferenciu Ľudská dôstojnosť v 21. storočí. Konferencie sú otvorené aj verejnosti.',
            'Kanál je určený všetkým, ktorých zaujíma sociálna služba a náuka Cirkvi. Sledovaním budete vedieť o konferenciách. '.self::CLOSING,
        ]],
        1748 => ['name' => 'Evanjelická spojená škola', 'body' => [
            'Evanjelická spojená škola v Martine zahŕňa evanjelické gymnázium a ďalšie zložky. Zriaďuje ju Evanjelická cirkev a. v. na Slovensku. Spája vzdelanie s kresťanskými hodnotami.',
            'Pripravuje dni otvorených dverí, aj online, pre budúcich študentov a rodičov. Organizuje aj školské slávnosti a služby Božie.',
            'Kanál je určený uchádzačom a ich rodičom. Sledovaním budete vedieť o dňoch otvorených dverí. '.self::CLOSING,
        ]],
        1745 => ['name' => 'Organizačný tím Púte zaľúbených', 'body' => [
            'Organizačný tím Púte zaľúbených pripravuje každoročnú púť pre zaľúbené páry v Národnej svätyni v Šaštíne. Púť sa koná okolo sviatku sv. Valentína. Jej web je zalubeni.sk.',
            'Program spája svätú omšu, požehnanie párov, prednášky a stretnutia. Niektoré ročníky prebiehali online cez YouTube kanál baziliky.',
            'Kanál je určený zaľúbeným, snúbencom a mladým manželom. Sledovaním budete vedieť o ďalšom ročníku púte. '.self::CLOSING,
        ]],
        1744 => ['name' => 'Univerzitné pastoračné centrum v Trnave (UPeCe TeTe)', 'body' => [
            'Univerzitné pastoračné centrum v Trnave (UPeCe TeTe) sa stará o vysokoškolákov trnavských univerzít. Ponúka sväté omše, stretká a rozhovory. Pôsobí pri trnavských kostoloch.',
            'Pripravuje duchovné cvičenia v pôste, aj online, a ďalšie formačné programy pre študentov.',
            'Kanál je určený študentom v Trnave. Sledovaním budete vedieť o programe centra. '.self::CLOSING,
        ]],
        1743 => ['name' => 'Gymnázium svätej Uršule', 'body' => [
            'Gymnázium svätej Uršule je súčasťou Spojenej školy svätej Uršule v Bratislave. Zriaďujú ho sestry uršulínky. Nadväzuje na dlhú tradíciu uršulínskeho vzdelávania.',
            'Pripravuje dni otvorených dverí pre žiakov 5. a 9. ročníka a ich rodičov, aj online. Organizuje aj školské slávnosti.',
            'Kanál je určený uchádzačom a rodičom. Sledovaním budete vedieť o dňoch otvorených dverí. '.self::CLOSING,
        ]],
        1740 => ['name' => 'Diecézny školský úrad v Žiline', 'body' => [
            'Diecézny školský úrad v Žiline spravuje katolícke školy Žilinskej diecézy. Podporuje ich riaditeľov a učiteľov. Jeho web je dsuza.sk.',
            'Informuje o prijímaní na stredné katolícke školy a pripravuje podujatia pre školy. Spolupracuje s diecéznym katechetickým úradom.',
            'Kanál je určený žiakom, rodičom a učiteľom katolíckych škôl. Sledovaním budete vedieť o prijímaní a podujatiach. '.self::CLOSING,
        ]],
        1737 => ['name' => 'Združenie apoštolov Božieho milosrdenstva Faustínum', 'body' => [
            'Združenie apoštolov Božieho milosrdenstva Faustínum šíri posolstvo Božieho milosrdenstva podľa sv. Faustíny Kowalskej. Hlavné sídlo má v Krakove-Łagiewnikoch. Má členov aj na Slovensku.',
            'Pripravuje slávnosti a ďakovné omše, napríklad pri 25. výročí založenia v Bazilike Božieho milosrdenstva v Krakove. Členovia sa stretávajú aj v slovenských farnostiach.',
            'Kanál je určený ctiteľom Božieho milosrdenstva. Sledovaním budete vedieť o slávnostiach združenia. '.self::CLOSING,
        ]],
        1731 => ['name' => 'Spolu pre Etiópiu', 'body' => [
            'Spolu pre Etiópiu je spoločná iniciatíva slovenských mimovládnych organizácií. Vznikla ako reakcia na humanitárnu krízu v etiópskom regióne Tigraj. Jej web je spolupreetiopiu.sk.',
            'Vyzýva na pomoc obetiam vojny a zbiera prostriedky na humanitárne projekty. Informuje o situácii v Etiópii.',
            'Kanál je určený všetkým, ktorí chcú pomôcť. Sledovaním budete vedieť o zbierkach a výzvach. '.self::CLOSING,
        ]],
        1729 => ['name' => 'Inštitút dcér Márie Pomocnice kresťanov', 'body' => [
            'Inštitút dcér Márie Pomocnice, saleziánky, založili sv. Ján Bosco a sv. Mária Dominika Mazzarellová. Venujú sa výchove dievčat a mladých. Na Slovensku pôsobia vo viacerých mestách.',
            'Pripravujú modlitbové iniciatívy, napríklad celosvetový ruženec za Mjanmarsko, aj programy pre dievčatá. Organizujú tábory a stretká.',
            'Kanál je určený dievčatám, mladým a rodinám. Sledovaním budete vedieť o podujatiach saleziánok. '.self::CLOSING,
        ]],
        1723 => ['name' => 'Turčianska galéria', 'body' => [
            'Turčianska galéria v Martine je regionálna galéria Žilinského samosprávneho kraja. Spravuje zbierky výtvarného umenia. Pripravuje výstavy, prednášky a tvorivé dielne.',
            'Pripravila napríklad výstavu Ladislav Záborský – Nebo ako závažná realita k storočnici narodenia maliara. Výstavy sprevádzajú komentované prehliadky.',
            'Kanál je určený milovníkom výtvarného umenia. Sledovaním budete vedieť o nových výstavách. '.self::CLOSING,
        ]],
        1722 => ['name' => 'cirkevný zbor Liptovský Mikuláš-Palúdzka', 'body' => [
            'Cirkevný zbor ECAV Liptovský Mikuláš-Palúdzka je evanjelický zbor v mestskej časti Palúdzka. Patrí do Liptovsko-oravského seniorátu. Má aktívne Spoločenstvo evanjelických žien.',
            'Pripravuje biblické hodiny Spoločenstva evanjelických žien, aj online cez Zoom, o ženách z biblických príbehov. Hodiny sú otvorené ženám aj z iných zborov.',
            'Kanál je určený ženám a evanjelikom z Liptova. Sledovaním budete vedieť o biblických hodinách. '.self::CLOSING,
        ]],
        1720 => ['name' => 'Maltézsky rád', 'body' => [
            'Zvrchovaný vojenský špitálsky rád sv. Jána, nazývaný Maltézsky rád, je katolícka rehoľa s dlhou históriou. Venuje sa službe chorým a chudobným. Na Slovensku pôsobí aj v Nitre.',
            'Členovia sa zapájajú do modlitbových iniciatív, napríklad do maratónu májových modlitieb za skončenie pandémie. Rád podporuje charitatívne projekty.',
            'Kanál je určený veriacim, ktorých zaujíma služba Maltézskeho rádu. Sledovaním budete vedieť o jeho podujatiach. '.self::CLOSING,
        ]],
        1719 => ['name' => 'Nitriansky komorný orchester ZOE', 'body' => [
            'Nitriansky komorný orchester ZOE je hudobné teleso z Nitry. Jeho umeleckým vedúcim je Juraj Tomka. Hrá duchovnú aj svetskú hudbu.',
            'Pripravuje koncerty, napríklad koncert venovaný svetskej hudbe či premiérové koncerty slovenskej sakrálnej tvorby so zborom Chorus Salvatoris. Koncertuje v kostoloch aj sálach.',
            'Kanál je určený milovníkom klasickej hudby. Sledovaním budete vedieť o koncertoch orchestra. '.self::CLOSING,
        ]],
        1718 => ['name' => 'Saleziánky z projektu GPS', 'body' => [
            'Projekt GPS pripravujú saleziánky pre dievčatá, ktoré hľadajú svoje povolanie. Ponúka sprevádzanie, stretnutia a púte. Pôsobí na celom Slovensku.',
            'Pripravuje Púť za svoje povolanie a projekt Kompas FMA, ktorý ponúka rozhovor každému, kto sa potrebuje s niekým porozprávať.',
            'Kanál je určený dievčatám a mladým ženám. Sledovaním budete vedieť o pútiach a stretnutiach. '.self::CLOSING,
        ]],
        1715 => ['name' => 'CZ ECAV Košice- Terasa', 'body' => [
            'Cirkevný zbor ECAV Košice-Terasa je evanjelický zbor na sídlisku Terasa v Košiciach. Bohoslužby slávi v Evanjelickom božom chráme na Toryskej ulici. Slúži evanjelikom západných Košíc.',
            'Zapája sa do Noci kostolov a ekumenických slávností, napríklad ekumenickej ďakovnej bohoslužby za úrodu. Pripravuje aj programy pre deti a mládež.',
            'Kanál je určený evanjelikom z Košíc. Sledovaním budete vedieť o službách Božích a podujatiach zboru. '.self::CLOSING,
        ]],
        1712 => ['name' => 'Farnosť Báč', 'body' => [
            'Farnosť Báč leží na Žitnom ostrove v okrese Dunajská Streda. Slúži veriacim obce a okolia.',
            'Pripravuje mariánske slávnosti, napríklad slávnosť Panny Márie s krížom eucharistického kongresu, ktorý putoval farnosťami. Slávnosti spájajú svätú omšu a procesiu.',
            'Kanál je určený veriacim z Báču a okolia. Sledovaním budete vedieť o slávnostiach farnosti. '.self::CLOSING,
        ]],
        1710 => ['name' => 'farnosť Veselé', 'body' => [
            'Farnosť Veselé pri Piešťanoch patrí do Trnavskej arcidiecézy. K farnosti patrí aj filiálka Rakovice. Jej web je fara-vesele.sk.',
            'Pripravuje spomienky na biskupa Petra Dubovského SJ, ktorý pochádzal z Rakovíc, napríklad deviatnik vďačnosti k storočnici jeho narodenia. Slávnosti spájajú sväté omše a modlitby.',
            'Kanál je určený veriacim z Veselého a Rakovíc. Sledovaním budete vedieť o slávnostiach farnosti. '.self::CLOSING,
        ]],
        1705 => ['name' => 'štátny archív v Trnave pracoviska Archív Skalica', 'body' => [
            'Archív Skalica je pracovisko Štátneho archívu v Trnave. Uchováva písomnosti z okresov Skalica a Senica. Sprístupňuje ich bádateľom a verejnosti.',
            'Pripravuje výstavy archívnych dokumentov, napríklad výstavu o histórii zdravotníctva na Záhorí. Výstavy sú prístupné počas úradných hodín.',
            'Kanál je určený bádateľom a milovníkom histórie Záhoria. Sledovaním budete vedieť o výstavách archívu. '.self::CLOSING,
        ]],
        1704 => ['name' => 'cirkevný zbor ECAV v Sobotišti', 'body' => [
            'Cirkevný zbor ECAV v Sobotišti je evanjelický zbor v Sobotišti na Záhorí. Zbor má tradíciu spomienkových služieb Božích na hrade Branč. Slúži evanjelikom obce.',
            'Pripravuje spomienkové služby Božie na Branči, ktoré sa konajú každoročne začiatkom júla. Spomienky pripomínajú národné dejiny.',
            'Kanál je určený evanjelikom zo Sobotišťa a okolia. Sledovaním budete vedieť o spomienkových slávnostiach. '.self::CLOSING,
        ]],
        1703 => ['name' => 'Peter Broz - koordinátor pre západ', 'body' => [
            'Peter Broz koordinuje účastníkov zo západného Slovenska na pešej púti Muráň – Levoča. Púť mladých má tradíciu od 90. rokov. Jej web je muranlevoca.sk.',
            'Púť vedie začiatkom júla niekoľko dní pešo z Muráňa na Mariánsku horu v Levoči. Pútnici putujú v skupinách s kňazmi.',
            'Kanál je určený mladým pútnikom. Sledovaním budete vedieť o termíne a prihlasovaní na púť. '.self::CLOSING,
        ]],
        1702 => ['name' => 'Nadácia POMOC Nitrianskeho samosprávneho kraja', 'body' => [
            'Nadácia POMOC Nitrianskeho samosprávneho kraja podporuje sociálne a kultúrne projekty v kraji. Pomáha ľuďom v núdzi.',
            'S Nitrianskym biskupstvom pripravuje benefičné koncerty, napríklad koncert Speváckeho zboru slovenských učiteľov v Bazilike sv. Emeráma počas Cyrilo-metodských slávností.',
            'Kanál je určený obyvateľom Nitrianskeho kraja. Sledovaním budete vedieť o benefičných podujatiach. '.self::CLOSING,
        ]],
        1700 => ['name' => 'Rímskokatolícky farský úrad v Bratislave – Devíne', 'fix_website' => 'https://devin.fara.sk', 'body' => [
            'Farnosť Bratislava-Devín patrí do Bratislavskej arcidiecézy. Farským kostolom je Kostol sv. Kríža pod Devínskym hradom. Devín je spojený s veľkomoravskou históriou.',
            'Pripravuje slávnosti sv. Cyrila a Metoda a ďalšie cyrilo-metodské spomienky. Spolupracuje so združením Dedičstvo otcov.',
            'Kanál je určený veriacim z Devína. Sledovaním budete vedieť o slávnostiach farnosti. '.self::CLOSING,
        ]],
        1699 => ['name' => 'Pápežská akademia za život', 'body' => [
            'Pápežská akadémia za život je inštitúcia Svätej stolice, ktorú založil pápež Ján Pavol II. v roku 1994. Venuje sa bioetike a ochrane ľudského života. Spája odborníkov z celého sveta.',
            'Pripravuje konferencie a okrúhle stoly, napríklad online okrúhly stôl o očkovaní so Svetovou lekárskou asociáciou. Vydáva aj stanoviská k etickým otázkam.',
            'Kanál je určený lekárom, etikom a všetkým, ktorých zaujíma bioetika. Sledovaním budete vedieť o podujatiach akadémie. '.self::CLOSING,
        ]],
        1695 => ['name' => 'Ponitrianske múzeum v Nitre', 'body' => [
            'Ponitrianske múzeum v Nitre je regionálne múzeum Nitrianskeho samosprávneho kraja. Spravuje archeologické, historické a etnografické zbierky Ponitria.',
            'Pripravilo napríklad výstavu Poklady z Bojnej – dedičstvo slovenských kresťanov o veľkomoravskom hradisku v Bojnej. Výstavy sprevádzajú prednášky.',
            'Kanál je určený milovníkom histórie. Sledovaním budete vedieť o nových výstavách. '.self::CLOSING,
        ]],
        1693 => ['name' => 'Rada mladez a univerzity', 'body' => [
            'Rada pre mládež a univerzity je orgán Konferencie biskupov Slovenska. Koordinuje pastoráciu mládeže a vysokoškolákov. Jej web je mladez.kbs.sk.',
            'Pripravuje celoslovenské iniciatívy a súťaže pre mladých, napríklad výzvu Nebuď OFF, poď do OF. Spolupracuje s diecéznymi centrami mládeže.',
            'Kanál je určený mladým a animátorom. Sledovaním budete vedieť o celoslovenských podujatiach. '.self::CLOSING,
        ]],
        1691 => ['name' => 'Farnosť Nevoľné', 'body' => [
            'Farnosť Nevoľné leží pri Kremnici a patrí do Banskobystrickej diecézy. Obec je rodiskom biskupa Rudolfa Baláža. Farnosť si jeho pamiatku pripomína.',
            'Pripravuje spomienky na biskupa Baláža, napríklad pri desiatom výročí jeho úmrtia. Spomienky spájajú svätú omšu a modlitbu pri pamätníku.',
            'Kanál je určený veriacim z Nevoľného a ctiteľom biskupa Baláža. Sledovaním budete vedieť o spomienkach. '.self::CLOSING,
        ]],
        1690 => ['name' => 'Dikastérium pre laikov, rodinu a život', 'body' => [
            'Dikastérium pre laikov, rodinu a život je úrad Svätej stolice, ktorý zriadil pápež František v roku 2016. Venuje sa laikom, rodinám, mladým a starším. Organizuje aj Svetové dni mládeže.',
            'Vyhlasuje svetové dni, napríklad Svetový deň starých rodičov a seniorov, a pripravuje Svetové dni mládeže. Priame prenosy zo SDM prinášala aj TV LUX.',
            'Kanál je určený rodinám, mladým a seniorom. Sledovaním budete vedieť o svetových iniciatívach. '.self::CLOSING,
        ]],
        1689 => ['name' => 'Farnosť Nálepkovo', 'body' => [
            'Farnosť Nálepkovo leží v okrese Gelnica. Pri obci na úpätí Čiernej hory stojí kaplnka sv. Anny. Farnosť sa o ňu stará.',
            'Každé leto pripravuje odpustovú slávnosť ku cti sv. Joachima a Anny pri kaplnke sv. Anny. Slávnosť spája svätú omšu a stretnutie pútnikov.',
            'Kanál je určený veriacim z Nálepkova a okolia. Sledovaním budete vedieť o odpustových slávnostiach. '.self::CLOSING,
        ]],
        1688 => ['name' => 'cirkevný zbor Koceľovce', 'body' => [
            'Cirkevný zbor ECAV Koceľovce je evanjelický zbor na Gemeri. V obci stojí gotický kostol so vzácnymi nástennými maľbami. Patrí k zastaveniam Gotickej cesty.',
            'Pripravuje biblické hodiny Spoločenstva evanjelických žien, aj online, napríklad o biblickej postave Rachab.',
            'Kanál je určený evanjelikom a ženám z Gemera. Sledovaním budete vedieť o biblických hodinách. '.self::CLOSING,
        ]],
        1686 => ['name' => 'Rehoľa kazateľov - dominikánov na Slovensku', 'body' => [
            'Rehoľa kazateľov, dominikáni, založil sv. Dominik v 13. storočí. Na Slovensku pôsobia v Košiciach, Bratislave, Zvolene a Šaštíne. Venujú sa kázaniu a vzdelávaniu.',
            'Každý rok v auguste slávia výročnú slávnosť sv. otca Dominika, napríklad s košickým arcibiskupom. Pripravujú aj akadémie, obnovy a púte.',
            'Kanál je určený priateľom dominikánov. Sledovaním budete vedieť o slávnostiach rehole. '.self::CLOSING,
        ]],
        1683 => ['name' => 'Organizátori apoštolskej cesty pápeža Františka na Slovensku', 'body' => [
            'Organizátori apoštolskej cesty pápeža Františka pripravovali jeho návštevu Slovenska v septembri 2021. Pápež navštívil Bratislavu, Prešov, Košice a Šaštín. Informácie zverejňovali na navstevapapeza.sk.',
            'Organizovali registráciu pútnikov a dobrovoľníkov a zverejňovali program návštevy. Na stretnutiach s pápežom sa zúčastnili desaťtisíce ľudí.',
            'Kanál je určený všetkým, ktorí si pripomínajú pápežovu návštevu. Sledovaním budete vedieť o súvisiacich podujatiach. '.self::CLOSING,
        ]],
        1681 => ['name' => 'Silvia Fecsková', 'body' => [
            'Silvia Fecsková informuje o koncertoch duchovnej a organovej hudby. Venuje sa najmä organovým recitálom. Spolupracuje s hudobnými festivalmi.',
            'Ohlasuje napríklad organové recitály v Bazilike minor sv. Egídia v Bardejove či koncerty v Cíferi. Na recitáloch vystupujú slovenskí aj zahraniční organisti.',
            'Kanál je určený milovníkom organovej hudby. Sledovaním budete vedieť o recitáloch. '.self::CLOSING,
        ]],
        1679 => ['name' => 'Prípravný tím KBS pre návštevu Svätého Otca Františka na Slovensku', 'body' => [
            'Prípravný tím Konferencie biskupov Slovenska pripravoval návštevu pápeža Františka na Slovensku v septembri 2021. Koordinoval program, bezpečnosť aj komunikáciu.',
            'Pripravoval brífingy pre médiá, napríklad na nádvorí Arcibiskupského paláca v Bratislave, a informoval verejnosť o programe návštevy.',
            'Kanál je určený všetkým, ktorí si pripomínajú pápežovu návštevu. Sledovaním budete vedieť o súvisiacich podujatiach. '.self::CLOSING,
        ]],
        1678 => ['name' => 'Chorus Salvatoris', 'body' => [
            'Chorus Salvatoris je bratislavský spevácky zbor, ktorý spieva v jezuitskom Kostole Najsvätejšieho Spasiteľa. Venuje sa duchovnej hudbe. Jeho web je chorus-salvatoris.com.',
            'Spieva pri liturgiách a koncertuje, napríklad na premiérovom koncerte slovenskej sakrálnej tvorby s orchestrom ZOE a súborom Harmony.',
            'Kanál je určený milovníkom zborového spevu. Sledovaním budete vedieť o koncertoch zboru. '.self::CLOSING,
        ]],
        1677 => ['name' => 'Farnosť sv.Jakuba', 'body' => [
            'Farnosť sv. Jakuba v Zákamennom na Orave patrí do Spišskej diecézy. Zákamenné je rodiskom biskupa Jána Vojtaššáka. Farnosť si jeho pamiatku pripomína.',
            'Pripravuje pamätné dni výročia smrti Božieho sluhu biskupa Jána Vojtaššáka a púte, napríklad do Medžugoria.',
            'Kanál je určený veriacim zo Zákamenného a Oravy. Sledovaním budete vedieť o slávnostiach a pútiach. '.self::CLOSING,
        ]],
        1676 => ['name' => 'Crescendo Slovensko', 'body' => [
            'Crescendo je medzinárodná kresťanská sieť hudobníkov a umelcov. Na Slovensku spája profesionálov aj amatérov, ktorí chcú žiť vieru v umení.',
            'Spolupodieľa sa na celoslovenskom Dni D – dni chvál, keď sa v jeden deň a hodinu chváli na mnohých miestach Slovenska, napríklad v kaplnke Bratislavského hradu.',
            'Kanál je určený hudobníkom a umelcom. Sledovaním budete vedieť o podujatiach siete. '.self::CLOSING,
        ]],
        1672 => ['name' => 'AETÓS združenie gréckokatolíckej mládeže Košickej eparchie', 'body' => [
            'AETÓS je združenie gréckokatolíckej mládeže Košickej eparchie. Názov znamená po grécky orol. Spolupracuje s Radou pre mládež eparchie.',
            'Pripravuje púte a stretnutia, napríklad Púť synov a otcov či Kvetný víkend. Organizuje aj tábory a formačné stretnutia.',
            'Kanál je určený mladým gréckokatolíkom. Sledovaním budete vedieť o pútiach a stretnutiach. '.self::CLOSING,
        ]],
        1668 => ['name' => 'školské sestry de Notre Dame', 'body' => [
            'Školské sestry de Notre Dame sú rehoľa, ktorá sa venuje výchove a vzdelávaniu. Na Slovensku majú komunitu aj v Beckove. V roku 1950 ich postihla Akcia R, likvidácia ženských reholí.',
            'Pripravujú spomienkové slávnosti, napríklad spomienku na Akciu R v Beckove. Slávnosti spájajú svätú omšu a svedectvá.',
            'Kanál je určený priateľom sestier a všetkým, ktorí si pripomínajú prenasledovanie Cirkvi. Sledovaním budete vedieť o spomienkach. '.self::CLOSING,
        ]],
        1667 => ['name' => 'Fakulta humanitných vied Žilinskej univerzity', 'body' => [
            'Fakulta humanitných vied Žilinskej univerzity ponúka štúdium humanitných a spoločenských vied. Pri fakulte pôsobí Spoločnosť pre kresťansky orientovanú filozofiu.',
            'Spoločnosť pripravuje konferencie, napríklad 25. konferenciu Zlo a smrť v Saleziánskom dome v Žiline. Konferencie sú otvorené odborníkom aj študentom.',
            'Kanál je určený študentom a záujemcom o filozofiu. Sledovaním budete vedieť o konferenciách. '.self::CLOSING,
        ]],
        1666 => ['name' => 'Duchovné centrum Majak', 'body' => [
            'Duchovné centrum Maják sa nachádza v Španej Doline pri Banskej Bystrici. Ponúka duchovné obnovy, kurzy a ubytovanie. Je miestom stretnutí a formácie.',
            'Pripravuje Školu osobnej formácie, celoročný kurz osobnostného a duchovného rastu. Konajú sa tu aj víkendové obnovy.',
            'Kanál je určený mladým a dospelým, ktorí hľadajú formáciu. Sledovaním budete vedieť o kurzoch a obnovách. '.self::CLOSING,
        ]],
        1665 => ['name' => 'Farnosť Jablonica', 'body' => [
            'Farnosť Jablonica leží na Záhorí a patrí do Trnavskej arcidiecézy. Je známa mariánskou úctou.',
            'Pripravuje Jablonické mariánske trojdnie, ktoré sa konalo aj ako príprava na návštevu pápeža Františka. Trojdnie spája sväté omše, modlitbu a prednášky.',
            'Kanál je určený veriacim z Jablonice a okolia. Sledovaním budete vedieť o slávnostiach farnosti. '.self::CLOSING,
        ]],
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

            if (isset($profile['fix_website']) && $canal->website !== $profile['fix_website']) {
                $changes['website'] = $profile['fix_website'];
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
