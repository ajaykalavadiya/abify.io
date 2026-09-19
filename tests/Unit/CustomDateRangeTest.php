<?php

namespace Tests\Unit;

use App\Support\Analytics\DateRange;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class CustomDateRangeTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_builds_an_inclusive_window_from_two_dates(): void
    {
        Carbon::setTestNow('2026-09-14 12:00:00');

        $range = DateRange::custom('2026-09-01', '2026-09-07');

        $this->assertSame(DateRange::CUSTOM, $range->key);
        $this->assertSame(7, $range->days, 'Both endpoints count as days.');
        $this->assertSame('2026-09-01 00:00:00', $range->start->toDateTimeString());
        $this->assertSame('2026-09-07 23:59:59', $range->end->toDateTimeString());
    }

    public function test_the_comparison_window_is_the_same_length_immediately_before(): void
    {
        Carbon::setTestNow('2026-09-14 12:00:00');

        $range = DateRange::custom('2026-09-08', '2026-09-14');

        $this->assertSame('2026-09-01 00:00:00', $range->previousStart->toDateTimeString());
        $this->assertSame('2026-09-07 23:59:59', $range->previousEnd->toDateTimeString());
    }

    public function test_a_reversed_pair_is_swapped_rather_than_rejected(): void
    {
        Carbon::setTestNow('2026-09-14 12:00:00');

        $range = DateRange::custom('2026-09-07', '2026-09-01');

        $this->assertSame('2026-09-01 00:00:00', $range->start->toDateTimeString());
        $this->assertSame('2026-09-07 23:59:59', $range->end->toDateTimeString());
    }

    /**
     * Nothing is recorded ahead of now, so a future end date would only pad
     * the window with guaranteed zeroes and drag every average down.
     */
    public function test_a_future_end_date_is_clamped_to_today(): void
    {
        Carbon::setTestNow('2026-09-14 12:00:00');

        $range = DateRange::custom('2026-09-10', '2027-01-01');

        $this->assertSame('2026-09-14 23:59:59', $range->end->toDateTimeString());
    }

    /**
     * These windows scan raw event rows, so an unbounded span is a table scan
     * a customer can trigger from a date picker.
     */
    public function test_an_over_long_window_is_capped(): void
    {
        Carbon::setTestNow('2026-09-14 12:00:00');

        $range = DateRange::custom('2000-01-01', '2026-09-14');

        $this->assertSame(DateRange::MAX_CUSTOM_DAYS, $range->days);
        $this->assertSame('2026-09-14 23:59:59', $range->end->toDateTimeString());
    }

    public function test_an_incomplete_or_unparseable_pair_falls_back_to_the_default(): void
    {
        Carbon::setTestNow('2026-09-14 12:00:00');

        foreach ([[null, null], ['2026-09-01', null], ['nonsense', 'rubbish']] as [$from, $to]) {
            $range = DateRange::custom($from, $to);

            $this->assertSame(DateRange::DEFAULT, $range->key);
        }
    }

    public function test_from_request_prefers_presets_unless_custom_is_asked_for(): void
    {
        Carbon::setTestNow('2026-09-14 12:00:00');

        $this->assertSame('7d', DateRange::fromRequest('7d', '2026-01-01', '2026-01-05')->key);
        $this->assertSame(DateRange::CUSTOM, DateRange::fromRequest('custom', '2026-09-01', '2026-09-07')->key);
        $this->assertSame(DateRange::DEFAULT, DateRange::fromRequest(null)->key);
    }

    public function test_a_custom_window_is_labelled_by_its_dates(): void
    {
        Carbon::setTestNow('2026-09-14 12:00:00');

        $this->assertSame(
            '1 Sep 2026 – 7 Sep 2026',
            DateRange::custom('2026-09-01', '2026-09-07')->label()
        );
    }
}
