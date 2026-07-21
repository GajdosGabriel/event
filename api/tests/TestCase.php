<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Routing\Middleware\ThrottleRequests;

abstract class TestCase extends BaseTestCase
{
    /**
     * Rate limity sú vypnuté pre celú test suite — počítadlá sú zdieľané cez
     * cache a inak by testy padali podľa poradia spustenia. Testy, ktoré samotné
     * limitovanie overujú, si ho zapnú cez `$this->withMiddleware(ThrottleRequests::class)`.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
    }
}
