<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Väzba kanála na fakturačnú identitu.
 *
 * Doteraz visela Organization vo vzduchu: mala `account_uuid`, ale nič
 * v aplikácii z nej nevisel žiadny obsah ani člen. `Organization::users()`
 * pritom ukazovala na tabuľku `organization_user`, ktorá nikdy nevznikla,
 * takže volanie tej relácie padalo.
 *
 * Tímové členstvo sa nekopíruje na organizáciu — má ho `canal_user` aj
 * s rolami (CanalRole) a pozvánkami (CanalInvitation). Druhý paralelný
 * členský systém by znamenal dva zdroje pravdy pre „kto čo smie".
 *
 * Reťazec je preto:
 *
 *     User ──canal_user(role)── Canal ──organization_id──▶ Organization ──account_uuid──▶ Account
 *
 * `organization_id` je nullable zámerne — kanál bez nej je bežný stav
 * (osobný kanál z registrácie) a znamená neplatený režim.
 *
 * FK constraint tu nie je, rovnako ako pri `canal_id`, `venue_id`
 * a ostatných väzbách v tomto projekte; drží sa index kvôli JOINom
 * z dashboardu a mazanie rieši soft delete.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('canals', 'organization_id')) {
            return;
        }

        Schema::table('canals', function (Blueprint $table) {
            $table->unsignedInteger('organization_id')->nullable()->after('id');
            $table->index('organization_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('canals', 'organization_id')) {
            return;
        }

        Schema::table('canals', function (Blueprint $table) {
            $table->dropIndex(['organization_id']);
            $table->dropColumn('organization_id');
        });
    }
};
