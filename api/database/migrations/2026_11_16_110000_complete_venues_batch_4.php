<?php

use App\Enums\ModelStatus;
use App\Models\Venue;
use App\Services\Resources\ResourceMerger;
use App\Services\SystemLog\Recorder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Štvrtá dávka miest konania (november 2026): ďalších 60 nearchivovaných miest
 * v poradí (#55 – #144). Zástupné miesta celých obcí („Trnava", „Žilina",
 * „Spišské Podhradie") a nezmysly („Dvojka", „Busseto, Italy…") sa nepopisujú.
 * Konkrétne miesta dostanú popis, adresu a súradnice z Nominatim/OpenStreetMap;
 * miesta vzniknuté z adries dostanú skutočný názov.
 *
 * Opravy chybných údajov:
 *  - #125 kláštor minoritov pri sv. Jakubovi a #113 Kostol Krista Spasiteľa sú
 *    v Prahe (Česko), nie v obci Staré či v Prahe pri Lučenci; #113 mal popis
 *    istanbulskej Chóry,
 *  - #75 Dóm sv. Štefana je vo Viedni, nie v obci Vieska,
 *  - #110 Spišské diecézne múzeum je na Spišskej Kapitule, nie v Nitre,
 *  - #82 Klokočov mal popis českej obce pri Vítkove,
 *  - #59 Martindom na Rožňavskej 17 mal PSČ z Gemera,
 *  - #141, #121, #76, #72 mali popis a web mesta.
 *
 * Duplikát sa zlúči: #101 „Vyšehradská bazilika" (podujatia vo Vatikáne) → #1050 Bazilika sv. Petra.
 *
 * #130 Klariská ulica je tiež Koncertná sieň Klarisky — zlúčenie s #996 blokuje
 * priložený súbor, preto sa len opraví.
 *
 * Archivované miesta sa nemenia. Popis sa prepíše len vtedy, keď ešte nemá tri
 * odseky; ulica, PSČ a web sa dopĺňajú do prázdneho poľa (okrem `fix_*`).
 * Miesto musí sedieť ID aj názvom.
 */
return new class extends Migration
{
    private const OTHERS = 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.';

    /** Zahraničné mestá, ktoré v číselníku obcí ešte nie sú (vzor: Rím). */
    private const CITIES = [
        'praha-cesko' => ['name' => 'Praha (Česko)', 'zip' => '110 00'],
        'vieden' => ['name' => 'Viedeň', 'zip' => '1010'],
    ];

    /** @var array<int, array<string, mixed>> */
    private const VENUES = [
        143 => ['name' => 'Jakubovo námestie 4-5', 'fix_name' => 'Centrum Salvator (Bratislava)', 'fix_street' => 'Jakubovo námestie 4-5', 'fix_postcode' => '811 09',
            'latitude' => 48.1436831, 'longitude' => 17.1199592, 'coordinates_source' => 'address', 'fix_coordinates' => true, 'body' => [
                'Centrum Salvator je duchovné centrum Kongregácie sestier Najsvätejšieho Spasiteľa na Jakubovom námestí v Bratislave. Ponúka priestory na obnovy, konferencie a stretnutia.',
                'Konajú sa tu víkendové duchovné obnovy, napríklad Hľadáme odpovede, celodenné stretnutia Modlitba, ktorá otvára srdce či konferencie Nadácie Slovakia Christiana.',
                self::OTHERS,
            ]],
        142 => ['name' => 'Jedlíkova 7, KOŠICE https://maps.app.goo.gl/Td6zFqbCc3L2dGkYA', 'fix_name' => 'Kultúrno-spoločenské centrum TUKE (Košice)', 'fix_street' => 'Jedlíkova 7', 'fix_postcode' => '040 11', 'body' => [
            'Kultúrno-spoločenské centrum Technickej univerzity v Košiciach sídli na Jedlíkovej ulici v areáli univerzity. Slúži na kultúrne, spoločenské a vzdelávacie podujatia.',
            'Konajú sa tu kurzy a tréningy, napríklad trojdňový Tréning zručností tvorby komunity podľa metódy Community Building.',
            self::OTHERS,
        ]],
        141 => ['name' => 'Košická ulica, Levoča', 'fix_name' => 'Minoritský kostol (Levoča)', 'fix_website' => null, 'postcode' => '054 01', 'body' => [
            'Minoritský kostol stojí na Košickej ulici v historickom centre Levoče. Pri kostole je kláštor minoritov. Patrí k pamiatkam mestskej pamiatkovej rezervácie.',
            'Konajú sa tu bohoslužby a stretnutia spoločenstva Pavol z Levoče, napríklad prednášky o. Matúša Oravca či manželov Marettovcov o vzťahoch a rodine.',
            self::OTHERS,
        ]],
        139 => ['name' => 'Saleziánskom kostole sv. Jána Bosca', 'fix_name' => 'Kostol sv. Jána Bosca (Bratislava-Trnávka)', 'body' => [
            'Kostol sv. Jána Bosca je saleziánsky kostol v Bratislave-Trnávke. Pri ňom je saleziánske stredisko mládeže. Slúži farnosti Trnávka.',
            'Konajú sa tu bohoslužby a saleziánske slávnosti, napríklad doživotné rehoľné sľuby saleziánov. Stretáva sa tu aj spoločenstvo mladých vysokoškolákov.',
            self::OTHERS,
        ]],
        138 => ['name' => 'turistická útulňa sv. Františka', 'fix_name' => 'Turistická útulňa sv. Františka (Zobor)', 'body' => [
            'Turistická útulňa sv. Františka stojí pod Zoborom nad Nitrou. Postavil ju Zoborský skrášľovací spolok. Slúži turistom na odpočinok.',
            'Útulňu požehnali v septembri 2026 v Roku sv. Františka. Pri útulni sa konajú aj turistické a spomienkové stretnutia.',
            self::OTHERS,
        ]],
        134 => ['name' => 'Kostol Milosrdných bratov', 'fix_name' => 'Kostol milosrdných bratov (Skalica)', 'body' => [
            'Kostol milosrdných bratov stojí na Námestí slobody v Skalici. Patrí k historickému areálu rehole milosrdných bratov.',
            'Konajú sa tu bohoslužby a obnovy, napríklad Loretánske dni pred sviatkom Narodenia Panny Márie, ktoré pripravuje farnosť Skalica.',
            self::OTHERS,
        ]],
        130 => ['name' => 'Klariská ulica', 'fix_name' => 'Koncertná sieň Klarisky (Klariská ulica)', 'fix_website' => null, 'postcode' => '811 03',
            'latitude' => 48.1438684, 'longitude' => 17.1054672, 'coordinates_source' => 'venue', 'fix_coordinates' => true, 'body' => [
                'Koncertná sieň Klarisky je kultúrny priestor v bývalom gotickom kostole klarisiek v Starom Meste v Bratislave. Kostol stojí na Klariskej ulici, vchod do siene je z Farskej ulice.',
                'Konajú sa tu koncerty starej a duchovnej hudby, napríklad cyklus Musica Bratislavensis združenia Musica aeterna. Na koncerty sa predávajú vstupenky.',
                self::OTHERS,
            ]],
        128 => ['name' => 'Štefánikova 136/46, Trnava', 'fix_name' => 'Centrum pomoci pre rodinu (Trnava)', 'fix_street' => 'Štefánikova 136/46', 'postcode' => '917 01',
            'latitude' => 48.3781347, 'longitude' => 17.585046, 'coordinates_source' => 'venue', 'fix_coordinates' => true, 'body' => [
                'Centrum pomoci pre rodinu v Trnave sídli na Štefánikovej ulici. Pomáha rodinám, manželom a rodičom v ťažkých situáciách.',
                'Pripravuje kurzy a poradenstvo, napríklad Manželské večery. Kurzy sú otvorené pre všetky páry.',
                self::OTHERS,
            ]],
        126 => ['name' => 'Galéria IKONY', 'fix_name' => 'Galéria IKONY (Žilina)', 'body' => [
            'Galéria IKONY je galéria a kultúrny priestor v centre Žiliny. Prevádzkuje ju občianske združenie IKONY. V roku 2026 oslávila 10 rokov.',
            'Konajú sa tu výstavy ikon a duchovného umenia, prednášky a besedy, napríklad trojročný cyklus prednášok s biblistami.',
            self::OTHERS,
        ]],
        125 => ['name' => 'Kláštor minoritov pri svätom Jakubovi', 'fix_name' => 'Kláštor minoritov pri sv. Jakubovi (Praha)', 'city' => 'praha-cesko', 'country' => 'Česko', 'fix_postcode' => '110 00',
            'latitude' => 50.0884, 'longitude' => 14.4247, 'coordinates_source' => 'manual', 'fix_coordinates' => true, 'body' => [
                'Kláštor minoritov pri sv. Jakubovi stojí na Starom Meste v Prahe. Pri ňom je barokový Kostol sv. Jakuba Väčšieho. Minoriti tu pôsobia od 13. storočia.',
                'Konal sa tu napríklad Františkánsky festival Tri dni so svätým Františkom pri 800. výročí jeho smrti.',
                self::OTHERS,
            ]],
        121 => ['name' => 'Námestie svätého Egídia 11/25, Poprad', 'fix_name' => 'Coworking Poprad', 'fix_street' => 'Námestie svätého Egídia 11/25', 'postcode' => '058 01', 'fix_website' => 'https://coworkingpoprad.sk',
            'latitude' => 49.055997, 'longitude' => 20.303851, 'coordinates_source' => 'address', 'fix_coordinates' => true, 'body' => [
                'Coworking Poprad je zdieľaný pracovný priestor na Námestí sv. Egídia v Poprade. Ponúka miesto na prácu aj stretávanie.',
                'Konajú sa tu Biznis raňajky Poprad a večery Stretnutie s otcom Andrejom Branickým s hosťami, napríklad Marekom Madrom z IPčko.',
                self::OTHERS,
            ]],
        118 => ['name' => 'Kostol sv. Martina', 'fix_name' => 'Kostol sv. Martina (Teplička nad Váhom)', 'body' => [
            'Kostol sv. Martina je farský kostol v Tepličke nad Váhom pri Žiline. Patrí do Žilinskej diecézy. Je zasvätený sv. Martinovi z Tours.',
            'Pri kostole sa začína turisticko-pútnická cesta Via Peregrini Fatricensis, ktorú otvárali s OOCR Turiec.',
            self::OTHERS,
        ]],
        115 => ['name' => 'Kostol Sedembolestnej Panny Márie', 'fix_name' => 'Kostol Sedembolestnej Panny Márie (Žilina)', 'body' => [
            'Kostol Sedembolestnej Panny Márie stojí v Žiline na ulici Na Straník. Patrí do Žilinskej diecézy. Je zasvätený patrónke Slovenska.',
            'Konajú sa tu bohoslužby a diecézne stretnutia, napríklad Veni Sancte učiteľov náboženskej výchovy na začiatku školského roka.',
            self::OTHERS,
        ]],
        114 => ['name' => 'Evanjelický cirkevný dom', 'fix_name' => 'Evanjelický cirkevný dom (Liptovský Mikuláš)', 'body' => [
            'Evanjelický cirkevný dom v Liptovskom Mikuláši patrí Cirkevnému zboru ECAV. Slúži na stretnutia, prednášky a koncerty. Stojí v centre mesta.',
            'Konajú sa tu podujatia evanjelickej cirkvi, napríklad oslavy 390. výročia Cithary sanctorum Juraja Tranovského.',
            self::OTHERS,
        ]],
        113 => ['name' => 'Chrám svätého Spasiteľa (Chóra)', 'fix_name' => 'Kostol Krista Spasiteľa (Praha-Barrandov)', 'city' => 'praha-cesko', 'country' => 'Česko', 'fix_street' => null, 'fix_postcode' => '152 00', 'fix_website' => null,
            'latitude' => 50.0322, 'longitude' => 14.3956, 'coordinates_source' => 'municipality', 'fix_coordinates' => true, 'body' => [
                'Kostol Krista Spasiteľa je moderný katolícky kostol na sídlisku Barrandov v Prahe. Je súčasťou komunitného centra. Slúži farnosti Barrandov.',
                'Konajú sa tu modlitby za mier a bezpečný život vo Svätej zemi, ktoré pokračujú každý mesiac. Pripájajú sa k nim aj veriaci zo Slovenska.',
                self::OTHERS,
            ]],
        112 => ['name' => 'Arcidiecézne centrum pre mládež ACM Vinica', 'body' => [
            'Arcidiecézne centrum pre mládež Vinica sídli v Bratislave-Rači. Patrí Bratislavskej arcidiecéze. Je centrom práce s mladými.',
            'Konajú sa tu formačné programy, napríklad Bratislavská animátorská škola pre mladých, ktorí chcú slúžiť vo farnostiach. Centrum ponúka aj ubytovanie.',
            self::OTHERS,
        ]],
        110 => ['name' => 'Spišské diecézne múzeum', 'fix_village_id' => 3246, 'fix_street' => 'Spišská Kapitula 14', 'fix_postcode' => '053 04',
            'latitude' => 49.0006559, 'longitude' => 20.7426943, 'coordinates_source' => 'address', 'fix_coordinates' => true, 'body' => [
                'Spišské diecézne múzeum sídli v kanónii na Spišskej Kapitule. Vystavuje sakrálne umenie Spišskej diecézy. Je súčasťou pamiatky UNESCO.',
                'Konajú sa tu výstavy a slávnosti, napríklad výstava o Majstrovi Pavlovi z Levoče, ktorú potom presunuli do Ríma, či oslava 360 rokov Kaplnky sv. Rozálie.',
                self::OTHERS,
            ]],
        105 => ['name' => 'amfiteáter Národného výstaviska Agrokomplex', 'body' => [
            'Amfiteáter Národného výstaviska Agrokomplex je otvorené pódium v areáli výstaviska v Nitre. Slúži na koncerty a veľké podujatia.',
            'Počas výstavy Agrokomplex sa tu slávi svätá omša za úrodu. Konajú sa tu aj koncerty a kresťanské festivaly.',
            self::OTHERS,
        ]],
        102 => ['name' => 'Univerzitný kostol Svätej Rodiny', 'body' => [
            'Univerzitný kostol Svätej Rodiny je kostol Katolíckej univerzity v Ružomberku. Stojí v časti Rybárpole. Slúži študentom a farníkom.',
            'Konajú sa tu univerzitné sväté omše, napríklad slávnostné otvorenie akademického roka Veni Sancte. Pri kostole pôsobí univerzitné pastoračné centrum.',
            self::OTHERS,
        ]],
        96 => ['name' => 'Hotel Hviezda', 'fix_name' => 'Hotel Hviezda (Dudince)', 'postcode' => '962 71', 'body' => [
            'Hotel Hviezda je kúpeľný hotel v Dudinciach. Ponúka ubytovanie, wellness a konferenčné priestory.',
            'Konajú sa tu viacdňové stretnutia, napríklad Misijné dni Západného dištriktu ECAV. Program spája prednášky, chvály a oddych.',
            self::OTHERS,
        ]],
        95 => ['name' => 'v priestoroch Biskupského úradu', 'fix_name' => 'Biskupský úrad Západného dištriktu ECAV (Zvolen)', 'fix_street' => 'Námestie SNP 5', 'postcode' => '960 01', 'body' => [
            'Biskupský úrad Západného dištriktu Evanjelickej cirkvi a. v. sídli na Námestí SNP vo Zvolene. Je sídlom biskupa dištriktu.',
            'Konajú sa tu stretnutia a obnovy dištriktu, napríklad stretnutie manželiek farárov Nesené milosťou.',
            self::OTHERS,
        ]],
        88 => ['name' => 'Jarocká cesta, Nitra', 'fix_name' => 'Duchovné centrum Lukov dvor (Nitra)', 'postcode' => '949 01',
            'latitude' => 48.2919424, 'longitude' => 18.035749, 'coordinates_source' => 'venue', 'fix_coordinates' => true, 'body' => [
                'Duchovné centrum Lukov dvor je dom Misionárov Najsvätejšieho Srdca Ježišovho pri Nitre. Leží v prírode pri Jarockej ceste. Ponúka obnovy a ubytovanie.',
                'Konajú sa tu duchovné obnovy a kurzy, napríklad Šťastie začína vnútri, Uzdravujúca cesta života či tanečná meditácia v tichu.',
                self::OTHERS,
            ]],
        82 => ['name' => 'Klokočov (Vítkov)', 'fix_name' => 'Chrám Zosnutia Presvätej Bohorodičky (Klokočov)', 'fix_website' => null, 'fix_postcode' => '072 31',
            'latitude' => 48.8092018, 'longitude' => 22.0286765, 'coordinates_source' => 'venue', 'fix_coordinates' => true, 'body' => [
                'Chrám Zosnutia Presvätej Bohorodičky v Klokočove pri Michalovciach je gréckokatolícke pútnické miesto Košickej eparchie. Uctieva sa tu slziaca ikona Klokočovskej Bohorodičky. Leží pri Zemplínskej šírave.',
                'Konajú sa tu púte, najmä veľká púť v auguste, a chvály spoločenstva Emanuel. Prichádzajú sem pútnici z celého východného Slovenska.',
                self::OTHERS,
            ]],
        81 => ['name' => 'TIPOS Aréna', 'fix_name' => 'TIPOS Aréna (Bratislava)', 'body' => [
            'Zimný štadión Ondreja Nepelu, nazývaný TIPOS Aréna, je multifunkčná hala na Odbojárov v Bratislave. Hostí hokej, koncerty a veľké podujatia.',
            'Konajú sa tu aj veľké kresťanské podujatia, napríklad evanjelizačné turné Godzone Tour s témou Ešte nie je neskoro.',
            self::OTHERS,
        ]],
        76 => ['name' => 'Považská Bystrica – Rozkvet', 'fix_name' => 'Kostol Lasaletskej Panny Márie (Považská Bystrica)', 'fix_website' => null, 'fix_street' => 'Helenská 4898/208',
            'latitude' => 49.1070038, 'longitude' => 18.4428563, 'coordinates_source' => 'venue', 'fix_coordinates' => true, 'body' => [
                'Kostol Lasaletskej Panny Márie stojí na sídlisku Rozkvet v Považskej Bystrici. Spravujú ho misionári saletíni. Je zasvätený Panne Márii z La Salette.',
                'Konajú sa tu bohoslužby a prednášky, napríklad biblická formácia. Saletíni tu pripravujú aj púte a programy pre mladých.',
                self::OTHERS,
            ]],
        75 => ['name' => 'Viedenský Dóm sv. Štefana', 'fix_name' => 'Dóm sv. Štefana (Viedeň)', 'city' => 'vieden', 'country' => 'Rakúsko', 'fix_postcode' => '1010', 'body' => [
            'Dóm sv. Štefana je gotická katedrála vo Viedni a symbol mesta. Je sídlom viedenského arcibiskupa. Jeho južná veža meria 136 metrov.',
            'Konajú sa tu slávnostné omše, napríklad pri desiatom výročí svätorečenia Matky Terezy či pri uvedení arcibiskupa do úradu.',
            self::OTHERS,
        ]],
        72 => ['name' => 'Kovarce', 'fix_name' => 'Kostol sv. Mikuláša (Kovarce)', 'fix_website' => null, 'fix_street' => 'Kovarce 15', 'body' => [
            'Kostol sv. Mikuláša je farský kostol v obci Kovarce v okrese Topoľčany. Je zasvätený sv. Mikulášovi.',
            'Konajú sa tu bohoslužby a duchovno-formačné programy pre slobodných, ktoré pripravuje spoločenstvo Nádej.',
            self::OTHERS,
        ]],
        71 => ['name' => 'Bazilika minor Zoslania Svätého Ducha', 'fix_name' => 'Bazilika Zoslania Ducha Svätého (Michalovce)', 'body' => [
            'Bazilika minor Zoslania Ducha Svätého je gréckokatolícky chrám redemptoristov v Michalovciach. Uchováva relikvie blahoslaveného mučeníka Metoda Dominika Trčku.',
            'Každý rok sa sem koná pešia púť k relikviám bl. Metoda Dominika Trčku. Konajú sa tu aj liturgie a Noc kostolov.',
            self::OTHERS,
        ]],
        66 => ['name' => 'Námestie slobody (Bratislava)', 'fix_website' => null, 'body' => [
            'Námestie slobody je veľké námestie v Bratislave pri Grasalkovičovej záhrade a Úrade vlády. Uprostred je fontána Družba.',
            'Konajú sa tu veľké verejné zhromaždenia, napríklad Národný pochod za život. Námestie je ľahko dostupné z centra mesta.',
            self::OTHERS,
        ]],
        60 => ['name' => 'Herlianska 52, Košice', 'fix_name' => 'Kláštor sv. Augustína (Košice)', 'fix_street' => 'Herlianska 52', 'fix_postcode' => '040 14',
            'latitude' => 48.7291412, 'longitude' => 21.2937723, 'coordinates_source' => 'venue', 'fix_coordinates' => true, 'body' => [
                'Kláštor sv. Augustína je kláštor rehole augustiniánov v Košiciach-Košickej Novej Vsi. Bratia tu žijú a slúžia farnosti.',
                'Konajú sa tu duchovné a kultúrne podujatia, napríklad festival Augfest, ktorý spája hudbu, modlitbu a spoločenstvo.',
                self::OTHERS,
            ]],
        59 => ['name' => 'Rožňavská 17, Bratislava', 'fix_name' => 'Martindom (Bratislava)', 'fix_postcode' => '831 04', 'latitude' => 48.174801, 'longitude' => 17.1627716,
            'coordinates_source' => 'address', 'fix_coordinates' => true, 'body' => [
                'Martindom je priestor spoločenstva Martindom na Rožňavskej ulici v Bratislave-Trnávke. Slúži na chvály, modlitby a stretnutia.',
                'Konajú sa tu pravidelné modlitebné Stredy na R17 s hosťami, napríklad s Michalom a Ľubicou Steinerovcami.',
                self::OTHERS,
            ]],
        56 => ['name' => 'Európsky dom', 'fix_name' => 'Európsky dom (Spišská Kapitula)', 'body' => [
            'Európsky dom je informačné a kultúrne centrum na Spišskej Kapitule. Leží v areáli pamiatky UNESCO. Slúži návštevníkom.',
            'Konajú sa tu výstavy a podujatia, napríklad degustácia a výstava omšových vín In vino gratitudo pri 250. výročí Spišskej diecézy.',
            self::OTHERS,
        ]],
    ];

    /** Zdroj → cieľ: ten istý priestor pod inými názvami. */
    private const MERGES = [
        101 => [1050, 'Vyšehradská bazilika sv. Petra', 'Bazilika sv. Petra'],
    ];

    public function up(): void
    {
        foreach (self::VENUES as $id => $data) {
            $venue = Venue::query()->find($id);

            if (! $venue || $venue->status === ModelStatus::Archived || ($venue->name !== $data['name'] && $venue->name !== ($data['fix_name'] ?? null))) {
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
