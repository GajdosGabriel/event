<?php

use App\Enums\FileType;
use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\File;
use App\Models\Venue;
use App\Services\Canals\CanalEmails;
use App\Services\Resources\ResourceMerger;
use App\Services\SystemLog\Recorder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Posledných 20 kanálov (#1961 – #1980) a 20 miest (#1086 – #1112, bez zlúčených)
 * k 21. 11. 2026: chýbajúce údaje, zlúčenie duplicít a fotky z Wikimedia Commons.
 *
 * Kanály: #1975 Farnosť Nové Zámky (novezamky.nrb.sk), #1977 – #1979 zbory ECAV
 * Istebné, Senica, Sučany a #1980 Novohradský seniorát (adresár ecav.sk) dostanú
 * popis, telefón, web, e-mail a adresu sídla so súradnicami z Nominatim/OpenStreetMap.
 * NOS ECAV sídli v Malej Čalomiji, nie vo Veľkom Krtíši. Zbor Sučany patrí do
 * Turčianskeho seniorátu (import uvádzal Považský); jeho bývalá doména ecavsu.sk
 * je dnes kasíno, preto je webom stránka zboru na ecav.sk.
 * #1976 „o. Martin Pavúk a Emanuel" sa zlúči do #1950 Emanuel (Klokočov).
 * Kanály #1961 – #1972 sú doplnené z predchádzajúcich dávok, dostanú len fotku.
 * Osobný kanál #1973 sa nemení.
 *
 * Miesta: #1102, #1103, #1107 – #1112 dostanú skutočný názov, adresu a popis;
 * #1103 Mníchovská katedrála mala obec Zlaté (pribudne obec Mníchov), #1110 Senica
 * ulicu Fajnorova namiesto Hollého. Duplicity: #1105 → #733 (Kostol sv. Vincenta
 * de Paul), #1106 → #109 (Dóm svätej Alžbety), #1104 → #850 (svätyňa v Smižanoch,
 * ten istý úryvok ako zlúčené #1068) a archivované #792 → #1108 (drevený artikulárny
 * kostol vo Svätom Kríži, kde sa podujatie začína).
 *
 * Fotky: zmenšeniny 1280 px z Commons v assets/canal-venue-photos, autor a licencia
 * v meta súboru (rovnaké kľúče ako app:venues-find-images). Fotku dostane len kanál
 * či miesto bez obrázka; prevzatý kanál sa nemení.
 *
 * Kanál musí sedieť ID aj názvom; prevzatý, zlúčený či zmazaný sa preskočí.
 * Popis sa prepíše len vtedy, keď ešte nemá tri odseky; telefón, web a ulica
 * sa dopĺňajú do prázdneho poľa (okrem `fix_*`). Archivované miesta sa nemenia.
 */
