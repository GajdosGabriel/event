<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Import hľadá podľa `orginal_source` pri každom spracovanom článku.
     * Pri nočnom scrapovaní to bolo pár stoviek dotazov, pri prenose archívu
     * z hlascirkvi ich je vyše 12 000 — bez indexu je to zakaždým full scan.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->index('orginal_source');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['orginal_source']);
        });
    }
};
