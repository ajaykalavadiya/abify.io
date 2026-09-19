import { useMemo, useState } from 'react';
import { betaPdf } from '@/lib/stats';

export type ResultSeries = {
    key: string;
    label: string;
    color: string;
    isControl: boolean;
    alpha: number;
    beta: number;
};

const WIDTH = 720;
const HEIGHT = 320;
const MARGIN = { top: 16, right: 16, bottom: 32, left: 40 };
const PLOT_WIDTH = WIDTH - MARGIN.left - MARGIN.right;
const PLOT_HEIGHT = HEIGHT - MARGIN.top - MARGIN.bottom;
const SAMPLES = 160;

function mean(alpha: number, beta: number) {
    return alpha / (alpha + beta);
}

function stdDev(alpha: number, beta: number) {
    const sum = alpha + beta;
    return Math.sqrt((alpha * beta) / (sum * sum * (sum + 1)));
}

export function ResultsChart({ series }: { series: ResultSeries[] }) {
    const [hidden, setHidden] = useState<Set<string>>(new Set());
    const [hoverX, setHoverX] = useState<number | null>(null);

    const toggle = (key: string) => {
        setHidden((current) => {
            const next = new Set(current);
            if (next.has(key)) {
                next.delete(key);
            } else {
                next.add(key);
            }
            return next;
        });
    };

    const visible = series.filter((s) => !hidden.has(s.key));

    const domainMax = useMemo(() => {
        if (visible.length === 0) return 0.1;
        const max = Math.max(
            ...visible.map((s) => mean(s.alpha, s.beta) + 4 * stdDev(s.alpha, s.beta)),
        );
        return Math.min(1, Math.max(0.02, max));
    }, [visible]);

    const curves = useMemo(() => {
        return visible.map((s) => {
            const points: { x: number; y: number }[] = [];
            for (let i = 0; i <= SAMPLES; i++) {
                const x = (i / SAMPLES) * domainMax;
                points.push({ x, y: betaPdf(x, s.alpha, s.beta) });
            }
            const peak = points.reduce((best, p) => (p.y > best.y ? p : best), points[0]);
            return { series: s, points, peak };
        });
    }, [visible, domainMax]);

    const maxDensity = useMemo(
        () => Math.max(1e-9, ...curves.map((c) => c.peak.y)),
        [curves],
    );

    const xScale = (rate: number) => (rate / domainMax) * PLOT_WIDTH;
    const yScale = (density: number) => PLOT_HEIGHT - (density / maxDensity) * PLOT_HEIGHT;

    const toPath = (points: { x: number; y: number }[], close: boolean) => {
        const d = points.map((p, i) => `${i === 0 ? 'M' : 'L'} ${xScale(p.x)} ${yScale(p.y)}`).join(' ');
        if (!close) return d;
        return `${d} L ${xScale(points[points.length - 1].x)} ${PLOT_HEIGHT} L ${xScale(points[0].x)} ${PLOT_HEIGHT} Z`;
    };

    const xTicks = 5;
    const tickValues = Array.from({ length: xTicks + 1 }, (_, i) => (i / xTicks) * domainMax);

    const handleMouseMove = (event: React.MouseEvent<SVGSVGElement>) => {
        const rect = event.currentTarget.getBoundingClientRect();
        const scaleX = WIDTH / rect.width;
        const localX = (event.clientX - rect.left) * scaleX - MARGIN.left;
        if (localX < 0 || localX > PLOT_WIDTH) {
            setHoverX(null);
            return;
        }
        setHoverX((localX / PLOT_WIDTH) * domainMax);
    };

    return (
        <div className="space-y-3">
            <svg
                viewBox={`0 0 ${WIDTH} ${HEIGHT}`}
                className="w-full"
                role="img"
                aria-label="Conversion rate probability density per variation"
                onMouseMove={handleMouseMove}
                onMouseLeave={() => setHoverX(null)}
            >
                <g transform={`translate(${MARGIN.left},${MARGIN.top})`}>
                    {[0, 0.25, 0.5, 0.75, 1].map((fraction) => (
                        <line
                            key={fraction}
                            x1={0}
                            x2={PLOT_WIDTH}
                            y1={PLOT_HEIGHT * (1 - fraction)}
                            y2={PLOT_HEIGHT * (1 - fraction)}
                            className="stroke-border"
                            strokeWidth={1}
                        />
                    ))}

                    {tickValues.map((value) => (
                        <g key={value} transform={`translate(${xScale(value)},0)`}>
                            <line
                                y1={0}
                                y2={PLOT_HEIGHT}
                                className="stroke-border"
                                strokeWidth={1}
                                opacity={0.5}
                            />
                            <text
                                y={PLOT_HEIGHT + 18}
                                textAnchor="middle"
                                className="fill-muted-foreground text-[10px]"
                            >
                                {(value * 100).toFixed(1)}%
                            </text>
                        </g>
                    ))}

                    {curves.map(({ series: s, points }) => (
                        <path
                            key={`${s.key}-area`}
                            d={toPath(points, true)}
                            fill={s.color}
                            opacity={0.1}
                            stroke="none"
                        />
                    ))}
                    {curves.map(({ series: s, points }) => (
                        <path
                            key={`${s.key}-line`}
                            d={toPath(points, false)}
                            fill="none"
                            stroke={s.color}
                            strokeWidth={2}
                            strokeLinejoin="round"
                            strokeLinecap="round"
                        />
                    ))}

                    {curves.map(({ series: s, peak }) => (
                        <g key={`${s.key}-peak`}>
                            <line
                                x1={xScale(peak.x)}
                                x2={xScale(peak.x)}
                                y1={yScale(peak.y)}
                                y2={PLOT_HEIGHT}
                                stroke={s.color}
                                strokeWidth={1}
                                strokeDasharray="3 3"
                                opacity={0.6}
                            />
                            <circle
                                cx={xScale(peak.x)}
                                cy={yScale(peak.y)}
                                r={4}
                                fill={s.color}
                                className="stroke-card"
                                strokeWidth={2}
                            />
                        </g>
                    ))}

                    {hoverX !== null && (
                        <g>
                            <line
                                x1={xScale(hoverX)}
                                x2={xScale(hoverX)}
                                y1={0}
                                y2={PLOT_HEIGHT}
                                className="stroke-muted-foreground"
                                strokeWidth={1}
                            />
                            {(() => {
                                const boxWidth = 132;
                                const boxHeight = 20 + visible.length * 16;
                                const anchorX = xScale(hoverX);
                                const boxX = Math.min(Math.max(anchorX + 8, 0), PLOT_WIDTH - boxWidth);
                                return (
                                    <g transform={`translate(${boxX}, 4)`}>
                                        <rect
                                            width={boxWidth}
                                            height={boxHeight}
                                            rx={6}
                                            className="fill-popover stroke-border"
                                            strokeWidth={1}
                                        />
                                        <text x={8} y={16} className="fill-foreground text-[10px] font-medium">
                                            {(hoverX * 100).toFixed(2)}% rate
                                        </text>
                                        {visible.map((s, i) => (
                                            <g key={s.key} transform={`translate(8, ${32 + i * 16})`}>
                                                <circle cx={3} cy={-4} r={3} fill={s.color} />
                                                <text x={12} y={0} className="fill-muted-foreground text-[10px]">
                                                    {s.label}: {(betaPdf(hoverX, s.alpha, s.beta) / maxDensity * 100).toFixed(0)}
                                                    %
                                                </text>
                                            </g>
                                        ))}
                                    </g>
                                );
                            })()}
                        </g>
                    )}
                </g>
            </svg>

            <div className="flex flex-wrap items-center gap-x-4 gap-y-2">
                {series.map((s) => {
                    const isHidden = hidden.has(s.key);
                    return (
                        <button
                            key={s.key}
                            type="button"
                            onClick={() => toggle(s.key)}
                            className={`flex items-center gap-1.5 text-xs transition-opacity ${isHidden ? 'opacity-40' : ''}`}
                        >
                            <span
                                className="size-2.5 rounded-none"
                                style={{ backgroundColor: s.color }}
                            />
                            <span className="text-foreground">{s.label}</span>
                            {s.isControl && (
                                <span className="text-muted-foreground">(baseline)</span>
                            )}
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
