<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rozpracovaný plagát nahratý z verejnej stránky.
 *
 * Analýza beží ešte pred registráciou — inak by sme od človeka pýtali účet
 * skôr, než mu ukážeme, či to vôbec funguje. Výsledok musí niekde počkať, kým
 * sa zaregistruje a potvrdí e-mail, a preto tu je samostatná tabuľka namiesto
 * rovno založeného konceptu podujatia: nepotvrdené nahratie nesmie vyrobiť
 * kanál ani event, ktoré by potom nemal kto vlastniť.
 *
 * Záznam je dočasný — `expires_at` ho po pár dňoch upratuje
 * `app:poster-drafts-prune` (aj s nahratým súborom).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('poster_drafts', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Hash tokenu z odkazu, nie token samotný. Únik databázy tak
            // neznamená prístup k cudzím rozpracovaným plagátom.
            $table->string('token', 64)->unique();

            $table->string('email')->nullable();
            $table->string('source_kind', 20);
            $table->string('original_filename')->nullable();
            $table->string('file_disk', 50)->nullable();
            $table->string('file_path')->nullable();

            $table->longText('extracted_text')->nullable();
            $table->json('detection')->nullable();
            $table->json('analysis')->nullable();
            $table->json('overrides')->nullable();

            $table->integer('claimed_by_user_id')->unsigned()->nullable();
            $table->integer('event_id')->unsigned()->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();

            $table->timestamps();

            // Bez týchto väzieb by po zmazaní účtu alebo podujatia ostalo
            // v koncepte visieť neexistujúce ID, ktoré `claim` vráti klientovi.
            $table->foreign('claimed_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('event_id')->references('id')->on('events')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poster_drafts');
    }
};
