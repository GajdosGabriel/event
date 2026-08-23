<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rozpísaná adresa kanála.
 *
 * Kanál mal doteraz len obec a súradnice — sídlo organizátora sa nedalo
 * zapísať a mapa sa plnila iba odhadom podľa názvu. Stĺpce sú zámerne rovnaké
 * ako vo `venues`, aby ich vedel obslúžiť ten istý editor adresy aj ten istý
 * geokóder. `coordinates_source` drží presnosť polohy (venue, address, ai,
 * municipality, manual) — bez neho sa stred obce navonok nelíši od presnej
 * adresy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('canals', function (Blueprint $table) {
            if (! Schema::hasColumn('canals', 'street')) {
                $table->string('street', 250)->nullable()->after('municipality_id');
            }

            if (! Schema::hasColumn('canals', 'postcode')) {
                $table->string('postcode', 20)->nullable()->after('street');
            }

            if (! Schema::hasColumn('canals', 'country')) {
                $table->string('country', 100)->nullable()->after('postcode');
            }

            if (! Schema::hasColumn('canals', 'coordinates_source')) {
                $table->string('coordinates_source', 20)->nullable()->after('longitude');
            }
        });
    }

    public function down(): void
    {
        $columns = array_values(array_filter(
            ['street', 'postcode', 'country', 'coordinates_source'],
            fn (string $column): bool => Schema::hasColumn('canals', $column),
        ));

        if ($columns === []) {
            return;
        }

        Schema::table('canals', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }
};
