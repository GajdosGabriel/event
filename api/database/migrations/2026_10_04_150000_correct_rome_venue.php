<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $venue = DB::table('venues')->where('slug', 'kostol-san-girolamo-della-carita')
                ->where('street', 'like', 'Via di Monserrato%')->whereNull('deleted_at')->first();
            if ($venue === null) {
                return;
            }
            $city = DB::table('municipalities')->where('slug', 'rim')->first();
            if ($city !== null && ($city->shortname !== 'Rím' || $city->region_id !== 0)) {
                throw new RuntimeException('Slug rim patrí inej obci; oprava bola zastavená.');
            }
            $cityId = $city?->id ?? DB::table('municipalities')->insertGetId([
                'fullname' => 'Rím', 'shortname' => 'Rím', 'slug' => 'rim', 'zip' => '00186',
                // Zahraničné mesto nepatrí slovenskému okresu ani kraju.
                'district_id' => 0, 'region_id' => 0, 'use' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            // TK KBS: https://www.tkkbs.sk/view.php?cisloclanku=20260929023
            // Mesto Rím: https://www.turismoroma.it/it/node/745 (adresa aj súradnice).
            DB::table('venues')->where('id', $venue->id)->update([
                'village_id' => $cityId, 'street' => 'Via di Monserrato 62/A', 'postcode' => '00186',
                'country' => 'Taliansko', 'latitude' => 41.8953618, 'longitude' => 12.4702096,
                'coordinates_source' => 'manual',
                'body' => '<p>Kostol San Girolamo della Carità sa nachádza v Ríme na Via di Monserrato 62/A.</p>',
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        // Neprepisujeme overenú polohu späť na nesprávnu Bratislavu.
    }
};
