<?php

use Database\Seeders\TagSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Doplní emoji štítkom, ktoré ho nemajú.
 *
 * Číselník sa dopĺňal po etapách (návrhy z tag_suggestions) a časť štítkov
 * ostala v databáze bez ikony, takže vo verejnom filtri mal emoji len zlomok
 * čipov. Berie sa výhradne z TagSeeder — existujúca ikona sa neprepisuje.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tags') || ! Schema::hasColumn('tags', 'emoji')) {
            return;
        }

        foreach (TagSeeder::TAGS as $tags) {
            foreach ($tags as [$slug, , $emoji]) {
                DB::table('tags')
                    ->where('slug', $slug)
                    ->where(fn ($query) => $query->whereNull('emoji')->orWhere('emoji', ''))
                    ->update(['emoji' => $emoji]);
            }
        }
    }

    public function down(): void
    {
        // Ikony sa späť nemažú — nevieme, ktoré boli doplnené.
    }
};
