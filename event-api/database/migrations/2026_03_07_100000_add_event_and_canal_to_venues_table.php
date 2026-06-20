<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->unsignedInteger('canal_id')->after('id');

            $table->index('canal_id');

            $table->foreign('canal_id')->references('id')->on('canals');
        });
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->dropForeign(['canal_id']);
            $table->dropIndex(['canal_id']);
            $table->dropColumn(['canal_id']);
        });
    }
};
