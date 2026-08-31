<?php

use App\Services\Events\EventBodyConsolidator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zrušenie stĺpca `body_ai` — popis podujatia je odteraz jediné pole `body`.
 *
 * Presun dát robí App\Services\Events\EventBodyConsolidator: pri importovaných
 * podujatiach sa `body_ai` (copywriter prepis) stane `body`, pôvodný zoškrabaný
 * text sa odloží do `meta.imported_raw_body`. Ručne zadané podujatia si `body`
 * ponechajú a staging `body_ai` zmizne s dropom stĺpca.
 *
 * Na produkcii sa presun dá pustiť a skontrolovať vopred príkazom
 * `php artisan app:consolidate-event-body --dry-run`; volanie tu je poistka pre
 * prostredia, kde sa tak nestalo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('events', 'body_ai')) {
            return;
        }

        app(EventBodyConsolidator::class)->run(dryRun: false);

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('body_ai');
        });
    }

    /**
     * Späť sa to nevracia — `body_ai` bola duplicita, ktorej hodnota je teraz
     * buď v `body`, alebo (pre import) aj v `meta.imported_raw_body`.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (! Schema::hasColumn('events', 'body_ai')) {
                $table->text('body_ai')->nullable()->after('body');
            }
        });
    }
};
