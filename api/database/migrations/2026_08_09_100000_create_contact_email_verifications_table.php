<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rozpracované overenie kontaktného e-mailu ľubovoľného modelu (kanál,
     * miesto, podujatie, organizácia…).
     *
     * Prečo polymorfne a nie stĺpec `email_verification_token` na každej
     * tabuľke: adries je viac typov a pribúdajú, kým samotný proces je vždy
     * ten istý — pošli odkaz, počkaj na klik, zapíš `email_verified_at`.
     * Pridanie ďalšieho typu je potom len o zaradení do whitelistu
     * ContactEmailVerification::TARGETS, nie o zmene schémy.
     *
     * Do tabuľky sa ukladá `email`, ku ktorému bol odkaz vydaný — kým čaká na
     * potvrdenie, môže ho organizátor vo formulári prepísať. Odkaz na starú
     * adresu potom nesmie overiť tú novú, preto sa pri potvrdení porovnáva.
     *
     * Záznam je dočasný: po overení aj po zmene adresy sa maže. Overenie samo
     * žije v `email_verified_at` na modeli.
     */
    public function up(): void
    {
        if (Schema::hasTable('contact_email_verifications')) {
            return;
        }

        Schema::create('contact_email_verifications', function (Blueprint $table) {
            $table->id();
            // Trieda modelu (rovnako ako `messages.messageable_type`). Krátky
            // alias z ContactEmailVerification::TARGETS je len pre API a odkazy,
            // aby verejný tvar nezávisel od mena triedy.
            $table->string('verifiable_type', 50);
            $table->unsignedInteger('verifiable_id');
            $table->string('email', 190);
            // sha256 odkazu z e-mailu — v databáze nikdy nie je použiteľný tvar.
            $table->string('token', 64)->unique();
            // Kedy naposledy odišiel e-mail. Drží odstup medzi opakovanými
            // odoslaniami, aby sa tlačidlom „poslať znova" nedala adresa zahltiť.
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['verifiable_type', 'verifiable_id']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_email_verifications');
    }
};
