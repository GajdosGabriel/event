<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('events') || ! Schema::hasTable('venues')) {
            return;
        }

        if (! Schema::hasColumn('events', 'venue_id')) {
            return;
        }

        Schema::table('events', function (Blueprint $table) {
            $table->index('venue_id', 'events_venue_id_index');

            $table->foreign('venue_id', 'events_venue_id_foreign')
                ->references('id')
                ->on('venues')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('events')) {
            return;
        }

        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'venue_id')) {
                $table->dropForeign('events_venue_id_foreign');
                $table->dropIndex('events_venue_id_index');
            }
        });
    }
};
