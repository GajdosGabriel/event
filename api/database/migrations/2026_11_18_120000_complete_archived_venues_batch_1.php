<?php

use App\Models\Venue;
use App\Services\Resources\ResourceMerger;
use App\Services\SystemLog\Recorder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Archivované miesta, 1. dávka (november 2026). Archivované sú miesta, ktoré
 * majú už len minulé podujatia (retire_draft_records_used_by_events). Z 732
 * je 528 zástupných miest celých obcí — tie sa nemenia. Zo zvyšných 204
 * konkrétnych miest, podľa počtu podujatí od najviac:
 *
 *  - 17 duplikátov sa zlúči: archivovaný zdroj do publikovaného miesta
 *    (ResourceMerger to po novom povoľuje — cieľ si ponechá svoj stav),
 *    alebo dva archivované medzi sebou (#98 → #58, #926 → #828),
 *  - miesta s aspoň dvoma podujatiami dostanú popis, skutočný názov, opravenú
 *    obec, PSČ a súradnice. Ostávajú archivované.
 *
 * Opravy: Ostrihomská bazilika, Kostol Sacré-Coeur (Brusel), Centrum slovenskej
 * kultúry (Nová Belá, Poľsko), San Giovanni Rotondo, Santa Maria Maggiore,
 * hotel Belvedere (Zakopané) a ďalšie mali obec alebo súradnice zo Slovenska.
 *
 * Popis sa prepíše len vtedy, keď ešte nemá tri odseky; ulica, PSČ a web sa
 * dopĺňajú do prázdneho poľa (okrem `fix_*`). Miesto musí sedieť ID aj názvom.
 */
return new class extends Migration
{
    private const OTHERS = 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.';

    /** Zahraničné mestá, ktoré v číselníku obcí ešte nie sú (vzor: Rím). */
    private const CITIES = [
        'zakopane' => ['name' => 'Zakopané', 'zip' => '34-500'],
        'ostrihom' => ['name' => 'Ostrihom', 'zip' => '2500'],
        'brusel' => ['name' => 'Brusel', 'zip' => '1000'],
        'nova-bela' => ['name' => 'Nová Belá (Poľsko)', 'zip' => '34-433'],
        'san-giovanni-rotondo' => ['name' => 'San Giovanni Rotondo', 'zip' => '71013'],
    ];

    /** @var array<int, array<string, mixed>> */
    private const VENUES = [
        927 => [
            'name' => 'balkón biskupskej rezidencie',
            'fix_name' => 'Biskupská rezidencia (Rožňava)',
            'body' => [
                0 => 'Biskupská rezidencia v Rožňave je sídlom rožňavského biskupa. Stojí v centre mesta neďaleko katedrály. Je sídlom Rožňavskej diecézy.',
                1 => 'Z balkóna rezidencie viedol biskup Stanislav Stolárik mariánsku pobožnosť, ktorú mohli veriaci sledovať online. Biskupstvo pripravuje aj ďalšie diecézne podujatia.',
                2 => self::OTHERS,
            ],
        ],
        929 => [
            'name' => 'Evanjelický kostol',
            'fix_name' => 'Evanjelický kostol (Banská Bystrica)',
            'body' => [
                0 => 'Evanjelický kostol na Lazovnej ulici je hlavný chrám Cirkevného zboru ECAV Banská Bystrica. Stojí v centre mesta. Postavili ho v 19. storočí.',
                1 => 'Konajú sa tu služby Božie, koncerty a slávnosti, ktoré niekedy prenáša aj RTVS. Kostol je otvorený pre všetkých.',
                2 => self::OTHERS,
            ],
        ],
        931 => [
            'name' => 'Chrám Narodenia presvätej Bohorodičky (Košice)',
            'fix_street' => 'Dominikánske námestie 2',
            'fix_postcode' => '040 01',
            'latitude' => 48.72053,
            'longitude' => 21.2528161,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Katedrálny chrám Narodenia Presvätej Bohorodičky je katedrálou Gréckokatolíckej eparchie Košice. Stojí na Dominikánskom námestí v Košiciach. Je sídelným chrámom košického eparchu.',
                1 => 'Konajú sa tu archijerejské liturgie, slávnosti eparchie a stretnutia mladých, napríklad Kvetný víkend Rady pre mládež. Chrám je otvorený veriacim aj návštevníkom.',
                2 => self::OTHERS,
            ],
        ],
        934 => [
            'name' => 'Kostol svätého Kríža (Devín)',
            'fix_website' => null,
            'body' => [
                0 => 'Kostol sv. Kríža je farský kostol v Bratislave-Devíne. Pôvodne gotická stavba bola viackrát prestavaná. Stojí neďaleko Devínskeho hradu.',
                1 => 'Konajú sa tu bohoslužby a cyrilo-metodské slávnosti, napríklad spomienka na výročie úmrtia sv. Konštantína-Cyrila, ktorú pripravuje združenie Dedičstvo otcov. Devín je spojený s veľkomoravskou históriou.',
                2 => self::OTHERS,
            ],
        ],
        935 => [
            'name' => 'Kostol Návštevy Panny Márie',
            'fix_name' => 'Kostol Navštívenia Panny Márie (milosrdní bratia)',
            'fix_street' => 'Námestie SNP 10',
            'fix_postcode' => '811 06',
            'latitude' => 48.1460496,
            'longitude' => 17.110459,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Kostol Navštívenia Panny Márie je kostol rehole milosrdných bratov na Námestí SNP v Bratislave. Pri ňom stojí ich nemocnica. Je to barokový kostol z 18. storočia.',
                1 => 'Konajú sa tu bohoslužby a slávnosti spojené so službou chorým, napríklad Svetový deň chorých. Kostol je otvorený veriacim aj pacientom nemocnice.',
                2 => self::OTHERS,
            ],
        ],
        936 => [
            'name' => 'Bazilika sv. Klementa',
            'fix_street' => 'Via di San Giovanni in Laterano',
            'fix_postcode' => '00184',
            'country' => 'Taliansko',
            'latitude' => 41.8891952,
            'longitude' => 12.4975379,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Bazilika sv. Klementa je starobylá bazilika v Ríme neďaleko Kolosea. Je v nej hrob sv. Cyrila, ktorý zomrel v Ríme v roku 869. Spravujú ju írski dominikáni.',
                1 => 'Každý rok 14. februára sa tu stretávajú slovanské národy pri slávnosti sv. Cyrila a Metoda. Na slávnosti sa zúčastňujú aj Slováci žijúci v Ríme.',
                2 => self::OTHERS,
            ],
        ],
        938 => [
            'name' => 'hotel Belvedere',
            'fix_name' => 'Hotel Belvedere (Zakopané)',
            'city' => 'zakopane',
            'fix_street' => 'Droga do Białego 3',
            'fix_postcode' => '34-500',
            'country' => 'Poľsko',
            'latitude' => 49.2848922,
            'longitude' => 19.9565909,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Hotel Belvedere je horský hotel v Zakopanom pod poľskými Tatrami. Ponúka ubytovanie a konferenčné priestory. Leží na okraji mesta pri Białej doline.',
                1 => 'Televízia LUX tu pripravila zimnú lyžovačku pre rodiny s deťmi. Pobyt spájal lyžovanie, oddych a program pre rodiny.',
                2 => self::OTHERS,
            ],
        ],
        941 => [
            'name' => 'Svätojakubská cesta na Slovensku',
            'body' => [
                0 => 'Svätojakubská cesta na Slovensku je značená pútnická trasa, ktorá je súčasťou siete ciest do Santiaga de Compostela. Vedie z Košíc cez Spiš a Liptov až do Bratislavy. Obnovuje ju združenie Confraternity Sancti Iacobi.',
                1 => 'Konajú sa tu spoločné putovania, napríklad Jacobeo, Jacobeo YOUTH, Jakubské trojdnie či Putovanie zmierenia. Pútnici dostávajú pútnický preukaz.',
                2 => self::OTHERS,
            ],
        ],
        111 => [
            'name' => 'Kostol sv. Arnolda Janssena',
            'fix_name' => 'Kostol sv. Arnolda Janssena (Petržalka)',
            'fix_postcode' => '851 01',
            'body' => [
                0 => 'Kostol sv. Arnolda Janssena je moderný kostol na Krupinskej ulici v Bratislave-Petržalke. Je zasvätený zakladateľovi misionárov verbistov. Slúži veriacim petržalských Dvorov.',
                1 => 'Konajú sa tu bohoslužby a stretnutia, napríklad sväté omše za bezdetných manželov a za slobodných so spoločenskými hrami, ktoré pripravuje tím SingleKatolici.sk.',
                2 => self::OTHERS,
            ],
        ],
        798 => [
            'name' => 'Bazilika Santa Maria Maggiore',
            'fix_postcode' => '00185',
            'country' => 'Taliansko',
            'fix_street' => 'Piazza di Santa Maria Maggiore',
            'latitude' => 41.8976275,
            'longitude' => 12.4984704,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Bazilika Santa Maria Maggiore je najväčší mariánsky chrám v Ríme a jedna zo štyroch hlavných pápežských bazilík. Uchováva ikonu Salus Populi Romani. Je v nej pochovaný pápež František.',
                1 => 'Konajú sa tu pápežské a slávnostné liturgie, napríklad omša k 30. výročiu samostatnosti Slovenska a Česka s kardinálom Parolinom. Výročie jej posviacky sa slávi 5. augusta.',
                2 => self::OTHERS,
            ],
        ],
        803 => [
            'name' => 'Katedrála svätého Františka Xaverského',
            'fix_name' => 'Katedrála sv. Františka Xaverského (Banská Bystrica)',
            'body' => [
                0 => 'Katedrála sv. Františka Xaverského stojí na Námestí SNP v Banskej Bystrici. Je katedrálou Banskobystrickej diecézy. Barokový chrám postavili jezuiti.',
                1 => 'Konajú sa tu diecézne slávnosti a bohoslužby, ktoré prenáša aj Rádio Lumen, napríklad duchovná cesta 30 (k)rokov smerom k Bohu.',
                2 => self::OTHERS,
            ],
        ],
        57 => [
            'name' => 'Studnička',
            'fix_name' => 'Pútnický areál Studnička (Nová Ves nad Žitavou)',
            'body' => [
                0 => 'Studnička je mariánsky pútnický areál pri Novej Vsi nad Žitavou. Je zasvätený Panne Márii Kráľovnej. Stará sa oň farnosť Nová Ves nad Žitavou – Slepčany.',
                1 => 'Konajú sa tu púte, napríklad Púť k Panne Márii Kráľovnej, púť hasičov či púť samospráv. Púte bývajú v lete.',
                2 => self::OTHERS,
            ],
        ],
        791 => [
            'name' => 'San Giovanni Rotondo',
            'fix_name' => 'San Giovanni Rotondo (Taliansko)',
            'city' => 'san-giovanni-rotondo',
            'fix_postcode' => '71013',
            'country' => 'Taliansko',
            'latitude' => 41.7067,
            'longitude' => 15.7036,
            'coordinates_source' => 'municipality',
            'fix_coordinates' => true,
            'body' => [
                0 => 'San Giovanni Rotondo je pútnické mesto v Apúlii v Taliansku. Pôsobil a je tu pochovaný sv. páter Pio z Pietrelciny.',
                1 => 'Smerujú sem púte zo Slovenska, napríklad púte po stopách sv. pátra Pia, sv. Mikuláša a sv. Michala archanjela.',
                2 => self::OTHERS,
            ],
        ],
        922 => [
            'name' => 'Kostol Povýšenia svätého Kríža (Ružomberok)',
            'fix_website' => null,
            'body' => [
                0 => 'Kostol Povýšenia sv. Kríža je kostol na Námestí Andreja Hlinku v Ružomberku. Stojí v historickom centre mesta.',
                1 => 'Konajú sa tu bohoslužby a slávnosti, ktoré niekedy prenáša aj televízia.',
                2 => self::OTHERS,
            ],
        ],
        40 => [
            'name' => 'Bobrovská kalvária',
            'fix_name' => 'Bobrovská kalvária (Bobrov)',
            'body' => [
                0 => 'Bobrovská kalvária je pútnické miesto nad obcou Bobrov na Orave. Krížová cesta vedie ku kaplnke na kopci.',
                1 => 'Konajú sa tu púte, napríklad Púť rodín Spišskej diecézy a púť detí a rodín farnosti Bobrov.',
                2 => self::OTHERS,
            ],
        ],
        58 => [
            'name' => 'Bazilika svätého Egídia (Bardejov)',
            'fix_website' => null,
            'body' => [
                0 => 'Bazilika sv. Egídia je gotický farský kostol na Radničnom námestí v Bardejove. Je súčasťou pamiatky UNESCO. Uchováva vzácne gotické krídlové oltáre.',
                1 => 'Konajú sa tu bohoslužby a koncerty, napríklad medzinárodný festival Organové dni Jozefa Grešáka.',
                2 => self::OTHERS,
            ],
        ],
        84 => [
            'name' => 'Kláštor Školských sestier de Notre Dame',
            'fix_name' => 'Kláštor Školských sestier de Notre Dame (Beckov)',
            'body' => [
                0 => 'Kláštor Školských sestier de Notre Dame stojí v Beckove pod hradom. Sestry sa venujú výchove a vzdelávaniu.',
                1 => 'Pozývajú dievčatá od 16 do 35 rokov na stretnutia v kláštore a pripomínajú si aj Akciu R, likvidáciu ženských reholí v roku 1950.',
                2 => self::OTHERS,
            ],
        ],
        746 => [
            'name' => 'Bazilika svätého Eugena',
            'fix_name' => 'Bazilika sv. Eugena (Rím)',
            'fix_postcode' => '00197',
            'country' => 'Taliansko',
            'body' => [
                0 => 'Bazilika sv. Eugena je kostol v rímskej štvrti Parioli. Spravuje ju prelatúra Opus Dei.',
                1 => 'Konajú sa tu kňazské a diakonské vysviacky členov Opus Dei.',
                2 => self::OTHERS,
            ],
        ],
        758 => [
            'name' => 'Kostol Sacré-Coeur',
            'fix_name' => 'Kostol Sacré-Coeur (Brusel)',
            'city' => 'brusel',
            'country' => 'Belgicko',
            'fix_postcode' => '1000',
            'latitude' => 50.8466,
            'longitude' => 4.3528,
            'coordinates_source' => 'municipality',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Kostol Sacré-Coeur v Bruseli je miestom, kde sa stretáva Slovenská katolícka misia v Bruseli.',
                1 => 'Konajú sa tu slovenské sväté omše a výročia misie, napríklad s biskupom Františkom Rábekom.',
                2 => self::OTHERS,
            ],
        ],
        772 => [
            'name' => 'Bazilika Nanebovzatia Panny Márie a sv. Vojtecha',
            'fix_name' => 'Bazilika Nanebovzatia Panny Márie a sv. Vojtecha (Ostrihom)',
            'city' => 'ostrihom',
            'country' => 'Maďarsko',
            'fix_street' => 'Szent István tér 1',
            'fix_postcode' => '2500',
            'latitude' => 47.7991083,
            'longitude' => 18.736538,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Ostrihomská bazilika je najväčší kostol Maďarska a sídlo ostrihomsko-budapeštianskeho arcibiskupa. Stojí na hradnom vrchu nad Dunajom oproti Štúrovu.',
                1 => 'Smerujú sem púte zo Slovenska, napríklad jubilejná celodiecézna púť Spišskej diecézy pri 250. výročí diecézy. Ostrihom bol kedysi sídlom arcidiecézy, ku ktorej patrila väčšina Slovenska.',
                2 => self::OTHERS,
            ],
        ],
        810 => [
            'name' => 'Kúpele Nimnica',
            'body' => [
                0 => 'Kúpele Nimnica ležia pri Púchove a Považskej Bystrici. Ponúkajú liečebné pobyty a priestory na konferencie.',
                1 => 'Konajú sa tu kresťanské pobyty a konferencie, napríklad kúpeľný pobyt divákov TV LUX so svätou omšou v priamom prenose.',
                2 => self::OTHERS,
            ],
        ],
        812 => [
            'name' => 'Centrum slovenskej kultúry',
            'fix_name' => 'Centrum slovenskej kultúry (Nová Belá, Poľsko)',
            'city' => 'nova-bela',
            'country' => 'Poľsko',
            'fix_street' => 'Świętej Katarzyny 2',
            'fix_postcode' => '34-433',
            'latitude' => 49.440858,
            'longitude' => 20.1436419,
            'coordinates_source' => 'venue',
            'fix_coordinates' => true,
            'body' => [
                0 => 'Centrum slovenskej kultúry v Novej Belej slúži slovenskej menšine na poľskom Spiši. Ponúka aj ubytovanie.',
                1 => 'Konajú sa tu cyrilometodské slávnosti a výstavy, napríklad filatelistická výstava Živé cyrilometodské dedičstvo s Cyrilometodiadou.',
                2 => self::OTHERS,
            ],
        ],
        828 => [
            'name' => 'Kostol Sedembolestnej Panny Márie (Pohorelá)',
            'fix_postcode' => '831 07',
            'fix_website' => null,
            'body' => [
                0 => 'Kostol Sedembolestnej Panny Márie je farský kostol v Bratislave-Vajnoroch. Vajnory sú rodiskom blahoslaveného Titusa Zemana.',
                1 => 'Konajú sa tu bohoslužby a výročné slávnosti blahorečenia Titusa Zemana, ktoré pripravujú saleziáni a farnosť Vajnory.',
                2 => self::OTHERS,
            ],
        ],
        848 => [
            'name' => 'Teatro Colorato',
            'fix_name' => 'Teatro Colorato (Bratislava)',
            'body' => [
                0 => 'Teatro Colorato je divadelný a koncertný priestor na Františkánskom námestí v Bratislave.',
                1 => 'Konajú sa tu koncerty a talkshow, napríklad Adam, kde si? Bratislavskej arcidiecézy či Vianočná Janais.',
                2 => self::OTHERS,
            ],
        ],
        860 => [
            'name' => 'Gymnázium Svätej Rodiny',
            'fix_name' => 'Gymnázium Svätej Rodiny (Petržalka)',
            'fix_postcode' => '851 01',
            'body' => [
                0 => 'Gymnázium Svätej Rodiny je súčasťou Spojenej školy Svätej Rodiny na Záporožskej ulici v Bratislave-Petržalke. Je to katolícka škola.',
                1 => 'Spolu s farnosťou Povýšenia Svätého Kríža pripravuje modlitby a súťaže, napríklad novénu k Panne Márii Fatimskej za Ukrajinu.',
                2 => self::OTHERS,
            ],
        ],
        866 => [
            'name' => 'Katedrála svätého Šebastiána',
            'fix_name' => 'Katedrála sv. Šebastiána (Bratislava)',
            'body' => [
                0 => 'Katedrála sv. Šebastiána je katedrálou Ordinariátu ozbrojených síl a ozbrojených zborov SR. Stojí na Peknej ceste v Bratislave-Krasňanoch.',
                1 => 'Konajú sa tu slávnosti ordinariátu a spomienky, napríklad na výročie súdneho procesu s biskupmi Vojtaššákom, Gojdičom a Buzalkom.',
                2 => self::OTHERS,
            ],
        ],
        874 => [
            'name' => 'evanjelický kostol',
            'fix_name' => 'Evanjelický kostol (Žilina)',
            'body' => [
                0 => 'Evanjelický kostol stojí na Námestí Žilinskej synody v Žiline. Patrí Cirkevnému zboru ECAV Žilina. Námestie pripomína synodu z roku 1610.',
                1 => 'Konajú sa tu služby Božie, ktoré niekedy vysiela rozhlas.',
                2 => self::OTHERS,
            ],
        ],
        901 => [
            'name' => 'Seminár (katolíci)',
            'fix_name' => 'Kňazský seminár sv. Gorazda (Nitra)',
            'fix_website' => null,
            'body' => [
                0 => 'Kňazský seminár sv. Gorazda v Nitre pripravuje kandidátov kňazstva viacerých diecéz. Stojí na Samovej ulici pod Nitrianskym hradom.',
                1 => 'Pozýva mladých mužov na štúdium teológie. V jeho priestoroch sa konajú aj obnovy, napríklad pre bezdetné manželské páry.',
                2 => self::OTHERS,
            ],
        ],
        908 => [
            'name' => 'Slovenský rozhlas',
            'fix_name' => 'Slovenský rozhlas (Bratislava)',
            'body' => [
                0 => 'Budova Slovenského rozhlasu v tvare obrátenej pyramídy stojí na Mýtnej ulici v Bratislave. Je v nej aj Veľké koncertné štúdio.',
                1 => 'Konajú sa tu koncerty a podujatia, napríklad koncerty Ars ante portas či Festival slobody.',
                2 => self::OTHERS,
            ],
        ],
        913 => [
            'name' => 'Kaplnka Matky vteleného Slova',
            'fix_name' => 'Kaplnka Matky vteleného Slova (Rádio Mária)',
            'body' => [
                0 => 'Kaplnka Matky vteleného Slova je kaplnka Rádia Mária Slovensko v Bratislave. Rádio z nej vysiela sväté omše a modlitby.',
                1 => 'Rádio Mária odtiaľ vysiela bohoslužby, najmä v časoch, keď kostoly nemohli prijímať veriacich.',
                2 => self::OTHERS,
            ],
        ],
    ];

    /** Zdroj → cieľ: ten istý priestor pod inými názvami. */
    private const MERGES = [
        940 => [
            0 => 986,
            1 => 'Centrum pre rodinu - Sigord',
            2 => 'Centrum pre rodinu Sigord',
        ],
        43 => [
            0 => 918,
            1 => 'Sanktuárium Božieho Milosrdenstva',
            2 => 'Skalné sanktuárium Božieho milosrdenstva',
        ],
        894 => [
            0 => 727,
            1 => 'Bazilika Božieho milosrdenstva',
            2 => 'Kostol Božieho milosrdenstva (Košice-KVP)',
        ],
        943 => [
            0 => 998,
            1 => 'farský kostol sv. Martina z Tours',
            2 => 'Kostol sv. Martina z Tours (Bošany)',
        ],
        103 => [
            0 => 850,
            1 => 'Svätyňa Božieho milosrdenstva',
            2 => 'Diecézna svätyňa Božieho milosrdenstva',
        ],
        811 => [
            0 => 967,
            1 => 'Konkatedrála Sedembolestnej Panny Márie',
            2 => 'Konkatedrála Sedembolestnej Panny Márie (Poprad)',
        ],
        755 => [
            0 => 787,
            1 => 'Komunita blahoslavenstiev',
            2 => 'Komunita Blahoslavenstiev (Okoličné)',
        ],
        794 => [
            0 => 116,
            1 => 'Bazilika sv. Benedikta',
            2 => 'Kláštor Hronský Beňadik',
        ],
        28 => [
            0 => 1061,
            1 => 'Aula Pazmaneum',
            2 => 'Aula Pázmaneum Trnavskej univerzity',
        ],
        932 => [
            0 => 982,
            1 => 'priestranstvo pred Bazilikou sv. Petra',
            2 => 'Námestie sv. Petra',
        ],
        818 => [
            0 => 715,
            1 => 'Kostol Najsvätejšieho Spasiteľa (Bratislava)',
            2 => 'Jezuitský kostol Najsvätejšieho Spasiteľa (Bratislava)',
        ],
        802 => [
            0 => 107,
            1 => 'Kostol svätého Antona Paduánskeho (Košice)',
            2 => 'Seminárny kostol na Hlavnej 89',
        ],
        923 => [
            0 => 108,
            1 => 'Spišská Kapitula',
            2 => 'Spišská Kapitula',
        ],
        796 => [
            0 => 1005,
            1 => 'Dom prijatia pre rodiny Rodinkovo',
            2 => 'Rodinkovo, Beluša',
        ],
        834 => [
            0 => 104,
            1 => 'bazilika minor',
            2 => 'Bazilika Sedembolestnej Panny Márie',
        ],
        98 => [
            0 => 58,
            1 => 'Bazilika minor sv. Egídia',
            2 => 'Bazilika svätého Egídia (Bardejov)',
        ],
        926 => [
            0 => 828,
            1 => 'Kostol Sedembolestnej Panny Márie (Vajnory)',
            2 => 'Kostol Sedembolestnej Panny Márie (Pohorelá)',
        ],
    ];

    public function up(): void
    {
        foreach (self::VENUES as $id => $data) {
            $venue = Venue::query()->find($id);

            if (! $venue || ($venue->name !== $data['name'] && $venue->name !== ($data['fix_name'] ?? null))) {
                continue;
            }

            $changes = [];

            if (isset($data['fix_name']) && $venue->name === $data['name']) {
                $changes['name'] = $data['fix_name'];
            }

            if (isset($data['body']) && substr_count((string) $venue->body, '<p') !== 3) {
                $changes['body'] = implode('', array_map(fn (string $p) => '<p>'.e($p).'</p>', $data['body']));
            }

            foreach (['street', 'postcode', 'website', 'phone', 'email'] as $field) {
                if (array_key_exists('fix_'.$field, $data)) {
                    if ($data['fix_'.$field] !== $venue->$field) {
                        $changes[$field] = $data['fix_'.$field];
                    }
                } elseif (isset($data[$field]) && trim((string) $venue->$field) === '') {
                    $changes[$field] = $data[$field];
                }
            }

            if (isset($data['country']) && $venue->country !== $data['country']) {
                $changes['country'] = $data['country'];
            }

            $villageId = isset($data['city']) ? $this->city($data['city']) : ($data['fix_village_id'] ?? null);
            if ($villageId !== null && (int) $venue->village_id !== $villageId) {
                $changes['village_id'] = $villageId;
            }

            $precise = in_array($venue->coordinates_source, ['venue', 'address', 'manual'], true);
            if (isset($data['latitude']) && (! $precise || ! empty($data['fix_coordinates']))) {
                $changes += ['latitude' => $data['latitude'], 'longitude' => $data['longitude'], 'coordinates_source' => $data['coordinates_source']];
            }

            if ($changes !== []) {
                $venue->forceFill($changes)->save();
                Recorder::info('venues', 'profile_described', "Miesto {$venue->name}: doplnené ".implode(', ', array_keys($changes)),
                    status: 'ok', subject: $venue, context: ['fields' => array_keys($changes)]);
            }
        }

        foreach (self::MERGES as $sourceId => [$targetId, $sourceName, $targetName]) {
            $source = Venue::query()->find($sourceId);
            $target = Venue::query()->find($targetId);
            if (! $source || ! $target || $source->name !== $sourceName || $target->name !== $targetName) {
                continue;
            }
            try {
                app(ResourceMerger::class)->merge('venues', $sourceId, $targetId, null);
            } catch (Throwable $e) {
                Recorder::warning('admin', 'resource.merge_skipped', "Zlúčenie miesta #{$sourceId} → #{$targetId} preskočené",
                    subject: $target, context: ['error' => mb_substr($e->getMessage(), 0, 250)]);
            }
        }
    }

    /** Zahraničné mesto v číselníku obcí (bez okresu a kraja), vytvorí sa raz. */
    private function city(string $slug): int
    {
        $city = DB::table('municipalities')->where('slug', $slug)->first();
        if ($city !== null) {
            return (int) $city->id;
        }

        return (int) DB::table('municipalities')->insertGetId([
            'fullname' => self::CITIES[$slug]['name'], 'shortname' => self::CITIES[$slug]['name'], 'slug' => $slug,
            'zip' => self::CITIES[$slug]['zip'], 'district_id' => 0, 'region_id' => 0, 'use' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Doplnené údaje sa nevracajú.
    }
};
