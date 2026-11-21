<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Odkiaľ prišli zobrazenia podujatia — QR kód, pozvánka, odkaz z panela
     * Propagácia (App\Enums\ViewSource).
     *
     * Vlastná tabuľka s trvalým počtom, nie stĺpec v `views`: tie riadky
     * app:views-prune po 90 dňoch maže, a organizátor sa na to, či zabral
     * plagát, pýta aj po akcii. Počíta sa len zobrazenie, ktoré pribudlo aj
     * do `views_count` (jeden návštevník za deň), takže sa čísla nerozídu.
     */
    public function up(): void
    {
        Schema::create('event_view_sources', function (Blueprint $table) {
            $table->unsignedInteger('event_id');
            $table->string('source', 20);
            $table->unsignedInteger('views')->default(0);

            $table->primary(['event_id', 'source']);
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_view_sources');
    }
};
