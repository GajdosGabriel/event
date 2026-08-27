<?php

namespace Tests\Feature\Console;

use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

/**
 * Hosting nemá shell a schedule:run beží vnútri HTTP requestu z webcronu.
 * Schedule::command() by na každý príkaz spustil shell a nové PHP — presne to
 * na produkcii padalo s návratovým kódom 126 (shell binárku nájde, ale nesmie
 * ju spustiť). Test drží plán pri volaniach v procese.
 */
class ScheduleTest extends TestCase
{
    public function test_ziadny_naplanovany_prikaz_nespusta_podproces(): void
    {
        $events = app(Schedule::class)->events();

        $this->assertNotEmpty($events);

        foreach ($events as $event) {
            $this->assertInstanceOf(
                CallbackEvent::class,
                $event,
                'Naplánovaný príkaz [' . $event->getSummaryForDisplay() . '] by sa spustil cez shell.'
            );
        }
    }

    public function test_kazdy_naplanovany_prikaz_ma_vlastny_zamok(): void
    {
        $mutexNames = array_map(
            fn ($event) => $event->mutexName(),
            app(Schedule::class)->events()
        );

        $this->assertSame(
            count($mutexNames),
            count(array_unique($mutexNames)),
            'Dva naplánované príkazy zdieľajú zámok, withoutOverlapping() by jeden z nich preskakoval.'
        );
    }
}
