<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Časové okno nástenky preč — ostáva jediný vypínač `is_open`.
     *
     * Okno vzniklo ako automatika, ktorá mala organizátorovi ušetriť klikanie:
     * nástenka sa mala sama otvoriť dve hodiny pred akciou a dve hodiny po nej
     * zavrieť. V praxi z toho bola len ďalšia vec, ktorá sa vie pokaziť —
     * dve polia navyše v nastaveniach, ktoré si vypýtali presný čas, hoci
     * organizátor chce odpovedať na jedinú otázku: „berieme otázky, alebo nie?"
     *
     * Nič sa tým nestráca. Zavretie po akcii bolo aj tak len odhad; kto chce
     * nástenku zavrieť, vypne `is_open` jedným klikom. A fáza nástenky (FAQ vs.
     * plátno) sa nikdy neriadila oknom, ale termínom podujatia —
     * QuestionBoardPhase to počíta ďalej a nezmenene.
     *
     * Stĺpce sa mažú aj z databázy, nielen z formulára: ponechaný stĺpec, ktorý
     * nikto nevie nastaviť a niečo podľa neho stále rozhoduje, je horší než
     * žiadny.
     */
    public function up(): void
    {
        Schema::table('question_boards', function (Blueprint $table) {
            $table->dropColumn(['opens_at', 'closes_at']);
        });
    }

    public function down(): void
    {
        Schema::table('question_boards', function (Blueprint $table) {
            $table->timestamp('opens_at')->nullable()->after('ask_for_name');
            $table->timestamp('closes_at')->nullable()->after('opens_at');
        });
    }
};
