<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Popisy importovaných kanálov už nehovoria „zo zdroja vyveska.sk / tkkbs.sk /
 * ecav.sk / hlascirkvi.sk“, ale „z verejných zdrojov“.
 *
 * Mení sa len fráza „zo zdroja <web>“ v popise. Názvy kanálov sa nemenia —
 * zberné kanály sa podľa nich dohľadávajú pri ďalšom importe (slug názvu).
 * Pôvodná fráza sa nevracia, lebo nesie rôzne weby — down() preto nič nerobí.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('canals')) {
            return;
        }

        DB::table('canals')
            ->where('body', 'like', '%zo zdroja %')
            ->select(['id', 'body'])
            ->orderBy('id')
            ->chunkById(200, function ($canals): void {
                foreach ($canals as $canal) {
                    $body = preg_replace('/zo zdroja [\w.\-]+\.[a-z]{2,}/u', 'z verejných zdrojov', (string) $canal->body);

                    if ($body === null || $body === $canal->body) {
                        continue;
                    }

                    // Bez updated_at: oprava textu nemá posúvať „naposledy upravené".
                    DB::table('canals')->where('id', $canal->id)->update(['body' => $body]);
                }
            });
    }

    public function down(): void
    {
        // Pôvodné weby zdrojov sa neukladajú, späť sa nevracajú.
    }
};
