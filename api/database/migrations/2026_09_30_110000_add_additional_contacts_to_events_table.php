<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Podujatie môže mať viac e-mailov a telefónov. Primárny ostáva v `email` /
 * `phone` (tie čítajú notifikácie, JSON-LD aj UI), ďalšie idú do JSON polí.
 * Stĺpec `phone` sa rozširuje na 30 znakov kvôli číslam s predvoľbou.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->change();
            $table->json('additional_emails')->nullable()->after('phone');
            $table->json('additional_phones')->nullable()->after('additional_emails');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['additional_emails', 'additional_phones']);
            $table->string('phone', 20)->nullable()->change();
        });
    }
};
