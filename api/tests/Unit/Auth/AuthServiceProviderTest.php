<?php

namespace Tests\Unit;

use App\Providers\AuthServiceProvider;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;
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

        $this->assertFalse(Gate::allows('event.delete')); // Predpokladáme, že normálny užívateľ nemá túto schopnosť
        $this->assertFalse(Gate::allows('event.create')); // Predpokladáme, že normálny užívateľ nemá túto schopnosť
        $this->assertFalse(Gate::allows('event.update')); // Predpokladáme, že normálny užívateľ nemá túto schopnosť
    }
}
