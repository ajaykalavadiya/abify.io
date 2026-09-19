import { Table2, TrendingUp } from 'lucide-react';
import { useId, useState, type ReactNode } from 'react';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';

export type ChartTable = {
    columns: string[];
    rows: (string | number)[][];
};

/**
 * A chart and its table-view twin.
 *
 * Every chart here encodes something with colour or position, so each one
 * ships an equivalent table: it is the accessible reading of the same data,
 * and the place a value goes when it won't fit as a direct label.
 */
export function ChartCard({
    title,
    description,
    table,
    action,
    children,
}: {
    title: string;
    description?: string;
    table?: ChartTable;
    action?: ReactNode;
    children: ReactNode;
}) {
    const [view, setView] = useState<'chart' | 'table'>('chart');
    const titleId = useId();

    return (
        <Card className="gap-4">
            <CardHeader className="flex flex-row items-start justify-between gap-4 space-y-0">
                <div className="min-w-0">
                    <h3
                        id={titleId}
                        className="text-foreground text-sm font-medium"
                    >
                        {title}
                    </h3>
                    {description && (
                        <p className="text-muted-foreground mt-0.5 text-xs">
                            {description}
                        </p>
                    )}
                </div>

                <div className="flex shrink-0 items-center gap-2">
                    {action}

                    {table && (
                        <ToggleGroup
                            type="single"
                            size="sm"
                            value={view}
                            onValueChange={(value) =>
                                value && setView(value as 'chart' | 'table')
                            }
                            aria-label={`${title} view`}
                        >
                            <ToggleGroupItem
                                value="chart"
                                aria-label="Chart view"
                                className="px-2"
                            >
                                <TrendingUp className="size-3.5" />
                            </ToggleGroupItem>
                            <ToggleGroupItem
                                value="table"
                                aria-label="Table view"
                                className="px-2"
                            >
                                <Table2 className="size-3.5" />
                            </ToggleGroupItem>
                        </ToggleGroup>
                    )}
                </div>
            </CardHeader>

            <CardContent>
                {view === 'chart' || !table ? (
                    children
                ) : (
                    <div className="max-h-80 overflow-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    {table.columns.map((column, index) => (
                                        <TableHead
                                            key={column}
                                            className={
                                                index === 0 ? '' : 'text-right'
                                            }
                                        >
                                            {column}
                                        </TableHead>
                                    ))}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {table.rows.map((row, rowIndex) => (
                                    <TableRow key={rowIndex}>
                                        {row.map((cell, cellIndex) => (
                                            <TableCell
                                                key={cellIndex}
                                                className={
                                                    cellIndex === 0
                                                        ? 'max-w-[22rem] truncate'
                                                        : 'text-right tabular-nums'
                                                }
                                            >
                                                {typeof cell === 'number'
                                                    ? cell.toLocaleString()
                                                    : cell}
                                            </TableCell>
                                        ))}
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
