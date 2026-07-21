<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Tieto endpointy boli verejné, neautentizované a mali vedľajšie účinky
 * (volanie platenej OpenAI API, zápis do DB, čistenie cache, queue:work).
 * Test drží stráž, aby sa omylom nevrátili.
 */
class RemovedDebugRoutesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_openai_debug_route_no_longer_exists(): void
    {
        $this->get('/openAI')->assertNotFound();
    }

    public function test_test_resource_no_longer_exists(): void
    {
        $this->getJson('/api/test')->assertNotFound();
    }

    public function test_artisan_run_requires_the_cron_token(): void
    {
        config(['app.cron_secret' => 'tajny-token']);

        $this->getJson('/api/artisan/run')->assertForbidden();
        $this->getJson('/api/artisan/run?token=zle')->assertForbidden();
    }

    public function test_artisan_run_works_with_a_valid_token(): void
    {
        config(['app.cron_secret' => 'tajny-token']);

        $this->getJson('/api/artisan/run?token=tajny-token')
            ->assertOk()
            ->assertJsonPath('status', 'ok');
    }
}
