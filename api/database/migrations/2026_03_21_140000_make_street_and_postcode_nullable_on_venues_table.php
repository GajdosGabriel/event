<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('venues')) {
            return;
        }

        Schema::table('venues', function (Blueprint $table) {
            if (Schema::hasColumn('venues', 'street')) {
                $table->string('street', 250)->nullable()->change();
            }

            if (Schema::hasColumn('venues', 'postcode')) {
                $table->string('postcode', 250)->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('venues')) {
            return;
        }

        Schema::table('venues', function (Blueprint $table) {
            if (Schema::hasColumn('venues', 'street')) {
                $table->string('street', 250)->nullable(false)->change();
            }

            if (Schema::hasColumn('venues', 'postcode')) {
                $table->string('postcode', 250)->nullable(false)->change();
            }
        });
    }
};
