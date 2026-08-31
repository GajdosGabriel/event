<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `body_rewritten_at` — kedy copywriter prepísal zoškrabaný popis importovaného
 * podujatia na finálny `body`.
 *
 * Nahrádza doterajší claim `body_ai IS NULL` v `app:ai-detector` (po zrušení
 * stĺpca `body_ai`) a zároveň chráni prepísané telo pred prepisom pri nočnom
 * re-importe — `EventImportService` pri nastavenom príznaku `body` nediktuje.
 *
 * Skutočný stĺpec, nie kľúč v `meta`: výberový dotaz príkazu ho potrebuje a
 * MySQL na netypovanom JSON indexovať nevie (rovnaký dôvod ako `ai_tagged_at`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (! Schema::hasColumn('events', 'body_rewritten_at')) {
                $table->timestamp('body_rewritten_at')->nullable()->after('body_ai');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'body_rewritten_at')) {
                $table->dropColumn('body_rewritten_at');
            }
        });
    }
};
