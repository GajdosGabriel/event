<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hlas za cudziu otázku. Namiesto piatich variácií tej istej otázky sa
     * jedna vyzbiera hore a prednášajúci vie, čo sálu naozaj zaujíma.
     *
     * `voter_hash` NIE je denne rotujúci pseudonym z `questions.author_hash`.
     * Hlas musí prežiť prepnutie wifi na LTE a po reloade stránky musí byť
     * rozpoznaný ako „môj hlas", aby sa dal odobrať — obe veci by hash z IP
     * pokazil. Preto je to sha256 z náhodného tokenu, ktorý si prehliadač raz
     * vygeneruje do localStorage.
     *
     * Áno, dá sa to obísť vyčistením úložiska. Je to hlasovanie o poradí otázok
     * na konferencii, nie voľby; skutočnú ochranu robí limiter na IP.
     *
     * Trvalé číslo je denormalizované v `questions.upvotes_count` — táto
     * tabuľka existuje kvôli unikátnemu indexu, teda pravidlu „jeden hlas na
     * otázku a prehliadač".
     */
    public function up(): void
    {
        Schema::create('question_votes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('question_id');
            $table->char('voter_hash', 64);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('question_id')->references('id')->on('questions')->cascadeOnDelete();
            $table->unique(['question_id', 'voter_hash'], 'question_votes_unique');
            // „Za ktoré otázky som už hlasoval?" pri načítaní stránky.
            $table->index('voter_hash', 'question_votes_voter_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_votes');
    }
};
