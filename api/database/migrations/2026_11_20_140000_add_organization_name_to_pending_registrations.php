<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registrácia za organizáciu: popri mene človeka (osobný kanál) si pamätá aj
 * názov organizácie, z ktorého po overení e-mailu vznikne organizačný kanál
 * (App\Services\Users\PersonalCanalProvisioner). Bez toho ľudia písali názov
 * spolku do poľa „Meno“ a vznikal z neho osobný kanál.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pending_registrations', function (Blueprint $table) {
            $table->string('organization_name', 255)->nullable()->after('display_name');
        });

        Schema::table('pending_profiles', function (Blueprint $table) {
            $table->string('organization_name', 255)->nullable()->after('display_name');
        });
    }

    public function down(): void
    {
        Schema::table('pending_registrations', function (Blueprint $table) {
            $table->dropColumn('organization_name');
        });

        Schema::table('pending_profiles', function (Blueprint $table) {
            $table->dropColumn('organization_name');
        });
    }
};