return new class extends Migration
{
    private const SOURCE = 'migration:2026_11_26_canal_venue_photo';

    private const CLOSING = 'Podrobnosti nájdete vždy v detaile podujatia.';

    private const OTHERS = 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.';

    /** Súradnice, ktoré import dáva kanálom bez adresy (stred Slovenska). */
    private const NATIONWIDE = [48.7411522, 19.4528646];

    /** Zahraničné obce, ktoré v číselníku ešte nie sú. */
    private const CITIES = [
        'mnichov' => ['name' => 'Mníchov', 'zip' => '80331'],
    ];

    /** @var array<int, array<string, mixed>> */
    private const CANALS = [
        1975 => [
            'name' => 'Farnosť Nové Zámky',
            'municipality' => 2431,
            'phone' => '0909 250 253',
            'website' => 'https://novezamky.nrb.sk',
            'emails' => [['email' => 'fara.novezamky@gmail.com', 'found_on' => 'https://novezamky.nrb.sk/']],
            'address' => ['street' => 'M. Flengera 2', 'postcode' => '940 02'],
            'coordinates' => [47.9859689, 18.1647525, 'address'],
            'body' => [
                'Rímskokatolícka farnosť Nové Zámky patrí do Nitrianskej diecézy. Farským kostolom je Kostol Povýšenia svätého Kríža na Hlavnom námestí, farský úrad sídli na Ulici M. Flengera. Farárom je Marek Ďurás.',
                'Sväté omše slávi po slovensky aj po maďarsky. V Pastoračnom centre Marianum pripravuje stretnutia, výstavy a farské slávnosti, napríklad Farský misijný deň s Pápežskými misijnými dielami.',
                'Kanál je určený veriacim z Nových Zámkov. Sledovaním budete vedieť o farských podujatiach. '.self::CLOSING,
            ],
        ],
        1977 => [
            'name' => 'CZ ECAV Istebné',
            'municipality' => 1225,
            'phone' => '0918 828 352',
            'website' => 'https://www.ecav.sk/vychodny-distrikt/liptovsko-oravsky-seniorat/istebne',
            'emails' => [['email' => 'istebne@ecav.sk', 'found_on' => 'https://www.ecav.sk/vychodny-distrikt/liptovsko-oravsky-seniorat/istebne']],
            'address' => ['street' => 'Istebné 236', 'postcode' => '027 53'],
            'coordinates' => [49.2170060, 19.2210261, 'address'],
            'body' => [
                'Cirkevný zbor ECAV Istebné patrí do Liptovsko-oravského seniorátu. Zboru slúži drevený artikulárny kostol, národná kultúrna pamiatka. Zborovou farárkou je Mgr. Alena Ďurčíková.',
                'Služby Božie sú každú nedeľu o 9.00, v zime o 10.30. Zbor pripravuje s obcou a školou stretnutia pre rodiny, napríklad výrobu adventných vencov v kultúrnom dome.',
                'Kanál je určený evanjelikom z Istebného a okolia. Sledovaním budete vedieť o podujatiach zboru. '.self::CLOSING,
            ],
        ],
        1978 => [
            'name' => 'CZ ECAV Senica',
            'municipality' => 3113,
            'phone' => '034/651 26 83',
            'website' => 'https://www.ecav.sk/zapadny-distrikt/myjavsky-seniorat/senica',
            'emails' => [['email' => 'senica@ecav.sk', 'found_on' => 'https://www.ecav.sk/zapadny-distrikt/myjavsky-seniorat/senica']],
            'address' => ['street' => 'Hollého 747/22', 'postcode' => '905 01'],
            'coordinates' => [48.6803838, 17.3745378, 'address'],
            'body' => [
                'Cirkevný zbor ECAV Senica patrí do Myjavského seniorátu. Klasicistický tolerančný kostol na Hollého ulici postavili v rokoch 1783 – 1784. Zborovým farárom je Mgr. Ján Čermák.',
                'Služby Božie sú každú nedeľu o 10.00, prvú nedeľu v mesiaci aj o 14.00 v kaplnke domova dôchodcov. V kostole sa konajú koncerty, napríklad vianočný koncert Viery Šustekovej a hostí.',
                'Kanál je určený evanjelikom zo Senice a milovníkom hudby. Sledovaním budete vedieť o koncertoch a podujatiach zboru. '.self::CLOSING,
            ],
        ],
        1979 => [
            'name' => 'CZ ECAV Sučany',
            'municipality' => 3329,
            'phone' => '043/429 31 36',
            'website' => 'https://www.ecav.sk/vychodny-distrikt/turciansky-seniorat/sucany',
            'emails' => [['email' => 'sucany@ecav.sk', 'found_on' => 'https://www.ecav.sk/vychodny-distrikt/turciansky-seniorat/sucany']],
            'address' => ['street' => 'Námestie SNP 22', 'postcode' => '038 52'],
            'coordinates' => [49.1006065, 18.9914112, 'address'],
            'body' => [
                'Cirkevný zbor ECAV Sučany patrí do Turčianskeho seniorátu. Evanjelický kostol stojí na Námestí SNP. Zboru slúžia manželia Mgr. Martin Škarupa a Mgr. Eva Škarupová Lašáková.',
                'Služby Božie sú každú nedeľu o 9.45 v Sučanoch a raz do mesiaca v Turčianskych Kľačanoch, Podhradí, Turčianskej Štiavničke a Lipovci. S obcou a katolíckymi spevokolmi pripravuje adventný koncert.',
                'Kanál je určený evanjelikom zo Sučian a okolia. Sledovaním budete vedieť o koncertoch a podujatiach zboru. '.self::CLOSING,
            ],
        ],
        1980 => [
            'name' => 'NOS ECAV',
            'municipality' => 3829,
            'fix_municipality' => 2018,
            'phone' => '0918 570 044',
            'website' => 'https://www.ecav.sk/zapadny-distrikt/novohradsky-seniorat',
            'emails' => [['email' => 'nos@ecav.sk', 'found_on' => 'https://www.ecav.sk/zapadny-distrikt/novohradsky-seniorat']],
            'address' => ['street' => 'Malá Čalomija 78', 'postcode' => '991 08'],
            'coordinates' => [48.0842623, 19.2309494, 'municipality'],
            'body' => [
                'Novohradský seniorát ECAV združuje evanjelické zbory v Novohrade, napríklad v Lučenci, Poltári, Veľkom Krtíši či Ábelovej. Patrí do Západného dištriktu a sídli v Malej Čalomiji. Seniorkou je Mgr. Soňa Pichnarčíková.',
                'Pripravuje spoločné podujatia zborov, napríklad adventné stretnutie spevokolov seniorátu Adventná radosť v NOS.',
                'Kanál je určený evanjelikom z Novohradu. Sledovaním budete vedieť o seniorátnych stretnutiach. '.self::CLOSING,
            ],
        ],
    ];

    /** @var array<int, array<string, mixed>> */
    private const VENUES = [
        1112 => ['name' => 'Evanjelický kostol', 'fix_name' => 'Evanjelický kostol (Veľký Krtíš)', 'village' => 3829,
            'street' => 'A. H. Škultétyho', 'postcode' => '990 01', 'body' => [
                'Evanjelický kostol stojí na Ulici A. H. Škultétyho vo Veľkom Krtíši. Je kultúrnou pamiatkou a slúži cirkevnému zboru ECAV Veľký Krtíš. V meste sa narodil August Horislav Škultéty, syn evanjelického farára.',
                'Konajú sa tu služby Božie a stretnutia Novohradského seniorátu, napríklad adventné stretnutie spevokolov.',
                self::OTHERS,
            ]],
        1111 => ['name' => 'ECAV kostol', 'fix_name' => 'Evanjelický kostol (Sučany)', 'village' => 3329,
            'street' => 'Námestie SNP', 'replace_street' => '94', 'postcode' => '038 52',
            'latitude' => 49.1003513, 'longitude' => 18.9914408, 'coordinates_source' => 'venue', 'body' => [
                'Evanjelický kostol stojí na Námestí SNP v Sučanoch. Slúži cirkevnému zboru ECAV Sučany z Turčianskeho seniorátu.',
                'Konajú sa tu služby Božie a koncerty, napríklad adventný koncert evanjelických a katolíckych spevokolov so súborom Sučianka.',
                self::OTHERS,
            ]],
        1110 => ['name' => 'Evanjelický kostol augsburského vyznania v Senici', 'fix_name' => 'Evanjelický kostol (Senica)', 'village' => 3113,
            'street' => 'Hollého 747/22', 'replace_street' => 'Fajnorova 806/1', 'postcode' => '905 01', 'body' => [
                'Evanjelický kostol na Hollého ulici v Senici je tolerančný kostol z rokov 1783 – 1784 v klasicistickom slohu. Pôvodne nemal vežu, dnešnú pristavali v roku 1794.',
                'Slúži cirkevnému zboru ECAV Senica. Konajú sa tu služby Božie a koncerty, napríklad vianočný koncert Viery Šustekovej a hostí.',
                self::OTHERS,
            ]],
        1109 => ['name' => 'Kultúrny dom', 'fix_name' => 'Kultúrny dom (Istebné)', 'village' => 1225,
            'postcode' => '027 53', 'body' => [
                'Kultúrny dom v Istebnom je spoločenská sála obce v Dolnom Kubíne. Slúži na obecné slávnosti, kultúrne podujatia a stretnutia spolkov.',
                'Konajú sa tu aj stretnutia evanjelického zboru, napríklad výroba adventných vencov, ktorú pripravuje ECAV Istebné s obcou a školou.',
                self::OTHERS,
            ]],
        1108 => ['name' => 'Liptovský Hrádok, Závažná Poruba', 'fix_name' => 'Drevený artikulárny kostol (Svätý Kríž)', 'village' => 3356,
            'postcode' => '032 11', 'latitude' => 49.0531726, 'longitude' => 19.5358321, 'coordinates_source' => 'venue', 'body' => [
                'Drevený evanjelický artikulárny kostol vo Svätom Kríži je najväčším dreveným kostolom v strednej Európe. Preniesli ho sem z Paludze, ktorú zatopila Liptovská Mara.',
                'Konajú sa tu služby Božie a podujatia, napríklad Noc kostolov alebo koncerty hosťujúcich zborov, ktoré pripravuje Liptovsko-oravský seniorát.',
                self::OTHERS,
            ]],
        1107 => ['name' => 'Dominikánske kultúrne centrum Veritas', 'village' => 1565,
            'street' => 'Dominikánske námestie 8', 'replace_street' => 'Dominikánske námestie', 'postcode' => '040 01', 'body' => [
                'Dominikánske kultúrne centrum Veritas pôsobí pri Dominikánskom kostole na Dominikánskom námestí v Košiciach. Pripravujú ho bratia dominikáni.',
                'Konajú sa tu prednášky, diskusie a duchovné obnovy, napríklad adventné večery s prednáškou, občerstvením a adoráciou v kostole.',
                self::OTHERS,
            ]],
        1103 => ['name' => 'Mníchovská katedrála', 'fix_name' => 'Katedrála Panny Márie – Frauenkirche (Mníchov)', 'village' => 4133,
            'fix_city' => 'mnichov', 'country' => 'Nemecko', 'street' => 'Frauenplatz 12', 'postcode' => '80331',
            'latitude' => 48.1385821, 'longitude' => 11.5735920, 'coordinates_source' => 'venue', 'body' => [
                'Katedrála Panny Márie, známa ako Frauenkirche, je neskorogotický chrám v centre Mníchova s dvoma vežami zakončenými kupolami. Je sídelným chrámom arcibiskupa Mníchova a Freisingu.',
                'Arcibiskupom tu bol v rokoch 1977 – 1982 Joseph Ratzinger, neskôr pápež Benedikt XVI. Kardinál Reinhard Marx tu 18. apríla 2027 slávi svätú omšu k 100. výročiu jeho narodenia.',
                self::OTHERS,
            ]],
        1102 => ['name' => 'Hlavné námestie, Pastoračné centrum Marianum a Kostol Povýšenia svätého Kríža', 'fix_name' => 'Pastoračné centrum Marianum (Nové Zámky)', 'village' => 2431,
            'street' => 'Hlavné námestie', 'postcode' => '940 02',
            'latitude' => 47.9855330, 'longitude' => 18.1630477, 'coordinates_source' => 'address', 'body' => [
                'Pastoračné centrum Marianum je budova farnosti Nové Zámky na Hlavnom námestí vedľa farského Kostola Povýšenia svätého Kríža. Na budove je busta a pamätná tabuľa Antona Bernoláka.',
                'Konajú sa tu farské stretnutia, výstavy a premietania, napríklad pri Farskom misijnom dni. Pri dobrom počasí sa program presúva aj na Hlavné námestie.',
                self::OTHERS,
            ]],
    ];

    /** Zdroj => [cieľ, názov zdroja, názov cieľa]. */
    private const MERGES = [
        1105 => [733, 'Bratislava-Ružinov (stretnutie médií bude pred kostolom/brífing bude v interiéri)', 'Kostol sv. Vincenta de Paul'],
        1106 => [109, 'Katedrála', 'Dóm svätej Alžbety'],
        1104 => [850, 'Centrum Božieho milosrdenstva', 'Diecézna svätyňa Božieho milosrdenstva'],
        792 => [1108, 'Drevený artikulárny kostol (Svätý Kríž)', 'Drevený artikulárny kostol (Svätý Kríž)'],
    ];

    /** Zdrojový kanál => [cieľ, názov zdroja, názov cieľa]. */
    private const CANAL_MERGES = [
        1976 => [1950, 'o. Martin Pavúk a Emanuel', 'Emanuel'],
    ];

    /** Kľúč (c = kanál, v = miesto) => [súbor, názov na Commons, autor, licencia, odkaz na licenciu]. */
    private const PHOTOS = [
        'v1112' => ['velky-krtis-evanjelicky-kostol.jpg', 'Veľký Krtíš - Evanjelický kostol.jpg', 'Ladislav Luppa', 'CC BY-SA 3.0', 'https://creativecommons.org/licenses/by-sa/3.0'],
        'v1111' => ['sucany-evanjelicky-kostol.jpg', 'Sučany 1 0059.jpg', 'Darwinek', 'CC BY-SA 3.0', 'https://creativecommons.org/licenses/by-sa/3.0'],
        'v1110' => ['senica-evanjelicky-kostol.jpg', 'Senica - Evanjelický kostol.jpg', 'Akul59', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        'v1108' => ['svaty-kriz-artikularny-kostol.jpg', 'Artikulárny kostol - Svätý Kríž.JPG', 'Pudelek (Marcin Szala)', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        'v1107' => ['kosice-dominikansky-kostol.jpg', 'Košice dominikánsky 03.jpg', 'Ľuboš Repta', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        'v1103' => ['mnichov-frauenkirche.jpg', 'Frauenkirche Munich March 2013.JPG', 'Martin Falbisoner', 'CC BY-SA 3.0', 'https://creativecommons.org/licenses/by-sa/3.0'],
        'v1102' => ['nove-zamky-farsky-kostol-2.jpg', 'Érsekújvár, óvárosi római katolikus plébániatemplom 2025 02.jpg', 'Pasztilla aka Attila Terbócs', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        'v1095' => ['krajne-evanjelicky-kostol.jpg', 'Krajné kostol 02 02.jpg', 'Akul59', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        'v1094' => ['banska-bystrica-evanjelicky-kostol.jpg', 'Banská-Bystrica-evangelický-kostel2024.jpg', 'Ben Skála', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        'v1091' => ['hronsky-benadik-bazilika.jpg', 'Národná kultúrna pamiatka, Basilica minor, Sv. Benedikta, opáta Hronský Beňadik 19 Slovakia50.jpg', 'Ing.Mgr.Jozef Kotulič', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        'v1088' => ['poltar-evanjelicky-kostol.jpg', 'Poltár - Evanjelický kostol -b.jpg', 'Ladislav Luppa', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        'c1961' => ['pezinok-evanjelicky-kostol.jpg', 'Evanjelický a.v. kostol, Potočná ul., Pezinok, okres Pezinok 02.JPG', 'Horakvlado', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        'c1962' => ['tura-luka-evanjelicky-kostol.jpg', 'Turá Lúka, evanjelický kostol.jpg', 'Palickap', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        'c1963' => ['lubietova-evanjelicky-kostol.jpg', 'Ľubietová - evanjelický kostol - 7. 7. 2022.jpg', 'Patrik Kunec', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        'c1966' => ['trnava-evanjelicky-kostol.jpg', 'Evanjelický kostol v Trnave.jpg', 'Akul59', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        'c1967' => ['deutsch-jahrndorf-evangelische-kirche.jpg', 'Deutsch Jahrndorf evang Pfarrkirche.jpg', 'Peter Lauppert', 'CC BY-SA 3.0 at', 'https://creativecommons.org/licenses/by-sa/3.0/at/deed.en'],
        'c1968' => ['roznava-evanjelicky-kostol.jpg', 'Rožňava - Evanjelický kostol.jpg', 'Ladislav Luppa', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        'c1969' => ['gelnica-evanjelicky-kostol.jpg', 'Gelnica Evanjelický kostol-4.jpg', 'Investigatio', 'CC BY-SA 3.0', 'https://creativecommons.org/licenses/by-sa/3.0'],
        'c1971' => ['banska-bystrica-evanjelicky-kostol-fara.jpg', 'Banská-Bystrica-evangelický-kostel2024b.jpg', 'Ben Skála', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        'c1972' => ['krajne-evanjelicky-kostol-2.jpg', 'Krajné kostol 02 01.jpg', 'Akul59', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        'c1975' => ['nove-zamky-farsky-kostol.jpg', 'Érsekújvár, óvárosi római katolikus plébániatemplom 2025 01.jpg', 'Pasztilla aka Attila Terbócs', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        'c1977' => ['istebne-artikularny-kostol.jpg', 'SK-Istebne-Artikularkirche-05.jpg', 'SchiDD', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        'c1978' => ['senica-evanjelicky-kostol.jpg', 'Senica - Evanjelický kostol.jpg', 'Akul59', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        'c1979' => ['sucany-evanjelicky-kostol.jpg', 'Sučany 1 0059.jpg', 'Darwinek', 'CC BY-SA 3.0', 'https://creativecommons.org/licenses/by-sa/3.0'],
    ];

    public function up(): void
    {
        $this->completeCanals();
        $this->mergeCanals();
        $this->completeVenues();
        $this->mergeVenues();
        $this->attachPhotos();
    }

    private function completeCanals(): void
    {
        $emails = Schema::hasTable('canal_emails') ? app(CanalEmails::class) : null;

        foreach (self::CANALS as $id => $profile) {
            $canal = Canal::withTrashed()->find($id);

            if (! $canal || $canal->trashed() || $canal->merged_into_id !== null || $canal->claimed_at !== null
                || $canal->name !== $profile['name']) {
                continue;
            }

            $municipality = (int) $canal->municipality_id;
            if ($municipality !== $profile['municipality'] && $municipality !== ($profile['fix_municipality'] ?? null)) {
                continue;
            }

            $changes = [];

            if (isset($profile['fix_municipality']) && $municipality === $profile['municipality']) {
                $changes['municipality_id'] = $profile['fix_municipality'];
            }

            if (substr_count((string) $canal->body, '<p') !== 3) {
                $changes['body'] = self::paragraphs($profile['body']);
            }

            foreach (['website', 'phone'] as $field) {
                if (trim((string) $canal->$field) === '') {
                    $changes[$field] = $profile[$field];
                }
            }

            if (trim((string) $canal->street) === '') {
                $changes['street'] = $profile['address']['street'];
                $changes['postcode'] = $profile['address']['postcode'];
            }

            $looseCoordinates = in_array($canal->coordinates_source, ['municipality', 'nationwide', 'ai', null], true);
            $atCenter = abs((float) $canal->latitude - self::NATIONWIDE[0]) < 0.0001
                && abs((float) $canal->longitude - self::NATIONWIDE[1]) < 0.0001;

            if ($looseCoordinates && ($atCenter || isset($changes['street']) || isset($changes['municipality_id']))) {
                [$lat, $lng, $source] = $profile['coordinates'];
                $changes += ['latitude' => $lat, 'longitude' => $lng, 'coordinates_source' => $source];
            }

            if ($changes !== []) {
                $canal->forceFill($changes)->save();
                Recorder::info('canals', 'profile_described', "Kanál {$canal->name}: doplnené ".implode(', ', array_keys($changes)),
                    status: 'ok', subject: $canal, context: ['fields' => array_keys($changes)]);
            }

            if ($emails) {
                $emails->discover($canal->fresh(), $profile['emails'], 'manual');
                $emails->refresh($canal->fresh());
            }
        }
    }

    private function mergeCanals(): void
    {
        foreach (self::CANAL_MERGES as $sourceId => [$targetId, $sourceName, $targetName]) {
            $source = Canal::query()->find($sourceId);
            $target = Canal::query()->find($targetId);
            if (! $source || ! $target || $source->name !== $sourceName || $target->name !== $targetName
                || $source->claimed_at !== null || $source->merged_into_id !== null || $target->merged_into_id !== null) {
                continue;
            }
            try {
                app(ResourceMerger::class)->merge('canals', $sourceId, $targetId, null);
            } catch (Throwable $e) {
                Recorder::warning('admin', 'resource.merge_skipped', "Zlúčenie kanála #{$sourceId} → #{$targetId} preskočené",
                    subject: $target, context: ['error' => mb_substr($e->getMessage(), 0, 250)]);
            }
        }
    }

    private function completeVenues(): void
    {
        foreach (self::VENUES as $id => $data) {
            $venue = Venue::query()->find($id);
            $village = (int) $venue?->village_id;
            $fixVillage = isset($data['fix_city']) ? $this->city($data['fix_city']) : null;

            if (! $venue || $venue->status === ModelStatus::Archived
                || ($venue->name !== $data['name'] && $venue->name !== ($data['fix_name'] ?? null))
                || ($village !== $data['village'] && ($fixVillage === null || $village !== $fixVillage))) {
                continue;
            }

            $changes = [];

            if (isset($data['fix_name']) && $venue->name === $data['name']) {
                $changes['name'] = $data['fix_name'];
            }

            if ($fixVillage !== null && $village === $data['village']) {
                $changes['village_id'] = $fixVillage;
            }

            if (isset($data['country'])) {
                $changes['country'] = $data['country'];
            }

            if (substr_count((string) $venue->body, '<p') !== 3) {
                $changes['body'] = self::paragraphs($data['body']);
            }

            $street = trim((string) $venue->street);
            if (isset($data['street']) && ($street === '' || $street === ($data['replace_street'] ?? null))) {
                $changes['street'] = $data['street'];
            }

            // Poštové smerovacie číslo sa opraví aj vtedy, keď ho import vzal z inej obce.
            if (isset($data['postcode']) && (trim((string) $venue->postcode) === '' || isset($changes['street']) || isset($changes['village_id']))) {
                $changes['postcode'] = $data['postcode'];
            }

            $precise = in_array($venue->coordinates_source, ['venue', 'address', 'manual'], true);
            if (isset($data['latitude']) && (! $precise || isset($changes['village_id']))) {
                $changes += ['latitude' => $data['latitude'], 'longitude' => $data['longitude'], 'coordinates_source' => $data['coordinates_source']];
            }

            $changes = array_filter($changes, fn ($value, $field) => $value != $venue->$field, ARRAY_FILTER_USE_BOTH);

            if ($changes !== []) {
                $venue->forceFill($changes)->save();
                Recorder::info('venues', 'profile_described', "Miesto {$venue->name}: doplnené ".implode(', ', array_keys($changes)),
                    status: 'ok', subject: $venue, context: ['fields' => array_keys($changes)]);
            }
        }
    }

    private function mergeVenues(): void
    {
        foreach (self::MERGES as $sourceId => [$targetId, $sourceName, $targetName]) {
            $source = Venue::query()->find($sourceId);
            $target = Venue::query()->find($targetId);
            if (! $source || ! $target || $source->name !== $sourceName || $target->name !== $targetName
                || $target->status === ModelStatus::Archived) {
                continue;
            }

            // Koncept z importu bez podujatí (#1104) sa zlučuje ako publikovaný — inak ho merger odmietne.
            if ($source->status === ModelStatus::Draft && ! DB::table('events')->where('venue_id', $sourceId)->exists()) {
                $source->forceFill(['status' => $target->status])->save();
            }

            try {
                app(ResourceMerger::class)->merge('venues', $sourceId, $targetId, null);
            } catch (Throwable $e) {
                Recorder::warning('admin', 'resource.merge_skipped', "Zlúčenie miesta #{$sourceId} → #{$targetId} preskočené",
                    subject: $target, context: ['error' => mb_substr($e->getMessage(), 0, 250)]);
            }
        }
    }

    private function attachPhotos(): void
    {
        if (! Schema::hasTable('files')) {
            return;
        }

        foreach (self::PHOTOS as $key => [$file, $title, $author, $license, $licenseUrl]) {
            $id = (int) substr($key, 1);
            $model = $key[0] === 'c' ? Canal::query()->find($id) : Venue::query()->find($id);

            if (! $model || $this->hasImage($model)
                || ($model instanceof Canal && ($model->claimed_at !== null || $model->merged_into_id !== null))
                || ($model instanceof Venue && $model->status === ModelStatus::Archived)) {
                continue;
            }

            $path = __DIR__.'/assets/canal-venue-photos/'.$file;
            if (! is_file($path)) {
                continue;
            }

            try {
                $model->addFiles(
                    files: new UploadedFile($path, $file, 'image/jpeg', null, true),
                    type: FileType::IMAGE,
                    makePrimary: true,
                    meta: [
                        'source' => self::SOURCE,
                        'origin' => 'wikimedia_commons',
                        'commons_title' => 'File:'.$title,
                        'commons_page' => 'https://commons.wikimedia.org/wiki/File:'.rawurlencode(str_replace(' ', '_', $title)),
                        'author' => $author,
                        'license' => $license,
                        'license_url' => $licenseUrl,
                    ],
                );
            } catch (Throwable $e) {
                Recorder::warning('files', 'photo_skipped', "Fotka {$file} pre #{$id} sa nenahrala",
                    subject: $model, context: ['error' => mb_substr($e->getMessage(), 0, 250)]);
            }
        }
    }

    private function hasImage(Model $model): bool
    {
        return File::query()
            ->where('fileable_type', $model->getMorphClass())
            ->where('fileable_id', $model->getKey())
            ->where('type', FileType::IMAGE->value)
            ->exists();
    }

    /** Obec podľa slugu; zahraničnú obec zo self::CITIES vytvorí raz. */
    private function city(string $slug): ?int
    {
        $id = DB::table('municipalities')->where('slug', $slug)->value('id');
        if ($id !== null) {
            return (int) $id;
        }

        if (! isset(self::CITIES[$slug])) {
            return null;
        }

        return (int) DB::table('municipalities')->insertGetId([
            'fullname' => self::CITIES[$slug]['name'], 'shortname' => self::CITIES[$slug]['name'], 'slug' => $slug,
            'zip' => self::CITIES[$slug]['zip'], 'district_id' => 0, 'region_id' => 0, 'use' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @param  array<int, string>  $paragraphs */
    private static function paragraphs(array $paragraphs): string
    {
        return implode('', array_map(fn (string $p) => '<p>'.e($p).'</p>', $paragraphs));
    }

    public function down(): void
    {
        // Doplnené údaje a zlúčenia sa nevracajú; odstránia sa len nahraté fotky.
        if (! Schema::hasTable('files')) {
            return;
        }

        File::query()
            ->where('meta->source', self::SOURCE)
            ->get()
            ->each(fn (File $file) => $file->delete());
    }
};
