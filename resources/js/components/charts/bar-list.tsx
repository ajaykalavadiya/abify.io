import { compactNumber } from '@/lib/chart-palette';

export type BarListItem = {
    key: string;
    label: string;
    value: number;
    /** Optional second figure shown after the value, e.g. unique visitors. */
    secondary?: string;
    href?: string;
};

/**
 * A ranked list of one measure — top pages, top sources.
 *
 * One colour for every bar, not a value ramp: the categories have no natural
 * order, and shading by size would spend the only free channel restating the
 * length the reader can already see.
 */
export function BarList({
    items,
    color,
    valueLabel,
    emptyMessage = 'No data in this period.',
}: {
    items: BarListItem[];
    color: string;
    valueLabel: string;
    emptyMessage?: string;
}) {
    if (items.length === 0) {
        return (
            <p className="text-muted-foreground py-6 text-sm">{emptyMessage}</p>
        );
    }

    const max = Math.max(...items.map((item) => item.value), 1);

    return (
        <ul className="space-y-2">
            {items.map((item) => (
                <li key={item.key} className="group relative">
                    <div className="flex items-baseline justify-between gap-4 text-xs">
                        <span
                            className="text-foreground min-w-0 flex-1 truncate"
                            title={item.href ?? item.label}
                        >
                            {item.href ? (
                                <a
                                    href={item.href}
                                    target="_blank"
                                    rel="noreferrer noopener"
                                    className="hover:underline"
                                >
                                    {item.label}
                                </a>
                            ) : (
                                item.label
                            )}
                        </span>

                        <span className="text-muted-foreground shrink-0 tabular-nums">
                            {item.secondary && (
                                <span className="mr-2">{item.secondary}</span>
                            )}
                            <span className="text-foreground font-medium">
                                {compactNumber(item.value)}
                            </span>
                        </span>
                    </div>

                    {/* Track and fill, hairline-thin: the label above carries
                        the value, the bar only carries the comparison. */}
                    <div className="bg-muted mt-1 h-1.5 w-full">
                        <div
                            className="h-full"
                            style={{
                                width: `${(item.value / max) * 100}%`,
                                backgroundColor: color,
                            }}
                            role="img"
                            aria-label={`${item.label}: ${item.value.toLocaleString()} ${valueLabel}`}
                        />
                    </div>
                </li>
            ))}
        </ul>
    );
}
