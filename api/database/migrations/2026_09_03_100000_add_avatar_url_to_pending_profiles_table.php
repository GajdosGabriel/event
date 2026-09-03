<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Avatar zo sociálnej siete putuje k osobnému kanálu tou istou cestou ako
     * meno — cez PendingProfile, ktorý spotrebuje PersonalCanalProvisioner.
     * Ukladá sa len URL; samotný súbor sťahuje až job po založení kanála.
     */
    public function up(): void
    {
        if (Schema::hasTable('pending_profiles') && ! Schema::hasColumn('pending_profiles', 'avatar_url')) {
            Schema::table('pending_profiles', function (Blueprint $table) {
                $table->string('avatar_url', 2048)->nullable()->after('display_name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pending_profiles') && Schema::hasColumn('pending_profiles', 'avatar_url')) {
            Schema::table('pending_profiles', function (Blueprint $table) {
                $table->dropColumn('avatar_url');
            });
        }
    }
};
