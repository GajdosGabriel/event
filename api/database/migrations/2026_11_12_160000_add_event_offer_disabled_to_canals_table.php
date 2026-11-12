<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Super-admin vypína kanálu technický e-mail o zverejnenej akcii
 * (CanalEventOffer) — zdroje importu s množstvom akcií. Vidno to v úprave
 * kanála v admine.
 *
 * Kanály s adresou na doménach zdrojov (vyveska.sk, tkkbs.sk aj
 * subdomény), ktoré vylučuje `canals.event_offer_excluded_domains`, dostanú
 * príznak hneď, aby to bolo v administrácii vidieť.
 */
return new class extends Migration
{
    private const DOMAINS = ['vyveska.sk', 'tkkbs.sk'];

    public function up(): void
    {
        Schema::table('canals', function (Blueprint $table) {
            $table->boolean('event_offer_disabled')->default(false);
        });

        DB::table('canals')
            ->where(function ($query) {
                foreach (self::DOMAINS as $domain) {
                    $query->orWhere('email', 'like', '%@'.$domain)
                        ->orWhere('email', 'like', '%.'.$domain);
                }
            })
            ->update(['event_offer_disabled' => true]);
    }

    public function down(): void
    {
        Schema::table('canals', function (Blueprint $table) {
            $table->dropColumn('event_offer_disabled');
        });
    }
};
