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

        Schema::table('events', function (Blueprint $table) {
            $table->index('canal_id', 'events_canal_id_index');
            $table->index('status', 'events_status_index');
            $table->index('published_at', 'events_published_at_index');
            $table->index('start_at', 'events_start_at_index');
            $table->index('end_at', 'events_end_at_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('events')) {
            return;
        }

        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex('events_canal_id_index');
            $table->dropIndex('events_status_index');
            $table->dropIndex('events_published_at_index');
            $table->dropIndex('events_start_at_index');
            $table->dropIndex('events_end_at_index');
        });
    }
};
