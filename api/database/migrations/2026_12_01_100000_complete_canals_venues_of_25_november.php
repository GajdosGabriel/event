<?php

use App\Enums\FileType;
use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\Event;
use App\Models\File;
use App\Models\Venue;
use App\Services\Resources\ResourceMerger;
use App\Services\SystemLog\Recorder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kanály, miesta a podujatia z importu 25. 11. 2026 (kanály #1987 – #1991,
 * miesta #1120 – #1123, podujatia #11458 – #11476).
 *
 * Kanály: #1987 Farnosť Liptovský Mikuláš dostane popis z faralm.sk (dekan
 * Stanislav Culka, kostol sv. Mikuláša). #1990 Novohradský seniorát ECAV mal
 * obec Stredné Plachtince (miesto podujatia) a súradnice stredu Slovenska —
 * sídlo je Malá Čalomija 78 (ecav.sk/zapadny-distrikt/novohradsky-seniorat).
 * #1988 je prevzatý, #1989 a #1991 sú osobné kanály používateľov — nemenia sa.
 *
 * Miesta: #1120 Svätoplukovo námestie a #1122 Kultúrny dom dostanú obec do
 * názvu a popis, #1123 Kostol sv. Františka z Assisi v Detve adresu Partizánska
 * 49/73 (import dal ulicu „Kostol", PSČ bez medzery a krajinu „Slovakia"). #1121 „Evanjelickom chráme
 * a. v. na Mlynskej ulici 23" je ten istý kostol ako #1083 — zlúči sa.
 *
 * Podujatia: #11469 — omša s podpisom duchovného puta Liptovského Mikuláša s Bari
 * je v Bazilike sv. Mikuláša v Bari, nie v trnavskej bazilike (#823). Miesto aj
 * obec Bari sa založia. #11475 Adventná radosť v NOS dostane popis a kontakt
 * seniorátu, #11461 stratí prázdne riadky „Ulica" a „Webstránka".
 * #11476 je podujatie používateľa z osobného kanála — nemení sa.
 *
 * Fotky: zmenšeniny 1280 px z Commons v assets/canal-venue-photos, autor a licencia
 * v meta. Fotku dostane len kanál či miesto bez obrázka; prevzatý kanál sa nemení.
 * Kanál musí sedieť ID aj názvom; prevzatý, zlúčený či zmazaný sa preskočí.
 */
return new class extends Migration
{
    private const SOURCE = 'migration:2026_12_01_canal_venue_photo';

    private const CLOSING = 'Podrobnosti nájdete vždy v detaile podujatia.';

    private const OTHERS = 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.';

    /** Súradnice, ktoré import dáva kanálom bez adresy (stred Slovenska). */
    private const NATIONWIDE = [48.7411522, 19.4528646];

    /** Zahraničné obce, ktoré v číselníku ešte nie sú. */
    private const CITIES = [
        'bari' => ['name' => 'Bari', 'zip' => '70122'],
    ];

    /**
     * `fix_municipality` => [chybná obec z importu, správna obec].
     *
     * @var array<int, array<string, mixed>>
     */
    private const CANALS = [
        1987 => [
            'name' => 'Farnosť Liptovský Mikuláš',
            'municipality' => 1892,
            'body' => [
                'Rímskokatolícka farnosť Liptovský Mikuláš patrí do Spišskej diecézy. Farský úrad sídli na Moyzesovej ulici 662/3, farským kostolom je kostol svätého Mikuláša, najstaršia stavebná pamiatka mesta a najväčšia ranogotická stavba na Liptove. Dekanom je Stanislav Culka.',
                'Vo farnosti pôsobí chrámový spevokol, mládežnícka kapela, farská charita, modlitby matiek, klubík mamičiek a stretká pre deti, mužov aj ženy. Pripravuje sväté omše pre mladých a rodiny a v decembri 2026 nadviaže duchovné puto s talianskym Bari, kde je hrob svätého Mikuláša.',
                'Kanál je určený veriacim z Liptovského Mikuláša a okolia. Sledovaním budete vedieť o farských podujatiach. '.self::CLOSING,
            ],
        ],
        1990 => [
            'name' => 'Novohradský seniorát ECAV',
            'municipality' => 2018,
            'fix_municipality' => [3306, 2018],
            'website' => 'https://www.ecav.sk/zapadny-distrikt/novohradsky-seniorat',
            'address' => ['street' => 'Malá Čalomija 78', 'postcode' => '991 08'],
            'coordinates' => [48.0842623, 19.2309494, 'municipality'],
            'body' => [
                'Novohradský seniorát Evanjelickej cirkvi augsburského vyznania na Slovensku patrí do Západného dištriktu. Združuje 31 cirkevných zborov v okolí Lučenca, Poltára a Veľkého Krtíša, od Ábelovej a Kalinova po Veľký Krtíš a Stredné Plachtince. Sídli v Malej Čalomiji 78.',
                'Seniorkou je Mgr. Soňa Pichnarčíková, seniorátnou dozorkyňou Mgr. Milica Jozafová. Seniorát pripravuje spoločné podujatia zborov, napríklad adventné stretnutie evanjelických spevokolov Adventná radosť v NOS v Stredných Plachtinciach.',
                'Kanál je určený evanjelikom z Novohradu. Sledovaním budete vedieť o podujatiach seniorátu a jeho zborov. '.self::CLOSING,
            ],
        ],
    ];

    /** @var array<int, array<string, mixed>> */
    private const VENUES = [
        1120 => ['name' => 'Svätoplukovo námestie', 'fix_name' => 'Svätoplukovo námestie (Nitra)', 'village' => 2333,
            'postcode' => '949 01', 'body' => [
                'Svätoplukovo námestie je hlavné námestie v centre Nitry, pomenované po veľkomoravskom kniežati Svätoplukovi. Stojí na ňom Divadlo Andreja Bagara a historická budova bývalej župy.',
                'Konajú sa tu mestské slávnosti, koncerty a adventné podujatia. Na Prvú adventnú nedeľu sem z Nitrianskeho hradu prichádza sprievod svetla.',
                self::OTHERS,
            ]],
        1122 => ['name' => 'Kultúrny dom', 'fix_name' => 'Kultúrny dom (Stredné Plachtince)', 'village' => 3306,
            'postcode' => '991 24', 'body' => [
                'Kultúrny dom v Stredných Plachtinciach slúži obci v okrese Veľký Krtíš na kultúrne a spoločenské podujatia. Informácie poskytuje Obecný úrad Stredné Plachtince, tel. 047/483 03 01.',
                'Konajú sa tu obecné a cirkevné podujatia, napríklad adventné stretnutie evanjelických spevokolov Novohradského seniorátu Adventná radosť v NOS.',
                self::OTHERS,
            ]],
        1123 => ['name' => 'Kostol sv.Františka z Assisi', 'fix_name' => 'Kostol sv. Františka z Assisi (Detva)', 'village' => 542,
            'street' => 'Partizánska 49/73', 'replace_street' => 'Kostol', 'postcode' => '962 12', 'replace_postcode' => '96212',
            'country' => 'Slovensko', 'replace_country' => 'Slovakia', 'body' => [
                'Kostol svätého Františka z Assisi je rímskokatolícky farský kostol v Detve v Banskobystrickej diecéze. Postavili ho v rokoch 1803 – 1804 na mieste staršieho kostola z roku 1664. Stojí na Partizánskej ulici v historickej časti mesta.',
                'Okrem bohoslužieb farnosti Detva sa tu konajú koncerty, napríklad Vianočný koncert GOLD Orchestra Detva so sólistami a mužskou speváckou skupinou z Detvy.',
                self::OTHERS,
            ]],
    ];

    /** Zdroj => [cieľ, názov zdroja, názov cieľa]. */
    private const MERGES = [
        1121 => [1083, 'Evanjelickom chráme a. v. na Mlynskej ulici 23', 'Evanjelický kostol (Košice)'],
    ];

    /** Nové miesta: kľúč => údaje (miesto sa nájde podľa názvu a obce, druhýkrát sa nezakladá). */
    private const NEW_VENUES = [
        'bari' => [
            'name' => 'Bazilika svätého Mikuláša (Bari)',
            'city' => 'bari',
            'street' => 'Largo Abate Elia 13',
            'postcode' => '70122',
            'country' => 'Taliansko',
            'website' => 'https://www.basilicasannicola.it',
            'phone' => '+39 080 573 7111',
            'coordinates' => [41.1302708, 16.8701858],
            'body' => [
                'Bazilika svätého Mikuláša je románsky chrám z konca 11. storočia v starom meste talianskeho Bari. V jej krypte sa od roku 1087 uchovávajú pozostatky svätého Mikuláša, preto patrí k významným pútnickým miestam katolíkov aj pravoslávnych. Spravujú ju dominikáni.',
                'Prichádzajú sem pútnici z celého sveta vrátane Slovenska. V decembri 2026 tu farnosť Liptovský Mikuláš za účasti spišského biskupa a apoštolského nuncia nadviaže duchovné puto s mestom Bari.',
                self::OTHERS,
            ],
        ],
    ];

    /** Podujatie => [názov, z miesta, kľúč nového miesta]. */
    private const EVENT_MOVES = [
        11469 => ['Liptovský Mikuláš a Bari spojí puto, omšu bude sláviť aj apoštolský nuncius', 823, 'bari'],
    ];

    /** Podujatie => názov, značka v pôvodnom popise, nový popis a prázdne kontakty. */
    private const EVENT_BODIES = [
        11475 => [
            'name' => 'Adventná radosť v NOS 2026',
            'marker' => 'Príďte sa potešiť',
            'email' => 'nos@ecav.sk',
            'phone' => '+421 918 570 044',
            'body' => '<p><strong>Adventná radosť v NOS 2026</strong> je adventné stretnutie evanjelických a. v. spevokolov Novohradského seniorátu ECAV. Koná sa v sobotu 5. decembra 2026 od 14.00 do 15.30 v kultúrnom dome v Stredných Plachtinciach.</p>'
                .'<p>Stretnú sa na ňom spevokoly cirkevných zborov seniorátu. Organizátori srdečne pozývajú všetkých, ktorí sa chcú potešiť a povzbudiť v adventnom očakávaní.</p>'
                .'<p><strong>Organizátor:</strong> Novohradský seniorát ECAV na Slovensku, nos@ecav.sk, +421 918 570 044. <strong>Kategória:</strong> kultúra.</p>',
        ],
    ];

    /** Podujatia z kalendára ECAV s prázdnymi riadkami „Ulica" / „Webstránka". */
    private const EMPTY_LABEL_EVENTS = [11461];

    /** Kľúč (c = kanál, v = miesto, n = nové miesto) => [súbor, názov na Commons, autor, licencia, odkaz na licenciu]. */
    private const PHOTOS = [
        'c1987' => ['liptovsky-mikulas-kostol-sv-mikulasa.jpg', 'Rímskokatolícky kostol svätého Mikuláša Liptowski 2SM.jpg', 'Sebastian Mierzwa', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        'c1990' => ['mala-calomija-evanjelicky-kostol.jpg', 'Malá Čalomija - Evanjelický kostol.jpg', 'Ladislav Luppa', 'CC0', 'http://creativecommons.org/publicdomain/zero/1.0/deed.en'],
        'v1120' => ['nitra-svatoplukovo-namestie.jpg', 'Nitra - Svätoplukovo námestie.jpg', 'Ladislav Luppa', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        'v1083' => ['kosice-evanjelicky-kostol-mlynska.jpg', 'Košice - Evanjelický kostol - Mlynská 23.jpg', 'Ladislav Luppa', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        'v1123' => ['detva-kostol-sv-frantiska.jpg', 'Detva kostol 02.jpg', 'Ľuboš Repta', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        'nbari' => ['bari-bazilika-sv-mikulasa.jpg', 'Basilica di San Nicola 03.jpg', 'Bernard Gagnon', 'CC0', 'http://creativecommons.org/publicdomain/zero/1.0/deed.en'],
    ];

    /** @var array<string, int> */
    private array $newVenueIds = [];

    public function up(): void
    {
        $this->completeCanals();
        $this->completeVenues();
        $this->mergeVenues();
        $this->createVenues();
        $this->moveEvents();
        $this->fixEvents();
        $this->attachPhotos();
    }

    private function completeCanals(): void
    {
        foreach (self::CANALS as $id => $profile) {
            $canal = Canal::withTrashed()->find($id);

            [$wrongMunicipality, $rightMunicipality] = $profile['fix_municipality'] ?? [null, null];
            $municipality = (int) $canal?->municipality_id;

            if (! $canal || $canal->trashed() || $canal->merged_into_id !== null || $canal->claimed_at !== null
                || $canal->name !== $profile['name']
                || ($municipality !== $profile['municipality'] && $municipality !== $wrongMunicipality)) {
                continue;
            }

            $changes = [];

            if ($wrongMunicipality !== null && $municipality === $wrongMunicipality) {
                $changes['municipality_id'] = $rightMunicipality;
            }

            if (isset($profile['body']) && substr_count((string) $canal->body, '<p') !== 3) {
                $changes['body'] = self::paragraphs($profile['body']);
            }

            if (isset($profile['website']) && trim((string) $canal->website) === '') {
                $changes['website'] = $profile['website'];
            }

            if (isset($profile['address']) && trim((string) $canal->street) === '') {
                $changes['street'] = $profile['address']['street'];
                $changes['postcode'] = $profile['address']['postcode'];
            }

            $looseCoordinates = in_array($canal->coordinates_source, ['municipality', 'nationwide', 'ai', null], true);
            $atCenter = abs((float) $canal->latitude - self::NATIONWIDE[0]) < 0.0001
                && abs((float) $canal->longitude - self::NATIONWIDE[1]) < 0.0001;

            if (isset($profile['coordinates']) && $looseCoordinates && ($atCenter || isset($changes['municipality_id']))) {
                [$lat, $lng, $source] = $profile['coordinates'];
                $changes += ['latitude' => $lat, 'longitude' => $lng, 'coordinates_source' => $source];
            }

            $changes = array_filter($changes, fn ($value, $field) => $value != $canal->$field, ARRAY_FILTER_USE_BOTH);

            if ($changes !== []) {
                $canal->forceFill($changes)->save();
                Recorder::info('canals', 'profile_described', "Kanál {$canal->name}: doplnené ".implode(', ', array_keys($changes)),
                    status: 'ok', subject: $canal, context: ['fields' => array_keys($changes)]);
            }
        }
    }

    private function completeVenues(): void
    {
        foreach (self::VENUES as $id => $data) {
            $venue = Venue::query()->find($id);

            if (! $venue || $venue->status === ModelStatus::Archived
                || ($venue->name !== $data['name'] && $venue->name !== ($data['fix_name'] ?? null))
                || (int) $venue->village_id !== $data['village']) {
                continue;
            }

            $changes = [];

            if (isset($data['fix_name']) && $venue->name === $data['name']) {
                $changes['name'] = $data['fix_name'];
            }

            if (isset($data['body']) && substr_count((string) $venue->body, '<p') !== 3) {
                $changes['body'] = self::paragraphs($data['body']);
            }

            $street = trim((string) $venue->street);
            if (isset($data['street']) && ($street === '' || $street === ($data['replace_street'] ?? null))) {
                $changes['street'] = $data['street'];
            }

            $postcode = trim((string) $venue->postcode);
            if ($postcode === '' || $postcode === ($data['replace_postcode'] ?? null)) {
                $changes['postcode'] = $data['postcode'];
            }

            if (isset($data['country']) && $venue->country === ($data['replace_country'] ?? null)) {
                $changes['country'] = $data['country'];
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

            try {
                app(ResourceMerger::class)->merge('venues', $sourceId, $targetId, null);
            } catch (Throwable $e) {
                Recorder::warning('admin', 'resource.merge_skipped', "Zlúčenie miesta #{$sourceId} → #{$targetId} preskočené",
                    subject: $target, context: ['error' => mb_substr($e->getMessage(), 0, 250)]);
            }
        }
    }

    private function createVenues(): void
    {
        foreach (self::NEW_VENUES as $key => $data) {
            $cityId = $this->city($data['city']);
            if ($cityId === null) {
                continue;
            }

            $venue = Venue::query()->where('name', $data['name'])->where('village_id', $cityId)->first();

            if (! $venue) {
                [$lat, $lng] = $data['coordinates'];
                $venue = new Venue;
                $venue->forceFill([
                    'name' => $data['name'],
                    'village_id' => $cityId,
                    'street' => $data['street'],
                    'postcode' => $data['postcode'],
                    'country' => $data['country'],
                    'website' => $data['website'],
                    'phone' => $data['phone'],
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'coordinates_source' => 'venue',
                    'status' => ModelStatus::Published,
                    'body' => self::paragraphs($data['body']),
                ])->save();

                Recorder::info('venues', 'profile_described', "Miesto {$venue->name}: založené",
                    status: 'ok', subject: $venue, context: ['fields' => ['name', 'street', 'body']]);
            }

            $this->newVenueIds[$key] = $venue->id;
        }
    }

    private function moveEvents(): void
    {
        foreach (self::EVENT_MOVES as $eventId => [$name, $fromId, $toKey]) {
            $toId = $this->newVenueIds[$toKey] ?? null;
            $event = Event::query()->find($eventId);

            if ($toId === null || ! $event || $event->name !== $name || (int) $event->venue_id !== $fromId) {
                continue;
            }

            $event->forceFill(['venue_id' => $toId])->save();

            if ($event->canal_id !== null) {
                DB::table('canal_venue')->insertOrIgnore([
                    'canal_id' => $event->canal_id, 'venue_id' => $toId, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            Recorder::info('venues', 'events_moved', "Podujatie #{$eventId} presunuté z miesta #{$fromId} na #{$toId}",
                status: 'ok', subject: Venue::query()->find($toId), context: ['events' => [$eventId], 'moved' => 1]);
        }
    }

    private function fixEvents(): void
    {
        foreach (self::EVENT_BODIES as $id => $data) {
            $event = Event::query()->find($id);
            if (! $event || $event->name !== $data['name'] || $this->claimed($event)) {
                continue;
            }

            $changes = [];
            if (str_contains((string) $event->body, $data['marker'])) {
                $changes['body'] = $data['body'];
            }
            foreach (['email', 'phone'] as $field) {
                if (isset($data[$field]) && trim((string) $event->$field) === '') {
                    $changes[$field] = $data[$field];
                }
            }

            if ($changes !== []) {
                $event->forceFill($changes)->save();
                Recorder::info('events', 'profile_described', "Podujatie {$event->name}: doplnené ".implode(', ', array_keys($changes)),
                    status: 'ok', subject: $event, context: ['fields' => array_keys($changes)]);
            }
        }

        foreach (Event::query()->whereIn('id', self::EMPTY_LABEL_EVENTS)->get() as $event) {
            if ($this->claimed($event)) {
                continue;
            }

            $body = self::withoutEmptyLabels((string) $event->body);
            if ($body !== $event->body) {
                $event->forceFill(['body' => $body])->save();
            }
        }
    }

    /** Vyhodí položky „Ulica" / „Webstránka", za ktorými nie je žiadna hodnota. */
    private static function withoutEmptyLabels(string $body): string
    {
        return (string) preg_replace('/<li>\s*(?:<strong>)?\s*(?:Ulica|Webstránka)\s*:?\s*(?:<\/strong>)?\s*:?\s*<\/li>\s*/u', '', $body);
    }

    /** Podujatie prevzatého kanála upravuje už len jeho tím. */
    private function claimed(Event $event): bool
    {
        return $event->canal_id !== null && Canal::withTrashed()->whereKey($event->canal_id)->whereNotNull('claimed_at')->exists();
    }

    private function attachPhotos(): void
    {
        if (! Schema::hasTable('files')) {
            return;
        }

        foreach (self::PHOTOS as $key => [$file, $title, $author, $license, $licenseUrl]) {
            $model = match ($key[0]) {
                'c' => Canal::query()->find((int) substr($key, 1)),
                'v' => Venue::query()->find((int) substr($key, 1)),
                'n' => isset($this->newVenueIds[substr($key, 1)]) ? Venue::query()->find($this->newVenueIds[substr($key, 1)]) : null,
            };

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
                Recorder::warning('files', 'photo_skipped', "Fotka {$file} pre {$key} sa nenahrala",
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
        // Doplnené údaje, zlúčenie a presun sa nevracajú; odstránia sa len nahraté fotky.
        if (! Schema::hasTable('files')) {
            return;
        }

        File::query()
            ->where('meta->source', self::SOURCE)
            ->get()
            ->each(fn (File $file) => $file->delete());
    }
};
