<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Odloží návrhy štítkov, ktoré medzitým do číselníka pribudli.
 *
 * Dva dôvody, prečo tam ostávali viseť ako nevybavené:
 *
 *  • Doplnenie štítka do TagSeeder-a sa do `tag_suggestions` nepremieta —
 *    seeder o tej tabuľke nevie. Sedem výrazov z druhej vlny (duchovné
 *    cvičenia, spoločenstvo, chvály, konferencia, adorácia, psychológia,
 *    evanjelizácia) by tak v novom admin zozname prekážalo navždy.
 *
 *  • EventTagger porovnával návrh len so slugom štítka, nie s jeho názvom.
 *    Štítok `modlitba` sa volá „Modlitbové stretnutie", takže návrh na už
 *    existujúci štítok pristál medzi chýbajúcimi štrnásťkrát. Samotnú chybu
 *    rieši EventTagger::knownSlugs(); tu sa upratáva, čo po nej ostalo.
 *
 * Zhoda sa počíta z databázy, nie zo zoznamu natvrdo: migrácia a
 * `db:seed --class=TagSeeder --force` sú dva samostatné kroky nasadenia a
 * môžu prísť v ľubovoľnom poradí. Čo v číselníku ešte nie je, ostane
 * nevybavené — a to je správne.
 *
 * `pout` (81×) sa zámerne nedotýka: nie je to ani nový štítok, ani presná
 * zhoda s názvom — je to česká podoba už existujúcej „Púte" (`put`).
 * Rozhodnutie, čo s ňou, patrí človeku do admin zoznamu.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tag_suggestions') || ! Schema::hasTable('tags')) {
            return;
        }

        $known = [];

        foreach (DB::table('tags')->get(['slug', 'name']) as $tag) {
            $known[(string) $tag->slug] = true;

            $fromName = Str::slug((string) $tag->name);

            if ($fromName !== '') {
                $known[$fromName] = true;
            }
        }

        if ($known === []) {
            return;
        }

        DB::table('tag_suggestions')
            ->whereNull('resolution')
            ->whereIn('slug', array_keys($known))
            ->update([
                'resolution' => 'promoted',
                'updated_at' => now(),
            ]);
    }

    /**
     * Späť sa to vrátiť nedá bez toho, aby sa zmazalo aj to, čo medzitým
     * vybavil človek. Zámerne prázdne.
     */
    public function down(): void {}
};
