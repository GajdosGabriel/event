<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Súkromná otázka — a s ňou podnet z publika.
     *
     * Doteraz bola každá otázka verejná: napísala sa preto, aby ju videli aj
     * ostatní, a zodpovedaná bola aj SEO obsahom. Chýbal druhý prípad, ktorý
     * verejný nikdy nebude:
     *
     * - **pred podujatím** sa niekto pýta na niečo, čo sa nehodí na plátno ani
     *   do FAQ („som na vozíku, dostanem sa dnu?"),
     * - **počas podujatia** treba organizátorovi povedať, že v sále je zima
     *   alebo nie je počuť. To nie je otázka pre prednášajúceho, je to podnet
     *   pre toho, kto akciu robí — a nikoho iného v sále nezaujíma.
     *
     * Je to **jeden stĺpec, nie druhá tabuľka ani druhý typ otázky.** Podnet sa
     * od súkromnej otázky líši jedine tým, kedy prišiel, a to už vieme z
     * `created_at` oproti termínu podujatia (QuestionBoardPhase). Uložený
     * príznak „toto je podnet" by bol druhý zdroj tej istej pravdy.
     *
     * Prečo nie správa (`messages`): správa je vlákno s vlastníkom účtu a žije
     * v inboxe. Podnet musí pribudnúť tam, kam sa organizátor počas akcie
     * pozerá — do nástenky otázok — a odpoveď naň je tá istá odpoveď ako pri
     * verejnej otázke, vrátane e-mailu pisateľovi.
     *
     * `visibility` zámerne nemá vlastný index. Verejné cesty filtrujú
     * `status` aj `visibility` naraz a existujúci
     * `questions_board_status_votes_index` ich privedie na jednotky riadkov;
     * súkromných je z podstaty veci rádovo menej než verejných.
     */
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            // Verejná je predvolená — všetko, čo v tabuľke leží doteraz, bolo
            // napísané do verejnej nástenky a nesmie sa jej stratiť.
            $table->string('visibility', 10)->default('public')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn('visibility');
        });
    }
};
