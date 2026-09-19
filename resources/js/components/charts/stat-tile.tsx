import { ArrowDownRight, ArrowUpRight, Minus } from 'lucide-react';
import { Card } from '@/components/ui/card';
import { compactNumber } from '@/lib/chart-palette';

export type StatDelta = {
    /** Percentage change against the previous period; null when there is no baseline. */
    change: number | null;
    previous: number;
    periodLabel: string;
};

/**
 * One headline figure with its change and a shape-only sparkline.
 *
 * The sparkline is deliberately unlabelled and unhovered — it is there to say
 * "rising", "spiky", "flat", and the numbers behind it live in the trend chart
 * below and in its table view.
 */
export function StatTile({
    label,
    value,
    format = compactNumber,
    delta,
    trend,
    higherIsBetter = true,
    color,
}: {
    label: string;
    value: number;
    format?: (value: number) => string;
    delta?: StatDelta;
    trend?: number[];
    higherIsBetter?: boolean;
    color: string;
}) {
    const change = delta?.change ?? null;
    const isFlat = change === null || Math.abs(change) < 0.05;
    const isGood =
        change !== null && (higherIsBetter ? change > 0 : change < 0);

    const Icon = isFlat
        ? Minus
        : change !== null && change > 0
          ? ArrowUpRight
          : ArrowDownRight;

    return (
        <Card className="gap-3 py-4">
            <div className="flex items-start justify-between gap-3 px-4">
                <div className="min-w-0">
                    <p className="text-muted-foreground truncate text-xs">
                        {label}
                    </p>
                    {/* Proportional figures: tabular-nums makes a large
                        standalone number look loose. */}
                    <p className="text-foreground mt-1 text-2xl font-semibold">
                        {format(value)}
                    </p>
                </div>

                {delta && (
                    <div className="shrink-0 text-right">
                        <span
                            className={`inline-flex items-center gap-0.5 text-xs font-medium ${
                                isFlat
                                    ? 'text-muted-foreground'
                                    : isGood
                                      ? 'text-emerald-600 dark:text-emerald-400'
                                      : 'text-rose-600 dark:text-rose-400'
                            }`}
                        >
                            {change === null ? (
                                // No baseline to compare against — a direction
                                // arrow here would claim a trend that isn't there.
                                'No prior data'
                            ) : (
                                <>
                                    <Icon className="size-3.5" aria-hidden />
                                    {`${Math.abs(change).toFixed(1)}%`}
                                </>
                            )}
                        </span>
                        {change !== null && (
                            <p className="text-muted-foreground mt-0.5 text-[11px]">
                                vs {delta.periodLabel}
                            </p>
                        )}
                    </div>
                )}
            </div>

            {trend && trend.length > 1 && (
                <Sparkline values={trend} color={color} />
            )}
        </Card>
    );
}

function Sparkline({ values, color }: { values: number[]; color: string }) {
    const width = 240;
    const height = 32;
    const max = Math.max(1, ...values);

    const points = values.map((value, index) => ({
        x: (index / (values.length - 1)) * width,
        y: height - (value / max) * (height - 4) - 2,
    }));

    const line = points
        .map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x} ${p.y}`)
        .join(' ');

    return (
        <svg
            viewBox={`0 0 ${width} ${height}`}
            className="h-8 w-full px-4"
            preserveAspectRatio="none"
            aria-hidden
        >
            <path
                d={`${line} L ${width} ${height} L 0 ${height} Z`}
                fill={color}
                opacity={0.1}
            />
            <path
                d={line}
                fill="none"
                stroke={color}
                strokeWidth={1.5}
                vectorEffect="non-scaling-stroke"
            />
        </svg>
    );
}
