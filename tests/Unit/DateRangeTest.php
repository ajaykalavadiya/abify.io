<?php

namespace Tests\Unit;

use App\Support\Analytics\DateRange;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DateRangeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-03-15 14:30:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_a_range_covers_whole_days_and_includes_today()
    {
        $range = DateRange::fromKey('7d');

        // Whole days, or every morning would compare a part-finished day
        // against a complete one and read as a drop.
        $this->assertSame('2026-03-09 00:00:00', $range->start->toDateTimeString());
        $this->assertSame('2026-03-15 23:59:59', $range->end->toDateTimeString());
        $this->assertCount(7, $range->eachDay());
    }

    public function test_the_previous_period_is_the_same_length_and_ends_where_this_one_starts()
    {
        $range = DateRange::fromKey('30d');

        $this->assertSame('2026-01-15 00:00:00', $range->previousStart->toDateTimeString());
        $this->assertSame('2026-02-13 23:59:59', $range->previousEnd->toDateTimeString());
        $this->assertSame(
            $range->start->diffInSeconds($range->end),
            $range->previousStart->diffInSeconds($range->previousEnd),
        );
    }

    public function test_the_two_periods_do_not_overlap()
    {
        $range = DateRange::fromKey('90d');

        $this->assertTrue($range->previousEnd->lt($range->start));
    }

    public function test_an_unknown_or_missing_key_falls_back_to_the_default()
    {
        $this->assertSame(DateRange::DEFAULT, DateRange::fromKey(null)->key);
        $this->assertSame(DateRange::DEFAULT, DateRange::fromKey('all-time')->key);
        // Guards against a query string picking an arbitrary window length.
        $this->assertSame(30, DateRange::fromKey('nonsense')->days);
    }

    public function test_each_day_is_gap_free_and_ordered_oldest_first()
    {
        $days = DateRange::fromKey('7d')->eachDay();

        $this->assertSame('2026-03-09', $days[0]);
        $this->assertSame('2026-03-15', end($days));
        $this->assertSame($days, array_unique($days));
    }
}
