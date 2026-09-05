<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Séria opakovaných termínov.
 *
 * Každý termín zostáva **samostatné podujatie** — s vlastnou kapacitou, vlastným
 * zoznamom prihlásených, vlastným check-inom a vlastnou verejnou adresou. Séria
 * je len väzba medzi nimi: povie, že patria k sebe, aby sa dal spoločný obsah
 * meniť na jednom mieste a aby výpis neukázal ten istý program päťkrát za sebou.
 *
 * Alternatíva („jedno podujatie, viac termínov") by znamenala prepísať lístky,
 * admissions, check-in, ICS aj JSON-LD — `start_at` je dnes v štyridsiatich
 * súboroch a všade znamená „kedy sa to koná".
 *
 * Tabuľka je zámerne takmer prázdna. Názov, popis ani obrázok sem nepatria:
 * zdrojom pravdy zostáva podujatie, séria len drží identitu skupiny. Keby tu
 * bol názov, boli by dva a rozišli by sa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_series', function (Blueprint $table) {
            $table->increments('id');
            // Séria patrí kanálu, nie používateľovi — presne ako podujatie.
            // Slúži na overenie práv bez toho, aby sa musel načítať niektorý
            // z termínov.
            $table->integer('canal_id')->unsigned()->index();
            $table->timestamps();
        });

        Schema::table('events', function (Blueprint $table) {
            $table->integer('series_id')->unsigned()->nullable()->after('canal_id');
            // Výpis sa pýta „najbližší termín série" a detail „ostatné termíny";
            // oboje je séria + termín.
            $table->index(['series_id', 'start_at'], 'events_series_start_index');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex('events_series_start_index');
            $table->dropColumn('series_id');
        });

        Schema::dropIfExists('event_series');
    }
};
