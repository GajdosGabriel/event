<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Dorovnanie mien na objednávkach a miestach prihlásených používateľov.
 *
 * Registračná cesta si donedávna meno skladala sama — pozrela sa len na
 * `pending_profiles.display_name` a keď tam nič nebolo, siahla po časti e-mailu
 * pred zavináčom. Lenže meno účtu drží osobný kanál založený pri registrácii
 * (viď User::displayName()), takže bežný používateľ s vyplneným menom
 * vystupoval v zozname prihlásených ako „gajdosgabo" namiesto „Gajdoš Gabriel".
 * Kód už volá User::displayName(), toto opravuje riadky zapísané predtým.
 *
 * Prepisujú sa výhradne riadky, kde meno doslova zodpovedá lokálnej časti
 * e-mailu — teda tie, ktoré vznikli tou núdzovou vetvou. Čokoľvek, čo si človek
 * napísal do formulára, ostáva nedotknuté, aj keby to bola prezývka.
 */
return new class extends Migration
{
    public function up(): void
    {
        $names = DB::table('users')
            ->join('canal_user', 'canal_user.user_id', '=', 'users.id')
            ->join('canals', 'canals.id', '=', 'canal_user.canal_id')
            ->where('canals.identity_mode', 'personal')
            ->whereNotNull('canals.name')
            ->where('canals.name', '<>', '')
            ->orderBy('users.id')
            ->orderBy('canals.id')
            ->pluck('canals.name', 'users.id');

        foreach ($names as $userId => $name) {
            DB::table('tickets')
                ->where('user_id', $userId)
                ->whereRaw("holder_name = SUBSTRING_INDEX(holder_email, '@', 1)")
                ->update(['holder_name' => $name, 'updated_at' => now()]);

            // Miesta sa viažu na objednávku, nie na účet — používateľa nájdeme
            // cez ňu. Cudzie mená (registrácia za niekoho iného) sa netýkajú:
            // ich `attendee_email` je iný než ten, z ktorého meno vzniklo.
            DB::table('ticket_admissions')
                ->whereIn('ticket_id', DB::table('tickets')->where('user_id', $userId)->select('id'))
                ->whereNotNull('attendee_name')
                ->whereNotNull('attendee_email')
                ->whereRaw("attendee_name = SUBSTRING_INDEX(attendee_email, '@', 1)")
                ->update(['attendee_name' => $name, 'updated_at' => now()]);
        }
    }

    /**
     * Späť sa to vrátiť nedá — pôvodná hodnota bola náhrada za chýbajúce meno,
     * presne tá chyba, ktorú migrácia opravuje. Zámerne prázdne.
     */
    public function down(): void {}
};
