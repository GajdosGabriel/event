<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('venues', 'event_id')) {
            return;
        }

        Schema::table('venues', function (Blueprint $table) {
            $table->dropForeign(['event_id']);
            $table->dropIndex(['event_id']);
            $table->dropColumn('event_id');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('venues', 'event_id')) {
            return;
        }

        Schema::table('venues', function (Blueprint $table) {
            $table->unsignedInteger('event_id')->nullable()->after('id');
            $table->index('event_id');
            $table->foreign('event_id')->references('id')->on('events')->nullOnDelete();
        });
    }
};
