<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Workshop s open_to_public = true sa dá objednať aj bez hlavnej vstupenky
     * (aj pre neregistrovaných účastníkov). Netýka sa bežných vstupeniek.
     */
    public function up(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            if (! Schema::hasColumn('ticket_types', 'open_to_public')) {
                $table->boolean('open_to_public')->default(false)->after('kind');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            if (Schema::hasColumn('ticket_types', 'open_to_public')) {
                $table->dropColumn('open_to_public');
            }
        });
    }
};
