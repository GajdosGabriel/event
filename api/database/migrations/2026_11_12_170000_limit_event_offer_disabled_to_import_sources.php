<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Technický e-mail o zverejnenej akcii (CanalEventOffer) sa vypína len trom
 * zdrojom importu so stovkami akcií: ECAV, Vývesku a TK KBS. Predošlá
 * migrácia ho vypla každému kanálu s adresou na ecav.sk — aj cirkevným
 * zborom a výborom, ktoré majú akcií málo. Tým ho vraciame; samotnej ECAV
 * (kontakt media@ecav.sk) ostáva vypnutý.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('canals')
            ->where(fn ($query) => $query->where('email', 'like', '%@ecav.sk')->orWhere('email', 'like', '%.ecav.sk'))
            ->update(['event_offer_disabled' => false]);

        DB::table('canals')
            ->where('email', 'media@ecav.sk')
            ->update(['event_offer_disabled' => true]);
    }

    public function down(): void
    {
        //
    }
};
