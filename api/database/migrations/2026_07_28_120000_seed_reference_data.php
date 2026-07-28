<?php

use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\TagSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Číselníky, bez ktorých aplikácia nefunguje: role s oprávneniami a štítky.
     *
     * Sú v migrácii, a nie v seedri, aby ich čerstvá databáza dostala samotným
     * `migrate` — `db:seed` sa na produkcii nepúšťa, DatabaseSeeder okrem
     * číselníkov generuje aj demo dáta. Prázdna tabuľka `tags` zhodila
     * plánovaný app:events-ai-tag na každom podujatí.
     *
     * Migrácia volá priamo seedre, aby zoznam rolí a štítkov ostal na jednom
     * mieste; oba sú idempotentné (firstOrCreate / updateOrCreate). Neskoršie
     * doplnenie štítka do TagSeeder-a sa sem už nepremietne — to sa naďalej
     * nasadzuje spustením `db:seed --class=TagSeeder --force`.
     */
    public function up(): void
    {
        // V testoch sa číselník nezanáša: testy si štítky zakladajú samy a
        // rovnaké slugy by padli na unikátnom indexe. Rovnako by 70 štítkov
        // menilo východiskový stav testom, ktoré overujú prácu s číselníkom.
        if (app()->runningUnitTests()) {
            return;
        }

        (new RolesAndPermissionsSeeder())->run();
        (new TagSeeder())->run();
    }

    /**
     * Číselníky sa nemažú: štítky visia na priradeniach v event_tag a role na
     * používateľoch. Rollback by tie väzby vzal so sebou.
     */
    public function down(): void
    {
        //
    }
};
