<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Berie táto nástenka aj súkromné otázky a podnety?
     *
     * Vypínač tu je preto, že súkromná otázka je pre organizátora **záväzok**:
     * pisateľ ju nikde neuvidí zverejnenú a odpoveď mu sľubujeme e-mailom.
     * Kto na to nemá kapacitu (importované podujatie, jednorazová akcia bez
     * obsluhy), si to musí vedieť vypnúť — inak by sľuboval niečo, čo nesplní.
     *
     * Predvolene **zapnuté**: nástenku zakladá organizátor ručne a v tej chvíli
     * ju chce mať funkčnú celú. Aby sa zo záväzku nestal tichý dlh, o prvom
     * súkromnom podnete sa dozvie e-mailom (App\Notifications\PrivateQuestionReceived).
     */
    public function up(): void
    {
        Schema::table('question_boards', function (Blueprint $table) {
            $table->boolean('allow_private')->default(true)->after('ask_for_name');
        });
    }

    public function down(): void
    {
        Schema::table('question_boards', function (Blueprint $table) {
            $table->dropColumn('allow_private');
        });
    }
};
