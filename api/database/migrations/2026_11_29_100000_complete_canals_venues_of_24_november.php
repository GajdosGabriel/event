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
use Illuminate\Support\Str;

/**
 * Kanály a miesta z importu 24. 11. 2026 (#1984 – #1986, #1117 – #1119),
 * súradnice a fotky kanálov a miest z predchádzajúcej dávky a opravy, na ktoré
 * sa pri kontrole prišlo.
 *
 * Kanály: #1985 Mariánska sála (Trnava) dostane popis, telefón a adresu
 * Františkánska 33A z marianskasala.eu. #1981 GOLD Orchestra Detva a #1982 sestry
 * SDR mali súradnice stredu Slovenska — dostanú obec Detva, resp. kláštor na Zimnej 66.
 * Zlúčenia: #1984 Vydavateľstvo Nové mesto → #1193 (tá istá firma, Cablkova 10,
 * nm.sk; #1193 sa premenuje z „Portál Nové mesto"), #1986 „Námestie Jána Pavla II"
 * (adresa biskupského úradu, nie organizátor) → #347 Nitrianske biskupstvo
 * a duplicity #1062 → #1857 Evanjelická bohoslovecká fakulta UK, #634 → #1935
 * Farnosť Bolešov (#1935 má otvorenú pozvánku na prevzatie a web).
 * #347 mal z importu adresu, web a telefón Kňazského seminára (Samova 14, ksnr.sk),
 * dostane údaje biskupského úradu z biskupstvo-nitra.sk; hlavnou adresou bude
 * nitra@kbs.sk, seminárna rektorat@ksnr.sk ostane doplnková.
 *
 * Miesta: #1118 „Šafárikovo nám. 6" je Aula Univerzity Komenského, #1119 fara
 * zboru ECAV Hybe (Hybe 122, ecav.sk). #127 Mariánska sála dostane číslo domu,
 * vlastný web namiesto trnava.sk a popis — sála stojí za kaplnkou Kostola
 * Najsvätejšej Trojice, nie pri františkánoch. #1114 Blumentálsky kostol mal
 * z importu web Dopravného podniku (dpb.sk) a PSČ bez medzery. Koncept #1117 → #850 (svätyňa
 * v Smižanoch, už piaty raz; zlúčenie si odteraz pamätá DuplicateResolver).
 *
 * Podujatia Mariánskej sály #11453, #11455 – #11457 import priradil ku Katedrále
 * sv. Jána Krstiteľa (#123) — „Kde: Mariánska sála (Františkánska 33A, Trnava)"
 * rozdelil po čiarke. Presunú sa na #127, len ak sú stále na #123.
 *
 * Fotky: zmenšeniny 1280 px z Commons v assets/canal-venue-photos, autor a licencia
 * v meta. Fotku dostane len kanál či miesto bez obrázka; prevzatý kanál sa nemení.
 * Kanál musí sedieť ID aj názvom; prevzatý, zlúčený či zmazaný sa preskočí.
 */
