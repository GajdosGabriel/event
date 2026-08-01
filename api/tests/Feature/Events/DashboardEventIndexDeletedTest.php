<?php

namespace Tests\Feature\Events;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

class DashboardEventIndexDeletedTest extends EventSetupTest
{
    #[Test]
    public function deleted_events_are_hidden_from_the_default_listing(): void
    {
        $this->futureEvent->delete();

        $response = $this->getJson('/api/dashboard/events');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertNotContains($this->futureEvent->id, $ids);
        $this->assertContains($this->pastEvent->id, $ids);
    }

    #[Test]
    public function deleted_events_are_listed_when_the_filter_asks_for_them(): void
    {
        $this->futureEvent->delete();

        $response = $this->getJson('/api/dashboard/events?deleted=1');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($this->futureEvent->id, $ids);
        $this->assertNotContains($this->pastEvent->id, $ids);
    }
}
