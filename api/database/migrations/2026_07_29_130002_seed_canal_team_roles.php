<?php

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Doplní rolu `canal-checkin` a rozšíri práva `canal-editor` (pribudlo
     * zakladanie a úprava podujatí, práca s lístkami a súbormi).
     *
     * Ide o číselník, takže rovnako ako 2026_07_28_120000_seed_reference_data
     * beží ako migrácia — `db:seed` sa na produkcii nepúšťa. Na rozdiel od nej
     * sa nepreskakuje v testoch: bez týchto rolí by pozvaný člen tímu neprešiel
     * cez `permission:` middleware a testy tímu by testovali iný stav než prod.
     * Seeder je idempotentný (firstOrCreate + syncPermissions).
     */
    public function up(): void
    {
        (new RolesAndPermissionsSeeder())->run();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Číselník rolí sa nemaže — visia na ňom priradenia používateľov.
     */
    public function down(): void
    {
        //
    }
};
