<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Dorovnanie pivotu: kde `is_owner` hovorí „vlastník", musí to hovoriť aj rola.
 *
 * App\Enums\CanalRole drží obe hodnoty zámerne v súlade (owner <=> is_owner),
 * ale ImportedCanalManager::ensureSystemOwnership() rolu nezapisoval vôbec, tak
 * riadok ostal na DB defaulte `editor`. Editor nemá `canal.update` ani
 * `canal.team`, takže vlastník každého importovaného kanála ho v dashboarde
 * nevedel upraviť ani spravovať jeho tím — hoci `is_owner` bolo `1`.
 *
 * Opačný smer (role='owner' pri is_owner=0) sa nedorovnáva: taký riadok
 * nevzniká a slepé nastavenie `is_owner` by z člena spravilo vlastníka.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('canal_user')
            ->where('is_owner', true)
            ->where('role', '<>', 'owner')
            ->update(['role' => 'owner', 'updated_at' => now()]);
    }

    /**
     * Späť sa to vrátiť nedá — pôvodná rola bola nesprávna a ktorý riadok bol
     * ktorý, sa už nezistí. Zámerne prázdne.
     */
    public function down(): void {}
};
