<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Dorovnanie pivotu: každé miesto potrebuje vlastnícky kanál.
 *
 * VenuePolicy::update() sa pýta výhradne na `ownerCanals()`, čiže na
 * `canal_venue.is_owner = 1`. ImportedVenueManager ale zakladal miesta cez
 * `assignCanal($canal, isOwner: false)`, takže importované miesto nemalo
 * vlastníka vôbec — a tým pádom ho v dashboarde nevedel upraviť nikto okrem
 * super-admina, ktorému to prejde cez gate bypass. Rovnaká diera ako pri rolách
 * v canal_user (viď sync_owner_role_in_canal_user), len na druhom pivote.
 *
 * Vlastníkom sa stáva najstaršia väzba — kanál, ktorý miesto priniesol.
 * Neskoršie kanály ho len zdieľajú, tak ako doteraz.
 *
 * Zberné „Celé Slovensko" (category = 'fallback') vlastníka zámerne nedostáva:
 * je to systémový záznam spoločný pre všetky importy a jeden náhodný kanál by
 * ho tým dostal do rúk.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Pivot nemá `id` (primárny kľúč je dvojica canal_id + venue_id), takže
        // „najstaršia väzba" sa nevyberie agregátom — poradie určí ORDER BY
        // a prvý riadok každého miesta si vezme PHP.
        $links = DB::table('canal_venue')
            ->join('venues', 'venues.id', '=', 'canal_venue.venue_id')
            ->where(fn ($q) => $q->whereNull('venues.category')->orWhere('venues.category', '<>', 'fallback'))
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('canal_venue as owned')
                    ->whereColumn('owned.venue_id', 'canal_venue.venue_id')
                    ->where('owned.is_owner', true);
            })
            ->orderBy('canal_venue.venue_id')
            ->orderBy('canal_venue.created_at')
            ->orderBy('canal_venue.canal_id')
            ->get(['canal_venue.venue_id', 'canal_venue.canal_id']);

        $claimed = [];

        foreach ($links as $link) {
            if (isset($claimed[$link->venue_id])) {
                continue;
            }

            $claimed[$link->venue_id] = true;

            DB::table('canal_venue')
                ->where('venue_id', $link->venue_id)
                ->where('canal_id', $link->canal_id)
                ->update(['is_owner' => true, 'updated_at' => now()]);
        }
    }

    /**
     * Späť sa to vrátiť nedá — pôvodný stav bol „miesto bez vlastníka", čo je
     * práve tá chyba, ktorú migrácia opravuje. Zámerne prázdne.
     */
    public function down(): void {}
};
