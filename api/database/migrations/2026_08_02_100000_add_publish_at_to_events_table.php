<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Naplánované publikovanie. `published_at` je čas prvého zverejnenia (história),
 * takže plánovaný čas potrebuje vlastný stĺpec — inak by sa nedalo rozoznať
 * „vyjde o týždeň" od „vyšlo pred týždňom".
 *
 * Stav `scheduled` je v enume `events.status` od začiatku, schéma sa preň
 * meniť nemusí.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dateTime('publish_at')->nullable()->after('published_at');
            // Príkaz app:events-publish-scheduled sa pýta presne na túto dvojicu.
            $table->index(['status', 'publish_at'], 'events_status_publish_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex('events_status_publish_at_index');
            $table->dropColumn('publish_at');
        });
    }
};
