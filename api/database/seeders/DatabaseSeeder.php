<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Len číselníky a lokálny testovací admin — vymyslené používateľov, kanály
     * a podujatia zo začiatkov projektu nahradili reálne dáta z importu.
     */
    public function run(): void
    {
        // Číselníky zanáša migrácia 2026_07_28_120000_seed_reference_data;
        // tu ostávajú kvôli databázam vytvoreným ešte pred ňou. Oba seedre sú
        // idempotentné, takže druhé spustenie nič nepokazí.
        $this->call(RolesAndPermissionsSeeder::class);
        $this->call(TagSeeder::class);
        $this->call(LocalDevAdminSeeder::class);
    }
}
