import { Head, Link } from "@inertiajs/react";
import { MousePointerClick } from "lucide-react";
import { useMemo, useState } from "react";
import Heading from "@/components/heading";
import {
    ResultsChart,
    type ResultSeries,
} from "@/components/analytics/results-chart";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import {
    experimentAnalyticsUrl,
    experimentShowUrl,
    experimentsIndexUrl,
    siteHeatmapUrl,
    siteOverviewUrl,
    sitesIndexUrl,
} from "@/lib/urls";
import { simulateWinProbabilities } from "@/lib/stats";
import type {
    AnalyticsVariationResult,
    Company,
    Experiment,
    Site,
} from "@/types";

// Fixed categorical order (validated for adjacent CVD/contrast separation),
// with orange reserved for the control/baseline variation per product
// convention — variants are assigned the remaining hues in order.
const CONTROL_COLOR = "#eb6834";
const VARIANT_PALETTE = [
    "#2a78d6",
    "#1baf7a",
    "#e87ba4",
    "#4a3aa7",
    "#eda100",
    "#008300",
    "#e34948",
];

export default function AnalyticsShow({
    account,
    site,
    experiment,
    results,
    currencies = [],
}: {
    account: Company;
    site: Site;
    experiment: Experiment;
    results: AnalyticsVariationResult[];
    /** ISO codes present in this experiment's revenue, usually exactly one. */
    currencies?: string[];
}) {
    const goals = experiment.goals ?? [];
    const [goalId, setGoalId] = useState<number | null>(goals[0]?.id ?? null);
    const controlImpressions =
        results.find((r) => r.variation.is_control)?.impressions ?? 0;

    const colorByVariationId = useMemo(() => {
        const map = new Map<number, string>();
        let nextVariantIndex = 0;
        for (const result of results) {
            if (result.variation.is_control) {
                map.set(result.variation.id, CONTROL_COLOR);
            } else {
                map.set(
                    result.variation.id,
                    VARIANT_PALETTE[nextVariantIndex % VARIANT_PALETTE.length],
                );
                nextVariantIndex++;
            }
        }
        return map;
    }, [results]);

    const rows = useMemo(() => {
        if (goalId === null) return [];

        const baselineIndex = results.findIndex((r) => r.variation.is_control);
        const controlGoal = results[baselineIndex]?.goals.find(
            (g) => g.goal.id === goalId,
        );

        const perVariation = results.map((result) => {
            const goalResult = result.goals.find((g) => g.goal.id === goalId);
            // Total events, which for a revenue goal counts every order.
            const conversions = goalResult?.conversions ?? 0;
            // Distinct people who converted. This is the binomial count the
            // Beta model below needs: a shopper who ordered three times is
            // one success out of one visitor, and feeding the order count in
            // would push alpha past the trial count and make the posterior
            // meaningless.
            const converters = goalResult?.converters ?? conversions;
            const visitors = result.impressions;

            return {
                result,
                conversions,
                converters,
                visitors,
                rate: goalResult?.rate ?? 0,
                revenue: goalResult?.revenue ?? null,
                revenuePerVisitor: goalResult?.revenue_per_visitor ?? null,
                alpha: Math.min(converters, visitors) + 1,
                beta: Math.max(0, visitors - converters) + 1,
            };
        });

        const { probabilityToBeatBaseline, probabilityToBeBest } =
            simulateWinProbabilities(
                perVariation.map((p) => ({ alpha: p.alpha, beta: p.beta })),
                baselineIndex >= 0 ? baselineIndex : 0,
            );

        return perVariation.map((p, index) => ({
            variation: p.result.variation,
            color:
                colorByVariationId.get(p.result.variation.id) ?? CONTROL_COLOR,
            conversions: p.conversions,
            converters: p.converters,
            visitors: p.visitors,
            rate: p.rate,
            revenue: p.revenue,
            revenuePerVisitor: p.revenuePerVisitor,
            alpha: p.alpha,
            beta: p.beta,
            improvement:
                !p.result.variation.is_control &&
                controlGoal &&
                controlGoal.rate > 0
                    ? ((p.rate - controlGoal.rate) / controlGoal.rate) * 100
                    : null,
            probabilityToBeatBaseline: p.result.variation.is_control
                ? null
                : probabilityToBeatBaseline[index],
            probabilityToBeBest: probabilityToBeBest[index],
        }));
    }, [results, goalId, colorByVariationId]);

    // Revenue columns only appear for goals that actually record money, so a
    // plain signup test is not padded with empty currency cells.
    const hasRevenue = rows.some((row) => row.revenue !== null);

    // Mixed currencies are reported rather than summed (the backend never
    // converts), so fall back to a plain number when a site takes more than
    // one rather than labelling a total with the wrong symbol.
    const formatMoney = (value: number | null) => {
        if (value === null) return "—";
        if (currencies.length !== 1) return value.toFixed(2);

        return new Intl.NumberFormat(undefined, {
            style: "currency",
            currency: currencies[0],
        }).format(value);
    };

    const chartSeries: ResultSeries[] = rows.map((row) => ({
        key: String(row.variation.id),
        label: row.variation.name,
        color: row.color,
        isControl: row.variation.is_control,
        alpha: row.alpha,
        beta: row.beta,
    }));

    return (
        <>
            <Head title={`${experiment.name} — Analytics`} />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between gap-4">
                    <Heading title="Analytics" description={experiment.name} />
                    {goals.length > 1 && (
                        <Select
                            value={goalId !== null ? String(goalId) : undefined}
                            onValueChange={(value) => setGoalId(Number(value))}
                        >
                            <SelectTrigger className="w-56">
                                <SelectValue placeholder="Select a goal" />
                            </SelectTrigger>
                            <SelectContent>
                                {goals.map((goal) => (
                                    <SelectItem
                                        key={goal.id}
                                        value={String(goal.id)}
                                    >
                                        {goal.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    )}
                </div>

                {goals.length === 0 ? (
                    <p className="text-muted-foreground text-sm">
                        No goals configured.
                    </p>
                ) : (
                    <>
                        <Card>
                            <CardContent>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Variation</TableHead>
                                            <TableHead>
                                                Conversion rate
                                            </TableHead>
                                            <TableHead>Improvement</TableHead>
                                            <TableHead>
                                                Probability to beat baseline
                                            </TableHead>
                                            <TableHead>
                                                Probability to be best
                                            </TableHead>
                                            <TableHead>
                                                Conversions / visitors
                                            </TableHead>
                                            {hasRevenue && (
                                                <TableHead>Revenue</TableHead>
                                            )}
                                            {hasRevenue && (
                                                <TableHead>
                                                    Revenue / visitor
                                                </TableHead>
                                            )}
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {rows.map((row) => (
                                            <TableRow key={row.variation.id}>
                                                <TableCell className="font-medium">
                                                    <div className="flex items-center gap-2">
                                                        <span
                                                            className="size-2.5 shrink-0 rounded-none"
                                                            style={{
                                                                backgroundColor:
                                                                    row.color,
                                                            }}
                                                        />
                                                        {row.variation.name}
                                                        {row.variation
                                                            .is_control && (
                                                            <Badge variant="secondary">
                                                                control
                                                            </Badge>
                                                        )}
                                                    </div>

                                                    {/* The 'why' behind the
                                                        number beside it: the
                                                        same map, drawn only
                                                        from the people who
                                                        saw this variation. */}
                                                    <Link
                                                        href={siteHeatmapUrl(
                                                            account.uid,
                                                            {
                                                                variation:
                                                                    row
                                                                        .variation
                                                                        .id,
                                                            },
                                                        )}
                                                        className="text-muted-foreground hover:text-foreground mt-1 inline-flex items-center gap-1 text-xs underline-offset-4 hover:underline"
                                                    >
                                                        <MousePointerClick className="size-3" />
                                                        View heatmap
                                                    </Link>
                                                </TableCell>
                                                <TableCell>
                                                    {row.rate.toFixed(2)}%
                                                </TableCell>
                                                <TableCell>
                                                    {row.improvement ===
                                                    null ? (
                                                        <span className="text-muted-foreground">
                                                            baseline
                                                        </span>
                                                    ) : (
                                                        <span
                                                            className={
                                                                row.improvement >=
                                                                0
                                                                    ? "text-emerald-600 dark:text-emerald-400"
                                                                    : "text-rose-600 dark:text-rose-400"
                                                            }
                                                        >
                                                            {row.improvement >=
                                                            0
                                                                ? "+"
                                                                : ""}
                                                            {row.improvement.toFixed(
                                                                2,
                                                            )}
                                                            %
                                                        </span>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    {row.probabilityToBeatBaseline ===
                                                    null
                                                        ? "—"
                                                        : `${Math.round(row.probabilityToBeatBaseline * 100)}%`}
                                                </TableCell>
                                                <TableCell>
                                                    {Math.round(
                                                        row.probabilityToBeBest *
                                                            100,
                                                    )}
                                                    %
                                                </TableCell>
                                                <TableCell className="text-muted-foreground">
                                                    {row.conversions} /{" "}
                                                    {row.visitors}
                                                </TableCell>
                                                {hasRevenue && (
                                                    <TableCell>
                                                        {formatMoney(
                                                            row.revenue,
                                                        )}
                                                    </TableCell>
                                                )}
                                                {hasRevenue && (
                                                    <TableCell className="font-medium">
                                                        {formatMoney(
                                                            row.revenuePerVisitor,
                                                        )}
                                                    </TableCell>
                                                )}
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    Conversion rate distribution
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {controlImpressions === 0 ? (
                                    <p className="text-muted-foreground text-sm">
                                        No traffic recorded yet — impressions
                                        appear once the tracking script starts
                                        sending data.
                                    </p>
                                ) : (
                                    <ResultsChart series={chartSeries} />
                                )}
                            </CardContent>
                        </Card>
                    </>
                )}
            </div>
        </>
    );
}

AnalyticsShow.layout = (props: {
    account: Company;
    site: Site;
    experiment: Experiment;
}) => ({
    breadcrumbs: [
        { title: props.account.name, href: sitesIndexUrl(props.account.uid) },
        { title: props.site.name, href: siteOverviewUrl(props.account.uid) },
        { title: "Tests", href: experimentsIndexUrl(props.account.uid) },
        {
            title: props.experiment.name,
            href: experimentShowUrl(props.account.uid, props.experiment.id),
        },
        {
            title: "Analytics",
            href: experimentAnalyticsUrl(
                props.account.uid,
                props.experiment.id,
            ),
        },
    ],
});
