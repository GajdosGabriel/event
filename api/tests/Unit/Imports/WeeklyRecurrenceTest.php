<?php

namespace Tests\Unit\Imports;

use App\Support\WeeklyRecurrence;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WeeklyRecurrenceTest extends TestCase
{
    /** Streda 14. 10. 2026, 12:00 miestneho času. */
    private function wednesday(): CarbonImmutable
    {
        return CarbonImmutable::parse('2026-10-14 12:00', 'Europe/Bratislava');
    }

    #[Test]
    public function it_finds_the_next_occurrence_of_a_weekly_event(): void
    {
        $next = WeeklyRecurrence::nextOccurrence('Modlitba sa koná každý pondelok o 18:00 v Katedrále sv. Martina.', $this->wednesday());

        $this->assertSame('2026-10-19 18:00', $next?->setTimezone('Europe/Bratislava')->format('Y-m-d H:i'));
    }

    #[Test]
    public function it_understands_plural_and_other_phrasings(): void
    {
        $at = fn (string $text) => WeeklyRecurrence::nextOccurrence($text, $this->wednesday())
            ?->setTimezone('Europe/Bratislava')->format('D H:i');

        $this->assertSame('Mon 18:00', $at('Prednášky sa konajú pravidelne v pondelky o 18:00 v priestoroch UPC.'));
        $this->assertSame('Thu 19:30', $at('Stretávame sa vždy vo štvrtok večer o 19.30.'));
        $this->assertSame('Sun 17:00', $at('Adorácia je každú nedeľu o 17:00.'));
        $this->assertSame('Wed 18:00', $at('Každú stredu o 18:00.'));
    }

    #[Test]
    public function the_same_day_counts_only_while_the_time_is_ahead(): void
    {
        $morning = CarbonImmutable::parse('2026-10-19 09:00', 'Europe/Bratislava');
        $evening = CarbonImmutable::parse('2026-10-19 19:00', 'Europe/Bratislava');
        $text = 'každý pondelok o 18:00';

        $this->assertSame('2026-10-19', WeeklyRecurrence::nextOccurrence($text, $morning)?->setTimezone('Europe/Bratislava')->toDateString());
        $this->assertSame('2026-10-26', WeeklyRecurrence::nextOccurrence($text, $evening)?->setTimezone('Europe/Bratislava')->toDateString());
    }

    #[Test]
    public function it_ignores_text_without_a_weekly_time(): void
    {
        $this->assertNull(WeeklyRecurrence::nextOccurrence('Sväté omše sú každú nedeľu.', $this->wednesday()));
        $this->assertNull(WeeklyRecurrence::nextOccurrence('Stretnutie bude v pondelok 19. októbra o 18:00.', $this->wednesday()));
        $this->assertNull(WeeklyRecurrence::nextOccurrence('Prihlásiť sa dá do 15. septembra 2026.', $this->wednesday()));
    }
}
