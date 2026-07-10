<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * „Povolenie registrácie" už nie je samostatný prepínač — registrácia je
     * dostupná, keď má podujatie aspoň jeden aktívny typ lístka (odvodené).
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'tickets_enabled')) {
                $table->dropColumn('tickets_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (! Schema::hasColumn('events', 'tickets_enabled')) {
                $table->boolean('tickets_enabled')->default(false)->after('registration_deadline_at');
            }
        });
    }
};
