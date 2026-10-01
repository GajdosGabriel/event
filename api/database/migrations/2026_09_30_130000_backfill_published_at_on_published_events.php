<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Publikované podujatia bez `published_at`.
 *
 * Podujatie založené rovno ako publikované (wizard „Zverejniť") nedostalo čas
 * prvého zverejnenia, kým sa neuložilo znova. Verejné výpisy filtrujú aj
 * triedia podľa `published_at`, takže takéto podujatia chýbali v „nových".
 * Presný okamih zverejnenia sa už nezistí; `created_at` je bezpečný odhad —
 * podujatie nemohlo byť zverejnené skôr, než vzniklo. Dotýka sa len riadkov,
 * kde je `published_at` prázdne (vrátane zmazaných), iné sa nemenia.
 *
 * down() nič nevracia: pôvodné NULL nesie len chybný stav.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('events')
            ->where('status', 'published')
            ->whereNull('published_at')
            ->update(['published_at' => DB::raw('COALESCE(created_at, updated_at, NOW())')]);
    }

    public function down(): void
    {
        // Jednosmerná oprava dát.
    }
};
