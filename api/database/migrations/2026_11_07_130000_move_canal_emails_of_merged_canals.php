<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kanály zlúčené pred vznikom `canal_emails` si adresy nechali — migrácia
 * tabuľky ich preniesla aj zmazaným kanálom. ResourceMerger ich dnes presúva
 * do cieľa; tu sa to dorovná spätne. Adresa, ktorú cieľ už má, sa zmaže.
 * Cieľ dostane adresu ako ďalšiu, nie primárnu.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('canal_emails')) {
            return;
        }

        $merged = DB::table('canals')->whereNotNull('deleted_at')->whereNotNull('merged_into_id')
            ->pluck('merged_into_id', 'id');

        foreach ($merged as $sourceId => $targetId) {
            // Reťaz zlúčení (A → B → C) vedie k poslednému živému kanálu.
            $seen = [$sourceId];
            while (isset($merged[$targetId]) && ! in_array($targetId, $seen, true)) {
                $seen[] = $targetId;
                $targetId = $merged[$targetId];
            }

            $alive = DB::table('canals')->where('id', $targetId)->whereNull('deleted_at')->exists();
            $known = $alive ? DB::table('canal_emails')->where('canal_id', $targetId)->pluck('email')->all() : [];

            if ($alive) {
                DB::table('canal_emails')->where('canal_id', $sourceId)->whereNotIn('email', $known)
                    ->update(['canal_id' => $targetId, 'is_primary' => false]);
            }

            DB::table('canal_emails')->where('canal_id', $sourceId)->delete();
        }
    }

    public function down(): void
    {
        // Presun sa nevracia — zmazaný kanál adresy nepotrebuje.
    }
};
