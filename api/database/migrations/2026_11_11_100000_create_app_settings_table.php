<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nastavenia meniteľné z adminu (AppSettings) — zatiaľ limity a vypínače AI
 * vyhľadávania zo stránky Spotreba AI. Kľúč je názov configu, ktorý hodnota
 * prepisuje; bez riadku platí config/.env.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->json('value');
            // users.id je int unsigned, foreignId (bigint) by kľúč nevytvorilo.
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