return new class extends Migration
{
    private const SOURCE = 'migration:2026_11_29_canal_venue_photo';

    private const CLOSING = 'Podrobnosti nájdete vždy v detaile podujatia.';

    private const OTHERS = 'Na mieste sa konajú podujatia rôznych organizátorov. Aktuálny program nájdete v zozname podujatí tohto miesta. Čas a vstup si overte vždy v detaile podujatia.';

    /** Súradnice, ktoré import dáva kanálom bez adresy (stred Slovenska). */
    private const NATIONWIDE = [48.7411522, 19.4528646];

    /**
     * `fix_when` => [pole, hodnota]: celý profil (aj neprázdne polia a popis) sa
     * prepíše len vtedy, keď kanál ešte má túto chybnú hodnotu z importu.
     *
     * @var array<int, array<string, mixed>>
     */
    private const CANALS = [
        1985 => [
            'name' => 'Mariánska sála',
            'municipality' => 3596,
            'phone' => '0904 302 269',
            'website' => 'https://marianskasala.eu',
            'emails' => [['email' => 'marianskasala@gmail.com', 'found_on' => 'https://marianskasala.eu/']],
            'address' => ['street' => 'Františkánska 33A', 'postcode' => '917 01'],
            'coordinates' => [48.3794717, 17.5829474, 'address'],
            'body' => [
                'Mariánska sála je spoločenská sála v Trnave, ktorú prevádzkuje občianske združenie Mariánska sála. Stojí na Františkánskej ulici 33A, na dvore za kaplnkou Kostola Najsvätejšej Trojice.',
                'V sále sa konajú koncerty, divadlá, tvorivé dielne, výstavy, besedy, premietania, bazáre aj plesy, napríklad Malý rodinný ples, Víkend kociek či silvestrovská zábava.',
                'Kanál je určený rodinám a mladým z Trnavy a okolia. Sledovaním budete vedieť o programe sály. '.self::CLOSING,
            ],
        ],
        1981 => [
            'name' => 'GOLD Orchestra Detva',
            'municipality' => 542,
            'coordinates' => [48.5595662, 19.4205586, 'municipality'],
        ],
        1982 => [
            'name' => 'Kongregácia sestier Božského Vykupiteľa',
            'municipality' => 3240,
            'coordinates' => [48.9427693, 20.5683163, 'address'],
        ],
        1193 => [
            'name' => 'Portál Nové mesto',
            'rename' => 'Vydavateľstvo Nové mesto',
            'municipality' => 242,
            'phone' => '0911 178 276',
            'website' => 'https://www.nm.sk',
            'emails' => [['email' => 'info@nm.sk', 'found_on' => 'https://www.nm.sk/']],
            'body' => [
                'Vydavateľstvo Nové mesto z Bratislavy vydáva časopis Nové mesto, ktorý na Slovensku vychádza už 30 rokov, kresťanské knihy a portál nm.sk s článkami a recenziami. Sídli na Cablkovej ulici 10.',
                'Je blízke Hnutiu fokoláre. Pripravuje prezentácie kníh a webináre, napríklad prezentáciu knihy Márie Kohutiarovej Denník mamy v Dome Quo Vadis alebo webinár o metóde No Blame Approach proti šikane.',
                'Kanál je určený čitateľom kresťanskej literatúry. Sledovaním budete vedieť o nových knihách a podujatiach vydavateľstva. '.self::CLOSING,
            ],
        ],
        347 => [
            'name' => 'Nitrianske biskupstvo',
            'municipality' => 2333,
            'fix_when' => ['website', 'https://ksnr.sk'],
            'phone' => '037/772 17 47',
            'website' => 'https://www.biskupstvo-nitra.sk',
            'emails' => [['email' => 'nitra@kbs.sk', 'found_on' => 'https://www.biskupstvo-nitra.sk/']],
            'replace_primary' => ['rektorat@ksnr.sk', 'nitra@kbs.sk'],
            'address' => ['street' => 'Námestie Jána Pavla II. 7', 'postcode' => '950 50'],
            'coordinates' => [48.3184475, 18.0863993, 'address'],
            'body' => [
                'Nitrianske biskupstvo spravuje Nitriansku diecézu, najstaršiu na Slovensku. Biskupský úrad sídli na Námestí Jána Pavla II. 7 pod Nitrianskym hradom, katedrálou je Bazilika svätého Emeráma.',
                'Pripravuje diecézne podujatia a púte, často s Kňazským seminárom svätého Gorazda, napríklad Deň otvorených dverí v seminári, miništrantské tábory či vernisáž výstavy Ikony sv. Mikuláša v katedrále.',
                'Kanál je určený veriacim Nitrianskej diecézy. Sledovaním budete vedieť o diecéznych podujatiach. '.self::CLOSING,
            ],
        ],
    ];

    /** Zdrojový kanál => [cieľ, názov zdroja, názvy cieľa]. */
    private const CANAL_MERGES = [
        1984 => [1193, 'Vydavateľstvo Nové mesto', ['Vydavateľstvo Nové mesto', 'Portál Nové mesto']],
        1986 => [347, 'Námestie Jána Pavla II', ['Nitrianske biskupstvo']],
        1062 => [1857, 'Evanjelická bohoslovecká fakulta Univerzity Komenského', ['Evanjelická bohoslovecká fakulta UK v Bratislave']],
        // Smer k novšiemu: #1935 má otvorenú pozvánku na prevzatie pre farský úrad
        // (merger by ju zablokoval) aj web; slug je u oboch rovnaký.
        634 => [1935, 'Farnosť Bolešov', ['Farnosť Bolešov']],
    ];

    /**
     * `fix_body_when`: popis s tromi odsekmi sa prepíše, len ak obsahuje tento chybný úryvok.
     *
     * @var array<int, array<string, mixed>>
     */
    private const VENUES = [
        1118 => ['name' => 'Šafárikovo nám. 6', 'fix_name' => 'Aula Univerzity Komenského (Bratislava)', 'village' => 242,
            'street' => 'Šafárikovo námestie 6', 'replace_street' => 'Šafárikovo námestie', 'postcode' => '814 99', 'body' => [
                'Aula Univerzity Komenského je slávnostná sála v historickej budove univerzity na Šafárikovom námestí 6 v Bratislave, neďaleko Dunaja. V budove sídli rektorát a Právnická fakulta UK.',
                'Konajú sa tu promócie, slávnostné zasadnutia a verejné prednášky, napríklad profesorská prednáška Evanjelickej bohosloveckej fakulty UK s diskusiou.',
                self::OTHERS,
            ]],
        1119 => ['name' => 'Evanjelická fara', 'fix_name' => 'Evanjelická fara (Hybe)', 'village' => 1159,
            'street' => 'Hybe 122', 'postcode' => '032 31', 'body' => [
                'Evanjelická fara v Hybiach patrí cirkevnému zboru ECAV Hybe z Liptovsko-oravského seniorátu. Zborovým farárom je Mgr. Stanislav Grega.',
                'Na fare sa stretáva mládež a zborové spoločenstvá, napríklad mládež Liptovsko-oravského seniorátu s hosťami z IPčka. Služby Božie sú každú nedeľu o 10.00 v evanjelickom kostole.',
                self::OTHERS,
            ]],
        // Import priradil web Dopravného podniku Bratislava (zastávka pri kostole) a PSČ bez medzery.
        1114 => ['name' => 'Kostol Nanebovzatia Panny Márie (Blumentálsky)', 'village' => 242,
            'postcode' => '811 07', 'replace_postcode' => '81107', 'clear_website' => 'https://dpb.sk'],
        127 => ['name' => 'Mariánska sála', 'village' => 3596,
            'street' => 'Františkánska 33A', 'replace_street' => 'Františkánska', 'postcode' => '917 01',
            'website' => 'https://marianskasala.eu', 'replace_website' => 'https://www.trnava.sk',
            'email' => 'marianskasala@gmail.com', 'phone' => '0904 302 269',
            'fix_body_when' => 'františkánskom kláštore', 'body' => [
                'Mariánska sála je spoločenská sála na Františkánskej ulici 33A v Trnave. Stojí na dvore za kaplnkou Kostola Najsvätejšej Trojice, vchádza sa cez parkovisko. Prevádzkuje ju občianske združenie Mariánska sála.',
                'Konajú sa tu koncerty, divadlá, tvorivé dielne, výstavy, bazáre aj plesy, napríklad celoslovenské stretnutie františkánskej rodiny SPOLU, Misijný bazár či silvestrovská zábava.',
                self::OTHERS,
            ]],
    ];

    /** Zdroj => [cieľ, názov zdroja, názov cieľa]. */
    private const MERGES = [
        1117 => [850, 'Centrum Božieho milosrdenstva', 'Diecézna svätyňa Božieho milosrdenstva'],
    ];

    /** Podujatia na nesprávnom mieste: [podujatia, z miesta, na miesto]. */
    private const EVENT_MOVES = [
        [[11453, 11455, 11456, 11457], 123, 127],
    ];

    /** Kľúč (c = kanál, v = miesto) => [súbor, názov na Commons, autor, licencia, odkaz na licenciu]. */
    private const PHOTOS = [
        'v1114' => ['bratislava-blumentalsky-kostol.jpg', 'Farský kostol nanebovzatia Panny Márie (Blumentál).jpg', 'Lukáš Perný', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        'v1118' => ['bratislava-univerzita-komenskeho.jpg', 'Šafárikovo námestie (Šafárikovo Square) and Univerzita Komenského (Comenius University) in Bratislava, Slovak Republic.JPG', 'Kiwiev', 'CC0', 'http://creativecommons.org/publicdomain/zero/1.0/deed.en'],
        'v1119' => ['hybe-evanjelicky-kostol.jpg', 'Evanjelický kostol - Hybe.jpg', 'Ladislav Luppa', 'CC BY-SA 3.0', 'https://creativecommons.org/licenses/by-sa/3.0'],
        'v127' => ['trnava-kostol-najsvatejsej-trojice.jpg', 'Trnava kostol Najsvätejšej Trojice.jpg', 'Akul59', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        'c1985' => ['trnava-kostol-najsvatejsej-trojice-2.jpg', 'Kostol Najsvätejšej Trojice v Trnave.jpg', 'Juraj76', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
        'c1981' => ['detva-kostol-sv-frantiska.jpg', 'Detva kostol 02.jpg', 'Ľuboš Repta', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0'],
    ];

    public function up(): void
    {
        $this->completeCanals();
        $this->mergeCanals();
        $this->completeVenues();
        $this->mergeVenues();
        $this->moveEvents();
        $this->attachPhotos();
    }

    private function completeCanals(): void
    {
        $emails = Schema::hasTable('canal_emails') ? app(CanalEmails::class) : null;

        foreach (self::CANALS as $id => $profile) {
            $canal = Canal::withTrashed()->find($id);

            if (! $canal || $canal->trashed() || $canal->merged_into_id !== null || $canal->claimed_at !== null
                || ($canal->name !== $profile['name'] && $canal->name !== ($profile['rename'] ?? null))
                || (int) $canal->municipality_id !== $profile['municipality']) {
                continue;
            }

            $fix = isset($profile['fix_when']) && (string) $canal->{$profile['fix_when'][0]} === $profile['fix_when'][1];
            // Kanál s `fix_when`, ktorý už opravený je, dostane nanajvýš hlavnú adresu (nižšie).
            $skipFields = isset($profile['fix_when']) && ! $fix;

            $changes = [];

            if (! $skipFields && isset($profile['rename']) && $canal->name !== $profile['rename']) {
                $changes['name'] = $profile['rename'];
                $changes['slug'] = $this->freeSlug($profile['rename'], $canal->id);
            }

            // Popis, ktorý ešte hovorí o starom názve, sa po premenovaní prepíše.
            $staleBody = isset($profile['rename']) && str_contains((string) $canal->body, $profile['name']);
            if (! $skipFields && isset($profile['body']) && ($fix || $staleBody || substr_count((string) $canal->body, '<p') !== 3)) {
                $changes['body'] = self::paragraphs($profile['body']);
            }

            foreach (['website', 'phone'] as $field) {
                if (isset($profile[$field]) && ($fix || trim((string) $canal->$field) === '')) {
                    $changes[$field] = $profile[$field];
                }
            }

            if (isset($profile['address']) && ($fix || trim((string) $canal->street) === '')) {
                $changes['street'] = $profile['address']['street'];
                $changes['postcode'] = $profile['address']['postcode'];
            }

            $looseCoordinates = in_array($canal->coordinates_source, ['municipality', 'nationwide', 'ai', null], true);
            $atCenter = abs((float) $canal->latitude - self::NATIONWIDE[0]) < 0.0001
                && abs((float) $canal->longitude - self::NATIONWIDE[1]) < 0.0001;

            if (isset($profile['coordinates']) && ($fix || ($looseCoordinates && ($atCenter || isset($changes['street']))))) {
                [$lat, $lng, $source] = $profile['coordinates'];
                $changes += ['latitude' => $lat, 'longitude' => $lng, 'coordinates_source' => $source];
            }

            $changes = $skipFields ? [] : array_filter($changes, fn ($value, $field) => $value != $canal->$field, ARRAY_FILTER_USE_BOTH);

            if ($changes !== []) {
                $canal->forceFill($changes)->save();
                Recorder::info('canals', 'profile_described', "Kanál {$canal->name}: doplnené ".implode(', ', array_keys($changes)),
                    status: 'ok', subject: $canal, context: ['fields' => array_keys($changes)]);
            }

            if ($emails && isset($profile['emails'])) {
                if (! $skipFields) {
                    $emails->discover($canal->fresh(), $profile['emails'], 'manual');
                }

                // Hlavná adresa z importu patrí inej inštitúcii — ostane ako doplnková.
                [$wrongPrimary, $rightPrimary] = $profile['replace_primary'] ?? [null, null];
                if ($wrongPrimary !== null && $emails->primary($canal->fresh())?->email === $wrongPrimary) {
                    $emails->sync($canal->fresh(), $rightPrimary, $emails->all($canal->fresh())->pluck('email')->all());
                }

                $emails->refresh($canal->fresh());
            }
        }
    }

    private function mergeCanals(): void
    {
        foreach (self::CANAL_MERGES as $sourceId => [$targetId, $sourceName, $targetNames]) {
            $source = Canal::query()->find($sourceId);
            $target = Canal::query()->find($targetId);
            if (! $source || ! $target || $source->name !== $sourceName || ! in_array($target->name, $targetNames, true)
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

            if (! $venue || $venue->status === ModelStatus::Archived
                || ($venue->name !== $data['name'] && $venue->name !== ($data['fix_name'] ?? null))
                || (int) $venue->village_id !== $data['village']) {
                continue;
            }

            $changes = [];

            if (isset($data['fix_name']) && $venue->name === $data['name']) {
                $changes['name'] = $data['fix_name'];
            }

            $body = (string) $venue->body;
            if (isset($data['body']) && (substr_count($body, '<p') !== 3 || (isset($data['fix_body_when']) && str_contains($body, $data['fix_body_when'])))) {
                $changes['body'] = self::paragraphs($data['body']);
            }

            $street = trim((string) $venue->street);
            if (isset($data['street']) && ($street === '' || $street === ($data['replace_street'] ?? null))) {
                $changes['street'] = $data['street'];
            }

            $postcode = trim((string) $venue->postcode);
            if ($postcode === '' || $postcode === ($data['replace_postcode'] ?? null) || isset($changes['street'])) {
                $changes['postcode'] = $data['postcode'];
            }

            $website = trim((string) $venue->website);
            if (isset($data['website']) && ($website === '' || $website === ($data['replace_website'] ?? null))) {
                $changes['website'] = $data['website'];
            }
            if (isset($data['clear_website']) && rtrim($website, '/') === $data['clear_website']) {
                $changes['website'] = null;
            }

            foreach (['email', 'phone'] as $field) {
                if (isset($data[$field]) && trim((string) $venue->$field) === '') {
                    $changes[$field] = $data[$field];
                }
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

            // Koncept z importu bez podujatí sa zlučuje ako publikovaný — inak ho merger odmietne.
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

    private function moveEvents(): void
    {
        foreach (self::EVENT_MOVES as [$eventIds, $fromId, $toId]) {
            if (! Venue::query()->whereKey($toId)->exists()) {
                continue;
            }

            $moved = DB::table('events')->whereIn('id', $eventIds)->where('venue_id', $fromId)
                ->update(['venue_id' => $toId, 'updated_at' => now()]);

            if ($moved > 0) {
                DB::table('canal_venue')->insertOrIgnore(
                    DB::table('events')->whereIn('id', $eventIds)->where('venue_id', $toId)->whereNotNull('canal_id')
                        ->distinct()->pluck('canal_id')
                        ->map(fn ($canalId) => ['canal_id' => $canalId, 'venue_id' => $toId, 'created_at' => now(), 'updated_at' => now()])->all(),
                );
                Recorder::info('venues', 'events_moved', "Podujatia presunuté z miesta #{$fromId} na #{$toId}",
                    status: 'ok', subject: Venue::query()->find($toId), context: ['events' => $eventIds, 'moved' => $moved]);
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

    private function freeSlug(string $name, int $canalId): string
    {
        $base = Str::slug($name);
        $slug = $base;

        for ($i = 2; DB::table('canals')->where('slug', $slug)->where('id', '!=', $canalId)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    /** @param  array<int, string>  $paragraphs */
    private static function paragraphs(array $paragraphs): string
    {
        return implode('', array_map(fn (string $p) => '<p>'.e($p).'</p>', $paragraphs));
    }

    public function down(): void
    {
        // Doplnené údaje, zlúčenia a presuny sa nevracajú; odstránia sa len nahraté fotky.
        if (! Schema::hasTable('files')) {
            return;
        }

        File::query()
            ->where('meta->source', self::SOURCE)
            ->get()
            ->each(fn (File $file) => $file->delete());
    }
};
