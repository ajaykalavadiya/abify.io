import { Head, Link, router } from "@inertiajs/react";
import {
    Check,
    Copy,
    ExternalLink,
    MousePointerClick,
    Plus,
} from "lucide-react";
import { useMemo } from "react";
import { ActivityGrid } from "@/components/charts/activity-grid";
import { BarList } from "@/components/charts/bar-list";
import { ChartCard } from "@/components/charts/chart-card";
import { DonutChart } from "@/components/charts/donut-chart";
import { StatTile } from "@/components/charts/stat-tile";
import {
    TimeSeriesChart,
    type TimeSeries,
} from "@/components/charts/time-series-chart";
import { DateRangePicker } from "@/components/date-range-picker";
import { SiteGa4Settings } from "@/components/site-ga4-settings";
import Heading from "@/components/heading";
import {
    SiteRecordingSettings,
    type IntegrationSettings,
    type RecordingSettings,
} from "@/components/site-recording-settings";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
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
import { useAppearance } from "@/hooks/use-appearance";
import { useClipboard } from "@/hooks/use-clipboard";
import { compactNumber, seriesColor } from "@/lib/chart-palette";
import {
    experimentAnalyticsUrl,
    experimentShowUrl,
    experimentsAbCreateUrl,
    siteHeatmapUrl,
    siteOverviewUrl,
    sitesIndexUrl,
} from "@/lib/urls";
import type {
    Company,
    ReportingRange,
    Site,
    SiteOverviewMetrics,
} from "@/types";

// Pinned to the device, not to its position in the response: the order of
// `metrics.devices` follows the configured breakpoints, and a reader who
// learned "mobile is blue" must not have it repainted because a breakpoint
// moved.
const DEVICE_COLOR_SLOT: Record<string, number> = {
    mobile: 0,
    tablet: 1,
    desktop: 2,
};

const TEST_TYPE_LABELS: Record<string, string> = {
    ab: "A/B",
    split_url: "Split URL",
    mvt: "Multivariate",
};

