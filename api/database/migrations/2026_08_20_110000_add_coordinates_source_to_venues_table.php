<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Odkiaľ pochádzajú GPS súradnice miesta.
 *
 * Detekcia dopĺňa súradnice rebríkom zdrojov (budova → adresa → AI → stred
 * obce). Bez tohto stĺpca by sa presnosť stratila hneď po uložení a nikto by
 * už nerozoznal značku na budove od značky v strede mesta, ktorú treba
 * upresniť. Hodnoty: venue, address, ai, municipality, manual.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('venues', 'coordinates_source')) {
            return;
        }

        Schema::table('venues', function (Blueprint $table) {
            $table->string('coordinates_source', 20)->nullable()->after('longitude');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('venues', 'coordinates_source')) {
            return;
        }

        Schema::table('venues', function (Blueprint $table) {
            $table->dropColumn('coordinates_source');
        });
    }
};
