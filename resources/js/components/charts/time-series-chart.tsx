import { useMemo, useState } from 'react';
import { ChartTooltip } from '@/components/charts/chart-tooltip';
import { compactNumber } from '@/lib/chart-palette';

export type TimeSeries = {
    key: string;
    label: string;
    color: string;
    values: number[];
};

const WIDTH = 760;
const HEIGHT = 260;
const MARGIN = { top: 16, right: 56, bottom: 28, left: 44 };
const PLOT_WIDTH = WIDTH - MARGIN.left - MARGIN.right;
const PLOT_HEIGHT = HEIGHT - MARGIN.top - MARGIN.bottom;

/** Round the axis maximum up to a clean number so ticks read 0 / 500 / 1,000. */
function niceCeiling(value: number): number {
    if (value <= 0) return 10;

    const magnitude = 10 ** Math.floor(Math.log10(value));
    const normalized = value / magnitude;
    const step =
        normalized <= 1 ? 1 : normalized <= 2 ? 2 : normalized <= 5 ? 5 : 10;

    return step * magnitude;
}

/**
 * Multi-series daily trend with a snapping crosshair.
 *
 * One shared y-axis on purpose — visitors, sessions and pageviews are all
 * counts of the same thing at different granularities, so they belong on one
 * scale. Putting a second axis behind one of them would invent a relationship
 * the data doesn't have.
 */
