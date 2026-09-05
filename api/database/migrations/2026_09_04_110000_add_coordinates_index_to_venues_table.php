<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index pre hľadanie „v mojom okolí".
 *
 * Filter najprv oreže miesta hrubým obdĺžnikom okolo bodu (`whereBetween` nad
 * oboma súradnicami) a až na tom, čo prejde, počíta haversine. Bez indexu by
 * obdĺžnik znamenal prechod celej tabuľky a hrubé sito by stratilo zmysel.
 *
 * Zložený index v poradí (latitude, longitude): rozsah na šírke je vždy užší
 * — jeden stupeň má 111 km, kým na dĺžke sa smerom k pólom rozťahuje.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->index(['latitude', 'longitude'], 'venues_coordinates_index');
        });
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->dropIndex('venues_coordinates_index');
        });
    }
};
