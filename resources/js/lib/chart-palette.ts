// The shared data-visualisation palette.
//
// Two selected sets of the same eight hues — one stepped for the light card
// surface (#ffffff), one for the dark card surface (#0f172b) — validated as an
// ordered set: worst adjacent CVD ΔE 9.1 light / 8.4 dark, worst adjacent
// normal-vision ΔE 19.6 light / 19.3 dark. The order is the safety mechanism,
// not decoration: re-ordering these breaks the adjacent-pair guarantee, so add
// series by taking the next slot rather than picking a hue that looks nice.
//
// Three light-mode slots (aqua, yellow, magenta) sit below 3:1 against white,
// so anything painted in them carries a visible label or a table row — never
// colour alone.

export const CHART_SERIES_LIGHT = [
    '#2a78d6', // 1 blue
    '#eb6834', // 2 orange
    '#1baf7a', // 3 aqua
    '#eda100', // 4 yellow
    '#e87ba4', // 5 magenta
    '#008300', // 6 green
    '#4a3aa7', // 7 violet
    '#e34948', // 8 red
] as const;

export const CHART_SERIES_DARK = [
    '#3987e5',
    '#d95926',
    '#199e70',
    '#c98500',
    '#d55181',
    '#008300',
    '#9085e9',
    '#e66767',
] as const;

/**
 * Control keeps orange across the product (see the experiment analytics page),
 * so a variation never inherits the baseline's colour by sitting in slot 2.
 */
export const CONTROL_COLOR = { light: '#eb6834', dark: '#d95926' };

/**
 * Single-hue blue ramp, light → dark, for magnitude: the activity grid and the
 * mono heatmap. Sequential encoding is one hue by rule — a rainbow makes the
 * middle of the scale look like a category of its own.
 */
export const SEQUENTIAL_BLUE = [
    '#cde2fb',
    '#b7d3f6',
    '#9ec5f4',
    '#86b6ef',
    '#6da7ec',
    '#5598e7',
    '#3987e5',
    '#2a78d6',
    '#256abf',
    '#1c5cab',
    '#184f95',
    '#104281',
] as const;

/** Fixed status colours — never reused as a series hue. */
export const STATUS_COLORS = {
    good: '#0ca30c',
    warning: '#fab219',
    serious: '#ec835a',
    critical: '#d03b3b',
} as const;

export type Appearance = 'light' | 'dark';

export function seriesColor(index: number, appearance: Appearance): string {
    const palette =
        appearance === 'dark' ? CHART_SERIES_DARK : CHART_SERIES_LIGHT;

    // Past eight slots a generated hue is indistinguishable from an existing
    // one under CVD, so callers must fold the tail into "Other" instead. The
    // clamp keeps a mistake visible (repeated colour) rather than silently
    // inventing one.
    return palette[Math.min(index, palette.length - 1)];
}

/**
 * A step from the sequential ramp for a 0–1 magnitude.
 */
export function sequentialColor(intensity: number): string {
    const clamped = Math.min(1, Math.max(0, intensity));
    const index = Math.round(clamped * (SEQUENTIAL_BLUE.length - 1));

    return SEQUENTIAL_BLUE[index];
}

/**
 * Compact figures for stat tiles and axis ticks: 1,284 / 12.9K / 4.2M.
 */
export function compactNumber(value: number): string {
    if (Math.abs(value) >= 1_000_000) {
        return `${(value / 1_000_000).toFixed(value % 1_000_000 === 0 ? 0 : 1)}M`;
    }

    if (Math.abs(value) >= 10_000) {
        return `${(value / 1_000).toFixed(value % 1_000 === 0 ? 0 : 1)}K`;
    }

    return value.toLocaleString();
}

export function formatPercent(value: number, digits = 1): string {
    return `${value.toFixed(digits)}%`;
}
