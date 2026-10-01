<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Presunie podujatia zo zberného kanála `tkkbs.sk` (id=91) k ich skutočným
 * organizátorom.
 *
 * Kanál 91 drží 1 568 podujatí (1 532 z hlascirkvi.sk, 36 z tkkbs.sk) — AI
 * detektor pri nich meno organizátora nenašiel. Prehliadka titulkov a
 * `ai_detector.event_payload.organizer` ukázala dve skupiny:
 *
 *  1. Organizátor už má v DB vlastný kanál (TV LUX, diecézy, Biskup Haľko,
 *     Verbisti, EKC, Slovensko na kolenách, EMfest, nm.sk, …) — podujatie sa
 *     len presunie, nový kanál sa nezakladá (v DB je aj tak veľa duplicít,
 *     pozri 2026_09_22_230000_merge_duplicate_organization_canals.php).
 *  2. Organizátor kanál nemá — založia sa dva nové: „Hrdí na rodinu“
 *     (hrdinarodinu.sk) a „ClipTime“ (cliptime.sk). Ich údaje sú z ich
 *     oficiálnych webov (30. 9. 2026); e-mail Hrdí na rodinu je osobná
 *     adresa, preto sa neuvádza, ClipTime žiadny kontakt na webe nemá.
 *     Sídlo je obec Bratislava (súradnice obce, coordinates_source=municipality).
 *
 * Podujatie sa presunie len keď je stále na kanáli 91 a zhoduje sa `id` aj
 * `orginal_source`; cieľový kanál sa musí zhodovať v `id` aj `name`. Čo medzitým
 * niekto upravil ručne, ostane nedotknuté. down() vráti podujatia na kanál 91
 * (len tie, čo sú stále na cieľovom kanáli) a zmaže dva nové kanály, ak
 * na nich nezostali žiadne iné podujatia.
 */
