<?php

namespace Tests\Unit\Support;

use App\Models\Event;
use App\Support\PublicEventFilters;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PublicEventFiltersTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_today_uses_the_slovak_calendar_day_even_before_utc_midnight(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-02 22:30:00', 'UTC'));
        $filters = PublicEventFilters::fromRequest(Request::create('/', 'GET', ['range' => 'today']));
        $this->assertSame('2026-10-02 22:00:00', $filters['date_from']);
        $this->assertSame('2026-10-03 21:59:59', $filters['date_to']);
    }

    public function test_weekend_boundaries_follow_the_daylight_saving_change(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-25 10:00:00', 'UTC'));
        $filters = PublicEventFilters::fromRequest(Request::create('/', 'GET', ['range' => 'weekend']));
        $this->assertSame('2026-10-22 22:00:00', $filters['date_from']);
        $this->assertSame('2026-10-25 22:59:59', $filters['date_to']);
    }

    public function test_timestamp_filters_compare_instants_and_date_only_filters_keep_calendar_semantics(): void
    {
        $query = Event::query()->byDateRange('2026-10-02 22:00:00', '2026-10-03 21:59:59');
        $this->assertStringNotContainsString('date(', strtolower($query->toSql()));
        $this->assertContains('2026-10-02 22:00:00', $query->getBindings());

        $dateQuery = Event::query()->byDateRange('2026-10-03', '2026-10-03');
        $this->assertStringContainsString('date(', strtolower($dateQuery->toSql()));
    }
}
