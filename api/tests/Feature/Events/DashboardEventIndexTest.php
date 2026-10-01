<?php

namespace Tests\Feature\Events;

use App\Enums\ModelStatus;
use App\Models\Canal; // Ensure you import the Event model
use App\Models\Event;  // Import the User model
use App\Models\User; // Import the Canal model
use PHPUnit\Framework\Attributes\Test; // Import the ModelStatus enum if needed
// For generating random strings
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
            $events->every(fn ($item) => in_array($item['canal_id'], $canalIds)),
            'Všetky výsledky musia patriť do očakávaných canal_id'
        );
    }
}
