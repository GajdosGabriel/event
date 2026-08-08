<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Odstránenie stĺpcov, ktoré Event nepotrebuje.
 *
 * Dva dôvody:
 *
 *  1. `street` a `psc` – adresu vlastní Account. Dve kópie tej istej adresy
 *     sa po prvej úprave v inom projekte rozídu a nikto nevie, ktorá platí
 *     na faktúre. Číta sa cez AccountClient (`address.line`).
 *
 *  2. `mod_title`, `phone_numeric`, `youtube_channel`, `youtube_playlist`,
 *     `avatar` – v kóde ich nečíta ani nezapisuje nič okrem validácie
 *     vo formulári. Obrázky navyše v tomto projekte nedrží stĺpec, ale
 *     tabuľka `files` (ako pri miestach a kanáloch), takže `avatar`
 *     odporuje aj vlastnému vzoru projektu.
 *
 * Zostáva to, čo Event naozaj vlastní: názov, slug, popis, obec, kontakt
 * pre návštevníkov, stav publikovania a väzba na Account.
 */
return new class extends Migration
{
    /** @var array<int, string> */
    private const DROPPED = [
        'street', 'psc',
        'mod_title', 'phone_numeric',
        'youtube_channel', 'youtube_playlist',
        'avatar',
    ];

    public function up(): void
    {
        $existing = array_values(array_filter(
            self::DROPPED,
            fn (string $column) => Schema::hasColumn('organizations', $column),
        ));

        if ($existing === []) {
            return;
        }

        Schema::table('organizations', function (Blueprint $table) use ($existing) {
            $table->dropColumn($existing);
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('avatar', 200)->nullable()->after('person');
            $table->string('street', 191)->nullable()->after('slug');
            $table->integer('psc')->nullable()->after('street');
            $table->string('mod_title', 20)->nullable()->after('description');
            $table->string('phone_numeric', 20)->nullable()->after('phone');
            $table->string('youtube_channel', 40)->nullable()->after('phone_numeric');
            $table->string('youtube_playlist', 40)->nullable()->after('youtube_channel');
        });
    }
};
