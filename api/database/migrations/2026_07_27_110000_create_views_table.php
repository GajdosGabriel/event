<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Zobrazenia verejných detailov — podujatí, miest a kanálov.
     *
     * Tabuľka slúži na rozpoznanie opakovaného zobrazenia, nie ako archív:
     * trvalé číslo žije v denormalizovanom `views_count` na samotnom zázname,
     * takže mazanie starých riadkov (app:views-prune) o počet nepripraví.
     *
     * `visitor_hash` je pseudonym počítaný zo sha256(IP | user-agent | app key |
     * dnešný dátum). IP sa nikde neukladá, cookie sa nenastavuje a keďže je
     * v hashi dátum, každý deň vznikne iný pseudonym — z tabuľky sa teda nedá
     * poskladať história návštevníka naprieč dňami.
     *
     * Unikátny index cez všetky štyri stĺpce je zároveň pravidlo „jeden
     * návštevník = jedno zobrazenie za deň": zápis ide cez insertOrIgnore
     * a počítadlo sa zvýši len vtedy, keď riadok naozaj pribudol.
     */
    public function up(): void
    {
        Schema::create('views', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('viewable_type', 60);
            $table->unsignedInteger('viewable_id');
            $table->char('visitor_hash', 64);
            $table->date('viewed_on');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['viewable_type', 'viewable_id', 'visitor_hash', 'viewed_on'],
                'views_unique_per_day',
            );
            // Pre prípadné „koľko zobrazení za posledných N dní".
            $table->index(['viewable_type', 'viewable_id', 'viewed_on'], 'views_target_day_index');
            // Mazanie starých riadkov.
            $table->index('viewed_on', 'views_viewed_on_index');
        });

        foreach (['events', 'venues', 'canals'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'views_count')) {
                    $table->unsignedInteger('views_count')->default(0);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['events', 'venues', 'canals'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'views_count')) {
                    $table->dropColumn('views_count');
                }
            });
        }

        Schema::dropIfExists('views');
    }
};
