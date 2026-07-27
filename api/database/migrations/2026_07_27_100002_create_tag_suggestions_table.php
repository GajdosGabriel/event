<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Návrhy na rozšírenie číselníka.
     *
     * AI je schémou donútená vyberať len zo zoznamu povolených slugov, inak by
     * číselník zaplevala synonymami („koncert" / „koncerty" / „hudobný koncert")
     * a rozbila filtre. V tej istej odpovedi ale vráti aj pár voľných výrazov,
     * ktoré by bola použila, keby smela — tie sa hromadia sem s počítadlom
     * výskytov. Číselník tak rastie z reálnych dát a nestojí to ani jedno
     * volanie navyše.
     */
    public function up(): void
    {
        Schema::create('tag_suggestions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('slug', 60)->unique();
            $table->string('label', 80);
            $table->unsignedInteger('occurrences')->default(1);
            $table->unsignedInteger('last_event_id')->nullable();
            $table->timestamp('last_seen_at');
            // null = nespracované, inak promoted|rejected
            $table->string('resolution', 12)->nullable();
            $table->timestamps();

            $table->index(['resolution', 'occurrences']);

            $table->foreign('last_event_id')->references('id')->on('events')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tag_suggestions');
    }
};
