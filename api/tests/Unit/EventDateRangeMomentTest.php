<?php

namespace Tests\Unit;

use App\Support\EventDateRange;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class EventDateRangeMomentTest extends TestCase
{
    #[Test]
    public function moment_converts_utc_to_bratislava_in_summer_and_winter(): void
    {
        $this->assertSame('01. 10. 2026 08:59', EventDateRange::moment(Carbon::parse('2026-10-01 06:59:00', 'UTC')));
        $this->assertSame('01. 12. 2026 08:59', EventDateRange::moment(Carbon::parse('2026-12-01 07:59:00', 'UTC')));
        $this->assertNull(EventDateRange::moment(null));
    }
}
