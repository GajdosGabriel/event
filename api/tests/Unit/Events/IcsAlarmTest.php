<?php

namespace Tests\Unit\Events;

use App\Models\Event;
use App\Services\Calendar\IcsGenerator;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Pripomienka vypálená priamo do `.ics`.
 *
 * Zmysel: „Pridať do kalendára" doteraz zapísalo termín, ale nikto sa
 * návštevníkovi neozval. VALARM to rieši bez e-mailu a bez akéhokoľvek údaja
 * o ňom — pripomenie mu jeho vlastný kalendár.
 */
class IcsAlarmTest extends TestCase
{
    /**
     * Podujatie bez databázy. `id` je povinné — generátor z neho skladá verejnú
     * adresu aj stabilné UID, takže bez neho by test padal na PublicUrl a nie
     * na tom, čo overuje.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function makeEvent(array $attributes): Event
    {
        $event = new Event($attributes);
        $event->id = 1;

        return $event;
    }

    private function generate(Event $event): string
    {
        return (string) app(IcsGenerator::class)->forEvent($event);
    }

    #[Test]
    public function timed_event_gets_a_two_hour_alarm(): void
    {
        $event = $this->makeEvent([
            'name' => 'Koncert',
            'start_at' => now()->addDays(3)->setTime(19, 0),
            'end_at' => now()->addDays(3)->setTime(21, 0),
        ]);

        $ics = $this->generate($event);

        $this->assertStringContainsString('BEGIN:VALARM', $ics);
        $this->assertStringContainsString('ACTION:DISPLAY', $ics);
        $this->assertStringContainsString('TRIGGER;RELATED=START:-PT2H', $ics);
        $this->assertStringContainsString('END:VALARM', $ics);
    }

    #[Test]
    public function alarm_sits_inside_the_event_block(): void
    {
        $event = $this->makeEvent([
            'name' => 'Koncert',
            'start_at' => now()->addDays(3)->setTime(19, 0),
        ]);

        $ics = $this->generate($event);

        // RFC 5545: VALARM je súčasťou VEVENT. Mimo neho by ho kalendár ignoroval
        // v lepšom prípade a odmietol súbor v horšom.
        $this->assertLessThan(
            strpos($ics, 'END:VEVENT'),
            strpos($ics, 'BEGIN:VALARM'),
            'VALARM musí byť pred END:VEVENT.',
        );
    }

    #[Test]
    public function all_day_event_is_reminded_the_evening_before(): void
    {
        // Celodenné podujatie je 00:00–23:59 **miestneho** času, ale aplikácia
        // beží v UTC a dátumový cast zónu pri zápise zahodí — v databáze teda
        // 5. 9. 2026 leží ako 4. 9. 22:00 → 5. 9. 21:59 UTC. `->utc()` tu robí
        // presne to, čo by spravil zápis do DB; bez neho by test skúšal stav,
        // ktorý v produkcii nikdy nenastane.
        $event = $this->makeEvent([
            'name' => 'Festival',
            'start_at' => Carbon::parse('2026-09-05 00:00', 'Europe/Bratislava')->utc(),
            'end_at' => Carbon::parse('2026-09-05 23:59', 'Europe/Bratislava')->utc(),
        ]);

        // Dve hodiny pred polnocou by zazvonili o 22:00 predošlého dňa; šesť
        // z toho spraví 18:00, teda čas, keď sa plánuje ďalší deň.
        $this->assertStringContainsString('TRIGGER;RELATED=START:-PT6H', $this->generate($event));
    }

    #[Test]
    public function organiser_window_wins_over_the_default(): void
    {
        $event = $this->makeEvent([
            'name' => 'Konferencia',
            'start_at' => now()->addDays(10)->setTime(9, 0),
            'end_at' => now()->addDays(10)->setTime(17, 0),
            'reminder_hours_before' => 48,
        ]);

        // Rovnaké pravidlo ako pri e-mailovej pripomienke — jedno nastavenie,
        // aby sa človeku neozvalo dvakrát v úplne iný čas.
        $this->assertStringContainsString('TRIGGER;RELATED=START:-PT48H', $this->generate($event));
    }

    #[Test]
    public function event_without_a_start_has_no_ics_at_all(): void
    {
        $ics = app(IcsGenerator::class)->forEvent(new Event(['name' => 'Bez termínu']));

        $this->assertNull($ics);
    }
}
