<?php

use App\Models\Venue;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Miesta z kontroly produkčnej databázy (6. 11. 2026).
 *
 * Nadväzuje na 2026_10_23_110000_merge_duplicate_venues_from_production_audit.php
 * a zlučuje rovnako. Kandidáti: miesta založené po predošlej kontrole
 * (id > 1021) porovnané so všetkými v tej istej obci (názov, ulica)
 * a podľa presných súradníc; zástupné súradnice obce či krajiny, ktoré má
 * veľa miest naraz, sa za zhodu nepovažujú. Každá dvojica posúdená ručne.
 *
 * Nezlučuje sa: Katedrála sv. Jána Krstiteľa (1045, podujatia z Prešova)
 * a kostolík v Trnave (123), Kostol sv. Kríža (Devín) 1037 s chybnou ulicou,
 * Nábrežie Jána Pavla II / kláštor vo Svite — údaje sú nejasné.
 *
 * Bazilika sv. Petra (1050) visela na súradniciach stredu Slovenska —
 * dostane krajinu Vatikán a súradnice Ríma ako ostatné miesta vo Vatikáne.
 *
 * down() obnoví zlúčené duplicity; presuny sa nevracajú.
 */
return new class extends Migration
{
    /**
     * @var array<int, array{0:int, 1:list<int>}> [kanonické_id, [duplicitné_id, ...]]
     */
    private const MERGES = [
        [46, [1048]],           // Bratislava – Centrum Salvator na Jakubovom námestí
        [717, [955, 1051]],     // Košice – Teologická fakulta KU
        [1053, [826]],          // Bratislava – Univerzitné pastoračné centrum sv. Jozefa Freinademetza
        [1054, [85]],           // Živčáková (Korňa / Turzovka)
        [940, [1041, 1042]],    // Zlatá Baňa – Centrum pre rodinu Sigord 134
        [133, [1029]],          // Bratislava – Dom Quo Vadis, Veterná 1
        [1050, [858]],          // Vatikán – Bazilika sv. Petra (858 s obcou Ruská)
    ];

    /**
     * Duplicity, z ktorých sa nepreberá adresa: 1051 má ulicu kostola
     * sv. Antona (Františkánska), nie fakulty.
     */
    private const NO_FILL = [1051];

    private const VATICAN = 1050;

    /** Zástupné súradnice „Celé Slovensko", na ktorých 1050 visí. */
    private const SLOVAKIA_LATITUDE = 48.7411522;

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

            $this->moveToVatican();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('venues')) {
            return;
        }

        $duplicateIds = collect(self::MERGES)->flatMap(fn ($merge) => $merge[1])->all();

        DB::table('venues')->whereIn('id', $duplicateIds)->update(['deleted_at' => null, 'updated_at' => now()]);

        // Súradnice Baziliky sv. Petra sa na stred Slovenska nevracajú — bola to chyba.
    }

    private function merge(int $canonicalId, int $duplicateId): bool
    {
        $canonical = DB::table('venues')->where('id', $canonicalId)->whereNull('deleted_at')->first();
        $duplicate = DB::table('venues')->where('id', $duplicateId)->whereNull('deleted_at')->first();

        if ($canonical === null || $duplicate === null) {
            return false;
        }

        $update = ['updated_at' => now()];

        $fill = ! in_array($duplicateId, self::NO_FILL, true);

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

    private function moveToVatican(): void
    {
        DB::table('venues')
            ->where('id', self::VATICAN)
            ->whereNull('deleted_at')
            ->where('latitude', self::SLOVAKIA_LATITUDE)
            ->update([
                'country' => 'Vatikán',
                'latitude' => self::ROME_LATITUDE,
                'longitude' => self::ROME_LONGITUDE,
                'coordinates_source' => 'municipality',
                'updated_at' => now(),
            ]);
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
