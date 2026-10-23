<?php

use App\Models\Venue;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Miesta z kontroly produkčnej databázy (23. 10. 2026).
 *
 *  1. Duplicity: tá istá budova dvakrát (rovnaká adresa / názov), často
 *     s chybnou obcou — „Katedrála svätého Martina (Bratislava)" v Martine,
 *     kostol v Partizánskom v Oravskej Polhore, ružomberský kostol
 *     v Podunajských Biskupiciach. Zástupné miesta „Slovensko", „celé",
 *     „Slovensko, celé" a „Pečie celé Slovensko" (názov akcie omylom ako
 *     miesto) idú do „Celé Slovensko". Zlučuje sa rovnako ako v
 *     2026_10_02_110000_merge_duplicate_venues.php, navyše sa presúvajú
 *     zobrazenia a vybavia sa nerozhodnuté import-duplicity.
 *  2. Názvy v 6. páde z importu („Jezuitskom kostole", „Aule Pazmaneum")
 *     dostanú 1. pád; „Liptovskom Mikuláši" je len adresa.
 *  3. Rímske kostoly a miesta vo Vatikáne viseli na Bratislave (aj so
 *     súradnicami Bratislavy) — presunú sa do obce Rím so súradnicami mesta.
 *
 * Každá zmena sa robí len keď záznam stále sedí na id aj pôvodný údaj.
 * down() obnoví zlúčené duplicity a vráti názvy; presuny sa nevracajú.
 */
