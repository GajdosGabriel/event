<?php

namespace Tests\Feature\Events;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Models\Event; // Ensure you import the Event model
use App\Models\User;  // Import the User model
use App\Models\Canal; // Import the Canal model
use App\Enums\ModelStatus; // Import the ModelStatus enum if needed
use Illuminate\Support\Str; // For generating random strings
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestSupport\CanalSetupTest;
use Tests\TestSupport\EventSetupTest;

class DashboardEventIndexTest extends EventSetupTest
{

    #[Test]
    public function user_can_see_yours_events()
    {
        $response = $this->getJson('/api/dashboard/events');

        $response->assertStatus(200);

        // 3. Id canalov ktoré patria user
        $canalIds = $this->user->canals()->pluck('id')->all();


        // 2. Získaj dáta ako kolekciu
        $events = collect($response->json('data')); // alebo bez 'data' ak máš root-level array

        // 3. Over, že každý event patrí jednému z canalIds
        $this->assertTrue(
            $events->every(fn($item) => in_array($item['canal_id'], $canalIds)),
            'Všetky výsledky musia patriť do očakávaných canal_id'
        );
    }
}
