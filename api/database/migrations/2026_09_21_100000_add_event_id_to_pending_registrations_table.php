<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registrácia z tlačidla „Prihlásiť sa" pri podujatí (hlascirkvi.sk) si pamätá,
 * na ktoré podujatie sa človek hlási — rezervácia sa vykoná až po overení
 * e-mailu, keď účet reálne vznikne (App\Services\Tickets\EventSignup).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pending_registrations', function (Blueprint $table) {
            $table->unsignedInteger('event_id')->nullable()->after('display_name');
            $table->foreign('event_id')->references('id')->on('events')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pending_registrations', function (Blueprint $table) {
            $table->dropForeign(['event_id']);
            $table->dropColumn('event_id');
        });
    }
};
