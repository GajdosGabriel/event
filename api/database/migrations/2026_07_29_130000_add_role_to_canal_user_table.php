<?php

use App\Enums\CanalRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rola člena tímu v konkrétnom kanáli.
     *
     * Doteraz pivot rozlišoval len `is_owner` (áno/nie) a skutočné práva viseli
     * na globálnej spatie role používateľa — nedalo sa byť vlastníkom jedného
     * kanála a brigádnikom na vstupe v druhom.
     *
     * `is_owner` ostáva a drží sa v súlade s rolou (owner <=> 1), aby existujúce
     * dotazy cez ownedCanals()/owners() fungovali bez zmeny.
     */
    public function up(): void
    {
        if (Schema::hasColumn('canal_user', 'role')) {
            return;
        }

        Schema::table('canal_user', function (Blueprint $table) {
            $table->enum('role', array_column(CanalRole::cases(), 'value'))
                ->default(CanalRole::Editor->value)
                ->after('is_owner');
        });

        DB::table('canal_user')
            ->where('is_owner', true)
            ->update(['role' => CanalRole::Owner->value]);

        DB::table('canal_user')
            ->where('is_owner', false)
            ->update(['role' => CanalRole::Editor->value]);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('canal_user', 'role')) {
            return;
        }

        Schema::table('canal_user', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
