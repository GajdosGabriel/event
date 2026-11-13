<?php

use App\Models\Canal;
use App\Models\Municipality;
use App\Services\SystemLog\Recorder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Štvrtá dávka (november 2026): ďalších 60 importovaných kanálov v poradí
 * (#1572 – #1664). Štyridsaťpäť z nich malo všeobecný AI popis alebo len
 * „organizátor podujatí" — dostanú tri odseky podľa podujatí kanála a verejne
 * známych faktov. Ostatné už trojodsekový popis majú.
 *
 * Opravy obce:
 *  - #1632 Farnosť Trnavá Hora bola v Trnave, #1598 farnosť Ladice v Kostoľanoch
 *    pod Tribečom, #1609 mníchovský inštitút v obci Zlaté.
 *
 * Kanál musí sedieť ID aj názvom; prevzatý, zlúčený či zmazaný sa preskočí.
 */
return new class extends Migration
{
    private const CLOSING = 'Podrobnosti nájdete vždy v detaile podujatia.';

    /** @var array<int, array<string, mixed>> */
    private const PROFILES = [
        1664 => ['name' => 'Gréckokatolícke arcibiskupstvo Prešov', 'website' => 'https://www.grkatpo.sk', 'phone' => '051/75 62 601',
            'address' => ['street' => 'Hlavná 1', 'postcode' => '080 01', 'municipality_id' => 2822, 'latitude' => 48.993863, 'longitude' => 21.2431405], 'body' => [
                'Gréckokatolícke arcibiskupstvo Prešov je sídlom Prešovskej archieparchie a metropolie Gréckokatolíckej cirkvi na Slovensku. Sídli na Hlavnej ulici v Prešove pri katedrále sv. Jána Krstiteľa. Na jeho čele stojí prešovský arcibiskup metropolita.',
                'Pripravuje archieparchiálne slávnosti, púte a tlačové konferencie, napríklad k návšteve pápeža Františka v Prešove v roku 2021 spolu s mestom a krajom.',
                'Kanál je určený gréckokatolíkom a médiám. Sledovaním budete vedieť o slávnostiach archieparchie. '.self::CLOSING,
            ]],
        1663 => ['name' => 'Múzeum Spiša', 'body' => [
            'Múzeum Spiša v Spišskej Novej Vsi je regionálne múzeum Košického samosprávneho kraja. Spravuje zbierky z dejín, prírody a kultúry Spiša.',
            'Pripravuje výstavy, napríklad výstavu Sakrálna výšivka ako kultúrne dedičstvo zo zbierky Alice Tokarčíkovej. Výstavy sprevádzajú prednášky a tvorivé dielne.',
            'Kanál je určený milovníkom histórie a kultúry Spiša. Sledovaním budete vedieť o nových výstavách. '.self::CLOSING,
        ]],
        1659 => ['name' => 'Dunajsko-nitriansky seniorát', 'body' => [
            'Dunajsko-nitriansky seniorát spája evanjelické cirkevné zbory na juhozápade Slovenska. Patrí do Západného dištriktu Evanjelickej cirkvi a. v. Na jeho čele stojí senior.',
            'Pripravuje seniorátne dni, napríklad stretnutie Ty mi dávaš nový dych v Jabloňovciach. Program spája služby Božie, hudbu a spoločenstvo.',
            'Kanál je určený evanjelikom z Ponitria a Podunajska. Sledovaním budete vedieť o podujatiach seniorátu. '.self::CLOSING,
        ]],
        1658 => ['name' => 'CZ ECAV Brezno', 'body' => [
            'Cirkevný zbor ECAV Brezno je evanjelický zbor na Horehroní. Má historický zborový areál v centre mesta. Slúži evanjelikom Brezna a okolia.',
            'Otvára zborový areál verejnosti a pripravuje prehliadky kostola a fary. Organizuje aj služby Božie a stretnutia pre deti a mládež.',
            'Kanál je určený evanjelikom a návštevníkom Brezna. Sledovaním budete vedieť o podujatiach zboru. '.self::CLOSING,
        ]],
        1657 => ['name' => 'Tím GPS', 'body' => [
            'Tím GPS pripravuje formačný kurz pre dievčatá, ktoré hľadajú svoje povolanie. Kurz vedú saleziánky. Trvá jeden školský rok.',
            'Stretnutia sa konajú napríklad v Dolnom Kubíne. Dievčatá sa venujú poznaniu seba a duchovnému životu. Kurz je otvorený pre stredoškoláčky a mladé ženy.',
            'Kanál je určený dievčatám a ich rodičom. Sledovaním budete vedieť o nových kurzoch. '.self::CLOSING,
        ]],
        1654 => ['name' => 'Katedra aplikovanej edukológie', 'body' => [
            'Katedra aplikovanej edukológie pôsobí na Prešovskej univerzite v Prešove. Venuje sa výchove a vzdelávaniu. Spolupracuje s Gréckokatolíckou teologickou fakultou.',
            'Pripravuje vedecké konferencie, napríklad medzinárodnú konferenciu Sacrum a príroda o vzťahu duchovného a prírodného sveta.',
            'Kanál je určený pedagógom, študentom a odbornej verejnosti. Sledovaním budete vedieť o konferenciách. '.self::CLOSING,
        ]],
        1653 => ['name' => 'Katedra žurnalistiky Filizofickej fakulty Katolíckej univerzity v Ružomberku', 'body' => [
            'Katedra žurnalistiky Filozofickej fakulty Katolíckej univerzity v Ružomberku pripravuje novinárov a mediálnych odborníkov. Spája žurnalistiku s kresťanskou etikou.',
            'Pripravuje konferencie a spomienky, napríklad na prof. Tadeusza Zasępu, ktorý bol rektorom univerzity. Organizuje aj súťaže a stretnutia s novinármi.',
            'Kanál je určený študentom a záujemcom o žurnalistiku. Sledovaním budete vedieť o podujatiach katedry. '.self::CLOSING,
        ]],
        1652 => ['name' => 'Monika Koláriková', 'body' => [
            'Monika Koláriková z Trnavy pripravuje kultúrne večery pre mladých. Dáva priestor talentom zo spoločenstiev a farností.',
            'Organizuje Večer talentov, kde mladí predvádzajú hudbu, poéziu a iné umenie. Večer sa konal napríklad na Šrobárovej ulici v Trnave.',
            'Kanál je určený mladým z Trnavy. Sledovaním budete vedieť o ďalších večeroch. '.self::CLOSING,
        ]],
        1650 => ['name' => 'CZ ECAV Tornaľa', 'body' => [
            'Cirkevný zbor ECAV Tornaľa je evanjelický zbor na Gemeri. K zboru patria aj okolité obce, napríklad Kráľ. Slúži evanjelikom mesta a okolia.',
            'Jeho služby Božie vysiela aj rozhlas, napríklad z evanjelického kostola v obci Kráľ. Pripravuje aj stretnutia pre deti a mládež.',
            'Kanál je určený evanjelikom z Gemera. Sledovaním budete vedieť o službách Božích a prenosoch. '.self::CLOSING,
        ]],
        1648 => ['name' => 'Zemplínske múzeum v Michalovciach', 'body' => [
            'Zemplínske múzeum v Michalovciach je regionálne múzeum Košického samosprávneho kraja. Sídli v michalovskom kaštieli. Spravuje zbierky z dejín Zemplína.',
            'Pripravuje výstavy a konferencie, napríklad k Klokočovskej Bohorodičke so Slavistickým ústavom SAV a Košickou eparchiou.',
            'Kanál je určený milovníkom histórie Zemplína. Sledovaním budete vedieť o výstavách. '.self::CLOSING,
        ]],
        1644 => ['name' => 'UPF Slovensko', 'body' => [
            'UPF Slovensko je slovenská pobočka Universal Peace Federation. Podporuje dialóg náboženstiev, rodinu a pokoj. Spája ľudí rôznych vierovyznaní.',
            'Pripravuje konferencie a diskusie, napríklad o náboženskej slobode ako ohrozenom ľudskom práve v Hoteli Devín v Bratislave. Organizuje aj prednáškové večery.',
            'Kanál je určený všetkým, ktorých zaujíma medzináboženský dialóg. Sledovaním budete vedieť o konferenciách. '.self::CLOSING,
        ]],
        1643 => ['name' => 'Ekumenické spoločenstvo cirkví na území mesta Košice', 'body' => [
            'Ekumenické spoločenstvo cirkví na území mesta Košice spája kresťanské cirkvi a náboženské spoločnosti v meste. Podporuje ich spoluprácu.',
            'Každú jeseň pripravuje ekumenickú ďakovnú bohoslužbu za úrodu, v roku 2026 už jej 28. ročník. Bohoslužby sa striedajú v chrámoch rôznych cirkví.',
            'Kanál je určený veriacim z Košíc. Sledovaním budete vedieť o ekumenických bohoslužbách. '.self::CLOSING,
        ]],
        1639 => ['name' => 'Súkromná škôlka s katolíckou výchovou', 'body' => [
            'Súkromná škôlka s katolíckou výchovou je materská škola v Bratislave. Vychováva deti v duchu kresťanských hodnôt. Spolupracuje s rodinami.',
            'Pripravuje dni otvorených dverí pre rodičov a deti. Rodičia sa môžu zoznámiť s učiteľkami a programom škôlky.',
            'Kanál je určený rodičom predškolákov. Sledovaním budete vedieť o dňoch otvorených dverí. '.self::CLOSING,
        ]],
        1634 => ['name' => 'Centrum pre duchovné povolanie', 'body' => [
            'Centrum pre duchovné povolanie pôsobí vo farnosti sv. Mikuláša v Prešove. Sprevádza chlapcov a mladých mužov pri rozlišovaní povolania. Spolupracuje s kňazmi a seminárom.',
            'Pripravuje duchovno-formačné pobyty, napríklad pobyt v Ríme. Pobyty spájajú modlitbu, rozhovory a spoločenstvo.',
            'Kanál je určený mladým mužom, ktorí uvažujú o kňazstve. Sledovaním budete vedieť o pobytoch. '.self::CLOSING,
        ]],
        1633 => ['name' => 'Komisia sociálnych vecí Komisia biskupských konferencií Európskej únie (COMECE)', 'body' => [
            'COMECE je Komisia biskupských konferencií Európskej únie so sídlom v Bruseli. Sleduje politiku EÚ a prináša do nej hlas Katolíckej cirkvi. Jej komisia sociálnych vecí sa venuje solidarite a sociálnej politike.',
            'Pripravuje webináre a stanoviská, napríklad webinár Počúvajte volanie chudobných o dopadoch pandémie. Podujatia prebiehajú najmä online.',
            'Kanál je určený všetkým, ktorých zaujíma sociálna politika Európy. Sledovaním budete vedieť o webinároch. '.self::CLOSING,
        ]],
        1632 => ['name' => 'Farnosť Trnavá Hora', 'municipality_id' => 3597, 'body' => [
            'Farnosť Trnavá Hora leží v okrese Žiar nad Hronom. Slúži veriacim obce a okolia. Patrí do Banskobystrickej diecézy.',
            'Pripravuje misie a duchovné obnovy, aj online, napríklad misie Zaži istotu v neistote. Organizuje aj farské slávnosti.',
            'Kanál je určený veriacim z Trnavej Hory. Sledovaním budete vedieť o podujatiach farnosti. '.self::CLOSING,
        ]],
        1630 => ['name' => 'slovenský gréckokatolícky kňaz Miron Keruľ-Kmec', 'body' => [
            'Miron Keruľ-Kmec je slovenský gréckokatolícky kňaz, ktorý pôsobí v USA. Venuje sa duchovnému životu podľa učenia východných cirkevných otcov. Jeho web je filokalia.sk.',
            'Ponúka online stretnutia Filokalia Live o umení duchovného života. Stretnutia sa vysielajú pre Slovákov doma aj v zahraničí.',
            'Kanál je určený všetkým, ktorí sa zaujímajú o východnú spiritualitu. Sledovaním budete vedieť o stretnutiach. '.self::CLOSING,
        ]],
        1627 => ['name' => 'Manželia Ján a Mária Čopoví', 'body' => [
            'Manželia Ján a Mária Čopoví zo Sniny sa venujú službe manželom. Pôsobia v Košickej eparchii. Sprevádzajú páry na ich ceste.',
            'Organizujú kurz Manželské večery, aj online. Kurz pomáha manželom rozprávať sa o dôležitých témach vzťahu.',
            'Kanál je určený manželom a snúbencom. Sledovaním budete vedieť o ďalších kurzoch. '.self::CLOSING,
        ]],
        1623 => ['name' => 'Rooseveltova nemocnica', 'body' => [
            'Fakultná nemocnica s poliklinikou F. D. Roosevelta v Banskej Bystrici patrí k najväčším nemocniciam stredného Slovenska.',
            'Spolupodieľa sa na svätých omšiach za darcov orgánov, ktoré sa konajú v adventnom čase v Katedrále sv. Martina v Bratislave a vysiela ich TV LUX.',
            'Kanál je určený zdravotníkom, pacientom a rodinám darcov. Sledovaním budete vedieť o súvisiacich podujatiach. '.self::CLOSING,
        ]],
        1621 => ['name' => 'Kúpele Nimnica, a.s', 'body' => [
            'Kúpele Nimnica ležia pri Púchove a Považskej Bystrici. Liečia najmä choroby tráviaceho a pohybového ústrojenstva. Ponúkajú ubytovanie a konferenčné priestory.',
            'V kúpeľoch sa konal napríklad semestrálny program vzdelávania v oblasti psychológie a viery pre kňazov a rehoľníkov.',
            'Kanál je určený hosťom kúpeľov a účastníkom programov. Sledovaním budete vedieť o podujatiach. '.self::CLOSING,
        ]],
        1618 => ['name' => 'Saletini Rozkvet', 'body' => [
            'Saletíni sú misionári Matky Božej z La Salette. V Považskej Bystrici-Rozkvete spravujú farnosť pri Kostole Lasaletskej Panny Márie. Pôsobia aj online.',
            'Pripravujú aktivity pre veriacich, napríklad celoročnú akciu Raňajky s Bohom na Facebooku a biblickú formáciu. Organizujú aj púte.',
            'Kanál je určený veriacim z Považskej Bystrice a priaznivcom saletínov. Sledovaním budete vedieť o ich podujatiach. '.self::CLOSING,
        ]],
        1616 => ['name' => 'Dom modlitby Lorinčík', 'body' => [
            'Dom modlitby Lorinčík je kláštor bosých karmelitánov v Košiciach-Lorinčíku. Ponúka ticho, modlitbu a duchovné sprevádzanie.',
            'Pripravuje duchovné obnovy, napríklad obnovy pre mladých mužov na rozlišovanie povolania. Obnovy sa konajú cez víkendy.',
            'Kanál je určený všetkým, ktorí hľadajú ticho a modlitbu. Sledovaním budete vedieť o obnovách. '.self::CLOSING,
        ]],
        1614 => ['name' => 'Františkáni z Trstenej', 'body' => [
            'Františkáni v Trstenej na Orave patria Slovenskej provincii františkánov. Žijú podľa reguly sv. Františka.',
            'Každý prvý piatok v mesiaci spájajú ochranu stvorenstva s františkánskou spiritualitou. Pripravujú aj spovedanie a slávnosti františkánskeho roka.',
            'Kanál je určený veriacim z Oravy. Sledovaním budete vedieť o podujatiach františkánov. '.self::CLOSING,
        ]],
        1613 => ['name' => 'Farnosť Dobrého pastiera', 'body' => [
            'Farnosť Dobrého pastiera pôsobí na sídlisku Solinky v Žiline. Patrí do Žilinskej diecézy. Má aktívne spoločenstvá.',
            'Pripravuje aj ekumenické podujatia, napríklad výstavu Stretnutie s Bibliou s Biblickou spoločnosťou na Slovensku.',
            'Kanál je určený veriacim zo Žiliny. Sledovaním budete vedieť o podujatiach farnosti. '.self::CLOSING,
        ]],
        1611 => ['name' => 'Arcidiecézny synodálny tím Bratislavskej arcidiecézy', 'body' => [
            'Arcidiecézny synodálny tím sprevádza synodálny proces v Bratislavskej arcidiecéze. Pomáha farnostiam zapojiť veriacich do rozhovoru o Cirkvi.',
            'Pripravuje webináre pre synodálnych koordinátorov farností a materiály na stretnutia. Webináre prebiehajú online.',
            'Kanál je určený koordinátorom a veriacim arcidiecézy. Sledovaním budete vedieť o synodálnych podujatiach. '.self::CLOSING,
        ]],
        1610 => ['name' => 'Cirkevné gymnázium Štefana Mišíka', 'body' => [
            'Cirkevné gymnázium Štefana Mišíka v Spišskej Novej Vsi je katolícke gymnázium Spišskej diecézy. Nesie meno kňaza a národovca Štefana Mišíka.',
            'Pripravuje dni otvorených dverí, aj online, pre budúcich študentov a rodičov. Organizuje aj školské slávnosti.',
            'Kanál je určený uchádzačom a rodičom. Sledovaním budete vedieť o dňoch otvorených dverí. '.self::CLOSING,
        ]],
        1609 => ['name' => 'Inštitút na podporu mladých publicistov', 'municipality_id' => 4209, 'body' => [
            'Inštitút na podporu mladých publicistov (ifp) v Mníchove je katolícka novinárska škola. Vychováva novinárov v duchu kresťanských hodnôt.',
            'Každé leto pripravuje trojtýždňový žurnalistický kurz pre mladých nemecky hovoriacich novinárov zo strednej a východnej Európy, aj zo Slovenska.',
            'Kanál je určený mladým novinárom. Sledovaním budete vedieť o kurzoch inštitútu. '.self::CLOSING,
        ]],
        1608 => ['name' => 'CZ ECAV Bratislava-Dúbravka', 'body' => [
            'Cirkevný zbor ECAV Bratislava-Dúbravka je evanjelický zbor v mestskej časti Dúbravka. Patrí do Bratislavského seniorátu ECAV. Slúži evanjelikom západnej Bratislavy.',
            'Pripravuje stretnutia pre manželov, napríklad v rámci Národného týždňa manželstva. Organizuje aj služby Božie a programy pre deti.',
            'Kanál je určený evanjelikom z Dúbravky a okolia. Sledovaním budete vedieť o podujatiach zboru. '.self::CLOSING,
        ]],
        1607 => ['name' => 'Kongregácia pre inštitúty zasväteného života a spoločnosti apoštolského života', 'body' => [
            'Kongregácia pre inštitúty zasväteného života a spoločnosti apoštolského života bola úradom Svätej stolice pre rehole. Od roku 2022 nesie názov Dikastérium. Stará sa o zasvätený život v Cirkvi.',
            'Pripravuje online programy, napríklad k Medzinárodnému dňu modlitby a povedomia o obchodovaní s ľuďmi na sviatok sv. Jozefíny Bakhity.',
            'Kanál je určený rehoľníkom a všetkým, ktorí chcú pomôcť obetiam obchodovania s ľuďmi. Sledovaním budete vedieť o iniciatívach. '.self::CLOSING,
        ]],
        1606 => ['name' => 'skupina kresťanských koučov', 'body' => [
            'Skupina kresťanských koučov spája koučov, ktorí pracujú s kresťanskými hodnotami. Pomáha jednotlivcom aj párom.',
            'Pripravila napríklad sedemdňový online seriál o manželstve počas Národného týždňa manželstva.',
            'Kanál je určený manželom a všetkým, ktorí hľadajú osobný rast. Sledovaním budete vedieť o ďalších seriáloch. '.self::CLOSING,
        ]],
        1605 => ['name' => 'Arcidiecézne centrum mládeže', 'body' => [
            'Arcidiecézne centrum mládeže Vinica v Bratislave-Rači sa venuje mladým Bratislavskej arcidiecézy. Pripravuje programy pre mladých a animátorov.',
            'Pripravuje podcasty, napríklad sériu o sviatosti zmierenia, Bratislavskú animátorskú školu a diecézne stretnutie ClipTime.',
            'Kanál je určený mladým a animátorom. Sledovaním budete vedieť o programoch centra. '.self::CLOSING,
        ]],
        1603 => ['name' => 'Rada pre rodinu Košickej eparchie', 'body' => [
            'Rada pre rodinu Košickej eparchie sa venuje rodinám v gréckokatolíckej Košickej eparchii. Pripravuje programy pre manželov a rodičov.',
            'Pripravila napríklad sedem manželských video večerov počas Národného týždňa manželstva. Organizuje aj stretnutia a obnovy pre rodiny.',
            'Kanál je určený manželom a rodinám. Sledovaním budete vedieť o programoch rady. '.self::CLOSING,
        ]],
        1601 => ['name' => 'Kongregácia misionárov najsätejšieho srdca Ježišovho', 'body' => [
            'Misionári Najsvätejšieho Srdca Ježišovho sú rehoľa, ktorú založil páter Jules Chevalier. Na Slovensku majú Duchovné centrum Lukov dvor v Nitre. Ponúkajú duchovné obnovy a sprevádzanie.',
            'Pripravujú obnovy a kurzy, napríklad tanečnú meditáciu v tichu, Uzdravujúcu cestu života či online prednášky o psychosomatike.',
            'Kanál je určený všetkým, ktorí hľadajú duchovné obnovy. Sledovaním budete vedieť o programoch centra. '.self::CLOSING,
        ]],
        1599 => ['name' => 'Dekanát Teologickej fakulty', 'body' => [
            'Dekanát Teologickej fakulty Katolíckej univerzity v Košiciach vedie fakultu na Hlavnej ulici. Fakulta pripravuje kňazov a laických teológov.',
            'Pripravuje konferencie, napríklad konferenciu Pápež František, synodalita a Ecclesia semper reformanda. Konferencie sú otvorené aj verejnosti.',
            'Kanál je určený študentom a odbornej verejnosti. Sledovaním budete vedieť o konferenciách fakulty. '.self::CLOSING,
        ]],
        1598 => ['name' => 'Rímskokatolícka farosť Ladice', 'municipality_id' => 1770, 'body' => [
            'Farnosť Ladice leží v okrese Zlaté Moravce. K farnosti patria aj Kostoľany pod Tribečom so vzácnym Kostolom sv. Juraja. Ten patrí k najstarším kostolom na Slovensku.',
            'Farnosť vyhlásila zbierku na opravu Kostola sv. Juraja v Kostoľanoch pod Tribečom. Pripravuje aj slávnosti a bohoslužby.',
            'Kanál je určený veriacim z Ladíc a Kostolian. Sledovaním budete vedieť o zbierkach a slávnostiach. '.self::CLOSING,
        ]],
        1597 => ['name' => 'Ústredie Slovenského skautingu', 'body' => [
            'Slovenský skauting je jedna z najväčších výchovných organizácií pre deti a mladých na Slovensku. Je členom svetových skautských organizácií. Ústredie riadi jeho činnosť.',
            'Pripravuje celoslovenské akcie, napríklad Svetový deň zamyslenia 22. februára a oslavy patróna skautov sv. Juraja dobrými skutkami.',
            'Kanál je určený skautom a rodičom. Sledovaním budete vedieť o skautských akciách. '.self::CLOSING,
        ]],
        1596 => ['name' => 'Kongregácia služobníc Ducha Svätého ustavičnej poklony', 'body' => [
            'Služobnice Ducha Svätého ustavičnej poklony sú kontemplatívne sestry, nazývané aj ružové sestry. V Nitre žijú v Kláštore Najsvätejšej Trojice. Ich službou je neustála adorácia.',
            'Pripravujú adorácie pre verejnosť, napríklad adoráciu za pokoj na Ukrajine. Ich kaplnka je otvorená na tichú modlitbu.',
            'Kanál je určený veriacim, ktorí sa chcú pridať k adorácii. Sledovaním budete vedieť o adoráciách sestier. '.self::CLOSING,
        ]],
        1591 => ['name' => 'Svetový luteránsky zväz', 'body' => [
            'Svetový luteránsky zväz je spoločenstvo luteránskych cirkví celého sveta so sídlom v Ženeve. Patrí doň aj Evanjelická cirkev a. v. na Slovensku.',
            'Spolu s inými kresťanskými spoločenstvami pripravuje ekumenické modlitby, napríklad online modlitby za mier na Ukrajine na Popolcovú stredu.',
            'Kanál je určený evanjelikom a všetkým, ktorí sa zaujímajú o ekumenizmus. Sledovaním budete vedieť o iniciatívach zväzu. '.self::CLOSING,
        ]],
        1590 => ['name' => 'Spoločenstvo sv. Zdislavy', 'body' => [
            'Spoločenstvo sv. Zdislavy je laické spoločenstvo vo Zvolene. Žije dominikánskou spiritualitou. Nesie meno sv. Zdislavy, matky a dominikánskej terciárky.',
            'Pripravuje modlitebné stretnutia, napríklad modlitby za pokoj na Ukrajine v dominikánskom kostole vo Zvolene.',
            'Kanál je určený veriacim zo Zvolena. Sledovaním budete vedieť o stretnutiach spoločenstva. '.self::CLOSING,
        ]],
        1588 => ['name' => 'Bratislavskí roveri', 'body' => [
            'Bratislavskí roveri sú starší skauti z Bratislavy. Spája ich služba, dobrodružstvo a viera.',
            'Pripravujú akcie pre mužov, napríklad ranný chlapský pôstny powertraining na Kalvárii, ktorý spája cvičenie a modlitbu.',
            'Kanál je určený mladým mužom z Bratislavy. Sledovaním budete vedieť o akciách roverov. '.self::CLOSING,
        ]],
        1586 => ['name' => 'Medzinárodná aliancia pre pokoj a bezpečnosť', 'body' => [
            'Medzinárodná aliancia pre pokoj a bezpečnosť je iniciatíva, ktorá podporuje mier a dialóg. Pôsobí aj na Slovensku.',
            'Pripravila napríklad Deň modlitieb za Ukrajinu a Rusko v Bratislave. Modlitby spájali veriacich rôznych cirkví.',
            'Kanál je určený všetkým, ktorým záleží na mieri. Sledovaním budete vedieť o modlitbových stretnutiach. '.self::CLOSING,
        ]],
        1579 => ['name' => 'ECAV Nitra', 'body' => [
            'Cirkevný zbor ECAV Nitra je evanjelický zbor v Nitre. Patrí do Dunajsko-nitrianskeho seniorátu. Slúži evanjelikom mesta a okolia.',
            'Pripravuje koncerty a podujatia, napríklad benefičný koncert na pomoc ľuďom utekajúcim pred vojnou na Ukrajine.',
            'Kanál je určený evanjelikom z Nitry. Sledovaním budete vedieť o podujatiach zboru. '.self::CLOSING,
        ]],
        1577 => ['name' => 'CZ ECAV Beckov', 'body' => [
            'Cirkevný zbor ECAV Beckov je evanjelický zbor v obci Beckov pod hradom. Beckov je rodiskom Jozefa Miloslava Hurbana. Zbor si jeho pamiatku pripomína.',
            'Pripravuje spomienky na J. M. Hurbana, napríklad pri 205. výročí jeho narodenia. Spomienky spájajú služby Božie a kultúrny program.',
            'Kanál je určený evanjelikom z Beckova a ctiteľom Hurbana. Sledovaním budete vedieť o spomienkach. '.self::CLOSING,
        ]],
        1575 => ['name' => 'Arcidiecéza Matky Božej v Moskve', 'body' => [
            'Arcidiecéza Matky Božej v Moskve je katolícka arcidiecéza pre európsku časť Ruska. Jej katedrálou je katedrála Nepoškvrneného počatia v Moskve. Na čele stojí moskovský arcibiskup.',
            'Ruskí katolícki biskupi vyzvali veriacich zjednotiť sa v modlitbe za mier. Arcidiecéza pripravuje aj púte a slávnosti katolíkov v Rusku.',
            'Kanál je určený všetkým, ktorí sa zaujímajú o Cirkev v Rusku. Sledovaním budete vedieť o jej výzvach. '.self::CLOSING,
        ]],
        1572 => ['name' => 'Obec Zákamenné', 'body' => [
            'Zákamenné je obec na hornej Orave. Je rodiskom biskupa Jána Vojtaššáka, ktorého komunistický režim odsúdil v monsterprocese v roku 1951.',
            'Obec pripravuje kultúrne a spomienkové podujatia, napríklad predpremiéru filmu o monsterprocese J. Vojtaššák a spol. v kultúrnom dome.',
            'Kanál je určený obyvateľom Zákamenného a Oravy. Sledovaním budete vedieť o podujatiach obce. '.self::CLOSING,
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
