import type { ReactNode } from 'react';

export type TooltipRow = {
    key: string;
    label: string;
    value: string;
    color?: string;
};

/**
 * The hover readout shared by every chart on the dashboard.
 *
 * Positioned as a fraction of the container rather than in pixels, so it
 * tracks correctly inside an SVG that scales with its viewBox. It flips to the
 * left of the pointer near the right edge so it never leaves the card.
 */
export function ChartTooltip({
    title,
    rows,
    x,
    y,
    children,
}: {
    title: string;
    rows: TooltipRow[];
    /** Horizontal anchor, 0–1 across the container. */
    x: number;
    /** Vertical anchor, 0–1 down the container. */
    y?: number;
    children?: ReactNode;
}) {
    const flip = x > 0.62;

    return (
        <div
            className="border-border bg-popover pointer-events-none absolute z-10 min-w-40 -translate-y-1/2 border p-2.5 shadow-md"
            style={{
                left: `${x * 100}%`,
                top: `${(y ?? 0.5) * 100}%`,
                transform: `translate(${flip ? 'calc(-100% - 12px)' : '12px'}, -50%)`,
            }}
            role="tooltip"
        >
            <p className="text-muted-foreground mb-1.5 text-[11px] font-medium">
                {title}
            </p>

            <dl className="space-y-1">
                {rows.map((row) => (
                    <div
                        key={row.key}
                        className="flex items-baseline justify-between gap-4"
                    >
                        <dt className="text-muted-foreground flex items-center gap-1.5 text-[11px]">
                            {row.color && (
                                // A short stroke, not a filled box: at tooltip
                                // density a swatch is data-weight ink doing a
                                // label's job.
                                <span
                                    className="inline-block h-0.5 w-3 shrink-0"
                                    style={{ backgroundColor: row.color }}
                                />
                            )}
                            {row.label}
                        </dt>
                        {/* Value leads: the reader already knows the series and
                            came here for the number. */}
                        <dd className="text-foreground text-xs font-semibold tabular-nums">
                            {row.value}
                        </dd>
                    </div>
                ))}
            </dl>

            {children}
        </div>
    );
}
