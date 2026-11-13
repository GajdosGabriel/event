<?php

use App\Models\Canal;
use App\Models\Municipality;
use App\Services\Canals\CanalEmails;
use App\Services\SystemLog\Recorder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Najnovších 60 importovaných kanálov (november 2026, #1867 – #1954): doplnené
 * všetko, čo sa dalo overiť z verejných zdrojov — z podujatí kanála, z oficiálnych
 * webov a kontaktných stránok organizácií a z Nominatim/OpenStreetMap.
 *
 * Každý kanál môže dostať:
 *  - popis v troch odsekoch (kto je, čo pripravuje, komu je kanál určený),
 *    len ak ešte nemá tri odseky; dvojodsekovému popisu sa len pridá tretí,
 *  - web a telefón do prázdneho poľa,
 *  - e-mail cez CanalEmails::discover (adresa doslova na stránke `found_on`),
 *  - adresu sídla so súradnicami (ulica do prázdneho poľa, súradnice len kanálu
 *    s polohou na stred obce alebo Slovenska).
 *
 * Opravy:
 *  - #1935 sa volal len „Farnosť" — je to Farnosť Bolešov (bolesov.fara.sk),
 *  - #1876 Slovenská provincia jezuitov sídli na Panskej 11 v Bratislave, nie v Prešove.
 *
 * Kanál musí sedieť ID aj názvom; prevzatý, zlúčený či zmazaný sa preskočí.
 */
return new class extends Migration
{
    private const CLOSING = 'Podrobnosti nájdete vždy v detaile podujatia.';

    /** @var array<int, array<string, mixed>> */
    private const PROFILES = [
        1954 => [
            'name' => 'Iniciatíva na podporu cností vo verejnom živote',
            'body' => [
                0 => 'Iniciatíva na podporu cností vo verejnom živote je bratislavské spoločenstvo laikov, ktorým záleží na kresťanských hodnotách vo verejnom priestore. Pripomína, že sloboda a demokracia stoja na osobnej poctivosti a zodpovednosti. Pozýva k modlitbe za spoločnosť a jej predstaviteľov.',
                1 => 'Pri príležitosti Dňa boja za slobodu a demokraciu pripravuje svätú omšu za obete neslobody a pokoj v spoločnosti v Katedrále sv. Martina v Bratislave. Spomienka spája vďaku za november 1989 s modlitbou za dnešok. Podujatie je otvorené pre všetkých.',
                2 => 'Kanál je určený veriacim, ktorým nie je ľahostajný verejný život. Sledovaním budete vedieť o ďalších modlitbových stretnutiach iniciatívy. '.self::CLOSING,
            ],
        ],
        1953 => [
            'name' => 'Gréckokatolícke arcibiskupstvo Prešov a Združenie Sakrálne spevy Východu o.z',
            'website' => 'https://www.grkatpo.sk',
            'phone' => '051/75 62 601',
            'emails' => [['email' => 'abu@grkatpo.sk', 'found_on' => 'https://www.grkatpo.sk/kontakt']],
            'address' => ['street' => 'Hlavná 1', 'postcode' => '080 01', 'municipality_id' => 2822, 'latitude' => 48.993863, 'longitude' => 21.2431405],
            'body' => [
                0 => 'Gréckokatolícke arcibiskupstvo Prešov je sídlom Prešovskej archieparchie a metropolie Gréckokatolíckej cirkvi na Slovensku. Sídli na Hlavnej ulici v Prešove pri Katedrále sv. Jána Krstiteľa. Združenie Sakrálne spevy Východu sa venuje duchovnej hudbe byzantskej tradície.',
                1 => 'Spoločne pripravujú Festival duchovných piesní byzantského obradu, ktorý má už vyše tridsať ročníkov. Na festivale vystupujú zbory z farností aj z iných krajín. Koncerty sa konajú v prešovskej katedrále.',
                2 => 'Kanál je určený milovníkom liturgického spevu a veriacim východného obradu. Sledovaním budete vedieť o ďalších ročníkoch festivalu. '.self::CLOSING,
            ],
        ],
        1952 => [
            'name' => 'Premonštráti',
            'phone' => '0903 774 012',
            'body' => [
                0 => 'Premonštráti v Košiciach patria k Rádu premonštrátov a Opátstvu Jasov. Spravujú Kostol Najsvätejšej Trojice na Hlavnej ulici, nazývaný aj premonštrátsky. Barokový kostol postavili pôvodne jezuiti.',
                1 => 'V kostole sa okrem bohoslužieb konajú aj koncerty, napríklad vianočný koncert speváčky Márie Podhradskej. Premonštráti ponúkajú aj spovedanie pred sviatkami. Program uverejňujú na webe premonstratike.sk.',
                2 => 'Kanál je určený veriacim a návštevníkom centra Košíc. Sledovaním budete vedieť o koncertoch a slávnostiach v premonštrátskom kostole. '.self::CLOSING,
            ],
        ],
        1951 => [
            'name' => 'Diecézny katechetický úrad Košickej arcidiecézy',
            'phone' => '055/682 81 48',
            'emails' => [['email' => 'dkukosice@abuke.sk', 'found_on' => 'https://dku-ke.abuke.sk']],
            'address' => ['street' => 'Hlavná 28', 'postcode' => '041 83', 'municipality_id' => 1565, 'latitude' => 48.7197688, 'longitude' => 21.2574497],
            'body' => [
                0 => 'Diecézny katechetický úrad Košickej arcidiecézy sídli v Arcibiskupskom paláci na Hlavnej ulici v Košiciach. Stará sa o vyučovanie náboženstva a katechézu vo farnostiach. Podporuje katechétov a učiteľov náboženstva.',
                1 => 'Pripravuje metodické materiály, školenia a cykly katechéz, napríklad katechézy a deviatnik Poďte a uvidíte pre deti od 9 do 15 rokov. Materiály môžu využiť farnosti aj školy. Sprevádzajú dôležité udalosti arcidiecézy.',
                2 => 'Kanál je určený katechétom, učiteľom náboženstva a rodičom. Sledovaním budete vedieť o nových materiáloch a školeniach. '.self::CLOSING,
            ],
        ],
        1950 => [
            'name' => 'Emanuel',
            'body' => [
                0 => 'Emanuel je modlitbové spoločenstvo, ktoré sa stretáva v Klokočove v okrese Michalovce. Klokočov je známe gréckokatolícke pútnické miesto s chrámom Zosnutia Presvätej Bohorodičky. Spoločenstvo spája modlitbu a hudbu.',
                1 => 'Pripravuje večery chvál v klokočovskom chráme. Stretnutia sa konajú podvečer a trvajú približne dve hodiny. Sú otvorené pre všetkých.',
                2 => 'Kanál je určený veriacim z Klokočova a okolia Michaloviec. Sledovaním budete vedieť o ďalších chválach. '.self::CLOSING,
            ],
        ],
        1949 => [
            'name' => 'Univerzitné pastoračné centrum sv. Stanislava Kostku',
            'address' => ['street' => 'Františkánska 34', 'postcode' => '917 01', 'municipality_id' => 3596, 'latitude' => 48.3778641, 'longitude' => 17.583782],
            'body' => [
                0 => 'Univerzitné pastoračné centrum sv. Stanislava Kostku je cirkevná organizácia, ktorá sa v Trnave stará o vysokoškolákov. Jeho patrónom je sv. Stanislav Kostka, patrón mladých a študentov. Pôsobí pri trnavských univerzitách.',
                1 => 'Pripravuje študentské sväté omše, duchovné obnovy a slávnosti, napríklad hody k 300. výročiu svätorečenia sv. Stanislava Kostku s apoštolským nunciom. Slávnosti sa konajú v Kostole Najsvätejšej Trojice. Pozýva na ne aj verejnosť.',
                2 => 'Kanál je určený študentom a akademickej obci v Trnave. Sledovaním budete vedieť o omšiach a podujatiach centra. '.self::CLOSING,
            ],
        ],
        1935 => [
            'name' => 'Farnosť',
            'fix_name' => 'Farnosť Bolešov',
            'website' => 'https://bolesov.fara.sk',
            'phone' => '0915 305 547',
            'emails' => [['email' => 'farskyurad@farnostbolesov.sk', 'found_on' => 'https://bolesov.fara.sk']],
            'address' => ['street' => 'Bolešov 278', 'postcode' => '018 53', 'municipality_id' => 215, 'latitude' => 48.987107, 'longitude' => 18.1601517],
            'body' => [
                0 => 'Farnosť Bolešov je rímskokatolícka farnosť v okrese Ilava. Farským kostolom je Kostol sv. Ondreja apoštola. Farský úrad sídli v Bolešove č. 278.',
                1 => 'Okrem bohoslužieb pripravuje prednášky a stretnutia pre veriacich. Hosťom bol napríklad prof. Róbert Letz s prednáškou Svedkovia viery o prenasledovaní Cirkvi v rokoch 1948 – 1989. Podujatia sa konajú v kostole.',
                2 => 'Kanál je určený veriacim z Bolešova a okolia. Sledovaním budete vedieť o prednáškach a slávnostiach farnosti. '.self::CLOSING,
            ],
        ],
        1929 => [
            'name' => 'M & M',
            'body' => [
                0 => 'M & M je trnavský organizátor spoločenských podujatí pre slobodných. Večery spájajú hudbu, hry a zoznamovanie. Výťažok venuje rodinám v núdzi.',
                1 => 'Pripravuje napríklad Singles Oldies Party s hudbou 70. až 90. rokov, hrami na prelomenie ľadov a tancom. Párty sa konala v Rotunde Spiegelsaal v Trnave. Na podujatia sa prihlasuje vopred.',
                2 => 'Kanál je určený slobodným z Trnavy a okolia. Sledovaním budete vedieť o ďalších večeroch. '.self::CLOSING.' Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1927 => [
            'name' => 'Bratislavskí františkáni',
            'body' => [
                0 => 'Bratislavskí františkáni sú komunitou Rehole menších bratov v kláštore na Františkánskej ulici v Bratislave. Spravujú Kostol Zvestovania Pána, najstaršiu zachovanú sakrálnu stavbu v meste. V kláštore sídli aj provincialát slovenských františkánov.',
                1 => 'V kostole slávia bohoslužby a spovedajú počas celého týždňa. Pripravujú aj duchovné stretnutia, napríklad spoločné stretnutie slovenských a maďarských veriacich na záver obnovy chrámu. Kostol je otvorený pre veriacich aj návštevníkov.',
                2 => 'Kanál je určený veriacim a návštevníkom Starého Mesta. Sledovaním budete vedieť o slávnostiach a stretnutiach v kostole. '.self::CLOSING.' Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1925 => [
            'name' => 'Kresťanské spoločenstvá',
            'body' => [
                0 => 'Kresťanské spoločenstvá zo Zlatých Moraviec spájajú mladých veriacich mesta. Stretávajú sa pri modlitbe, chválach a slove. Spolupracujú s bratmi tešiteľmi.',
                1 => 'Pripravujú Mládežku a chvály, pravidelné stretnutia v prvý piatok mesiaca. Konajú sa v Kostole Božského Srdca Ježišovho na Hviezdoslavovej ulici. Stretnutie trvá zvyčajne od šiestej večer.',
                2 => 'Kanál je určený mladým zo Zlatých Moraviec a okolia. Sledovaním budete vedieť o ďalších stretnutiach. '.self::CLOSING,
            ],
        ],
        1924 => [
            'name' => 'Holy Cross Family Ministries',
            'body' => [
                0 => 'Holy Cross Family Ministries je medzinárodná katolícka služba rodinám, ktorú založil páter Patrick Peyton z Kongregácie Svätého kríža. Jej heslom je The family that prays together stays together. Pôsobí vo viacerých krajinách sveta.',
                1 => 'Šíri spoločnú rodinnú modlitbu, najmä ruženec. Organizuje medzinárodnú iniciatívu Globálny ruženec za pokoj, do ktorej sa môžu zapojiť aj veriaci zo Slovenska. Modlitba prebieha aj online.',
                2 => 'Kanál je určený rodinám a všetkým, ktorí sa modlia ruženec. Sledovaním budete vedieť o ďalších modlitbových iniciatívach. '.self::CLOSING,
            ],
        ],
        1921 => [
            'name' => 'Konferencia sekulárnych inštitútov Slovenska',
            'body' => [
                0 => 'Konferencia sekulárnych inštitútov Slovenska spája sekulárne inštitúty pôsobiace na Slovensku. Ich členovia žijú zasvätený život uprostred bežného sveta, v rodinách a v zamestnaní. Konferencia podporuje ich spoluprácu.',
                1 => 'Dvakrát do roka zvoláva riadne zasadnutie, napríklad jesenné zasadnutie v kláštore sestier uršulínok v Košiciach. Súčasťou býva zdieľanie skúseností a stretnutie s biskupom, napríklad s vladykom Jonášom Maximom. Pripravuje aj formačné stretnutia.',
                2 => 'Kanál je určený členom sekulárnych inštitútov a všetkým, ktorí sa zaujímajú o zasvätený život vo svete. Sledovaním budete vedieť o stretnutiach konferencie. '.self::CLOSING,
            ],
        ],
        1914 => [
            'name' => 'Katolícka cirkev',
            'body' => [
                0 => 'Katolícka cirkev je najväčšou kresťanskou cirkvou na svete, na čele s rímskym pápežom. Na Slovensku pôsobí prostredníctvom diecéz, farností, reholí a mnohých hnutí. Jej oficiálny web je vatican.va.',
                1 => 'V tomto kanáli sú celocirkevné udalosti, ktoré slávia veriaci na celom svete, napríklad Misijná nedeľa, ktorá v roku 2026 oslávila 100 rokov. Na Slovensku sa pri nej konala zbierka na pomoc Burundi. Slávenia sa konajú vo všetkých farnostiach.',
                2 => 'Kanál je určený všetkým veriacim. Sledovaním budete vedieť o celocirkevných slávnostiach a iniciatívach. '.self::CLOSING,
            ],
        ],
        1909 => [
            'name' => 'Centrum pastorácie Rómov Lačho pastiris',
            'body' => [
                0 => 'Centrum pastorácie Rómov Lačho pastiris pôsobí v Spišskej diecéze. Jeho názov v rómčine znamená Dobrý pastier. Venuje sa duchovnej službe rómskym komunitám.',
                1 => 'Pripravuje púte Rómov, napríklad púť do Diecéznej svätyne Božieho milosrdenstva v Smižanoch so spišským biskupom Františkom Trstenským. Program spája svätú omšu, spev a stretnutie rodín. Pozýva všetkých veriacich.',
                2 => 'Kanál je určený rómskym veriacim a všetkým, ktorí sa zapájajú do rómskej pastorácie. Sledovaním budete vedieť o pútiach a stretnutiach. '.self::CLOSING,
            ],
        ],
        1902 => [
            'name' => 'Spišské biskupstvo',
            'body' => [
                0 => 'Spišské biskupstvo je sídlom Spišskej diecézy na Spišskej Kapitule. Diecéza vznikla v roku 1776 a zahŕňa Spiš, Liptov a Oravu. Na jej čele stojí spišský biskup.',
                1 => 'Pripravuje celodiecézne podujatia, napríklad Carlovu kvapku krvi na Spišskej Kapitule ku cti sv. Carla Acutisa. Spolupracuje s Kňazským seminárom biskupa Jána Vojtaššáka. Diecéza ohlasuje aj púte, výročia a slávnosti.',
                2 => 'Kanál je určený veriacim Spišskej diecézy. Sledovaním budete vedieť o diecéznych podujatiach. '.self::CLOSING.' Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1900 => [
            'name' => 'Inštitút Ladislava Hanusa',
            'body' => [
                0 => 'Inštitút Ladislava Hanusa nesie meno kňaza a filozofa kultúry Ladislava Hanusa. Rozvíja kresťanské myslenie a jeho miesto vo verejnom živote. Spája ľudí z akademického, kultúrneho a cirkevného prostredia.',
                1 => 'Organizuje festival Hanusove dni v Košiciach, Ružomberku aj Bratislave. Ponúka diskusie, prednášky a kultúrny program s domácimi aj zahraničnými hosťami, napríklad o umelej inteligencii. Košické Hanusove dni sa konajú na Teologickej fakulte KU.',
                2 => 'Kanál je určený študentom a všetkým, ktorých zaujíma filozofia, kultúra a spoločnosť. Sledovaním budete vedieť o ďalších Hanusových dňoch. '.self::CLOSING,
            ],
        ],
        1899 => [
            'name' => 'katedrálny zbor Kyrillomethodeon, o. z',
            'website' => 'https://kyrillomethodeon.grkatba.sk',
            'body' => [
                0 => 'Kyrillomethodeon je katedrálny zbor Bratislavskej gréckokatolíckej eparchie. Spieva pri liturgiách v Katedrále Povýšenia vznešeného a životodarného Kríža v Bratislave. V roku 2026 si pripomína 90 rokov od založenia.',
                1 => 'Okrem liturgií koncertuje doma aj v zahraničí a pripravuje vianočné koncerty. Pri jubileu pripravil odborné kolokvium o liturgickom speve a slávnostnú svätú liturgiu. Záštitu prevzala Gréckokatolícka eparchia Bratislava.',
                2 => 'Kanál je určený milovníkom liturgického spevu východného obradu. Sledovaním budete vedieť o koncertoch zboru. '.self::CLOSING,
            ],
        ],
        1897 => [
            'name' => 'Diecézne sanktuárium v Obišovciach',
            'body' => [
                0 => 'Diecézne sanktuárium v Obišovciach je pútnický kostol Ružencovej Panny Márie z 17. storočia. Za diecéznu svätyňu Košickej arcidiecézy ho vyhlásil arcibiskup Bernard Bober v roku 2011. Uctieva sa tu milostivý obraz Obišovskej Panny Márie.',
                1 => 'Hlavnou udalosťou roka je odpustová púť k Ružencovej Panne Márii na prvú októbrovú nedeľu, ktorá má tradíciu vyše 300 rokov. Púť trvá zvyčajne dva dni. Konajú sa tu aj fatimské soboty.',
                2 => 'Kanál je určený pútnikom a veriacim z okolia Košíc. Sledovaním budete vedieť o pútiach a slávnostiach. '.self::CLOSING,
            ],
        ],
        1894 => [
            'name' => 'Martin',
            'body' => [
                0 => 'Martin je organizátor spoločných pútí na Živčákovú pri Turzovke. Živčáková je mariánske pútnické miesto na Kysuciach. Púte sprevádza duchovný otec Henrich Grejták.',
                1 => 'Pripravuje jesennú púť do Turzovky vrátane dvojdňovej púte. Program spája svätú omšu, chvály a modlitbu na hore. Odchody bývajú skoro ráno.',
                2 => 'Kanál je určený pútnikom, ktorí chcú putovať na Živčákovú. Sledovaním budete vedieť o termínoch pútí. '.self::CLOSING,
            ],
        ],
        1893 => [
            'name' => 'Diecézny synodálny tím Spišskej diecézy',
            'body' => [
                0 => 'Diecézny synodálny tím Spišskej diecézy sprevádza synodálnu cestu Cirkvi v diecéze. Podporuje vzájomné načúvanie a zapojenie laikov do života farností. Spolupracuje s biskupským úradom na Spišskej Kapitule.',
                1 => 'Otvoril prvé učenícke školy v Poprade a Kežmarku. Je to dvojročná formácia pre dospelých od 18 rokov, ktorí chcú prehĺbiť vzťah s Ježišom Kristom. Stretnutia sa konajú napríklad v Kostole sv. Cyrila a Metoda v Poprade.',
                2 => 'Kanál je určený veriacim Spišskej diecézy, ktorí hľadajú formáciu. Sledovaním budete vedieť o nových ročníkoch učeníckej školy. '.self::CLOSING,
            ],
        ],
        1891 => [
            'name' => 'ClipTime',
            'body' => [
                0 => 'ClipTime je diecézne stretnutie mládeže Bratislavskej arcidiecézy. Mladí sa na ňom stretávajú so svojimi biskupmi. Téma vychádza z posolstva pápeža k svetovému dňu mladých.',
                1 => 'Program tvoria prednášky, diskusie, workshopy, modlitba a koncerty. ClipTime 2026 sa koná 21. a 22. novembra s témou Odvaha žiť naplno. Na stretnutie sa treba zaregistrovať na cliptime.sk.',
                2 => 'Kanál je určený mladým z Bratislavskej arcidiecézy. Sledovaním budete vedieť o programe a registrácii. '.self::CLOSING.' Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1890 => [
            'name' => 'Hrdí na rodinu',
            'body' => [
                0 => 'Hrdí na rodinu je iniciatíva, ktorá organizuje Národný pochod Hrdí na rodinu. Pochod chce šíriť radosť, ktorú rodina a manželstvo dávajú deťom aj manželom. Koná sa každoročne v Bratislave.',
                1 => 'Pochod sa tradične začína na Jakubovom námestí, v roku 2026 sa konal jeho 14. ročník. Súčasťou programu sú sväté omše, diskusie a sprievodný program pre rodiny. Zapojiť sa dá aj službou na mieste či šírením plagátov.',
                2 => 'Kanál je určený rodinám a všetkým, ktorí chcú podporiť manželstvo a rodinu. Sledovaním budete vedieť o ďalšom ročníku pochodu. '.self::CLOSING.' Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1887 => [
            'name' => 'farnosť Vrútky',
            'body' => [
                0 => 'Farnosť Vrútky je rímskokatolícka farnosť v meste Vrútky pri Martine. Farským kostolom je Kostol sv. Jána Krstiteľa na Mlynskej ulici. Slúži veriacim mesta a okolia.',
                1 => 'Okrem bohoslužieb pripravuje kultúrne podujatia v kostole, napríklad literárno-hudobné pásmo Ja František o sv. Františkovi z Assisi. Pásmo spája čítané slovo a hudbu. Podujatia sú otvorené pre verejnosť.',
                2 => 'Kanál je určený veriacim z Vrútok a okolia. Sledovaním budete vedieť o podujatiach farnosti. '.self::CLOSING,
            ],
        ],
        1886 => [
            'name' => 'Rímskokatolícke spoločenstvá',
            'body' => [
                0 => 'Rímskokatolícke spoločenstvá zo Zlatých Moraviec spájajú mladých veriacich mesta. Stretávajú sa pri modlitbe a chválach. Nadväzujú na pravidelné stretnutia mládeže.',
                1 => 'Pripravujú Mládežku a chvály v prvý piatok mesiaca. Stretnutia sa konajú večer v kostole a trvajú približne dve hodiny. Ponúkajú spoločný čas v Božej prítomnosti.',
                2 => 'Kanál je určený mladým zo Zlatých Moraviec a okolia. Sledovaním budete vedieť o ďalších stretnutiach. '.self::CLOSING,
            ],
        ],
        1885 => [
            'name' => 'Saleziáni don Bosca a Farnosť Vajnory',
            'body' => [
                0 => 'Saleziáni don Bosca a Farnosť Vajnory spoločne pripomínajú odkaz blahoslaveného Titusa Zemana. Vajnory sú jeho rodiskom. Saleziánsky kňaz a mučeník bol blahorečený v roku 2017.',
                1 => 'Každý rok pripravujú svätú omšu pri výročí blahorečenia Titusa Zemana. Pri spomienke sa stretávajú farníci, saleziáni aj ich priatelia. Saleziáni pracujú najmä s deťmi a mládežou.',
                2 => 'Kanál je určený veriacim z Vajnor a ctiteľom bl. Titusa Zemana. Sledovaním budete vedieť o slávnostiach a výročiach. '.self::CLOSING,
            ],
        ],
        1883 => [
            'name' => 'Kongregácia sestier Služobníčok Najsvätejšej Panny Márie Nepoškvrnene počatej',
            'phone' => '0907 546 551',
            'address' => ['street' => 'Nábrežie Jána Pavla II. 697/2', 'postcode' => '059 21', 'municipality_id' => 3370, 'latitude' => 49.0542896, 'longitude' => 20.2105804],
        ],
        1882 => [
            'name' => 'Vydavateľstvo novaJAR',
            'address' => ['street' => 'Šrobárova 2668/19', 'postcode' => '058 01', 'municipality_id' => 2763, 'latitude' => 49.0424575, 'longitude' => 20.2977229],
            'append' => 'Kanál je určený čitateľom a poslucháčom podcastu Biblia za rok. Sledovaním budete vedieť o diskusiách a stretnutiach s autormi. '.self::CLOSING,
        ],
        1881 => [
            'name' => 'Občianske združenie Vykročiť',
            'body' => [
                0 => 'Občianske združenie Vykročiť je kresťanské združenie z okolia Piešťan. Pripravuje stretnutia, ktoré pomáhajú zastaviť sa a načerpať silu. Pôsobí ekumenicky.',
                1 => 'Organizuje Mužskú ekumenickú konferenciu, napríklad s témou Muž činu, muž odpočinku v kultúrnom dome vo Vrbovom. Konferencia trvá celý deň a spája prednášky, chvály a spoločenstvo. Pripravuje aj celodenné stretnutie Deň pri studni.',
                2 => 'Kanál je určený mužom a všetkým, ktorí hľadajú povzbudenie vo viere. Sledovaním budete vedieť o ďalších konferenciách. '.self::CLOSING.' Pri otázkach sa môžete obrátiť na kontakt uvedený v kanáli.',
            ],
        ],
        1880 => [
            'name' => 'Zariadenie pre seniorov a špecializované zariadenie VIVENTI',
            'append' => 'Kanál je určený klientom, ich rodinám a podporovateľom zariadenia. Sledovaním budete vedieť o podujatiach, napríklad o Dni seniorov. '.self::CLOSING,
        ],
        1876 => [
            'name' => 'Slovenská provincia Spoločnosti Ježišovej',
            'fix_municipality' => true,
            'address' => ['street' => 'Panská 11', 'postcode' => '814 99', 'municipality_id' => 242, 'latitude' => 48.1425126, 'longitude' => 17.1081336],
        ],
        1874 => [
            'name' => 'Farnosť Belá',
            'address' => ['street' => 'Cintorínska 297', 'postcode' => '013 05', 'municipality_id' => 105, 'latitude' => 49.2380058, 'longitude' => 18.9423196],
            'append' => 'Kanál je určený veriacim z Belej a pútnikom na Belskú púť. Sledovaním budete vedieť o slávnostiach farnosti. '.self::CLOSING,
        ],
        1871 => [
            'name' => 'Confraternity Sancti Iacobi',
            'address' => ['street' => 'Jasovská 9', 'postcode' => '040 11', 'municipality_id' => 1565, 'latitude' => 48.6869844, 'longitude' => 21.2075266],
        ],
        1870 => [
            'name' => 'Združenie obcí Termál',
            'body' => [
                0 => 'Združenie obcí Termál spája obce na juhozápadnom Slovensku. Spolupracuje pri rozvoji regiónu a cestovného ruchu. Podporuje aj miestne tradície.',
                1 => 'Spolu so samosprávami pripravuje Púť samospráv na pútnické miesto Studnička v Pozbe. Púť sa začína svätou omšou a stretávajú sa na nej starostovia aj obyvatelia obcí. Koná sa v lete.',
                2 => 'Kanál je určený obyvateľom regiónu a pútnikom. Sledovaním budete vedieť o púti samospráv a podujatiach združenia. '.self::CLOSING,
            ],
        ],
        1867 => [
            'name' => 'Rímskokatolícky cirkevný hudobný spolok sv. Mikuláša',
            'body' => [
                0 => 'Rímskokatolícky cirkevný hudobný spolok sv. Mikuláša pôsobí pri Bazilike sv. Mikuláša v Trnave. Zbor a orchester spievajú pri slávnostných bohoslužbách. Spolok je držiteľom ceny Fra Angelico.',
                1 => 'Uvádza diela chrámovej hudby, osobitne trnavského rodáka Mikuláša Schneidera-Trnavského. Koncertuje na festivaloch, napríklad na Trnavských organových dňoch či Cíferskej hudobnej jari. Pripravuje aj vianočné koncerty v bazilike.',
                2 => 'Kanál je určený milovníkom chrámovej hudby z Trnavy a okolia. Sledovaním budete vedieť o koncertoch spolku. '.self::CLOSING,
            ],
        ],
    ];

    public function up(): void
    {
        $emails = Schema::hasTable('canal_emails') ? app(CanalEmails::class) : null;
        $nationwide = Municipality::nationwideId();

        foreach (self::PROFILES as $id => $profile) {
            $canal = Canal::withTrashed()->find($id);

            if (! $canal || $canal->trashed() || $canal->merged_into_id !== null || $canal->claimed_at !== null
                || ($canal->name !== $profile['name'] && $canal->name !== ($profile['fix_name'] ?? null))) {
                continue;
            }

            $changes = [];

            if (isset($profile['fix_name']) && $canal->name === $profile['name']) {
                $changes['name'] = $profile['fix_name'];
            }

            // Prepíše sa len popis, ktorý ešte nemá tri odseky — upravený text ostane.
            $paragraphs = substr_count((string) $canal->body, '<p');

            if (isset($profile['body']) && $paragraphs !== 3) {
                $changes['body'] = implode('', array_map(fn (string $p) => '<p>'.e($p).'</p>', $profile['body']));
            } elseif (isset($profile['append']) && $paragraphs === 2) {
                $changes['body'] = rtrim((string) $canal->body).'<p>'.e($profile['append']).'</p>';
            }

            foreach (['website', 'phone'] as $field) {
                if (isset($profile[$field]) && trim((string) $canal->$field) === '') {
                    $changes[$field] = $profile[$field];
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

            if ($emails && ! empty($profile['emails'])) {
                $emails->discover($canal->fresh(), $profile['emails'], 'manual');
                $emails->refresh($canal->fresh());
            }
        }
    }

    public function down(): void
    {
        // Doplnené údaje sa nevracajú.
    }
};