export default function SitesShow({
    account,
    site,
    embedSnippet,
    range,
    metrics,
    hasData,
    recording,
    integrations,
}: {
    account: Company;
    site: Site;
    embedSnippet: string;
    range: ReportingRange;
    metrics: SiteOverviewMetrics;
    hasData: boolean;
    recording: RecordingSettings;
    integrations: IntegrationSettings;
}) {
    const { resolvedAppearance } = useAppearance();
    const color = (slot: number) => seriesColor(slot, resolvedAppearance);

    const {
        totals,
        series,
        devices,
        topPages,
        topReferrers,
        activity,
        experiments,
    } = metrics;

    const previousLabel = `previous ${range.days} days`;

    const trafficSeries: TimeSeries[] = useMemo(
        () => [
            {
                key: "visitors",
                label: "Visitors",
                color: color(0),
                values: series.map((p) => p.visitors),
            },
            {
                key: "pageviews",
                label: "Pageviews",
                color: color(1),
                values: series.map((p) => p.pageviews),
            },
            {
                key: "sessions",
                label: "Sessions",
                color: color(2),
                values: series.map((p) => p.sessions),
            },
        ],
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [series, resolvedAppearance],
    );

    return (
        <>
            <Head title={site.name} />

            <div className="space-y-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <Heading
                        title={site.name}
                        description={site.domain ?? "No domain set"}
                    />

                    <div className="flex flex-wrap items-center gap-2">
                        {/* One filter row, above everything it scopes — every
                            figure below re-renders against the same window. */}
                        <DateRangePicker
                            range={range}
                            basePath={`/a/${account.uid}/overview`}
                        />

                        <Button variant="outline" asChild>
                            <Link href={siteHeatmapUrl(account.uid)}>
                                <MousePointerClick /> Heatmap
                            </Link>
                        </Button>
                        <Button asChild>
                            <Link href={experimentsAbCreateUrl(account.uid)}>
                                <Plus /> New A/B test
                            </Link>
                        </Button>
                    </div>
                </div>

                {!hasData && (
                    <InstallSnippet site={site} snippet={embedSnippet} />
                )}

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <StatTile
                        label="Visitors"
                        value={totals.visitors.value}
                        delta={{
                            ...totals.visitors,
                            periodLabel: previousLabel,
                        }}
                        trend={series.map((point) => point.visitors)}
                        color={color(0)}
                    />
                    <StatTile
                        label="Pageviews"
                        value={totals.pageviews.value}
                        delta={{
                            ...totals.pageviews,
                            periodLabel: previousLabel,
                        }}
                        trend={series.map((point) => point.pageviews)}
                        color={color(1)}
                    />
                    <StatTile
                        label="Sessions"
                        value={totals.sessions.value}
                        delta={{
                            ...totals.sessions,
                            periodLabel: previousLabel,
                        }}
                        trend={series.map((point) => point.sessions)}
                        color={color(2)}
                    />
                    <StatTile
                        label="Goal conversions"
                        value={totals.conversions.value}
                        delta={{
                            ...totals.conversions,
                            periodLabel: previousLabel,
                        }}
                        color={color(5)}
                    />
                </div>

                <ChartCard
                    title="Traffic"
                    description={`${range.label.toLowerCase()} · ${totals.pages_per_session.value.toFixed(2)} pages per session`}
                    table={{
                        columns: ["Date", "Visitors", "Pageviews", "Sessions"],
                        rows: series.map((point) => [
                            point.date,
                            point.visitors,
                            point.pageviews,
                            point.sessions,
                        ]),
                    }}
                >
                    <TimeSeriesChart
                        labels={series.map((point) => point.date)}
                        series={trafficSeries}
                    />
                </ChartCard>

                <div className="grid gap-4 lg:grid-cols-3">
                    <ChartCard
                        title="Devices"
                        description="Visitors by viewport width"
                        table={{
                            columns: [
                                "Device",
                                "Visitors",
                                "Pageviews",
                                "Share",
                            ],
                            rows: devices.map((device) => [
                                device.label,
                                device.visitors,
                                device.pageviews,
                                `${device.share}%`,
                            ]),
                        }}
                    >
                        <DonutChart
                            centerLabel="visitors"
                            centerValue={totals.visitors.value}
                            segments={devices.map((device) => ({
                                key: device.device,
                                label: device.label,
                                value: device.visitors,
                                color: color(
                                    DEVICE_COLOR_SLOT[device.device] ?? 0,
                                ),
                            }))}
                        />
                    </ChartCard>

                    <ChartCard
                        title="Top pages"
                        description="By pageviews"
                        table={{
                            columns: ["Page", "Pageviews", "Visitors"],
                            rows: topPages.map((page) => [
                                page.path,
                                page.pageviews,
                                page.visitors,
                            ]),
                        }}
                    >
                        <BarList
                            color={color(0)}
                            valueLabel="pageviews"
                            items={topPages.map((page) => ({
                                key: page.url,
                                label: page.path,
                                value: page.pageviews,
                                secondary: `${compactNumber(page.visitors)} visitors`,
                                href: page.url,
                            }))}
                        />
                    </ChartCard>

                    <ChartCard
                        title="Traffic sources"
                        description="Visitors by referring site"
                        table={{
                            columns: ["Source", "Visitors"],
                            rows: topReferrers.map((referrer) => [
                                referrer.source,
                                referrer.visitors,
                            ]),
                        }}
                    >
                        <BarList
                            color={color(2)}
                            valueLabel="visitors"
                            items={topReferrers.map((referrer) => ({
                                key: referrer.source,
                                label: referrer.source,
                                value: referrer.visitors,
                            }))}
                        />
                    </ChartCard>
                </div>

                <ChartCard
                    title="When visitors arrive"
                    description="Pageviews by weekday and hour, in your server's timezone"
                    table={{
                        columns: ["Weekday", "Hour", "Pageviews"],
                        rows: activity.cells
                            .filter((cell) => cell.pageviews > 0)
                            .map((cell) => [
                                [
                                    "Mon",
                                    "Tue",
                                    "Wed",
                                    "Thu",
                                    "Fri",
                                    "Sat",
                                    "Sun",
                                ][cell.day],
                                `${String(cell.hour).padStart(2, "0")}:00`,
                                cell.pageviews,
                            ]),
                    }}
                >
                    <ActivityGrid cells={activity.cells} max={activity.max} />
                </ChartCard>

                <SiteRecordingSettings
                    accountUid={account.uid}
                    recording={recording}
                    integrations={integrations}
                />

                <SiteGa4Settings
                    accountUid={account.uid}
                    measurementId={integrations.ga4_measurement_id}
                />

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0">
                        <CardTitle className="text-sm font-medium">
                            Tests
                        </CardTitle>
                        <p className="text-muted-foreground text-xs">
                            Lifetime figures — a test's numbers only mean
                            something over its whole run
                        </p>
                    </CardHeader>
                    <CardContent>
                        {experiments.length === 0 ? (
                            <p className="text-muted-foreground py-4 text-sm">
                                No tests yet.{" "}
                                <Link
                                    href={experimentsAbCreateUrl(account.uid)}
                                    className="underline underline-offset-4"
                                >
                                    Create your first A/B test
                                </Link>
                                .
                            </p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Name</TableHead>
                                        <TableHead>Type</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">
                                            Visitors
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Conversions
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Rate
                                        </TableHead>
                                        <TableHead />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {experiments.map((experiment) => (
                                        <TableRow key={experiment.id}>
                                            <TableCell className="font-medium">
                                                <Link
                                                    href={experimentShowUrl(
                                                        account.uid,
                                                        experiment.id,
                                                    )}
                                                    className="hover:underline"
                                                >
                                                    {experiment.name}
                                                </Link>
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {TEST_TYPE_LABELS[
                                                    experiment.type
                                                ] ?? experiment.type}
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant={
                                                        experiment.status ===
                                                        "running"
                                                            ? "default"
                                                            : "secondary"
                                                    }
                                                >
                                                    {experiment.status}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {experiment.visitors.toLocaleString()}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {experiment.conversions.toLocaleString()}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {experiment.rate.toFixed(2)}%
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link
                                                        href={experimentAnalyticsUrl(
                                                            account.uid,
                                                            experiment.id,
                                                        )}
                                                    >
                                                        Results
                                                    </Link>
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>

                {hasData && (
                    <InstallSnippet
                        site={site}
                        snippet={embedSnippet}
                        collapsed
                    />
                )}
            </div>
        </>
    );
}

function InstallSnippet({
    site,
    snippet,
    collapsed = false,
}: {
    site: Site;
    snippet: string;
    collapsed?: boolean;
}) {
    const [copiedText, copy] = useClipboard();
    const copied = copiedText === snippet;

    const body = (
        <CardContent className="space-y-3">
            <p className="text-muted-foreground text-sm">
                Paste this snippet as high as possible in the{" "}
                <code>&lt;head&gt;</code> of {site.domain ?? "your site"}.
            </p>
            <pre className="bg-muted overflow-x-auto p-4 text-xs">
                {snippet}
            </pre>
            <Button variant="outline" size="sm" onClick={() => copy(snippet)}>
                {copied ? <Check /> : <Copy />}
                {copied ? "Copied" : "Copy snippet"}
            </Button>
        </CardContent>
    );

    if (collapsed) {
        return (
            <details className="group">
                <summary className="text-muted-foreground hover:text-foreground cursor-pointer list-none text-sm">
                    <span className="inline-flex items-center gap-1.5">
                        <ExternalLink className="size-3.5" />
                        Tracking snippet
                    </span>
                </summary>
                <Card className="mt-2">{body}</Card>
            </details>
        );
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-sm font-medium">
                    Install the tracking snippet
                </CardTitle>
                <p className="text-muted-foreground text-xs">
                    No data has reached this site yet. Once the snippet is live,
                    traffic appears here within a minute.
                </p>
            </CardHeader>
            {body}
        </Card>
    );
}

SitesShow.layout = (props: { account: Company; site: Site }) => ({
    breadcrumbs: [
        { title: props.account.name, href: sitesIndexUrl(props.account.uid) },
        { title: props.site.name, href: siteOverviewUrl(props.account.uid) },
    ],
});
