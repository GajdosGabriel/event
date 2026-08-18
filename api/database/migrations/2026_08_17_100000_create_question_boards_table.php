<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nástenka otázok z publika — jedna na podujatie alebo na jeden workshop.
     *
     * Prečo polymorfne a nie stĺpce na `events`: workshop nie je samostatná
     * entita, ale riadok v `ticket_types` s `kind = workshop` (viď
     * docs/workshop-waitlist.md). Nástenka na workshope by teda potrebovala
     * vlastnú sadu stĺpcov v druhej tabuľke. Takto je nastavenie na jednom
     * mieste a pridanie ďalšieho cieľa je len zápis do QuestionBoard::TARGETS.
     *
     * `boardable_type` sa ukladá ako plný názov triedy (default morph) —
     * rovnako ako `messages.messageable_type`. Do morph mapy sa nesiaha,
     * `files.fileable_type` už plné názvy má a remapovanie by rozbilo
     * načítanie súborov.
     *
     * `token` je autorizácia verejnej stránky `/q/{token}`, tak ako
     * `confirmation_token` pri RSVP. Preto musí byť neuhádnuteľný, ale zároveň
     * krátky — premieta sa na plátno a ľudia v zadnom rade si ho prepisujú
     * rukou. Desať znakov z 32-znakovej abecedy je 2^50 možností.
     */
    public function up(): void
    {
        Schema::create('question_boards', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('boardable_type', 60);
            $table->unsignedBigInteger('boardable_id');
            $table->char('token', 10)->unique();

            $table->boolean('is_open')->default(true);
            // Otázka je verejná až po schválení organizátorom.
            $table->boolean('moderation')->default(false);
            // Smie pýtajúci vidieť doterajšie otázky, alebo len napíše svoju?
            $table->boolean('show_questions')->default(true);
            $table->boolean('allow_upvotes')->default(true);
            // Nepovinné pole „Vaše meno" nad formulárom.
            $table->boolean('ask_for_name')->default(true);
            $table->string('intro', 255)->nullable();

            // Automatické okno. Null znamená, že o otvorení rozhoduje len
            // `is_open` — nástenka bez podujatia s časom (napr. workshop bez
            // termínu) tak nezostane trvalo zavretá.
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('closes_at')->nullable();

            // Denormalizovaný počet zverejnených otázok — rovnaký prístup ako
            // `events.views_count`. Zoznamy nástienok ho čítajú bez COUNT-u.
            $table->unsignedInteger('questions_count')->default(0);

            $table->timestamps();

            $table->unique(['boardable_type', 'boardable_id'], 'question_boards_target_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_boards');
    }
};
