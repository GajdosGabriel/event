<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Väzba na Account — centrálnu evidenciu firiem.
 *
 * Zámerne tu nepribúda IČO, DIČ ani adresa. Fakturačné údaje vlastní
 * Account; keby sme si ich sem skopírovali, po prvej úprave v inom
 * projekte by sa obe kópie rozišli a nikto by nevedel, ktorá platí.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->uuid('account_uuid')->nullable()->unique()->after('id');
            $table->timestamp('account_synced_at')->nullable()->after('account_uuid');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropUnique(['account_uuid']);
            $table->dropColumn(['account_uuid', 'account_synced_at']);
        });
    }
};
