<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('venues')) {
            return;
        }

        $fallbackCanalId = DB::table('canals')->value('id');
        $fallbackVillageId = DB::table('municipalities')->value('id') ?? 4209;

        if ($fallbackCanalId === null && DB::table('venues')->whereNull('canal_id')->exists()) {
            throw new RuntimeException('Cannot make venues.canal_id required because no canals exist to backfill null values.');
        }

        if (Schema::hasColumn('venues', 'canal_id')) {
            DB::table('venues')
                ->whereNull('canal_id')
                ->update(['canal_id' => $fallbackCanalId]);
        }

        if (Schema::hasColumn('venues', 'village_id')) {
            DB::table('venues')
                ->whereNull('village_id')
                ->update(['village_id' => $fallbackVillageId]);
        }

        Schema::table('venues', function (Blueprint $table) {
            if (Schema::hasColumn('venues', 'canal_id')) {
                $table->unsignedInteger('canal_id')->nullable(false)->change();
            }

            if (Schema::hasColumn('venues', 'village_id')) {
                $table->unsignedInteger('village_id')->nullable(false)->change();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('venues')) {
            return;
        }

        Schema::table('venues', function (Blueprint $table) {
            if (Schema::hasColumn('venues', 'canal_id')) {
                $table->unsignedInteger('canal_id')->nullable()->change();
            }

            if (Schema::hasColumn('venues', 'village_id')) {
                $table->unsignedInteger('village_id')->nullable()->change();
            }
        });
    }
};
