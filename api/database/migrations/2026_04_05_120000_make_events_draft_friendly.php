<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('events')) {
            return;
        }

        if (Schema::hasColumn('events', 'venue_id')) {
            Schema::table('events', function (Blueprint $table) {
                $table->unsignedInteger('venue_id')->nullable()->change();
            });
        }

        if (Schema::hasColumn('events', 'municipality_id')) {
            Schema::table('events', function (Blueprint $table) {
                $table->dropForeign('events_municipality_id_foreign');
                $table->dropIndex('events_municipality_id_index');
                $table->dropColumn('municipality_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('events')) {
            return;
        }

        if (! Schema::hasColumn('events', 'municipality_id')) {
            Schema::table('events', function (Blueprint $table) {
                $table->unsignedInteger('municipality_id')->nullable()->after('user_id');
            });
        }

        if (Schema::hasColumn('events', 'venue_id')) {
            Schema::table('events', function (Blueprint $table) {
                $table->unsignedInteger('venue_id')->nullable(false)->change();
            });
        }

        Schema::table('events', function (Blueprint $table) {
            $table->index('municipality_id', 'events_municipality_id_index');
            $table->foreign('municipality_id', 'events_municipality_id_foreign')
                ->references('id')
                ->on('municipalities')
                ->restrictOnDelete();
        });
    }
};