export function TimeSeriesChart({
    labels,
    series,
    formatValue = compactNumber,
}: {
    /** ISO dates, one per point. */
    labels: string[];
    series: TimeSeries[];
    formatValue?: (value: number) => string;
}) {
    const [hidden, setHidden] = useState<Set<string>>(new Set());
    const [hoverIndex, setHoverIndex] = useState<number | null>(null);

    const visible = series.filter((s) => !hidden.has(s.key));

    const max = useMemo(
        () => niceCeiling(Math.max(1, ...visible.flatMap((s) => s.values))),
        [visible],
    );

    const count = labels.length;
    const xAt = (index: number) =>
        count <= 1 ? 0 : (index / (count - 1)) * PLOT_WIDTH;
    const yAt = (value: number) => PLOT_HEIGHT - (value / max) * PLOT_HEIGHT;

    const toggle = (key: string) =>
        setHidden((current) => {
            const next = new Set(current);

            if (next.has(key)) {
                next.delete(key);
            } else {
                next.add(key);
            }

            return next;
        });

    // Around six labels regardless of range length, so 90 days doesn't stack
    // dates on top of each other.
    const tickEvery = Math.max(1, Math.ceil(count / 6));

    const yTicks = [0, 0.25, 0.5, 0.75, 1].map((fraction) => fraction * max);

    const formatDate = (iso: string) =>
        new Date(`${iso}T00:00:00`).toLocaleDateString(undefined, {
            month: 'short',
            day: 'numeric',
        });

    const handleMove = (event: React.MouseEvent<SVGSVGElement>) => {
        const rect = event.currentTarget.getBoundingClientRect();
        const localX =
            ((event.clientX - rect.left) / rect.width) * WIDTH - MARGIN.left;

        if (
            localX < -MARGIN.left / 2 ||
            localX > PLOT_WIDTH + MARGIN.right / 2
        ) {
            setHoverIndex(null);
            return;
        }

        // Snap to the nearest day: the reader aims at a date, not at a 2px line.
        const index = Math.round(
            (Math.min(Math.max(localX, 0), PLOT_WIDTH) / PLOT_WIDTH) *
                (count - 1),
        );
        setHoverIndex(Math.min(Math.max(index, 0), count - 1));
    };

    const leader = visible[0];

    return (
        <div className="relative">
            <svg
                viewBox={`0 0 ${WIDTH} ${HEIGHT}`}
                className="w-full"
                role="img"
                aria-label={`${series.map((s) => s.label).join(', ')} over time`}
                onMouseMove={handleMove}
                onMouseLeave={() => setHoverIndex(null)}
            >
                <g transform={`translate(${MARGIN.left},${MARGIN.top})`}>
                    {yTicks.map((tick) => (
                        <g key={tick}>
                            <line
                                x1={0}
                                x2={PLOT_WIDTH}
                                y1={yAt(tick)}
                                y2={yAt(tick)}
                                className="stroke-border"
                                strokeWidth={1}
                            />
                            <text
                                x={-8}
                                y={yAt(tick)}
                                textAnchor="end"
                                dominantBaseline="middle"
                                className="fill-muted-foreground text-[10px] tabular-nums"
                            >
                                {compactNumber(Math.round(tick))}
                            </text>
                        </g>
                    ))}

                    {labels.map((label, index) =>
                        index % tickEvery === 0 || index === count - 1 ? (
                            <text
                                key={label}
                                x={xAt(index)}
                                y={PLOT_HEIGHT + 18}
                                textAnchor={
                                    index === count - 1
                                        ? 'end'
                                        : index === 0
                                          ? 'start'
                                          : 'middle'
                                }
                                className="fill-muted-foreground text-[10px]"
                            >
                                {formatDate(label)}
                            </text>
                        ) : null,
                    )}

                    {/* Area wash under the lead series only — a stack of
                        translucent fills turns into mud. */}
                    {leader && (
                        <path
                            d={`M 0 ${PLOT_HEIGHT} ${leader.values
                                .map(
                                    (value, index) =>
                                        `L ${xAt(index)} ${yAt(value)}`,
                                )
                                .join(' ')} L ${PLOT_WIDTH} ${PLOT_HEIGHT} Z`}
                            fill={leader.color}
                            opacity={0.1}
                        />
                    )}

                    {visible.map((s) => (
                        <path
                            key={s.key}
                            d={s.values
                                .map(
                                    (value, index) =>
                                        `${index === 0 ? 'M' : 'L'} ${xAt(index)} ${yAt(value)}`,
                                )
                                .join(' ')}
                            fill="none"
                            stroke={s.color}
                            strokeWidth={2}
                            strokeLinejoin="round"
                            strokeLinecap="round"
                        />
                    ))}

                    {/* Direct end-label for the lead series; the rest are
                        carried by the legend and the tooltip. */}
                    {leader && count > 0 && (
                        <g>
                            <circle
                                cx={xAt(count - 1)}
                                cy={yAt(leader.values[count - 1])}
                                r={4}
                                fill={leader.color}
                                className="stroke-card"
                                strokeWidth={2}
                            />
                            <text
                                x={xAt(count - 1) + 10}
                                y={yAt(leader.values[count - 1])}
                                dominantBaseline="middle"
                                className="fill-foreground text-[10px] font-medium tabular-nums"
                            >
                                {formatValue(leader.values[count - 1])}
                            </text>
                        </g>
                    )}

                    {hoverIndex !== null && (
                        <g>
                            <line
                                x1={xAt(hoverIndex)}
                                x2={xAt(hoverIndex)}
                                y1={0}
                                y2={PLOT_HEIGHT}
                                className="stroke-muted-foreground"
                                strokeWidth={1}
                            />
                            {visible.map((s) => (
                                <circle
                                    key={s.key}
                                    cx={xAt(hoverIndex)}
                                    cy={yAt(s.values[hoverIndex])}
                                    r={4}
                                    fill={s.color}
                                    className="stroke-card"
                                    strokeWidth={2}
                                />
                            ))}
                        </g>
                    )}
                </g>
            </svg>

            {hoverIndex !== null && (
                <ChartTooltip
                    x={(MARGIN.left + xAt(hoverIndex)) / WIDTH}
                    y={0.35}
                    title={new Date(
                        `${labels[hoverIndex]}T00:00:00`,
                    ).toLocaleDateString(undefined, {
                        weekday: 'short',
                        month: 'short',
                        day: 'numeric',
                    })}
                    rows={visible.map((s) => ({
                        key: s.key,
                        label: s.label,
                        color: s.color,
                        value: formatValue(s.values[hoverIndex]),
                    }))}
                />
            )}

            <div className="mt-2 flex flex-wrap items-center gap-x-4 gap-y-2">
                {series.map((s) => {
                    const isHidden = hidden.has(s.key);
                    const total = s.values.reduce(
                        (sum, value) => sum + value,
                        0,
                    );

                    return (
                        <button
                            key={s.key}
                            type="button"
                            onClick={() => toggle(s.key)}
                            aria-pressed={!isHidden}
                            className={`flex items-center gap-1.5 text-xs transition-opacity ${isHidden ? 'opacity-40' : ''}`}
                        >
                            <span
                                className="h-0.5 w-3.5"
                                style={{ backgroundColor: s.color }}
                            />
                            <span className="text-foreground">{s.label}</span>
                            <span className="text-muted-foreground tabular-nums">
                                {compactNumber(total)}
                            </span>
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
