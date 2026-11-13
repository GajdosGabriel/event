<?php

use App\Models\Canal;
use App\Models\Municipality;
use App\Services\SystemLog\Recorder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ôsma dávka (november 2026): ďalších 60 importovaných kanálov v poradí
 * (#1193 – #1266). Kanály so všeobecným AI popisom alebo len „organizátor
 * podujatí" dostanú tri odseky podľa podujatí kanála a verejne známych faktov.
 *
 * Opravy: #1264 Slovenská farnosť v Prahe bola v obci Praha pri Lučenci,
 * #1257 farnosť v Topoľčiankach mala ako web vatican.va.
 *
 * Kanál musí sedieť ID aj názvom; prevzatý, zlúčený či zmazaný sa preskočí.
 */
return new class extends Migration
{
    private const CLOSING = 'Podrobnosti nájdete vždy v detaile podujatia.';

    /** @var array<int, array<string, mixed>> */
    private const PROFILES = [
        1266 => [
            'name' => 'Vincenskí-laickí misionári',
            'body' => [
                0 => 'Vincentskí laickí misionári (MiSeVi) sú laické hnutie vincentskej rodiny. Na Slovensku sídlia na Hurbanovej ulici v Banskej Bystrici. Pripravujú laikov na misie.',
                1 => 'Organizujú misijnú formáciu pre dobrovoľníkov, ktorí chcú ísť na misie, a podporujú misijné projekty.',
                2 => 'Kanál je určený budúcim misionárom a podporovateľom misií. Sledovaním budete vedieť o formácii. '.self::CLOSING,
            ],
        ],
        1264 => [
            'name' => 'Slovenská rímskokatolícka farnosť v Prahe',
            'city' => 'praha-cesko',
            'body' => [
                0 => 'Slovenská rímskokatolícka farnosť v Prahe slúži Slovákom žijúcim v Prahe. Sväté omše slávi po slovensky. Vznikla v roku 2008.',
                1 => 'Pripravuje slávnosti a prednášky, napríklad oslavu 15. výročia s biskupom Františkom Rábekom či cyklus Nezlomní o hrdinoch viery.',
                2 => 'Kanál je určený Slovákom v Prahe. Sledovaním budete vedieť o podujatiach farnosti. '.self::CLOSING,
            ],
        ],
        1262 => [
            'name' => 'Forma Ecclesia Cristiano Cali',
            'body' => [
                0 => 'Forma Ecclesia je talianska iniciatíva formácie kňazov a laikov. Jej web je formaecclesia.it.',
                1 => 'Pripravuje online prednášky, napríklad prednášku kardinála Angela Bagnasca o kňazskej službe, ktorú mohli sledovať aj veriaci zo Slovenska.',
                2 => 'Kanál je určený kňazom a laikom, ktorí hľadajú formáciu. '.self::CLOSING,
            ],
        ],
        1261 => [
            'name' => 'farnosť Bernolákovo',
            'body' => [
                0 => 'Farnosť Bernolákovo pri Senci patrí do Bratislavskej arcidiecézy. Slúži veriacim obce.',
                1 => 'Pripravuje farské slávnosti, napríklad Farský deň s programom pre rodiny.',
                2 => 'Kanál je určený veriacim z Bernolákova. Sledovaním budete vedieť o podujatiach farnosti. '.self::CLOSING,
            ],
        ],
        1260 => [
            'name' => 'Múzeum histórie obce Divín',
            'body' => [
                0 => 'Múzeum histórie obce Divín sídli v Zichyho kaštieli v Divíne v okrese Lučenec. Približuje dejiny obce a hradu Divín.',
                1 => 'Pripravuje sezónne výstavy, napríklad výstavu liturgických odevov a remeselných ozdôb.',
                2 => 'Kanál je určený milovníkom histórie Novohradu. Sledovaním budete vedieť o výstavách. '.self::CLOSING,
            ],
        ],
        1259 => [
            'name' => 'Žilinský miešaný zbor',
            'body' => [
                0 => 'Žilinský miešaný zbor je spevácky zbor zo Žiliny. Spieva duchovnú aj svetskú zborovú hudbu.',
                1 => 'Organizuje Medzinárodný festival a súťaž zborového umenia Žilina Voce Magna. Na festival prichádzajú zbory z celého sveta.',
                2 => 'Kanál je určený milovníkom zborového spevu. Sledovaním budete vedieť o festivale. '.self::CLOSING,
            ],
        ],
        1258 => [
            'name' => 'Farnosť Badín, filiálka Vlkanová',
            'body' => [
                0 => 'Farnosť Badín pri Banskej Bystrici patrí do Banskobystrickej diecézy. K farnosti patrí filiálka Vlkanová.',
                1 => 'Organizuje púte mladých, napríklad na festival Mladifest v Medžugorí.',
                2 => 'Kanál je určený mladým z Badína a Vlkanovej. Sledovaním budete vedieť o pútiach. '.self::CLOSING,
            ],
        ],
        1257 => [
            'name' => 'Rímskokatolícka Cirkev, farnosť svätej Kataríny Alexandrijskej',
            'fix_website' => null,
            'body' => [
                0 => 'Farnosť sv. Kataríny Alexandrijskej v Topoľčiankach patrí do Nitrianskej diecézy. Farským kostolom je Kostol sv. Kataríny Alexandrijskej.',
                1 => 'Pripravuje duchovné obnovy, napríklad obnovu s františkánom fra Ivom Pavićom z Medžugoria.',
                2 => 'Kanál je určený veriacim z Topoľčianok. Sledovaním budete vedieť o obnovách. '.self::CLOSING,
            ],
        ],
        1256 => [
            'name' => 'Obec Terchová',
            'body' => [
                0 => 'Terchová je obec pod Malou Fatrou, rodisko Juraja Jánošíka. Je vstupnou bránou do Vrátnej doliny.',
                1 => 'Obec a farnosť Terchová každoročne začiatkom júla pripravujú Cyrilometodské slávnosti. Konajú sa tu aj Jánošíkove dni.',
                2 => 'Kanál je určený obyvateľom a návštevníkom Terchovej. Sledovaním budete vedieť o slávnostiach. '.self::CLOSING,
            ],
        ],
        1255 => [
            'name' => 'Slovenská filharmónia',
            'body' => [
                0 => 'Slovenská filharmónia je najvýznamnejšia slovenská inštitúcia symfonickej hudby. Sídli v Redute v Bratislave.',
                1 => 'Jej hudobníci vystupujú aj na koncertoch mimo Bratislavy, napríklad na memoriáloch a slávnostiach v kostoloch.',
                2 => 'Kanál je určený milovníkom klasickej hudby. '.self::CLOSING,
            ],
        ],
        1254 => [
            'name' => 'farnosť Stropkov',
            'body' => [
                0 => 'Farnosť Stropkov patrí do Košickej arcidiecézy. Je známa úctou k Panne Márii Karmelskej. V Stropkove pôsobia aj františkáni.',
                1 => 'Pripravuje odpustovú slávnosť Panny Márie Karmelskej, na ktorú prichádzajú aj biskupi.',
                2 => 'Kanál je určený veriacim zo Stropkova. Sledovaním budete vedieť o slávnostiach. '.self::CLOSING,
            ],
        ],
        1252 => [
            'name' => 'Škola umenia a remesiel svätého Petra vo Vatikáne',
            'body' => [
                0 => 'Škola umenia a remesiel sv. Petra vo Vatikáne pripravuje remeselníkov na obnovu Baziliky sv. Petra. Spravuje ju Fabbrica di San Pietro.',
                1 => 'Ponúka štúdium mladým z celého sveta, aj zo Slovenska. Učí tradičné remeslá ako kamenárstvo či mozaiku.',
                2 => 'Kanál je určený mladým, ktorí sa zaujímajú o umelecké remeslá. '.self::CLOSING,
            ],
        ],
        1251 => [
            'name' => 'Pápežská švajčiarska garda',
            'body' => [
                0 => 'Pápežská švajčiarska garda je osobná stráž pápeža. Slúžia v nej mladí katolícki muži zo Švajčiarska. Vznikla v roku 1506.',
                1 => 'Pripravuje informačné dni pre záujemcov o službu v garde.',
                2 => 'Kanál je určený záujemcom o službu v garde. '.self::CLOSING,
            ],
        ],
        1250 => [
            'name' => 'farár v Skalke nad Váhom Štefan Bučo',
            'body' => [
                0 => 'Štefan Bučo je farár v Skalke nad Váhom. Skalka pri Trenčíne je najstarším pútnickým miestom na Slovensku.',
                1 => 'Pozýva na púte k sv. Andrejovi-Svoradovi a sv. Beňadikovi, pustovníkom zo Skalky.',
                2 => 'Kanál je určený pútnikom na Skalku. Sledovaním budete vedieť o pútiach. '.self::CLOSING,
            ],
        ],
        1247 => [
            'name' => 'Akadémia sv. Michala',
            'body' => [
                0 => 'Akadémia sv. Michala je katolícka iniciatíva v Bratislave pre deti a rodiny. Jej web je akademiasvmichala.sk.',
                1 => 'Pripravuje denné tábory pre deti, napríklad tábor Noemova archa pre deti od 6 do 10 rokov.',
                2 => 'Kanál je určený rodinám s deťmi. Sledovaním budete vedieť o táboroch. '.self::CLOSING,
            ],
        ],
        1246 => [
            'name' => 'Slovenská biblická spoločnosť',
            'body' => [
                0 => 'Slovenská biblická spoločnosť prekladá, vydáva a šíri Bibliu na Slovensku. Je ekumenická a patrí do Spojených biblických spoločností.',
                1 => 'Pripravuje diskusie a výstavy, napríklad diskusiu Biblia známa/neznáma v Žiline.',
                2 => 'Kanál je určený všetkým, ktorých zaujíma Biblia. '.self::CLOSING,
            ],
        ],
        1245 => [
            'name' => 'Anointing Fire Catholic Ministry (AFCM)',
            'body' => [
                0 => 'Anointing Fire Catholic Ministry je katolícka evanjelizačná služba, ktorú založil páter Antony Parankimalil. Pripravuje obnovy po celom svete.',
                1 => 'Na Slovensku pripravila napríklad duchovné obnovy pre mužov a pre ženy v Poprade.',
                2 => 'Kanál je určený všetkým, ktorí hľadajú duchovnú obnovu. '.self::CLOSING,
            ],
        ],
        1242 => [
            'name' => 'Bratia karmelitáni',
            'body' => [
                0 => 'Bratia karmelitáni pôsobia na Starých Horách pri Bazilike Navštívenia Panny Márie. Šíria úctu k Panne Márii Karmelskej a škapuliaru.',
                1 => 'Pripravujú Škapuliarsku púť a celoslovenské stretnutia Škapuliarskej rodiny.',
                2 => 'Kanál je určený ctiteľom škapuliara. Sledovaním budete vedieť o pútiach. '.self::CLOSING,
            ],
        ],
        1237 => [
            'name' => 'Obecný úrad Močenok',
            'body' => [
                0 => 'Obecný úrad Močenok spravuje obec Močenok v okrese Šaľa. K obci patrí Gorazdov.',
                1 => 'Spolupodieľa sa na festivale Gorazdov Močenok a púti z Močenku do Nitry po náučnom chodníku sv. Gorazda.',
                2 => 'Kanál je určený obyvateľom a pútnikom. '.self::CLOSING,
            ],
        ],
        1236 => [
            'name' => 'Stredoslovenské múzeum',
            'body' => [
                0 => 'Stredoslovenské múzeum v Banskej Bystrici je regionálne múzeum Banskobystrického samosprávneho kraja. Sídli v Thurzovom dome a Matejovom dome.',
                1 => 'Pripravuje výstavy a prednášky, napríklad o osude kňazov počas povstaní.',
                2 => 'Kanál je určený milovníkom histórie. Sledovaním budete vedieť o prednáškach. '.self::CLOSING,
            ],
        ],
        1233 => [
            'name' => 'Sekcia slovesného umenia Rady pre vedu, vzdelanie a kultúru Konferencie biskupov Slovenska',
            'body' => [
                0 => 'Sekcia slovesného umenia pôsobí pri Rade KBS pre vedu, vzdelanie a kultúru. Podporuje literárnu tvorbu.',
                1 => 'Vyhlasuje literárne súťaže pre stredoškolákov, napríklad Korene mojej hrdosti či Niekoľko viet o mojom otcovi.',
                2 => 'Kanál je určený študentom a učiteľom. Sledovaním budete vedieť o súťažiach. '.self::CLOSING,
            ],
        ],
        1232 => [
            'name' => 'Poľský úrad pre blahorečenie rodiny Ulmovcov',
            'body' => [
                0 => 'Rodina Ulmovcov z Markowej ukrývala počas vojny Židov a nacisti ju za to zavraždili. Pápež ju blahorečil v roku 2023.',
                1 => 'Úrad pripravoval slávnosť blahorečenia v Markowej a prihlasovanie pútnikov, aj zo Slovenska.',
                2 => 'Kanál je určený ctiteľom rodiny Ulmovcov. '.self::CLOSING,
            ],
        ],
        1231 => [
            'name' => 'Pavol Rehák',
            'body' => [
                0 => 'Pavol Rehák pripravuje saleziánske programy pre mladých.',
                1 => 'Organizuje Saleziánsky plenér na saleziánskej chate v Banskej Belej, kde mladí píšu hlaholikou a vyrábajú papier.',
                2 => 'Kanál je určený mladým. Sledovaním budete vedieť o plenéri. '.self::CLOSING,
            ],
        ],
        1230 => [
            'name' => 'COMECE Youth net',
            'body' => [
                0 => 'COMECE Youth Net je sieť mladých pri Komisii biskupských konferencií Európskej únie. Zapája mladých do diskusií o Európe.',
                1 => 'Pripravila napríklad diskusiu na Svetových dňoch mládeže v Lisabone.',
                2 => 'Kanál je určený mladým, ktorých zaujíma Európa. '.self::CLOSING,
            ],
        ],
        1229 => [
            'name' => 'Farnosť svätej Anny',
            'body' => [
                0 => 'Farnosť sv. Anny v Lehote pri Nitre patrí do Nitrianskej diecézy. Nad obcou je mariánsky pútnický areál.',
                1 => 'Každé leto pripravuje výročnú mariánsku púť, v roku 2023 už jej 30. ročník.',
                2 => 'Kanál je určený pútnikom. Sledovaním budete vedieť o púti. '.self::CLOSING,
            ],
        ],
        1228 => [
            'name' => 'juhokórejská skautská organizácia',
            'body' => [
                0 => 'Juhokórejská skautská organizácia hostila 25. svetové skautské jamboree v roku 2023.',
                1 => 'Na jamboree odcestovalo aj 86 slovenských skautov a skautiek.',
                2 => 'Kanál je určený skautom. '.self::CLOSING,
            ],
        ],
        1220 => [
            'name' => 'Diecézna náboženská nadácia - Svätyňa zrieknutia sa šiat sv. Františka',
            'body' => [
                0 => 'Svätyňa zrieknutia sa šiat v Assisi pripomína miesto, kde sa sv. František zriekol majetku. Je tu pochovaný sv. Carlo Acutis.',
                1 => 'Nadácia svätyne vyhlasuje Medzinárodnú cenu Františka z Assisi a Carla Acutisa za projekty Františkovej ekonomiky.',
                2 => 'Kanál je určený mladým a podnikateľom. '.self::CLOSING,
            ],
        ],
        1219 => [
            'name' => 'Gemerský seniorát ECAV na Slovensku',
            'body' => [
                0 => 'Gemerský seniorát spája evanjelické cirkevné zbory na Gemeri. Patrí do Východného dištriktu ECAV.',
                1 => 'Pripravuje Dni Gemerského seniorátu, napríklad vo Vyšnej Slanej či Rejdovej. Program spája služby Božie a stretnutie zborov.',
                2 => 'Kanál je určený evanjelikom z Gemera. Sledovaním budete vedieť o dňoch seniorátu. '.self::CLOSING,
            ],
        ],
        1217 => [
            'name' => 'Katedra filozofie a histórie Teologickej Fakulty Katolíckej univerzity v Košiciach',
            'body' => [
                0 => 'Katedra filozofie a histórie pôsobí na Teologickej fakulte Katolíckej univerzity v Košiciach.',
                1 => 'Pripravuje konferencie, napríklad konferenciu Zmena paradigmy, paradigma zmeny.',
                2 => 'Kanál je určený študentom a odborníkom. '.self::CLOSING,
            ],
        ],
        1215 => [
            'name' => 'Nadácia Svetových dní mládeže',
            'body' => [
                0 => 'Nadácia Svetových dní mládeže pripravovala SDM v Lisabone v roku 2023.',
                1 => 'Po SDM pripravila prieskum o vplyve podujatia na hospodárstvo, do ktorého sa mohli zapojiť aj účastníci zo Slovenska.',
                2 => 'Kanál je určený účastníkom SDM. '.self::CLOSING,
            ],
        ],
        1211 => [
            'name' => 'Nadácia Fides et Ratio',
            'body' => [
                0 => 'Nadácia Fides et Ratio podporuje kresťanskú kultúru a vzdelávanie na Slovensku.',
                1 => 'Vyhlasuje grantové schémy, napríklad Kresťanské umenie vo verejnom priestore.',
                2 => 'Kanál je určený umelcom a organizáciám. Sledovaním budete vedieť o grantoch. '.self::CLOSING,
            ],
        ],
        1210 => [
            'name' => 'Farnosť Ružencovej Panny Márie',
            'body' => [
                0 => 'Farnosť Ružencovej Panny Márie v Obišovciach spravuje diecéznu svätyňu Košickej arcidiecézy.',
                1 => 'Pripravuje púte a slávnosti, napríklad požehnanie nového oltára v pútnickom areáli.',
                2 => 'Kanál je určený pútnikom. Sledovaním budete vedieť o pútiach. '.self::CLOSING,
            ],
        ],
        1207 => [
            'name' => 'Farnosť svätého Mikuláša',
            'body' => [
                0 => 'Farnosť sv. Mikuláša v Prešove patrí do Košickej arcidiecézy. Jej hlavným chrámom je Konkatedrála sv. Mikuláša.',
                1 => 'Pripravuje odpustovú slávnosť Povýšenia Svätého Kríža na Prešovskej kalvárii.',
                2 => 'Kanál je určený veriacim z Prešova. Sledovaním budete vedieť o slávnostiach. '.self::CLOSING,
            ],
        ],
        1206 => [
            'name' => 'Katolícky ordinariát pre cirkvi východného obradu',
            'body' => [
                0 => 'Ordinariát pre veriacich východných katolíckych cirkví v Rakúsku spravuje gréckokatolíkov bez vlastného biskupa. Vedie ho viedenský arcibiskup.',
                1 => 'Pripravuje bohoslužby, napríklad ekumenickú bohoslužbu vo Viedni k ukrajinskému štátnemu sviatku.',
                2 => 'Kanál je určený gréckokatolíkom v Rakúsku. '.self::CLOSING,
            ],
        ],
        1203 => [
            'name' => 'Oravské centrum mládeže',
            'body' => [
                0 => 'Oravské centrum mládeže v Ústí nad Priehradou pripravuje programy pre mladých z Oravy. Spolupracuje so združením V.I.A.C.',
                1 => 'Organizuje semináre a online programy, napríklad pôstny program Odrobiny či seminár o službe oslobodenia.',
                2 => 'Kanál je určený mladým z Oravy. Sledovaním budete vedieť o programoch. '.self::CLOSING,
            ],
        ],
        1202 => [
            'name' => 'saleziánok GPS',
            'body' => [
                0 => 'Projekt GPS pripravujú saleziánky pre dievčatá, ktoré hľadajú svoje povolanie.',
                1 => 'Ponúka ročný formačný kurz pre stredoškoláčky a mladé ženy.',
                2 => 'Kanál je určený dievčatám. Sledovaním budete vedieť o kurzoch. '.self::CLOSING,
            ],
        ],
        1201 => [
            'name' => 'Farský úrad vo Višňovom',
            'body' => [
                0 => 'Farnosť Višňové pri Žiline patrí do Žilinskej diecézy.',
                1 => 'Pripravuje diecézne púte, napríklad diecéznu púť mužov a mariánsku púť.',
                2 => 'Kanál je určený pútnikom. Sledovaním budete vedieť o pútiach. '.self::CLOSING,
            ],
        ],
        1199 => [
            'name' => 'Mesto Kežmarok',
            'body' => [
                0 => 'Kežmarok je historické mesto pod Vysokými Tatrami. Má drevený artikulárny kostol zapísaný v UNESCO a Baziliku sv. Kríža.',
                1 => 'Mesto pripravuje kultúrne podujatia, napríklad sériu Zem (zas)ľúbená.',
                2 => 'Kanál je určený obyvateľom a návštevníkom Kežmarku. '.self::CLOSING,
            ],
        ],
        1198 => [
            'name' => 'UPC Prešov',
            'body' => [
                0 => 'Univerzitné pastoračné centrum v Prešove sa venuje vysokoškolákom prešovských univerzít.',
                1 => 'Spolupodieľa sa na slávnostiach, napríklad na odpuste Povýšenia Svätého Kríža na Prešovskej kalvárii.',
                2 => 'Kanál je určený študentom v Prešove. '.self::CLOSING,
            ],
        ],
        1196 => [
            'name' => 'Teologický inštitút Spišská Kapitula',
            'body' => [
                0 => 'Teologický inštitút v Spišskej Kapitule pripravuje kandidátov kňazstva Spišskej diecézy. Je súčasťou Katolíckej univerzity v Ružomberku.',
                1 => 'Hostí napríklad Celoslovenské stretnutie chrámových hudobníkov.',
                2 => 'Kanál je určený študentom a chrámovým hudobníkom. '.self::CLOSING,
            ],
        ],
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

            if (array_key_exists('fix_website', $profile) && $canal->website !== $profile['fix_website']) {
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
