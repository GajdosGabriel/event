<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Doplní importovaným kanálom obec podľa miest ich podujatí.
 *
 * Import zakladá kanál skôr, než vie čokoľvek o mieste, takže mu obec ostávala
 * na zbernom „Celé Slovensko" — a nikto ju už nedoplnil. Filter obce nad
 * organizátormi (Canal::scopeByMunicipality číta `municipality_id` priamo
 * z kanála) tak nemal čo vrátiť: v produkcii sedelo na zbernej hodnote
 * všetkých 180 kanálov.
 *
 * Pravidlo je rovnaké ako v App\Services\Canals\CanalSeatDeriver, ktorý ho od
 * teraz drží pri každom importe: obec sa nastaví, len keď všetky podujatia
 * kanála ukazujú na jednu jedinú. Zberný kanál na zdroj (vyveska.sk má
 * podujatia v 33 obciach) v „Celé Slovensko" ostane — preň je to správna
 * odpoveď, nie chýbajúci údaj.
 *
 * Ručne zadanú obec migrácia nechytá: `CanalStoreRequest` ju vyžaduje, takže
 * organizátor, ktorý si v dashboarde vybral „Celé Slovensko", to myslel vážne.
 * Preto len `registration_source = import`, a len kým sedí na zbernej hodnote.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Migrácia beží aj na prázdnej databáze v testoch, kde číselník obcí
        // ani kanály ešte nemusia byť.
        if (! Schema::hasTable('canals') || ! Schema::hasTable('municipalities') || ! Schema::hasTable('venues')) {
            return;
        }

        $nationwideId = DB::table('municipalities')->where('slug', 'cele-slovensko')->value('id');

        if ($nationwideId === null) {
            return;
        }

        // Jeden dotaz namiesto slučky cez kanály: pre každý kanál na zbernej
        // hodnote zistí, koľko rôznych obcí majú miesta jeho podujatí.
        // Použiteľné sú len tie s práve jednou.
        $derived = DB::table('canals')
            ->join('events', 'events.canal_id', '=', 'canals.id')
            ->join('venues', 'venues.id', '=', 'events.venue_id')
            ->where('canals.registration_source', 'import')
            ->where('canals.municipality_id', $nationwideId)
            ->whereNull('canals.deleted_at')
            ->whereNull('events.deleted_at')
            ->whereNull('venues.deleted_at')
            ->where('venues.village_id', '<>', $nationwideId)
            ->groupBy('canals.id')
            ->havingRaw('COUNT(DISTINCT venues.village_id) = 1')
            ->get([
                'canals.id as canal_id',
                DB::raw('MIN(venues.village_id) as municipality_id'),
            ]);

        foreach ($derived as $row) {
            DB::table('canals')
                ->where('id', $row->canal_id)
                ->update([
                    'municipality_id' => (int) $row->municipality_id,
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * Späť sa to vrátiť nedá bez toho, aby sa zmazala aj obec, ktorú medzitým
     * niekto zadal ručne. Pôvodný stav bol navyše „obec nikto nedoplnil", teda
     * práve tá diera, ktorú migrácia zapĺňa. Zámerne prázdne.
     */
    public function down(): void {}
};
