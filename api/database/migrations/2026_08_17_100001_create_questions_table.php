<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Otázka z publika. Píše ju anonym bez účtu — celý zmysel je, že sa dá
     * opýtať do troch sekúnd od naskenovania QR kódu.
     *
     * `body` je zámerne čistý text, nie HTML. Ostatné texty v projekte čistí
     * HtmlBodyCleaner (SanitizesHtmlBody), lebo do nich organizátor formátuje;
     * sem sa žiadne formátovanie nepripúšťa, takže sa nemá čo sanitizovať —
     * front to vykresľuje interpoláciou, nikdy cez v-html.
     *
     * `author_hash` je pseudonym počítaný rovnako ako `views.visitor_hash`:
     * sha256(IP | user-agent | app key | dnešný dátum). IP sa nikde neukladá
     * a pseudonym sa každý deň mení, takže sa z tabuľky nedá poskladať, čo
     * konkrétny človek písal naprieč akciami. Slúži na dve veci: odchytenie
     * dvojkliku („túto otázku ste už poslali") a hromadné skrytie všetkého od
     * jedného spamera počas prebiehajúcej akcie.
     *
     * `answer_body` a `answered_at` sú tu preto, že nástenka nekončí koncom
     * prednášky: organizátor vie otázku po akcii dopísať a odkaz `/q/{token}`
     * sa stane malým FAQ podujatia.
     */
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('question_board_id');

            $table->text('body');
            $table->string('author_name', 80)->nullable();
            $table->string('status', 20)->default('published');
            $table->unsignedInteger('upvotes_count')->default(0);

            $table->text('answer_body')->nullable();
            $table->timestamp('answered_at')->nullable();
            // „Práve odpovedáme" na premietacej stene.
            $table->timestamp('highlighted_at')->nullable();

            $table->char('author_hash', 64);

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('question_board_id')->references('id')->on('question_boards')->cascadeOnDelete();

            // Verejný zoznam: filtruje sa stav a radí sa podľa počtu hlasov.
            $table->index(['question_board_id', 'status', 'upvotes_count'], 'questions_board_status_votes_index');
            // Inkrementálny polling `?since={maxId}`.
            $table->index(['question_board_id', 'id'], 'questions_board_id_index');
            // Dohľadanie ďalších otázok od toho istého pisateľa pri dedupe
            // a pri hromadnom skrytí spamera.
            $table->index(['question_board_id', 'author_hash'], 'questions_board_author_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
