<?php

use App\Support\NationwideCoordinates;
use App\Support\OnlineVenue;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Online miesta („Zoom Communications") dostali z geokódera súradnice
 * v Kalifornii a mapa podujatia sa oddialila na celý svet. Vracia sa im
 * zástupný stred Slovenska = poloha neznáma; nové sa už negeokódujú.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('venues')) {
            return;
        }

        DB::table('venues')->select(['id', 'name'])->orderBy('id')->each(function ($venue) {
            if (! OnlineVenue::matches($venue->name)) {
                return;
            }

            DB::table('venues')->where('id', $venue->id)->update([
                'latitude' => NationwideCoordinates::LATITUDE,
                'longitude' => NationwideCoordinates::LONGITUDE,
                'coordinates_source' => NationwideCoordinates::SOURCE,
            ]);
        });
    }

    public function down(): void
    {
        // Pôvodné (chybné) súradnice sa späť nevracajú.
    }
};