return new class extends Migration
{
    private const COLLECTION_CANAL_ID = 91;

    private const BRATISLAVA_ID = 242;

    private const BRATISLAVA_LAT = 48.1516988;

    private const BRATISLAVA_LNG = 17.1093063;

    /**
     * Nové kanály: slug => údaje a podujatia (id => orginal_source).
     *
     * @var array<string, array{name: string, website: string, body: string, events: array<int, string>}>
     */
    private const NEW_CANALS = [
        'hrdi-na-rodinu' => [
            'name' => 'Hrdí na rodinu',
            'website' => 'https://hrdinarodinu.sk',
            'body' => '<p>Národný pochod Hrdí na rodinu je verejné podujatie pre všetkých, ktorí sa spolu s organizátormi domnievajú, že otec a mama nastálo sú to najlepšie pre deti. Koná sa každoročne v Bratislave na Jakubovom námestí a jeho poslaním je šíriť radosť, ktorú rodina a manželstvo dávajú deťom aj manželom, a povzbudiť ľudí, aby prekonávali ťažkosti v rodinách. Súčasťou je program, sväté omše a diskusie; k zapojeniu sa môže každý pomôcť vyvesením plagátu, službou na mieste alebo šírením informácií.</p>',
            'events' => [
                10819 => 'https://www.tkkbs.sk/view.php?cisloclanku=20260710009',
            ],
        ],
        'cliptime' => [
            'name' => 'ClipTime',
            'website' => 'https://cliptime.sk',
            'body' => '<p>ClipTime je diecézne stretnutie mládeže Bratislavskej arcidiecézy. Mladí sa na ňom stretávajú so svojimi biskupmi pri prednáškach a diskusiách o aktuálnych témach, kreatívnych workshopoch, modlitbe a hudbe; festival sa konal aj v Marianke pri Bratislave a program býva doplnený Nocou kostolov.</p>',
            'events' => [
                2781 => 'https://hlascirkvi.sk/akcie/2712/v-marianke-pri-bratislave-bude-festival-pre-mladych-s-nazvom-cliptime',
            ],
        ],
    ];

    /**
     * Presuny k už existujúcim kanálom: canal_id => [name, events[id => orginal_source]].
     *
     * @var array<int, array{name: string, events: array<int, string>}>
     */
    private const EXISTING = [
        524 => [
            'name' => 'Televízia LUX',
            'events' => [
                228 => 'https://www.tkkbs.sk/view.php?cisloclanku=20260825017',
                413 => 'https://hlascirkvi.sk/akcie/163/televizia-lux-prinesie-premieru-dokofilmu-o-biskupovi-vojtassakovi',
                514 => 'https://hlascirkvi.sk/akcie/272/televizia-lux-odvysiela-mimoriadnu-relaciu-o-boji-s-koronavirusom',
                525 => 'https://hlascirkvi.sk/akcie/284/tv-lux-ponuka-priestor-pre-otazky-v-novej-relacii-sme-tu-pre-vas',
                542 => 'https://hlascirkvi.sk/akcie/302/tv-lux-prinesie-na-sviatky-prenosy-z-rima-svatej-zeme-i-slovenska',
                648 => 'https://hlascirkvi.sk/akcie/426/televizia-lux-a-radio-lumen-prinesu-biskupsku-vysviacku-jana-kubosa',
                984 => 'https://hlascirkvi.sk/akcie/779/tv-lux-prinesie-mimoriadne-relaciu-jeden-na-jedneho-s-predsedom-kbs',
                1111 => 'https://hlascirkvi.sk/akcie/915/televizia-lux-ponukne-divakom-seriu-prenosov-z-cesty-papeza-v-iraku',
                1322 => 'https://hlascirkvi.sk/akcie/1217/tv-lux-a-radio-lumen-prinesu-prenosy-z-knazskych-vysviacok',
                1599 => 'https://hlascirkvi.sk/akcie/1504/tv-lux-prinasa-novy-dokumentarny-cyklus-papez-frantisek-prichadza',
                1637 => 'https://hlascirkvi.sk/akcie/1543/tv-lux-prinasa-vo-vysielani-novu-seriu-rozhovorov-papezske-cesty',
                1648 => 'https://hlascirkvi.sk/akcie/1554/tv-lux-uvedie-novu-cast-cyklu-s-nazvom-papez-frantisek-prichadza',
                1678 => 'https://hlascirkvi.sk/akcie/1587/televizia-lux-prinasa-seriu-myslienok-s-nazvom-frantisek-ti-odkazuje',
                1692 => 'https://hlascirkvi.sk/akcie/1601/tv-lux-odvysiela-mimoriadnu-relaciu-jeden-na-jedneho-o-registracii',
                1813 => 'https://hlascirkvi.sk/akcie/1723/tv-lux-a-rtvs-chystaju-seriu-prenosov-z-navstevy-papeza-frantiska',
                1928 => 'https://hlascirkvi.sk/akcie/1839/tv-lux-spustila-novy-rocnik-sutaze-amaterskych-filmarov-safi-2021',
                2121 => 'https://hlascirkvi.sk/akcie/2034/televizia-lux-prinesie-prenosy-so-svatym-otcom-z-assisi-a-z-vatikanu',
                2183 => 'https://hlascirkvi.sk/akcie/2097/tv-lux-bude-vysielat-roraty-prvym-bude-predsedat-biskup-rabek',
                2212 => 'https://hlascirkvi.sk/akcie/2126/televizia-lux-zaradila-do-vysielania-opat-adoracie-z-baziliky-v-sastine',
                3125 => 'https://hlascirkvi.sk/akcie/3063/tv-lux-a-radio-lumen-opat-prinesu-prenosy-z-knazskych-vysviacok',
                3204 => 'https://hlascirkvi.sk/akcie/3145/tv-lux-a-radio-lumen-ponuknu-cez-vikend-tri-prenosy-z-vysviacok',
                3340 => 'https://hlascirkvi.sk/akcie/3299/televizia-lux-odvysiela-seriu-prenosov-z-navstevy-papeza-v-kanade',
                3524 => 'https://hlascirkvi.sk/akcie/3490/tv-lux-oslovuje-divakov-aby-jej-poskytli-spatnu-vazbu-k-vysielaniu',
                3566 => 'https://hlascirkvi.sk/akcie/3536/tv-lux-prinasa-priame-prenosy-a-zaznamy-z-cesty-papeza-v-kazachstane',
                3796 => 'https://hlascirkvi.sk/akcie/3770/tv-lux-prinesie-po-tragedii-v-bratislave-specialnu-relaciu-sme-tu-pre-vas',
                3921 => 'https://hlascirkvi.sk/akcie/3895/tv-lux-odvysiela-priamy-prenos-omse-zo-svatomartinskeho-trojdnia',
                4166 => 'https://hlascirkvi.sk/akcie/4143/televizia-lux-chysta-v-pondelok-studio-special-s-nazvom-benedikt-xvi',
                4240 => 'https://www.tkkbs.sk/view.php?cisloclanku=20230118017',
                4433 => 'https://www.tkkbs.sk/view.php?cisloclanku=20230217024',
                4601 => 'https://www.tkkbs.sk/view.php?cisloclanku=20230315025',
                4669 => 'https://www.tkkbs.sk/view.php?cisloclanku=20230327025',
                4740 => 'https://www.tkkbs.sk/view.php?cisloclanku=20230403033',
                4841 => 'https://www.tkkbs.sk/view.php?cisloclanku=20230426015',
                4855 => 'https://www.tkkbs.sk/view.php?cisloclanku=20230428033',
                5281 => 'https://www.tkkbs.sk/view.php?cisloclanku=20230628033',
                5283 => 'https://www.tkkbs.sk/view.php?cisloclanku=20230628041',
                5400 => 'https://www.tkkbs.sk/view.php?cisloclanku=20230721027',
                5407 => 'https://www.tkkbs.sk/view.php?cisloclanku=20230724022',
                5460 => 'https://www.tkkbs.sk/view.php?cisloclanku=20230809017',
                5472 => 'https://www.tkkbs.sk/view.php?cisloclanku=20230810018',
                5702 => 'https://www.tkkbs.sk/view.php?cisloclanku=20230919034',
                5888 => 'https://www.tkkbs.sk/view.php?cisloclanku=20231011034',
                5935 => 'https://www.tkkbs.sk/view.php?cisloclanku=20231017024',
                6523 => 'https://www.tkkbs.sk/view.php?cisloclanku=20240212032',
                6960 => 'https://www.tkkbs.sk/view.php?cisloclanku=20240507018',
                7155 => 'https://www.tkkbs.sk/view.php?cisloclanku=20240614034',
                7319 => 'https://www.tkkbs.sk/view.php?cisloclanku=20240730030',
                7388 => 'https://www.tkkbs.sk/view.php?cisloclanku=20240814043',
                7415 => 'https://www.tkkbs.sk/view.php?cisloclanku=20240822025',
                7673 => 'https://www.tkkbs.sk/view.php?cisloclanku=20241001019',
                7690 => 'https://www.tkkbs.sk/view.php?cisloclanku=20241003049',
                7740 => 'https://www.tkkbs.sk/view.php?cisloclanku=20241010039',
                7807 => 'https://www.tkkbs.sk/view.php?cisloclanku=20241025044',
                8068 => 'https://www.tkkbs.sk/view.php?cisloclanku=20241212052',
                8185 => 'https://www.tkkbs.sk/view.php?cisloclanku=20250113030',
                8733 => 'https://www.tkkbs.sk/view.php?cisloclanku=20250423261',
                8862 => 'https://www.tkkbs.sk/view.php?cisloclanku=20250516022',
                8895 => 'https://www.tkkbs.sk/view.php?cisloclanku=20250522004',
                9032 => 'https://www.tkkbs.sk/view.php?cisloclanku=20250617031',
                9043 => 'https://www.tkkbs.sk/view.php?cisloclanku=20250618025',
                9316 => 'https://www.tkkbs.sk/view.php?cisloclanku=20250901017',
                9351 => 'https://www.tkkbs.sk/view.php?cisloclanku=20250904004',
                9876 => 'https://www.tkkbs.sk/view.php?cisloclanku=20251231005',
                9908 => 'https://www.tkkbs.sk/view.php?cisloclanku=20260108024',
                10005 => 'https://www.tkkbs.sk/view.php?cisloclanku=20260130012',
                10021 => 'https://www.tkkbs.sk/view.php?cisloclanku=20260204020',
                10156 => 'https://www.tkkbs.sk/view.php?cisloclanku=20260304014',
                10197 => 'https://www.tkkbs.sk/view.php?cisloclanku=20260311009',
                10500 => 'https://www.tkkbs.sk/view.php?cisloclanku=20260512014',
                10503 => 'https://www.tkkbs.sk/view.php?cisloclanku=20260512026',
                10690 => 'https://www.tkkbs.sk/view.php?cisloclanku=20260605018',
                10751 => 'https://www.tkkbs.sk/view.php?cisloclanku=20260623017',
                10782 => 'https://www.tkkbs.sk/view.php?cisloclanku=20260630035',
            ],
        ],
        998 => [
            'name' => 'Bazilika Sedembolestnej Panny Márie v Šaštíne',
            'events' => [
                318 => 'https://www.tkkbs.sk/view.php?cisloclanku=20260905002',
                8284 => 'https://www.tkkbs.sk/view.php?cisloclanku=20250129052',
            ],
        ],
        1745 => [
            'name' => 'Organizačný tím Púte zaľúbených',
            'events' => [
                416 => 'https://hlascirkvi.sk/akcie/166/pri-narodnej-svatyni-v-sastine-sa-uskutocni-13-rocnik-pute-zalubenych',
            ],
        ],
        602 => [
            'name' => 'Pastoračné centrum Anny Kolesárovej – Domček',
            'events' => [
                452 => 'https://hlascirkvi.sk/akcie/204/vo-vysokej-nad-uhom-bude-celodenna-adoracia-den-s-mamkou-mariou',
                8665 => 'https://www.tkkbs.sk/view.php?cisloclanku=20250409050',
            ],
        ],
        418 => [
            'name' => 'Biskupi Slovenska',
            'events' => [
                488 => 'https://hlascirkvi.sk/akcie/242/biskupi-slovenska-sa-stretnu-na-95-plenarnom-zasadnuti-v-cicmanoch',
                5046 => 'https://www.tkkbs.sk/view.php?cisloclanku=20230526001',
            ],
        ],
        36 => [
            'name' => 'Spišská diecéza',
            'events' => [
                677 => 'https://hlascirkvi.sk/akcie/455/spisska-dieceza-opat-pripomenie-pamatny-den-biskupa-jana-vojtassaka',
                2027 => 'https://hlascirkvi.sk/akcie/1938/spisska-dieceza-si-pripomenie-1-vyrocie-umrtianbspbiskupanbspstefana-secku',
                9645 => 'https://www.tkkbs.sk/view.php?cisloclanku=20251106015',
                9904 => 'https://www.tkkbs.sk/view.php?cisloclanku=20260108016',
                10958 => 'https://www.tkkbs.sk/view.php?cisloclanku=20260917005',
                10998 => 'https://www.tkkbs.sk/view.php?cisloclanku=20260921005',
            ],
        ],
        929 => [
            'name' => 'Kustód Svätej zeme',
            'events' => [
                739 => 'https://hlascirkvi.sk/akcie/518/kustod-svatej-zeme-pozyva-darcov-k-stedrosti-pri-zbierke-13-septembra',
            ],
        ],
        1248 => [
            'name' => 'Katolícke spoločenstvo EMfest tím',
            'events' => [
                748 => 'https://hlascirkvi.sk/akcie/527/marianske-stare-hory-budu-strnasty-raz-dejiskom-festivalu-emfest',
                3202 => 'https://hlascirkvi.sk/akcie/3143/stare-hory-prinasaju-pokoj-medzugoria-chysta-sa-tam-podujatie-emfest',
                5302 => 'https://www.tkkbs.sk/view.php?cisloclanku=20230704021',
            ],
        ],
        516 => [
            'name' => 'Sestry dominikánky',
            'events' => [
                799 => 'https://hlascirkvi.sk/akcie/580/sestry-dominikanky-pozyvaju-dievcata-na-kurz-povolanym-nablizku',
            ],
        ],
        568 => [
            'name' => 'Trnavská arcidiecéza',
            'events' => [
                950 => 'https://hlascirkvi.sk/akcie/739/trnavska-arcidieceza-prebera-stafetu-slavenia-svatych-omsi-za-zivot',
                8868 => 'https://www.tkkbs.sk/view.php?cisloclanku=20250519002',
            ],
        ],
        765 => [
            'name' => 'Biskup Haľko',
            'events' => [
                1146 => 'https://hlascirkvi.sk/akcie/956/biskup-halko-bude-rozjimat-online-nad-zastaveniami-krizovej-cesty',
                1485 => 'https://hlascirkvi.sk/akcie/1386/biskup-halko-zverejni-v-lete-62-ilustrovanych-impulzov-bozej-prirody',
                2187 => 'https://hlascirkvi.sk/akcie/2101/biskup-halko-pozyva-k-novej-iniciative-vecerneho-upokojenia-srdca',
                5418 => 'https://www.tkkbs.sk/view.php?cisloclanku=20230726028',
                9186 => 'https://www.tkkbs.sk/view.php?cisloclanku=20250720001',
                10836 => 'https://www.tkkbs.sk/view.php?cisloclanku=20260715021',
            ],
        ],
        346 => [
            'name' => 'Festival Lumen',
            'events' => [
                1260 => 'https://hlascirkvi.sk/akcie/1151/festival-lumen-prinesie-do-trnavy-atmosferu-cez-projekt-behind-the-walls',
            ],
        ],
        125 => [
            'name' => 'Augustiniáni',
            'events' => [
                1735 => 'https://hlascirkvi.sk/akcie/1644/augustiniani-pozyvaju-nanbsptrojdnie-ktorymnbsposlavia-svojich-svatych',
            ],
        ],
        1873 => [
            'name' => 'Rímskokatolícka farnosť Košice – Dóm',
            'events' => [
                1916 => 'https://hlascirkvi.sk/akcie/1827/v-dome-sv-alzbety-v-kosiciach-pozyvaju-na-druhu-hieronymovu-noc',
            ],
        ],
        838 => [
            'name' => 'Dobrá novina',
            'events' => [
                1959 => 'https://hlascirkvi.sk/akcie/1870/dobra-novina-otvara-27-rocnik-s-mottom-podme-nieco-dobre-podniknut',
            ],
        ],
        1836 => [
            'name' => 'Kysucká knižnica v Čadci',
            'events' => [
                2003 => 'https://hlascirkvi.sk/akcie/1914/kysucka-kniznica-vnbspcadci-otvorila-vystavu-s-madonou-na-kysuciach',
            ],
        ],
        1418 => [
            'name' => 'Červená streda',
            'events' => [
                2116 => 'https://hlascirkvi.sk/akcie/2029/cervena-streda-poukaze-na-porusovanie-prav-na-slobodu-vierovyznania',
            ],
        ],
        1540 => [
            'name' => 'Pro-life organizácie Slovenska',
            'events' => [
                2162 => 'https://hlascirkvi.sk/akcie/2075/pro-life-organizacie-slovenska-opat-pripravili-retaz-modlitieb-za-zivot',
            ],
        ],
        264 => [
            'name' => 'Sestry saleziánky',
            'events' => [
                2207 => 'https://hlascirkvi.sk/akcie/2121/sestry-salezianky-pokracuju-v-case-lockdownu-opat-projektom-kompas',
            ],
        ],
        661 => [
            'name' => 'Slovensko na kolenách',
            'events' => [
                2307 => 'https://hlascirkvi.sk/akcie/2228/vo-stvrtok-chystaju-prve-novorocne-stretnutie-slovensko-na-kolenachnbsp',
                2756 => 'https://hlascirkvi.sk/akcie/2687/aj-tento-mesiac-sa-slovensko-spoji-v-spolocnej-modlitbe-na-kolenach',
            ],
        ],
        98 => [
            'name' => 'Bratislavská arcidiecéza',
            'events' => [
                2450 => 'https://hlascirkvi.sk/akcie/2380/bratislavska-arcidieceza-si-pripomina-strnaste-vyrocie-svojho-zalozenia',
                2453 => 'https://hlascirkvi.sk/akcie/2383/bratislavska-arcidieceza-a-zilinska-dieceza-slavia-14-rokov-od-zalozenia',
            ],
        ],
        1273 => [
            'name' => 'Rada pre laické a apoštolské hnutia',
            'events' => [
                4006 => 'https://hlascirkvi.sk/akcie/3981/rada-pre-laicke-a-apostolske-hnutia-kbs-opat-ozivuje-stretnutia-lidrov',
            ],
        ],
        1301 => [
            'name' => 'Rehoľné sestry z rôznych spoločenstiev',
            'events' => [
                4079 => 'https://hlascirkvi.sk/akcie/4055/reholne-sestry-z-roznych-spolocenstiev-opat-chystaju-modlitbu-chval',
                4234 => 'https://www.tkkbs.sk/view.php?cisloclanku=20230117021',
                6261 => 'https://www.tkkbs.sk/view.php?cisloclanku=20231218024',
            ],
        ],
        30 => [
            'name' => 'Žilinská diecéza',
            'events' => [
                4169 => 'https://hlascirkvi.sk/akcie/4146/zilinska-dieceza-uzavrie-prvu-etapu-rekonstrukcie-katedraly-najsvatejsej-trojice',
                4426 => 'https://www.tkkbs.sk/view.php?cisloclanku=20230215019',
                5877 => 'https://www.tkkbs.sk/view.php?cisloclanku=20231010021',
                7752 => 'https://www.tkkbs.sk/view.php?cisloclanku=20241011039',
                10229 => 'https://www.tkkbs.sk/view.php?cisloclanku=20260317004',
            ],
        ],
        197 => [
            'name' => 'Verbisti',
            'events' => [
                4221 => 'https://hlascirkvi.sk/akcie/4198/verbisti-zacnu-slavit-jubileum-100-rokov-od-ich-prichodu-na-slovensko',
                6460 => 'https://www.tkkbs.sk/view.php?cisloclanku=20240131014',
                10887 => 'https://www.tkkbs.sk/view.php?cisloclanku=20260907008',
            ],
        ],
        239 => [
            'name' => 'Biskup Jozef Haľko',
            'events' => [
                4709 => 'https://www.tkkbs.sk/view.php?cisloclanku=20230329037',
                6264 => 'https://www.tkkbs.sk/view.php?cisloclanku=20231219011',
                6710 => 'https://www.tkkbs.sk/view.php?cisloclanku=20240321023',
                7979 => 'https://www.tkkbs.sk/view.php?cisloclanku=20241128046',
                10851 => 'https://www.tkkbs.sk/view.php?cisloclanku=20260717010',
            ],
        ],
        1375 => [
            'name' => 'portál nm.sk',
            'events' => [
                4810 => 'https://www.tkkbs.sk/view.php?cisloclanku=20230417025',
                7150 => 'https://www.tkkbs.sk/view.php?cisloclanku=20240612034',
            ],
        ],
        767 => [
            'name' => 'Farnosť Teplicka',
            'events' => [
                4817 => 'https://www.tkkbs.sk/view.php?cisloclanku=20230418015',
            ],
        ],
        485 => [
            'name' => 'Rada KBS pre rodinu',
            'events' => [
                4981 => 'https://www.tkkbs.sk/view.php?cisloclanku=20230517031',
            ],
        ],
        27 => [
            'name' => 'Františkáni',
            'events' => [
                5364 => 'https://www.tkkbs.sk/view.php?cisloclanku=20230717048',
                7317 => 'https://www.tkkbs.sk/view.php?cisloclanku=20240730025',
            ],
        ],
        1166 => [
            'name' => 'Pútnické miesto Skalka pri Trenčíne',
            'events' => [
                5707 => 'https://www.tkkbs.sk/view.php?cisloclanku=20230920025',
            ],
        ],
        401 => [
            'name' => 'Centrum Anny Kolesárovej',
            'events' => [
                6376 => 'https://www.tkkbs.sk/view.php?cisloclanku=20240119017',
            ],
        ],
        174 => [
            'name' => 'Hnutie Modlitby za kňazov',
            'events' => [
                6383 => 'https://www.tkkbs.sk/view.php?cisloclanku=20240119034',
            ],
        ],
        278 => [
            'name' => 'Dom Quo Vadis',
            'events' => [
                6389 => 'https://www.tkkbs.sk/view.php?cisloclanku=20240119039',
            ],
        ],
        681 => [
            'name' => 'Spolok sv. Vojtecha',
            'events' => [
                6390 => 'https://www.tkkbs.sk/view.php?cisloclanku=20240119036',
                6582 => 'https://www.tkkbs.sk/view.php?cisloclanku=20240226041',
            ],
        ],
        153 => [
            'name' => 'Saleziáni don Bosca',
            'events' => [
                6410 => 'https://www.tkkbs.sk/view.php?cisloclanku=20240122020',
            ],
        ],
        803 => [
            'name' => 'Exercičný dom sv. Ignáca z Loyoly',
            'events' => [
                6428 => 'https://www.tkkbs.sk/view.php?cisloclanku=20240123028',
            ],
        ],
        243 => [
            'name' => 'Pápežská nadácia ACN – Pomoc trpiacej Cirkvi',
            'events' => [
                6501 => 'https://www.tkkbs.sk/view.php?cisloclanku=20240208003',
            ],
        ],
        102 => [
            'name' => 'Diecézny katechetický úrad Žilinskej diecézy',
            'events' => [
                6532 => 'https://www.tkkbs.sk/view.php?cisloclanku=20240213023',
            ],
        ],
        690 => [
            'name' => 'Milosrdní bratia',
            'events' => [
                6533 => 'https://www.tkkbs.sk/view.php?cisloclanku=20240213038',
            ],
        ],
        794 => [
            'name' => 'Cirkevné konzervatórium v Bratislave',
            'events' => [
                6559 => 'https://www.tkkbs.sk/view.php?cisloclanku=20240219030',
            ],
        ],
        227 => [
            'name' => 'Rožňavská diecéza',
            'events' => [
                7782 => 'https://www.tkkbs.sk/view.php?cisloclanku=20241018029',
                9911 => 'https://www.tkkbs.sk/view.php?cisloclanku=20260109031',
            ],
        ],
        313 => [
            'name' => 'Katolícke noviny',
            'events' => [
                8607 => 'https://www.tkkbs.sk/view.php?cisloclanku=20250328036',
            ],
        ],
        561 => [
            'name' => 'Misijný sekretariát – pallotíni',
            'events' => [
                9273 => 'https://www.tkkbs.sk/view.php?cisloclanku=20250820026',
            ],
        ],
        85 => [
            'name' => 'Katolícka univerzita v Ružomberku',
            'events' => [
                9424 => 'https://www.tkkbs.sk/view.php?cisloclanku=20250919007',
            ],
        ],
        302 => [
            'name' => 'Spoločenstvo Extrémnej krížovej cesty (EKC) na Slovensku',
            'events' => [
                10032 => 'https://www.tkkbs.sk/view.php?cisloclanku=20260205007',
                10284 => 'https://www.tkkbs.sk/view.php?cisloclanku=20260327006',
            ],
        ],
        466 => [
            'name' => 'spoločenstva Nádej a portálu SingleKatolici.sk',
            'events' => [
                10221 => 'https://www.tkkbs.sk/view.php?cisloclanku=20260313019',
            ],
        ],
        29 => [
            'name' => 'Bratislavská eparchia',
            'events' => [
                10245 => 'https://www.tkkbs.sk/view.php?cisloclanku=20260319016',
            ],
        ],
        178 => [
            'name' => 'Enoia a Kríza identity muža a ženy',
            'events' => [
                9984 => 'https://www.tkkbs.sk/view.php?cisloclanku=20260123006',
            ],
        ],
        975 => [
            'name' => 'Hnutie Laudato si’',
            'events' => [
                10446 => 'https://www.tkkbs.sk/view.php?cisloclanku=20260430007',
            ],
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('canals') || ! Schema::hasTable('events')) {
            return;
        }

        foreach (self::NEW_CANALS as $slug => $canal) {
            $id = $this->createCanal($slug, $canal);

            if ($id !== null) {
                $this->moveEvents($id, $canal['name'], $canal['events'], self::COLLECTION_CANAL_ID);
            }
        }

        foreach (self::EXISTING as $canalId => $target) {
            $this->moveEvents($canalId, $target['name'], $target['events'], self::COLLECTION_CANAL_ID);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('canals') || ! Schema::hasTable('events')) {
            return;
        }

        foreach (self::EXISTING as $canalId => $target) {
            $this->moveEvents(self::COLLECTION_CANAL_ID, null, $target['events'], $canalId);
        }

        foreach (self::NEW_CANALS as $slug => $canal) {
            $id = DB::table('canals')->where('slug', $slug)->where('name', $canal['name'])->value('id');

            if ($id === null) {
                continue;
            }

            $this->moveEvents(self::COLLECTION_CANAL_ID, null, $canal['events'], (int) $id);

            if (! DB::table('events')->where('canal_id', $id)->exists()) {
                DB::table('canal_user')->where('canal_id', $id)->delete();
                DB::table('canals')->where('id', $id)->delete();
            }
        }
    }

    /**
     * @param  array{name: string, website: string, body: string, events: array<int, string>}  $canal
     */
    private function createCanal(string $slug, array $canal): ?int
    {
        $existing = DB::table('canals')->where('slug', $slug)->value('id');

        if ($existing !== null) {
            return (int) $existing;
        }

        if (! DB::table('municipalities')->where('id', self::BRATISLAVA_ID)->exists()) {
            return null;
        }

        $now = now();

        $id = DB::table('canals')->insertGetId([
            'municipality_id' => self::BRATISLAVA_ID,
            'name' => $canal['name'],
            'slug' => $slug,
            'body' => $canal['body'],
            'website' => $canal['website'],
            'latitude' => self::BRATISLAVA_LAT,
            'longitude' => self::BRATISLAVA_LNG,
            'coordinates_source' => 'municipality',
            'status' => 'published',
            'published_at' => $now,
            'registration_source' => 'import',
            'identity_mode' => 'organization',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Rovnako ako ImportedCanalManager::ensureSystemOwnership() — technický
        // vlastník importovaných kanálov je super-admin.
        $ownerId = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', 'super-admin')
            ->orderBy('model_has_roles.model_id')
            ->value('model_has_roles.model_id');

        if ($ownerId !== null) {
            DB::table('canal_user')->insert([
                'canal_id' => $id,
                'user_id' => $ownerId,
                'is_owner' => true,
                'role' => 'owner',
                'status' => 'published',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return $id;
    }

    /**
     * @param  array<int, string>  $events  event id => orginal_source
     */
    private function moveEvents(int $toCanalId, ?string $toCanalName, array $events, int $fromCanalId): void
    {
        if ($toCanalName !== null && ! DB::table('canals')->where('id', $toCanalId)->where('name', $toCanalName)->exists()) {
            return;
        }

        foreach ($events as $eventId => $source) {
            DB::table('events')
                ->where('id', $eventId)
                ->where('canal_id', $fromCanalId)
                ->where('orginal_source', $source)
                ->update(['canal_id' => $toCanalId, 'updated_at' => now()]);
        }
    }
};
