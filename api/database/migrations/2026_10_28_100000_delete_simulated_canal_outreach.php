<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Oslovenie kanálov ide len cez Account Emailing, simulácia vlastným
     * e-mailom zanikla. Simulované záznamy nikomu neodišli, no ich
     * `dedupe_key` by blokoval nahlásenie tej istej akcie do Accountu.
     */
    public function up(): void
    {
        DB::table('canal_outreach')->where('status', 'simulated')->delete();
    }

    public function down(): void
    {
        //
    }
};
