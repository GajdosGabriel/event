<?php

namespace Tests\Unit;

use App\Support\EventDateRange;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventDateRangeLabelTest extends TestCase
{
    /** Časy v testoch sú v UTC; október je v Bratislave UTC+2. */
    private function label(string $start, ?string $end): ?string
    {
        return EventDateRange::label(
            Carbon::parse($start, 'UTC'),
            $end !== null ? Carbon::parse($end, 'UTC') : null,
        );
    }

    #[Test]
    public function single_day_shows_times_without_repeating_date(): void
    {
        $this->assertSame('15. 10. 2026 17:00 - 19:00', $this->label('2026-10-15 15:00', '2026-10-15 17:00'));
    }

    #[Test]
    public function all_day_event_shows_only_date(): void
    {
        $this->assertSame('16. 10. 2026', $this->label('2026-10-15 22:00', '2026-10-16 21:59'));
    }

    #[Test]
    public function all_day_event_ending_at_midnight_stays_one_day(): void
    {
        $this->assertSame('16. 10. 2026', $this->label('2026-10-15 22:00', '2026-10-16 22:00'));
    }

    #[Test]
    public function multi_day_all_day_event_shows_date_range(): void
    {
        $this->assertSame('16. 10. 2026 - 24. 10. 2026', $this->label('2026-10-15 22:00', '2026-10-24 21:59'));
    }

    #[Test]
    public function multi_day_event_with_times_keeps_times(): void
    {
        $this->assertSame('16. 10. 2026 18:00 - 18. 10. 2026 12:00', $this->label('2026-10-16 16:00', '2026-10-18 10:00'));
    }
}
