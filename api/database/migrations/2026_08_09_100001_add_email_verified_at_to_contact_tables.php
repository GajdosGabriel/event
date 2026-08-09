<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zjednotenie stavu kontaktného e-mailu naprieč modelmi.
 *
 * `canals` a `organizations` stĺpec `email_verified_at` už mali (doteraz ho
 * nikto nezapisoval), `venues` a `events` nie. Aby sa dal e-mail vo všetkých
 * formulároch overovať rovnako, musí ho mať každý model s kontaktnou adresou.
 *
 * Existujúce adresy zostávajú neoverené (`null`) — o tom, či adresa naozaj
 * patrí organizátorovi, nevieme nič a spätne to dopísať by znamenalo tváriť sa,
 * že overená je. Overia sa pri najbližšom uložení formulára.
 */
return new class extends Migration
{
    /** Tabuľky s kontaktným e-mailom, ktorým stĺpec chýba. */
    private const TABLES = ['venues', 'events'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'email_verified_at')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->timestamp('email_verified_at')->nullable()->after('email');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'email_verified_at')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('email_verified_at');
            });
        }
    }
};
