<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kontakt na pisateľa otázky — aby sa dozvedel odpoveď.
     *
     * Otázka z QR kódu v sále odpoveď nepotrebuje: prednášajúci ju povie nahlas
     * a pisateľ sedí v miestnosti. Otázka z verejného detailu podujatia je iný
     * prípad — človek ju položí týždeň dopredu z gauča a odpoveď by musel chodiť
     * hľadať na stránku. Preto si ju môže vypýtať e-mailom.
     *
     * **Toto vedome mení povahu tabuľky.** `author_hash` je zámerne
     * neidentifikujúci (mení sa každý deň, IP sa neukladá), takže sa z `questions`
     * nedá poskladať, kto čo písal. `author_email` je oproti tomu priamy kontakt.
     * Vyvážené je to tým, že tam nežije dlho: vypĺňa sa len na výslovné želanie,
     * nikdy neopúšťa server (Question::$hidden) a **v okamihu odoslania odpovede
     * sa maže** — vtedy už splnil svoj jediný účel. Odhlasovací token preto
     * netreba: `answer_notified_at` pustí von nanajvýš jednu správu na otázku.
     */
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            // Vyplnené == „chcem vedieť o odpovedi". Samostatný príznak by bol
            // druhý zdroj tej istej pravdy, ktorý sa vie s adresou rozísť.
            $table->string('author_email', 190)->nullable()->after('author_name');

            // Kto sa pýtal, keď bol prihlásený. `unsignedInteger`, lebo users.id
            // je `increments`, nie `bigIncrements` — rovnako ako subscriptions.
            $table->unsignedInteger('user_id')->nullable()->after('author_email');

            // Odpoveď má prísť v reči, v akej sa človek pýtal, nie v predvolenej
            // reči servera.
            $table->string('locale', 5)->nullable()->after('user_id');

            // Poistka proti druhej vlne, presne ako subscriptions.notified_at
            // a events.reminder_sent_at: prepísaná odpoveď už e-mail neposiela.
            $table->timestamp('answer_notified_at')->nullable()->after('answered_at');

            // Účet môže zaniknúť skôr než otázka a otázka má prežiť — verejná
            // stránka by inak prišla o kus FAQ obsahu.
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn(['author_email', 'user_id', 'locale', 'answer_notified_at']);
        });
    }
};
