<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hľadanie náhradnej adresy kanála bez kontaktu (App\Services\Canals\CanalEmailFinder).
 *
 *  - `canal_emails.found_on` — kde sa nájdená adresa našla (URL, podujatie),
 *  - `canal_email_searches` — jeden riadok na kanál: kedy sa hľadalo, s akým
 *    výsledkom a kedy znova. Vrátený e-mail nastaví `requested_at`, aby sa
 *    kanál dostal na rad hneď, nie až pri ďalšom opakovaní.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('canal_emails') && ! Schema::hasColumn('canal_emails', 'found_on')) {
            Schema::table('canal_emails', function (Blueprint $table) {
                $table->string('found_on', 250)->nullable()->after('source');
            });
        }

        if (Schema::hasTable('canal_email_searches')) {
            return;
        }

        Schema::create('canal_email_searches', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('canal_id')->unique();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('searched_at')->nullable();
            $table->timestamp('retry_at')->nullable();
            // found = pridala sa adresa, none = nič, skipped = kanál nie je na rade (spravovaný, osobný…).
            $table->string('result', 16)->nullable();
            // Ktorý krok adresu našiel: events, website, ai.
            $table->string('found_via', 16)->nullable();
            $table->string('last_error', 250)->nullable();
            $table->timestamps();

            $table->index(['requested_at']);
            $table->index(['retry_at']);
            $table->foreign('canal_id')->references('id')->on('canals')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canal_email_searches');

        if (Schema::hasColumn('canal_emails', 'found_on')) {
            Schema::table('canal_emails', fn (Blueprint $table) => $table->dropColumn('found_on'));
        }
    }
};
