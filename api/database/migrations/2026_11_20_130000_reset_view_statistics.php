<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Vynuluje štatistiku zobrazení a začne ju zbierať odznova.
 *
 * Doteraz zapísané čísla nesedeli (denná aktivita v tabuľke `views` vs.
 * súčty v `views_count`), preto sa zmaže celá história zobrazení a vynulujú
 * sa počítadlá na podujatiach, miestach a kanáloch. Nové zobrazenia
 * zapisuje ViewRecorder ako doteraz.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('views')->delete();

        foreach (['events', 'venues', 'canals'] as $table) {
            // Priamo cez query builder — nesmie hýbať updated_at ani spúšťať observery.
            DB::table($table)->where('views_count', '>', 0)->update(['views_count' => 0]);
        }
    }

    public function down(): void
    {
        // Zmazaná štatistika sa neobnovuje.
    }
};
