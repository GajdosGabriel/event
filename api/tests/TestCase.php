<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Rate limity sú vypnuté pre celú test suite — počítadlá sú zdieľané cez
        // cache a inak by testy padali podľa poradia spustenia. Testy, ktoré samotné
        // limitovanie overujú, si ho zapnú cez `$this->withMiddleware(ThrottleRequests::class)`.
        $this->withoutMiddleware(ThrottleRequests::class);

        // Spatie si tabuľku oprávnení cachuje. CACHE_STORE=array prežíva celý
        // PHPUnit proces, kým RefreshDatabase medzitým databázu resetuje — cache
        // potom drží ID, ktoré už neexistujú, a givePermissionTo() priradí
        // oprávnenie, ktoré sa pri kontrole neuplatní. Prejaví sa to ako 403,
        // ktoré závisí od poradia testov.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
