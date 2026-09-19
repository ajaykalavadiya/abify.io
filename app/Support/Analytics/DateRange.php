<?php

namespace App\Support\Analytics;

use Illuminate\Support\Carbon;

/**
 * A preset reporting window, plus the equally long window immediately before
 * it — every headline number on the overview is shown against the same span
 * of the recent past, so "up 12%" always means the same thing.
 */
class DateRange
{
    /** @var array<string, array{label: string, days: int}> */
    public const PRESETS = [
        '7d' => ['label' => 'Last 7 days', 'days' => 7],
        '30d' => ['label' => 'Last 30 days', 'days' => 30],
        '90d' => ['label' => 'Last 90 days', 'days' => 90],
    ];

    public const DEFAULT = '30d';

    /** Key used when the window is an explicit pair of dates. */
    public const CUSTOM = 'custom';

    /**
     * Longest custom window allowed. Heatmap and analytics queries scan raw
     * event rows, so an unbounded "since the beginning of time" range is a
     * table scan a customer can trigger from a date picker.
     */
    public const MAX_CUSTOM_DAYS = 365;

    public function __construct(
        public string $key,
        public int $days,
        public Carbon $start,
        public Carbon $end,
        public Carbon $previousStart,
        public Carbon $previousEnd,
    ) {}

    public static function fromKey(?string $key): self
    {
        $key = isset(self::PRESETS[$key]) ? $key : self::DEFAULT;
        $days = self::PRESETS[$key]['days'];

        // Whole days, inclusive of today: a range that ended at "now" would
        // compare a part-finished day against a complete one and read as a
        // drop every morning.
        $end = Carbon::now()->endOfDay();
        $start = Carbon::now()->subDays($days - 1)->startOfDay();

        return new self(
            key: $key,
            days: $days,
            start: $start,
            end: $end,
            previousStart: $start->copy()->subDays($days),
            // endOfDay, not start-minus-one-second: 'start' carries no
            // microseconds but 'end' does, and the mismatch would quietly
            // clip the last second of the comparison window.
            previousEnd: $start->copy()->subDay()->endOfDay(),
        );
    }

    /**
     * An explicit window, as picked from a calendar.
     *
     * Falls back to the preset behaviour whenever the pair does not describe
     * a usable range — a half-filled picker should show the default window,
     * not an error.
     */
    public static function custom(?string $from, ?string $to): self
    {
        if (! $from || ! $to) {
            return self::fromKey(null);
        }

        try {
            $start = Carbon::parse($from)->startOfDay();
            $end = Carbon::parse($to)->endOfDay();
        } catch (\Throwable) {
            return self::fromKey(null);
        }

        // Reversed pickers are common enough to just handle.
        if ($start->greaterThan($end)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        // Nothing is recorded ahead of now, and allowing it would stretch the
        // comparison window against a stretch of guaranteed zeroes.
        if ($end->greaterThan(Carbon::now()->endOfDay())) {
            $end = Carbon::now()->endOfDay();
        }

        $days = (int) $start->diffInDays($end) + 1;

        if ($days > self::MAX_CUSTOM_DAYS) {
            $start = $end->copy()->subDays(self::MAX_CUSTOM_DAYS - 1)->startOfDay();
            $days = self::MAX_CUSTOM_DAYS;
        }

        return new self(
            key: self::CUSTOM,
            days: $days,
            start: $start,
            end: $end,
            previousStart: $start->copy()->subDays($days),
            previousEnd: $start->copy()->subDay()->endOfDay(),
        );
    }

    /**
     * Resolve a window from request input, preferring an explicit pair of
     * dates over a preset key.
     */
    public static function fromRequest(?string $key, ?string $from = null, ?string $to = null): self
    {
        return $key === self::CUSTOM
            ? self::custom($from, $to)
            : self::fromKey($key);
    }

    public function isCustom(): bool
    {
        return $this->key === self::CUSTOM;
    }

    public function label(): string
    {
        if ($this->isCustom()) {
            return $this->start->format('j M Y').' – '.$this->end->format('j M Y');
        }

        return self::PRESETS[$this->key]['label'];
    }

    /**
     * Every day in the range, oldest first, as Y-m-d — used to fill gaps so a
     * day with no traffic plots as zero rather than vanishing and pulling the
     * line across it.
     *
     * @return array<int, string>
     */
    public function eachDay(): array
    {
        $days = [];
        $cursor = $this->start->copy();

        while ($cursor->lte($this->end)) {
            $days[] = $cursor->toDateString();
            $cursor->addDay();
        }

        return $days;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return collect(self::PRESETS)
            ->map(fn (array $preset, string $key) => ['value' => $key, 'label' => $preset['label']])
            ->values()
            ->all();
    }
}
