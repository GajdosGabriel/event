<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kapacita sa už nedrží na úrovni podujatia — každý typ lístka má vlastnú
     * kapacitu. Spoločný strop naprieč typmi sme odstránili.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'capacity')) {
                $table->dropColumn('capacity');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (! Schema::hasColumn('events', 'capacity')) {
                $table->unsignedInteger('capacity')->nullable()->after('registration_deadline_at');
            }
        });
    }
};
