<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adresa, ktorú kanálu pridal jeho tím, platí až po potvrdení odkazom
 * z e-mailu (CanalEmails::request). Dovtedy nemôže byť primárna.
 *
 *  - `pending_at`      kedy ju tím pridal; null = adresa platí,
 *  - `pending_primary` tím ju chce mať ako primárnu — stane sa ňou po potvrdení.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('canal_emails') || Schema::hasColumn('canal_emails', 'pending_at')) {
            return;
        }

        Schema::table('canal_emails', function (Blueprint $table) {
            $table->timestamp('pending_at')->nullable()->after('status');
            $table->boolean('pending_primary')->default(false)->after('pending_at');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('canal_emails', 'pending_at')) {
            Schema::table('canal_emails', function (Blueprint $table) {
                $table->dropColumn(['pending_at', 'pending_primary']);
            });
        }
    }
};
