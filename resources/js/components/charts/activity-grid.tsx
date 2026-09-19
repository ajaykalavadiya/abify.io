import { useState } from 'react';
import { ChartTooltip } from '@/components/charts/chart-tooltip';
import { sequentialColor } from '@/lib/chart-palette';

export type ActivityCell = {
    day: number;
    hour: number;
    pageviews: number;
};

const DAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

/**
 * Pageviews by weekday and hour.
 *
 * A single-hue ramp because the value is pure magnitude — a rainbow here would
 * make the middle of the scale look like a category of its own. The scale
 * legend and the table view carry the numbers the cells only imply.
 */
export function ActivityGrid({
    cells,
    max,
}: {
    cells: ActivityCell[];
    max: number;
}) {
    const [hovered, setHovered] = useState<ActivityCell | null>(null);

    const byCell = new Map(
        cells.map((cell) => [`${cell.day}:${cell.hour}`, cell.pageviews]),
    );

    return (
        <div className="relative">
            <div className="overflow-x-auto">
                <div className="min-w-[34rem]">
                    <div className="flex">
                        <div className="w-8 shrink-0" />
                        <div className="grid flex-1 grid-cols-24 gap-0.5">
                            {Array.from({ length: 24 }, (_, hour) => (
                                <span
                                    key={hour}
                                    className="text-muted-foreground text-center text-[9px] tabular-nums"
                                >
                                    {hour % 3 === 0 ? hour : ''}
                                </span>
                            ))}
                        </div>
                    </div>

                    {DAYS.map((day, dayIndex) => (
                        <div key={day} className="mt-0.5 flex items-center">
                            <span className="text-muted-foreground w-8 shrink-0 pr-1 text-right text-[10px]">
                                {day}
                            </span>
                            <div className="grid flex-1 grid-cols-24 gap-0.5">
                                {Array.from({ length: 24 }, (_, hour) => {
                                    const pageviews =
                                        byCell.get(`${dayIndex}:${hour}`) ?? 0;
                                    // Square-root scaling: raw linear intensity
                                    // leaves everything but the single busiest
                                    // hour looking empty.
                                    const intensity =
                                        max > 0
                                            ? Math.sqrt(pageviews / max)
                                            : 0;

                                    return (
                                        // Fixed row height rather than a
                                        // square: on a wide dashboard square
                                        // cells grow to ~50px and dwarf the
                                        // charts above.
                                        <button
                                            key={hour}
                                            type="button"
                                            className="focus-visible:ring-ring h-5 w-full min-w-0 focus:outline-none focus-visible:ring-2"
                                            style={{
                                                backgroundColor:
                                                    pageviews > 0
                                                        ? sequentialColor(
                                                              intensity,
                                                          )
                                                        : 'var(--muted)',
                                            }}
                                            onMouseEnter={() =>
                                                setHovered({
                                                    day: dayIndex,
                                                    hour,
                                                    pageviews,
                                                })
                                            }
                                            onFocus={() =>
                                                setHovered({
                                                    day: dayIndex,
                                                    hour,
                                                    pageviews,
                                                })
                                            }
                                            onMouseLeave={() =>
                                                setHovered(null)
                                            }
                                            onBlur={() => setHovered(null)}
                                            aria-label={`${day} ${hour}:00 — ${pageviews.toLocaleString()} pageviews`}
                                        />
                                    );
                                })}
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            <div className="mt-3 flex items-center gap-2">
                <span className="text-muted-foreground text-[10px]">
                    Quieter
                </span>
                <div className="flex gap-0.5">
                    {[0, 0.25, 0.5, 0.75, 1].map((step) => (
                        <span
                            key={step}
                            className="size-3"
                            style={{ backgroundColor: sequentialColor(step) }}
                        />
                    ))}
                </div>
                <span className="text-muted-foreground text-[10px]">
                    Busier · up to {max.toLocaleString()} views/hour
                </span>
            </div>

            {hovered && (
                <ChartTooltip
                    x={hovered.hour / 24}
                    y={0.1 + (hovered.day / 7) * 0.8}
                    title={`${DAYS[hovered.day]} ${String(hovered.hour).padStart(2, '0')}:00`}
                    rows={[
                        {
                            key: 'pageviews',
                            label: 'Pageviews',
                            value: hovered.pageviews.toLocaleString(),
                        },
                    ]}
                />
            )}
        </div>
    );
}
