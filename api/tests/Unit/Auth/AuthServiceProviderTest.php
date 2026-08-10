<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Gate;
use Tests\TestSupport\UserSetupTest;

class AuthServiceProviderTest extends UserSetupTest
{
    public function test_boot_sets_up_gate_for_super_admin()
    {
        $this->actingAs($this->userSuperAdmin, 'sanctum');

        // Gate by mal teraz povoliť "some-ability" pre super-admina vďaka metóde before()
        // (za predpokladu, že máte before() metódu v Policy alebo Gate pre super admina)
        $this->assertTrue(Gate::allows('some-ability'));

        // Pre overenie, že to neovplyvní iných užívateľov
        $this->user->assignRole('canal-editor'); // Pridáme normálnu rolu pre testovanie
        $this->actingAs($this->user, 'sanctum');

        // Editor podujatia zakladá a upravuje — migrácia
        // 2026_07_29_130002_seed_canal_team_roles mu tie práva zámerne pridala.
        $this->assertTrue(Gate::allows('event.create'));
        $this->assertTrue(Gate::allows('event.update'));

        // Mazanie ostáva vlastníkovi; tu sa ukáže, že Gate::before() nepustí
        // super-admin skratku na bežného používateľa.
        $this->assertFalse(Gate::allows('event.delete'));
        $this->assertFalse(Gate::allows('canal.delete'));
    }
}
