<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vráti na overenie odkazy, ktoré označila za pokazené vlastná chyba sondy.
 *
 * WebsiteProbe si cieľ presmerovania preháňala cez Url::normalize(), ktorý
 * koncovú lomku zámerne orezáva. WordPress presmeruje `…/kurz` na kanonické
 * `…/kurz/`, sonda odpovedala zase adresou bez lomky, a po piatich skokoch
 * z toho bol `redirect_loop` na úplne funkčnom webe. Opravu drží
 * Url::redirectTarget(); tu sa upratáva, čo po chybe zostalo v dátach —
 * v produkcii 114 riadkov, z toho 83 z jedinej domény.
 *
 * Stav ide na `pending`, nie na `ok`: úspech si musí sonda overiť sama.
 * `pending` znamená „zatiaľ nevieme" a v UI sa zámerne nezobrazuje (viď
 * HasAttributeCheckState), takže falošná značka o pokazenom webe zmizne hneď
 * a skutočný verdikt dobehne pri najbližších behoch kontroly.
 *
 * `failures` sa nuluje, lebo tie zlyhania boli naše, nie cudzieho webu — inak
 * by prvému skutočnému výpadku chýbal odstup, po ktorom sa majiteľovi píše až
 * pri druhom zlyhaní. Z rovnakého dôvodu ide preč aj `notified_at`.
 *
 * Ostatné dôvody zlyhania sa nechávajú tak: `not_found`, `timeout`,
 * `server_error` ani `http_error` s touto chybou nesúvisia a po oprave sondy
 * naozaj zlyhávajú aj naďalej.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attribute_checks')) {
            return;
        }

        DB::table('attribute_checks')
            ->where('status', 'failed')
            ->where('reason', 'redirect_loop')
            ->update([
                'status' => 'pending',
                'reason' => null,
                'http_status' => null,
                'failures' => 0,
                'notified_at' => null,
                'next_check_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * Naspäť sa to vrátiť nedá a ani nemá — pôvodný stav bol chybný verdikt.
     * Zámerne prázdne.
     */
    public function down(): void {}
};
