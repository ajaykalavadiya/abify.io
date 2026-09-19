import { useState } from 'react';
import { ChartTooltip } from '@/components/charts/chart-tooltip';
import { compactNumber } from '@/lib/chart-palette';

export type DonutSegment = {
    key: string;
    label: string;
    value: number;
    color: string;
};

const SIZE = 160;
const STROKE = 22;
const RADIUS = (SIZE - STROKE) / 2;
const CIRCUMFERENCE = 2 * Math.PI * RADIUS;
/** 2px of surface between neighbouring arcs, in path units. */
const GAP = 2;

/**
 * Part-to-whole at a glance, for a handful of segments.
 *
 * Only appropriate because device is a genuine whole split three ways; if the
 * segments ever get close enough that the reader has to compare arc lengths,
 * this should become a bar list.
 */
export function DonutChart({
    segments,
    centerLabel,
    centerValue,
}: {
    segments: DonutSegment[];
    centerLabel: string;
    centerValue: number;
}) {
    const [hovered, setHovered] = useState<string | null>(null);

    const total = segments.reduce((sum, segment) => sum + segment.value, 0);
    const withValues = segments.filter((segment) => segment.value > 0);

    let offset = 0;

    return (
        <div className="flex flex-wrap items-center gap-6">
            <div className="relative shrink-0">
                <svg
                    viewBox={`0 0 ${SIZE} ${SIZE}`}
                    className="size-40"
                    role="img"
                    aria-label={`${centerLabel} by ${segments.map((s) => s.label).join(', ')}`}
                >
                    <circle
                        cx={SIZE / 2}
                        cy={SIZE / 2}
                        r={RADIUS}
                        fill="none"
                        className="stroke-muted"
                        strokeWidth={STROKE}
                    />

                    {withValues.map((segment) => {
                        const fraction = segment.value / Math.max(total, 1);
                        const length = Math.max(
                            fraction * CIRCUMFERENCE - GAP,
                            1,
                        );
                        const dash = `${length} ${CIRCUMFERENCE - length}`;
                        const rotation =
                            (offset / Math.max(total, 1)) * 360 - 90;

                        offset += segment.value;

                        return (
                            <circle
                                key={segment.key}
                                cx={SIZE / 2}
                                cy={SIZE / 2}
                                r={RADIUS}
                                fill="none"
                                stroke={segment.color}
                                strokeWidth={STROKE}
                                strokeDasharray={dash}
                                transform={`rotate(${rotation} ${SIZE / 2} ${SIZE / 2})`}
                                opacity={
                                    hovered && hovered !== segment.key
                                        ? 0.45
                                        : 1
                                }
                                onMouseEnter={() => setHovered(segment.key)}
                                onMouseLeave={() => setHovered(null)}
                                className="cursor-default transition-opacity"
                            />
                        );
                    })}

                    <text
                        x={SIZE / 2}
                        y={SIZE / 2 - 4}
                        textAnchor="middle"
                        className="fill-foreground text-[20px] font-semibold"
                    >
                        {compactNumber(centerValue)}
                    </text>
                    <text
                        x={SIZE / 2}
                        y={SIZE / 2 + 14}
                        textAnchor="middle"
                        className="fill-muted-foreground text-[10px]"
                    >
                        {centerLabel}
                    </text>
                </svg>

                {hovered && (
                    <ChartTooltip
                        x={0.5}
                        y={0.08}
                        title={
                            segments.find((s) => s.key === hovered)?.label ?? ''
                        }
                        rows={[
                            {
                                key: 'value',
                                label: centerLabel,
                                value: (
                                    segments.find((s) => s.key === hovered)
                                        ?.value ?? 0
                                ).toLocaleString(),
                                color: segments.find((s) => s.key === hovered)
                                    ?.color,
                            },
                            {
                                key: 'share',
                                label: 'Share',
                                value: `${(((segments.find((s) => s.key === hovered)?.value ?? 0) / Math.max(total, 1)) * 100).toFixed(1)}%`,
                            },
                        ]}
                    />
                )}
            </div>

            {/* The legend doubles as the value readout, so nothing here is
                gated behind a hover. */}
            <dl className="min-w-40 flex-1 space-y-2">
                {segments.map((segment) => (
                    <div
                        key={segment.key}
                        className="flex items-baseline justify-between gap-3 text-xs"
                    >
                        <dt className="text-muted-foreground flex items-center gap-2">
                            <span
                                className="size-2.5 shrink-0"
                                style={{ backgroundColor: segment.color }}
                            />
                            {segment.label}
                        </dt>
                        <dd className="tabular-nums">
                            <span className="text-foreground font-medium">
                                {segment.value.toLocaleString()}
                            </span>
                            <span className="text-muted-foreground ml-2">
                                {(
                                    (segment.value / Math.max(total, 1)) *
                                    100
                                ).toFixed(0)}
                                %
                            </span>
                        </dd>
                    </div>
                ))}
            </dl>
        </div>
    );
}
