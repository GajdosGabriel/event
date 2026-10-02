<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Zlúči duplicitné miesta zistené prehľadom všetkých miest (rovnaká obec +
 * rovnaká budova/adresa/názov, importované viackrát pod mierne odlišným
 * názvom, napr. „Kostol sv. Arnolda Janssena" 2-krát, „Katedrála sv. Martina
 * na Spišskej Kapitule" 3-krát alebo duplicitné zástupné miesta obcí).
 * Prehľad prebehol po obciach: zhoda názvu, ulice s číslom alebo
 * súradníc, ktoré nie sú len zástupnými súradnicami obce; každá dvojica
 * bola posúdená ručne. Rovnaké súradnice samy osebe nestačili (mnohé
 * odlišné miesta zdieľajú súradnice obce).
 *
 * Pre každú dvojicu (kanonické, [duplicitné...]):
 *  1. prázdne polia kanonického miesta sa doplnia z duplicity (adresa,
 *     web, e-mail, telefón, popis...) a pri zástupných súradniciach obce
 *     sa prevezmú presnejšie súradnice duplicity,
 *  2. events.venue_id sa presunie na kanonické miesto,
 *  3. canal_venue väzby sa presunú (is_owner = max, publikovaná väzba
 *     vyhráva), pôvodné sa zmažú,
 *  4. počet zobrazení sa pripočíta, kontroly obsahu/atribútov duplicity
 *     sa zmažú (kanonické sa prepočítajú),
 *  5. duplicitné miesto sa soft-deletne, nie fyzicky vymaže.
 *
 * down() duplicity obnoví (deleted_at = null); presun eventov a väzieb sa
 * späť nevracia (jednosmerný prepis ako pri zlúčení duplicitných kanálov).
 */
return new class extends Migration
{
    /**
     * @var array<int, array{0:int, 1:list<int>}> [kanonické_id, [duplicitné_id, ...]]
     */
    private const MERGES = [
        [957, [1]],          // Vrbové – Kultúrny dom
        [127, [135]],        // Trnava – Mariánska sála, Františkánska 1
        [908, [30]],         // Bratislava – Slovenský rozhlas / Veľké koncertné štúdio
        [111, [942]],        // Bratislava – Kostol sv. Arnolda Janssena
        [133, [962]],        // Bratislava – Dom Quo Vadis, Veterná 1
        [625, [624]],        // Bratislava – Fraňa Kráľa 2 (preklep „Kraňa")
        [723, [714]],        // Bratislava – Zrkadlová sieň Primaciálneho paláca
        [746, [776]],        // Rím – Bazilika sv. Eugena
        [865, [862]],        // Bratislava – Dom Pápežských misijných diel
        [795, [958]],        // Bratislava – Katedrála sv. Martina, Rudnayovo nám.
        [818, [870]],        // Bratislava – Františkánsky kostol, Františkánske nám.
        [167, [859]],        // Bratislava – zástupné miesto obce („null")
        [74, [120]],         // Košice – Dominikánske námestie 8
        [894, [117]],        // Košice – Bazilika Božieho milosrdenstva
        [168, [895]],        // Košice – zástupné miesto obce („null")
        [193, [14]],         // Levoča – zástupné miesto obce
        [93, [539]],         // Žiar nad Hronom – zástupné miesto obce
        [295, [106]],        // Dolný Kubín – zástupné miesto obce
        [54, [728]],         // Drienica 501
        [726, [79]],         // Nitra – Misijný dom na Kalvárii, Kalvária 3
        [901, [92]],         // Nitra – Kňazský seminár, Samova 14
        [972, [965]],        // Nitra – Univerzitné pastoračné centrum Pavla Straussa
        [43, [99]],          // Ladce – Sanktuárium Božieho milosrdenstva
        [842, [80, 956]],    // Spišská Kapitula – Katedrála sv. Martina
        [57, [149]],         // Nová Ves nad Žitavou – Studnička
        [943, [62]],         // Bošany – farský kostol, Park garbiarov 107
        [940, [65]],         // Zlatá Baňa – Centrum pre rodinu Sigord 134
        [874, [847]],        // Žilina – evanjelický kostol
        [104, [831, 146]],   // Šaštín – Národná svätyňa, Bazilika Sedembolestnej P. Márie
        [794, [903]],        // Hronský Beňadik – Bazilika sv. Benedikta
        [796, [764]],        // Beluša – Rodinkovo
    ];

    private const FILLABLE = ['street', 'postcode', 'website', 'email', 'phone', 'capacity', 'opening_hours', 'category', 'body'];

    public function up(): void
    {
        if (! Schema::hasTable('venues') || ! Schema::hasTable('canal_venue')) {
            return;
        }

        DB::transaction(function () {
            foreach (self::MERGES as [$canonicalId, $duplicateIds]) {
                foreach ($duplicateIds as $duplicateId) {
                    $this->merge($canonicalId, $duplicateId);
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('venues')) {
            return;
        }

        $duplicateIds = collect(self::MERGES)->flatMap(fn ($merge) => $merge[1])->all();

        DB::table('venues')->whereIn('id', $duplicateIds)->update(['deleted_at' => null, 'updated_at' => now()]);
    }

    private function merge(int $canonicalId, int $duplicateId): void
    {
        $canonical = DB::table('venues')->where('id', $canonicalId)->whereNull('deleted_at')->first();
        $duplicate = DB::table('venues')->where('id', $duplicateId)->whereNull('deleted_at')->first();

        if ($canonical === null || $duplicate === null) {
            return;
        }

        $update = ['updated_at' => now()];

        foreach (self::FILLABLE as $column) {
            if ($this->blank($canonical->{$column}) && ! $this->blank($duplicate->{$column})) {
                $update[$column] = $duplicate->{$column};
            }
        }

        if ($this->hasRoughCoordinates($canonical) && ! $this->hasRoughCoordinates($duplicate)) {
            $update['latitude'] = $duplicate->latitude;
            $update['longitude'] = $duplicate->longitude;
            $update['coordinates_source'] = $duplicate->coordinates_source;
        }

        $update['views_count'] = $canonical->views_count + $duplicate->views_count;

        DB::table('venues')->where('id', $canonicalId)->update($update);

        DB::table('events')->where('venue_id', $duplicateId)
            ->update(['venue_id' => $canonicalId, 'updated_at' => now()]);

        foreach (DB::table('canal_venue')->where('venue_id', $duplicateId)->get() as $link) {
            $existing = DB::table('canal_venue')
                ->where('canal_id', $link->canal_id)->where('venue_id', $canonicalId)->first();

            if ($existing === null) {
                DB::table('canal_venue')->insert([
                    'canal_id' => $link->canal_id,
                    'venue_id' => $canonicalId,
                    'is_owner' => $link->is_owner,
                    'status' => $link->status,
                    'created_at' => $link->created_at,
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('canal_venue')
                    ->where('canal_id', $link->canal_id)->where('venue_id', $canonicalId)
                    ->update([
                        'is_owner' => max($existing->is_owner, $link->is_owner),
                        'status' => $existing->status === 'published' || $link->status === 'published' ? 'published' : $existing->status,
                        'updated_at' => now(),
                    ]);
            }
        }
        DB::table('canal_venue')->where('venue_id', $duplicateId)->delete();

        if (Schema::hasTable('content_reviews')) {
            DB::table('content_reviews')->where('reviewable_type', 'like', '%Venue')
                ->whereIn('reviewable_id', [$canonicalId, $duplicateId])->delete();
        }

        if (Schema::hasTable('attribute_checks')) {
            DB::table('attribute_checks')->where('checkable_type', 'like', '%Venue')
                ->where('checkable_id', $duplicateId)->delete();
        }

        DB::table('venues')->where('id', $duplicateId)->update(['deleted_at' => now(), 'updated_at' => now()]);
    }

    private function blank(mixed $value): bool
    {
        return $value === null || trim((string) $value) === '' || in_array(strtolower(trim((string) $value)), ['null', '[]'], true);
    }

    /** Súradnice obce / neznáme = nepresné, presnejšie z duplicity ich nahradia. */
    private function hasRoughCoordinates(object $venue): bool
    {
        return $venue->latitude === null
            || in_array($venue->coordinates_source, [null, 'municipality'], true);
    }
};
