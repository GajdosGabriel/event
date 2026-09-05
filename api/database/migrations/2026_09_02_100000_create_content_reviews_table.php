<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kontrola zverejneného obsahu — jeden riadok na záznam, nie na beh.
 *
 * Zámerne to nie je história: zaujíma nás „ako je na tom tento popis teraz",
 * nie ako bol na tom pred tromi prepismi. Riadok sa prepisuje na mieste a
 * `content_hash` drží, ktorej verzii textu posudok patrí.
 *
 * Polymorfné od začiatku, rovnako ako `attribute_checks` a `subscriptions`:
 * dnes ide o podujatie, miesto a kanál, zajtra o čokoľvek s popisom.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Tabuľka na produkcii už existuje bez zodpovedajúceho riadku
        // v `migrations` — a `Schema::create()` na nej padal na
        // „Table 'content_reviews' already exists". Keďže táto migrácia beží
        // v poradí ako prvá z nespracovaných, zhodila celý `migrate` a tým
        // blokovala aj všetky ďalšie. Guard ju nechá prejsť naprázdno
        // a doplniť si chýbajúci riadok.
        if (Schema::hasTable('content_reviews')) {
            return;
        }

        Schema::create('content_reviews', function (Blueprint $table) {
            $table->id();

            // Plný názov triedy (default morph) — spoločná morph mapa
            // s files.fileable_type sa neprepisuje.
            $table->string('reviewable_type', 60);
            $table->unsignedInteger('reviewable_id');

            // Odtlačok posudzovaného textu. Bez neho by sa nedalo rozlíšiť
            // „tento popis sme už videli" od „popis sa medzitým zmenil",
            // a kontrola by buď bežala donekonečna, alebo by prehliadla prepis.
            $table->string('content_hash', 64)->nullable();

            // 0-100 od modelu. Nullable, kým kontrola nezbehla.
            $table->unsignedTinyInteger('score')->nullable();

            // Vety pre človeka: `summary` je zhrnutie, `issues` je pole výhrad
            // (severity, mode, message, quote). JSON, lebo tvar výhrady je vec
            // promptu a bude sa meniť — stĺpce by sme migrovali s ním.
            $table->text('summary')->nullable();
            $table->json('issues')->nullable();

            // Kedy je záznam splatný na kontrolu. NULL = nie je čo kontrolovať
            // (koncept, prikrátky text). Odklad po zverejnení je tu preto, aby
            // e-mail neodišiel skôr, než človek dopíše preklepy.
            $table->timestamp('due_at')->nullable();

            $table->timestamp('reviewed_at')->nullable();

            // Poistka proti druhej vlne e-mailov, presne ako
            // attribute_checks.notified_at.
            $table->timestamp('notified_at')->nullable();

            // Posledná chyba volania. Kontrola sa pri chybe nezopakuje hneď —
            // due_at sa posunie a dôvod ostane viditeľný pre admina.
            $table->string('last_error', 255)->nullable();

            $table->timestamps();

            // Jeden riadok na záznam.
            $table->unique(['reviewable_type', 'reviewable_id'], 'content_reviews_target_unique');

            // Výber dávky príkazom: „čo je splatné" cez čas, nie cez typ.
            $table->index('due_at', 'content_reviews_due_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_reviews');
    }
};
