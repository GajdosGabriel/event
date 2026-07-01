<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('tickets_enabled')->default(false)->after('registration_deadline_at');
            $table->unsignedInteger('capacity')->nullable()->after('tickets_enabled');
            $table->unsignedInteger('price_amount')->nullable()->after('capacity');
            $table->char('price_currency', 3)->default('EUR')->after('price_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['tickets_enabled', 'capacity', 'price_amount', 'price_currency']);
        });
    }
};
