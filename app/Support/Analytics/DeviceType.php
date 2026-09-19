<?php

namespace App\Support\Analytics;

/**
 * Classifies a visitor's viewport width into one of the heatmap device
 * buckets.
 *
 * Viewport width, not user agent: the heatmap's whole problem is that the
 * same relative coordinate means a different place on a different layout, and
 * what decides the layout is the CSS breakpoint the browser landed in — a
 * tablet held in landscape gets the desktop grid and belongs with desktop
 * clicks. The thresholds mirror the emulated capture widths in
 * config('screenshots.devices') so points and backdrop always agree.
 */
class DeviceType
{
    public const DESKTOP = 'desktop';

    public const TABLET = 'tablet';

    public const MOBILE = 'mobile';

    /**
     * Every configured device key, narrowest bucket first.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        return array_keys(self::ceilings());
    }

    public static function isValid(?string $device): bool
    {
        return $device !== null && in_array($device, self::all(), true);
    }

    /**
     * Each device's inclusive upper bound on viewport width, narrowest first.
     * A null ceiling is the catch-all bucket (desktop), which always sorts
     * last.
     *
     * @return array<string, int|null>
     */
    public static function ceilings(): array
    {
        $ceilings = [];

        foreach ((array) config('screenshots.devices') as $key => $device) {
            if (! is_array($device)) {
                continue;
            }

            $ceiling = $device['max_width'] ?? null;

            $ceilings[(string) $key] = is_int($ceiling) ? $ceiling : null;
        }

        uasort($ceilings, fn (?int $a, ?int $b) => ($a ?? PHP_INT_MAX) <=> ($b ?? PHP_INT_MAX));

        return $ceilings;
    }

    /**
     * The width band a device covers, as [minimum, maximum]. The maximum is
     * null for the widest bucket.
     *
     * @return array{0: int, 1: int|null}
     */
    public static function widthRange(string $device): array
    {
        $minimum = 1;

        foreach (self::ceilings() as $key => $ceiling) {
            if ($key === $device) {
                return [$minimum, $ceiling];
            }

            $minimum = $ceiling === null ? $minimum : $ceiling + 1;
        }

        return [1, null];
    }

    /**
     * A missing/zero width means the client never reported one; treat it as
     * desktop rather than inventing a fourth "unknown" segment nobody can act
     * on.
     */
    public static function fromViewportWidth(?int $width): string
    {
        if (! $width || $width <= 0) {
            return self::DESKTOP;
        }

        // Narrowest bucket first, so a 700px viewport is mobile rather than
        // "also under the tablet ceiling".
        foreach (self::ceilings() as $key => $ceiling) {
            if ($ceiling === null || $width <= $ceiling) {
                return $key;
            }
        }

        return self::DESKTOP;
    }

    public static function label(string $device): string
    {
        $label = config("screenshots.devices.{$device}.label");

        return is_string($label) ? $label : ucfirst($device);
    }
}
