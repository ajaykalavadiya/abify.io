import { router } from "@inertiajs/react";
import { useEffect, useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import type { ReportingRange } from "@/types";

const CUSTOM = "custom";

/**
 * Preset windows plus an explicit pair of dates, shared by the overview and
 * the heatmap so the two read the same way.
 *
 * Navigation carries the existing query string through, because on the
 * heatmap the range is only one of several things pinned in the URL — change
 * the window and you should still be looking at the same page and device.
 */
export function DateRangePicker({
    range,
    basePath,
    className,
}: {
    range: ReportingRange;
    /** Path to navigate to, without query string — e.g. `/a/abc/heatmap`. */
    basePath: string;
    className?: string;
}) {
    const [mode, setMode] = useState(range.value);
    const [from, setFrom] = useState(range.from);
    const [to, setTo] = useState(range.to);

    // The server is the source of truth for the resolved window: it clamps
    // reversed pairs, future dates and over-long spans, so the inputs follow
    // whatever came back rather than what was typed.
    useEffect(() => {
        setMode(range.value);
        setFrom(range.from);
        setTo(range.to);
    }, [range.value, range.from, range.to]);

    const navigate = (params: Record<string, string>) => {
        const query = new URLSearchParams(window.location.search);

        Object.entries(params).forEach(([key, value]) => {
            if (value) query.set(key, value);
            else query.delete(key);
        });

        router.get(
            `${basePath}?${query.toString()}`,
            {},
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    const onPreset = (value: string) => {
        setMode(value);

        if (value !== CUSTOM) {
            navigate({ range: value, from: "", to: "" });
        }
    };

    const today = new Date().toISOString().slice(0, 10);

    return (
        <div className={`flex flex-wrap items-center gap-2 ${className ?? ""}`}>
            <Select value={mode} onValueChange={onPreset}>
                <SelectTrigger className="w-44">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {range.options.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                    <SelectItem value={CUSTOM}>Custom range</SelectItem>
                </SelectContent>
            </Select>

            {mode === CUSTOM && (
                <div className="flex flex-wrap items-center gap-2">
                    <Input
                        type="date"
                        aria-label="From"
                        className="w-40"
                        value={from}
                        max={to || today}
                        onChange={(event) => setFrom(event.target.value)}
                    />
                    <span className="text-muted-foreground text-xs">to</span>
                    <Input
                        type="date"
                        aria-label="To"
                        className="w-40"
                        value={to}
                        min={from}
                        max={today}
                        onChange={(event) => setTo(event.target.value)}
                    />
                    <Button
                        size="sm"
                        variant="secondary"
                        disabled={!from || !to}
                        onClick={() => navigate({ range: CUSTOM, from, to })}
                    >
                        Apply
                    </Button>
                </div>
            )}
        </div>
    );
}
