<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();

            // Polymorfný cieľ odberu. Dnes ho UI ponúka len na podujatí, ale
            // schéma je od začiatku polymorfná: „daj mi vedieť o novom v Martine"
            // je tá istá vec s iným cieľom a doťahovať to neskôr by znamenalo
            // migrovať už naplnenú tabuľku. `subscribable_type` je plný názov
            // triedy (default morph), rovnako ako messages a question_boards —
            // morph mapa je zdieľaná s files.fileable_type a neprepisuje sa.
            $table->string('subscribable_type', 60);
            $table->unsignedInteger('subscribable_id');

            // Nullable, lebo odhlásenie e-mail zahodí a riadok nechá žiť —
            // odkaz z pätičky tak funguje aj na druhý klik a nedržíme adresu
            // niekoho, kto o nás už nestojí.
            $table->string('email', 190)->nullable();

            // Token v odkaze JE autorizácia odhlásenia — rovnaká konvencia ako
            // RSVP odkaz a nástenka otázok.
            $table->string('token', 64)->unique();

            // Jazyk, v ktorom si odber vypýtal. Pripomienka má prísť v reči,
            // v akej si stránku čítal, nie v predvolenej reči servera.
            $table->string('locale', 5)->nullable();

            // Single opt-in na konkrétne podujatie (človek si ho práve vypýtal),
            // ale stĺpec je tu pre odbery kanála, kde bude potvrdenie potrebné.
            $table->timestamp('confirmed_at')->nullable();

            // Poistka proti druhej vlne e-mailov, presne ako events.reminder_sent_at.
            $table->timestamp('notified_at')->nullable();

            $table->timestamp('unsubscribed_at')->nullable();

            // Kto si neskôr založí účet s tou istou adresou, má dostať svoje
            // odbery. Účet môže zaniknúť skôr než odber, preto len nullOnDelete.
            $table->unsignedInteger('user_id')->nullable();

            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

            // Dvakrát odoslaný formulár nesmie založiť dva odbery. Odhlásený
            // riadok má email NULL a do unikátnosti nezasahuje (MySQL berie
            // NULL ako vždy odlišný), takže sa dá prihlásiť znova.
            $table->unique(['subscribable_type', 'subscribable_id', 'email'], 'subscriptions_target_email_unique');

            // Výber adresátov pri rozosielaní ide cez cieľ a stav.
            $table->index(['subscribable_type', 'subscribable_id', 'notified_at'], 'subscriptions_target_notified_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