return new class extends Migration
{
    /**
     * @var array<int, array{0:int, 1:list<int>}> [kanonické_id, [duplicitné_id, ...]]
     */
    private const MERGES = [
        [12, [7, 756, 817, 759]], // Celé Slovensko – zástupné „Slovensko", „celé", „Slovensko, celé", „Pečie celé Slovensko"
        [104, [708]],             // Šaštín – Bazilika Sedembolestnej Panny Márie
        [795, [980, 33]],         // Bratislava – Katedrála sv. Martina (33 s obcou Martin)
        [922, [917, 920]],        // Ružomberok – Kostol Povýšenia sv. Kríža (zlé obce)
        [934, [921]],             // Devín – Kostol sv. Kríža
        [969, [767]],             // Partizánske – Kostol Božského Srdca Ježišovho
        [976, [766]],             // Poprad – Kostol sv. Cyrila a Metoda, Ľ. Svobodu 4388/59
        [828, [966]],             // Bratislava – Kostol Sedembolestnej P. Márie (Pohorelá), Baničova
        [54, [1003]],             // Drienica 501
        [124, [1012]],            // Žilina – budova HOUR
        [133, [1013]],            // Bratislava – Dom Quo Vadis, Veterná 1
        [1009, [94]],             // Spišská Nová Ves – Hutnícka 4 (94 s obcou Smižany)
        [199, [873]],             // Zborov
        [265, [761]],             // Šaštín - Stráže („Štráže")
        [554, [975]],             // Mojmírovce („Mojmírovciach")
        [719, [1021]],            // Ružomberok – Aula sv. Jána Pavla II., Hrabovská cesta 1A
    ];

    /** @var array<int, array{0: string, 1: string}> id => [starý názov, nový názov] */
    private const RENAMES = [
        24 => ['Evanjelickom cirkevnom dome', 'Evanjelický cirkevný dom'],
        28 => ['Aule Pazmaneum', 'Aula Pazmaneum'],
        39 => ['Farskom kostole sv. Klimenta', 'Farský kostol sv. Klimenta'],
        54 => ['Drienica č.501, okr.Sabinov, Drienica', 'Drienica 501, Drienica'],
        98 => ['Bazilike minor sv', 'Bazilika minor sv. Egídia'],
        719 => ['Aule sv. Jána Pavla II.', 'Aula sv. Jána Pavla II.'],
        857 => ['Liptovskom Mikuláši', 'Štúrova 56/6, Liptovský Mikuláš'],
        1017 => ['Jezuitskom kostole', 'Kostol Najsvätejšej Trojice (Jezuitský kostol)'],
    ];

    /** @var array<int, string> id => krajina; miesta v Ríme / Vatikáne zapísané v Bratislave */
    private const ROME = [
        746 => 'Taliansko',  // Bazilika svätého Eugena
        775 => 'Taliansko',  // Kostol Gesù e Maria
        781 => 'Taliansko',  // Bazilika Panny Márie v Trastevere
        788 => 'Taliansko',  // Kostol Najsvätejšieho Mena Ježišovho
        798 => 'Taliansko',  // Bazilika Santa Maria Maggiore
        825 => 'Taliansko',  // Koloseum
        886 => 'Taliansko',  // bazilika sv. Apolinára
        936 => 'Taliansko',  // Bazilika sv. Klementa
        851 => 'Vatikán',    // Aula Pavla VI.
        932 => 'Vatikán',    // priestranstvo pred Bazilikou sv. Petra
        982 => 'Vatikán',    // Námestie sv. Petra
    ];

    private const BRATISLAVA_ID = 242;

    /** Celé Slovensko, Zborov, Šaštín - Stráže, Mojmírovce. */
    private const PLACEHOLDERS = [12, 199, 265, 554];

    /** Stred Ríma — presnú polohu jednotlivých miest nepoznáme. */
    private const ROME_LATITUDE = 41.9027835;

    private const ROME_LONGITUDE = 12.4963655;

    private const FILLABLE = ['street', 'postcode', 'website', 'email', 'phone', 'capacity', 'opening_hours', 'category', 'body'];

    public function up(): void
    {
        if (! Schema::hasTable('venues') || ! Schema::hasTable('canal_venue')) {
            return;
        }

        DB::transaction(function () {
            foreach (self::MERGES as [$canonicalId, $duplicateIds]) {
                $merged = array_values(array_filter($duplicateIds, fn (int $id) => $this->merge($canonicalId, $id)));

                if ($merged !== []) {
                    $this->settleDecisions([$canonicalId, ...$merged]);
                }
            }

            foreach (self::RENAMES as $id => [$old, $new]) {
                DB::table('venues')->where('id', $id)->where('name', $old)->update(['name' => $new, 'updated_at' => now()]);
            }

            $this->moveToRome();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('venues')) {
            return;
        }

        $duplicateIds = collect(self::MERGES)->flatMap(fn ($merge) => $merge[1])->all();

        DB::table('venues')->whereIn('id', $duplicateIds)->update(['deleted_at' => null, 'updated_at' => now()]);

        foreach (self::RENAMES as $id => [$old, $new]) {
            DB::table('venues')->where('id', $id)->where('name', $new)->update(['name' => $old, 'updated_at' => now()]);
        }

        // Rím sa späť na Bratislavu nevracia — bola to chyba.
    }

    private function merge(int $canonicalId, int $duplicateId): bool
    {
        $canonical = DB::table('venues')->where('id', $canonicalId)->whereNull('deleted_at')->first();
        $duplicate = DB::table('venues')->where('id', $duplicateId)->whereNull('deleted_at')->first();

        if ($canonical === null || $duplicate === null) {
            return false;
        }

        $update = ['updated_at' => now()];

        // Zástupné miesto krajiny či obce nepreberá adresu ani súradnice
        // duplicity (Zbehy, Celulózka; ulica jednej budovy v Šaštíne).
        $fill = ! in_array($canonicalId, self::PLACEHOLDERS, true);

        foreach (self::FILLABLE as $column) {
            if ($fill && $this->blank($canonical->{$column}) && ! $this->blank($duplicate->{$column})) {
                $update[$column] = $duplicate->{$column};
            }
        }

        if ($fill && $this->hasRoughCoordinates($canonical) && ! $this->hasRoughCoordinates($duplicate)) {
            $update['latitude'] = $duplicate->latitude;
            $update['longitude'] = $duplicate->longitude;
            $update['coordinates_source'] = $duplicate->coordinates_source;
        }

        $update['views_count'] = (int) $canonical->views_count + (int) $duplicate->views_count;

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

        $this->moveViews($duplicateId, $canonicalId);

        if (Schema::hasTable('content_reviews')) {
            DB::table('content_reviews')->where('reviewable_type', Venue::class)
                ->whereIn('reviewable_id', [$canonicalId, $duplicateId])->delete();
        }

        if (Schema::hasTable('attribute_checks')) {
            DB::table('attribute_checks')->where('checkable_type', Venue::class)
                ->where('checkable_id', $duplicateId)->delete();
        }

        DB::table('venues')->where('id', $duplicateId)->update(['deleted_at' => now(), 'updated_at' => now()]);

        return true;
    }

    private function moveToRome(): void
    {
        $rome = DB::table('municipalities')->where('slug', 'rim')->where('shortname', 'Rím')->first();

        if ($rome === null) {
            return;
        }

        foreach (self::ROME as $id => $country) {
            DB::table('venues')
                ->where('id', $id)
                ->where('village_id', self::BRATISLAVA_ID)
                ->whereNull('deleted_at')
                ->update([
                    'village_id' => $rome->id,
                    'country' => $country,
                    'latitude' => self::ROME_LATITUDE,
                    'longitude' => self::ROME_LONGITUDE,
                    'coordinates_source' => 'municipality',
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * Návštevník, ktorý v ten deň videl obe miesta, je jedno zobrazenie
     * (unikátny index views_unique_per_day) — jeho riadok duplicity sa zahodí.
     */
    private function moveViews(int $fromId, int $toId): void
    {
        if (! Schema::hasTable('views')) {
            return;
        }

        $conflicting = DB::table('views as d')
            ->join('views as k', fn ($join) => $join->on('k.visitor_hash', '=', 'd.visitor_hash')->on('k.viewed_on', '=', 'd.viewed_on'))
            ->where('d.viewable_type', Venue::class)->where('d.viewable_id', $fromId)
            ->where('k.viewable_type', Venue::class)->where('k.viewable_id', $toId)
            ->pluck('d.id')->all();

        DB::table('views')->whereIn('id', $conflicting)->delete();
        DB::table('views')->where('viewable_type', Venue::class)->where('viewable_id', $fromId)
            ->update(['viewable_id' => $toId]);
    }

    /** Nerozhodnuté import-duplicity medzi miestami skupiny sú týmto vybavené. */
    private function settleDecisions(array $groupIds): void
    {
        if (! Schema::hasTable('duplicate_decisions')) {
            return;
        }

        DB::table('duplicate_decisions')
            ->where('entity', 'venue')
            ->whereNull('reviewed_at')
            ->whereIn('candidate_id', $groupIds)
            ->whereIn('created_id', $groupIds)
            ->update(['reviewed_at' => now(), 'updated_at' => now()]);
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
