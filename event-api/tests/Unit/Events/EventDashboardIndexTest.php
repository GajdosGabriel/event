<?php

namespace Tests\Unit\Events;

use Illuminate\Foundation\Testing\DatabaseTransactions; // <-- Používame DatabaseTransactions pre testy, ktoré potrebujú transakcie
use Tests\TestSupport\EventSetupTest;

class EventDashboardIndexTest extends EventSetupTest
{

    public function test_dashboard_user_can_index_events()
    {

        // 2. Získajte výsledky
        $results = $this->eventRepository->dashboardIndexQuery()->get();

        // dump($this->user->canals()->get()->pluck('id'));

        // dump($this->eventRepository->all()->count());

        // dump($results->toArray());

        // 3. Id canalov ktoré patria user
        $canalIds = $this->user->canals()->pluck('id')->all();


        // 3. Overte výsledky, každý záznam musí mat výsledok s id aktivných kanálov
        $this->assertTrue(
            $results->every(fn($item) => in_array($item['canal_id'], $canalIds)),
            'Všetky výsledky musia patriť do očakávaných canal_id'
        );



        // $this->assertFalse(
        //     $results->contains('id', $pastEvent->id),
        //     'Neaktívny canal by sa nemal objaviť'
        // );

        //      $this->assertEquals(1, $results->total());
        // $this->assertCount(1, $results->items());
        // $this->assertEquals($activeCanal->id, $results->items()[0]->id);
    }

    public function test_events_orderBy_id()
    {
        // 2. Získajte výsledky
        $results = $this->eventRepository->dashboardIndexQuery()->get();

        // Zober prvé dva
        $first = $results[0];
        $second = $results[1];

        // Over, že prvý je novší než druhý
        // Over, že prvý má vyššie ID než druhý
        $this->assertTrue(
            $first->id > $second->id,
            'Prvý event by mal mať vyššie ID ako druhý.'
        );
    }
}
