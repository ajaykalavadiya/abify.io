import { Head, router } from "@inertiajs/react";
import {
    Camera,
    ExternalLink,
    Monitor,
    MousePointerClick,
    Smartphone,
    Tablet,
    TriangleAlert,
} from "lucide-react";
import { useState } from "react";
import { DateRangePicker } from "@/components/date-range-picker";
import Heading from "@/components/heading";
import { HeatmapCanvas } from "@/components/heatmap/heatmap-canvas";
import { HeatmapStage } from "@/components/heatmap/heatmap-stage";
import { ScrollMap } from "@/components/heatmap/scroll-map";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { Spinner } from "@/components/ui/spinner";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import {
    heatmapScreenshotStoreUrl,
    siteHeatmapUrl,
    siteOverviewUrl,
    sitesIndexUrl,
} from "@/lib/urls";
import type {
    Company,
    DeviceOption,
    HeatmapDevice,
    HeatmapPage,
    HeatmapPalette,
    HeatmapPoint,
    HeatmapSummary,
    HeatmapVariationGroup,
    PageScreenshot,
    ReportingRange,
    ScrollDepth,
    Site,
} from "@/types";

const DEVICE_ICONS: Record<HeatmapDevice, typeof Monitor> = {
    desktop: Monitor,
    tablet: Tablet,
    mobile: Smartphone,
};

type MapType = "click" | "move" | "scroll";

const MAP_TYPES: { value: MapType; label: string; description: string }[] = [
    {
        value: "click",
        label: "Clicks",
        description: "Where visitors tapped and clicked.",
    },
    {
        value: "move",
        label: "Attention",
        description: "Where the pointer lingered.",
    },
    {
        value: "scroll",
        label: "Scroll",
        description: "How far down the page visitors got.",
    },
];

export default function HeatmapShow({
    account,
    site,
    pages,
    selectedUrl,
    device,
    type,
    devices,
    range,
    variationId,
    variations,
    points,
    scroll,
    summary,
    screenshot,
}: {
    account: Company;
    site: Site;
    pages: HeatmapPage[];
    selectedUrl: string | null;
    device: HeatmapDevice;
    type: MapType;
    devices: DeviceOption[];
    range: ReportingRange;
    variationId: number | null;
    variations: HeatmapVariationGroup[];
    points: HeatmapPoint[];
    scroll: ScrollDepth;
    summary: HeatmapSummary | null;
    screenshot: PageScreenshot | null;
}) {
    // Which map is showing lives in the URL alongside the page and the
    // device, so every view is linkable and a device switch keeps the map
    // the reader was looking at.
    const mapType = type;
    const [palette, setPalette] = useState<HeatmapPalette>("thermal");
    const [intensity, setIntensity] = useState(0.75);
    const [radius, setRadius] = useState(18);

    const [capturing, setCapturing] = useState(false);

    const deviceOption =
        devices.find((option) => option.value === device) ?? devices[0];
    const activePage = pages.find((page) => page.url === selectedUrl);

    const navigate = (params: {
        url?: string;
        device?: string;
        type?: string;
        variation?: number;
    }) => {
        router.visit(
            siteHeatmapUrl(account.uid, {
                url: selectedUrl ?? undefined,
                device,
                type: mapType,
                range: range.value,
                from: range.value === "custom" ? range.from : undefined,
                to: range.value === "custom" ? range.to : undefined,
                ...params,
            }),
            { preserveState: true, preserveScroll: true },
        );
    };

    const requestCapture = () => {
        if (!selectedUrl) return;

        router.post(
            heatmapScreenshotStoreUrl(account.uid),
            { url: selectedUrl, device },
            {
                preserveScroll: true,
                onStart: () => setCapturing(true),
                onFinish: () => setCapturing(false),
            },
        );
    };

    // The stage needs a page shape. Prefer the capture's own dimensions; fall
    // back to the document size visitors actually reported, so the map is
    // still proportioned like the real page before anything is captured.
    const stageWidth =
        screenshot?.width ?? summary?.doc_width ?? deviceOption?.width ?? 1440;
    const stageHeight =
        screenshot?.height ??
        summary?.doc_height ??
        Math.round((deviceOption?.width ?? 1440) * 1.6);

    if (pages.length === 0) {
        return (
            <>
                <Head title="Heatmap" />
                <div className="space-y-6 p-4">
                    <Heading
                        title="Heatmap"
                        description={`Click, attention and scroll maps for ${site.name}`}
                    />

                    {/* Shown here too: with a window applied, "no data" may
                        just mean this window, and the reader needs a way back
                        out rather than a dead end. */}
                    <DateRangePicker
                        range={range}
                        basePath={`/a/${account.uid}/heatmap`}
                    />

                    <Card>
                        <CardContent className="py-12 text-center">
                            <MousePointerClick className="text-muted-foreground mx-auto size-8" />
                            <p className="mt-3 text-sm font-medium">
                                No heatmap data for {range.label.toLowerCase()}
                            </p>
                            <p className="text-muted-foreground mx-auto mt-1 max-w-md text-sm">
                                Try a wider window, or check back once the
                                tracking script has reported clicks, pointer
                                movement and scroll depth from{" "}
                                {site.domain ?? "your site"}.
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Heatmap" />

            <div className="space-y-4 p-4">
                <Heading
                    title="Heatmap"
                    description={`Click, attention and scroll maps for ${site.name}`}
                />

                {/* One filter row above everything it scopes: page, device and
                    map type all re-render the same view. */}
                <div className="flex flex-wrap items-center gap-2">
                    <DateRangePicker
                        range={range}
                        basePath={`/a/${account.uid}/heatmap`}
                    />

                    {variations.length > 0 && (
                        <Select
                            value={variationId ? String(variationId) : "all"}
                            onValueChange={(value) =>
                                navigate({
                                    variation:
                                        value === "all"
                                            ? undefined
                                            : Number(value),
                                })
                            }
                        >
                            <SelectTrigger className="w-64">
                                <SelectValue placeholder="All visitors" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    All visitors
                                </SelectItem>
                                {/* Grouped by experiment: variation names
                                    repeat across tests, and "Variation B"
                                    alone says nothing about which test. */}
                                {variations.map((experiment) => (
                                    <SelectGroup key={experiment.id}>
                                        <SelectLabel>
                                            {experiment.name}
                                        </SelectLabel>
                                        {experiment.variations.map(
                                            (variation) => (
                                                <SelectItem
                                                    key={variation.id}
                                                    value={String(variation.id)}
                                                >
                                                    {variation.name}
                                                    {variation.is_control
                                                        ? " (control)"
                                                        : ""}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectGroup>
                                ))}
                            </SelectContent>
                        </Select>
                    )}

                    <Select
                        value={selectedUrl ?? undefined}
                        onValueChange={(url) => navigate({ url })}
                    >
                        <SelectTrigger className="w-full max-w-lg">
                            <SelectValue placeholder="Select a page" />
                        </SelectTrigger>
                        <SelectContent>
                            {pages.map((page) => (
                                <SelectItem
                                    key={page.url_hash}
                                    value={page.url}
                                >
                                    <span className="truncate">{page.url}</span>
                                    <span className="text-muted-foreground ml-2">
                                        {page.sessions.toLocaleString()}{" "}
                                        sessions
                                    </span>
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Tabs
                        value={device}
                        onValueChange={(value) => navigate({ device: value })}
                    >
                        <TabsList>
                            {devices.map((option) => {
                                const Icon = DEVICE_ICONS[option.value];
                                const sessions =
                                    activePage?.devices?.[option.value] ?? 0;

                                return (
                                    <TabsTrigger
                                        key={option.value}
                                        value={option.value}
                                        className="gap-1.5"
                                        title={`${option.label} — ${option.width}px wide`}
                                    >
                                        <Icon className="size-3.5" />
                                        {option.label}
                                        <span className="text-muted-foreground tabular-nums">
                                            {sessions.toLocaleString()}
                                        </span>
                                    </TabsTrigger>
                                );
                            })}
                        </TabsList>
                    </Tabs>

                    <Tabs
                        value={mapType}
                        onValueChange={(value) => navigate({ type: value })}
                    >
                        <TabsList>
                            {MAP_TYPES.map((option) => (
                                <TabsTrigger
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </TabsTrigger>
                            ))}
                        </TabsList>
                    </Tabs>

                    {selectedUrl && (
                        <Button variant="ghost" size="sm" asChild>
                            <a
                                href={selectedUrl}
                                target="_blank"
                                rel="noreferrer noopener"
                            >
                                <ExternalLink /> Open page
                            </a>
                        </Button>
                    )}
                </div>

                <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_18rem]">
                    <Card className="overflow-hidden">
                        <CardContent className="space-y-3">
                            <ScreenshotNotice
                                screenshot={screenshot}
                                device={deviceOption}
                                capturing={capturing}
                                onCapture={requestCapture}
                            />

                            <HeatmapStage
                                src={screenshot?.src ?? null}
                                aspectWidth={stageWidth}
                                aspectHeight={stageHeight}
                                alt={`Screenshot of ${selectedUrl ?? site.name} at ${deviceOption?.label ?? device} width`}
                            >
                                {(size) =>
                                    mapType === "scroll" ? (
                                        <ScrollMap
                                            bands={scroll.bands}
                                            fold={scroll.fold}
                                            opacity={intensity}
                                        />
                                    ) : (
                                        <HeatmapCanvas
                                            points={points}
                                            width={size.width}
                                            height={size.height}
                                            radius={radius}
                                            intensity={intensity}
                                            palette={palette}
                                        />
                                    )
                                }
                            </HeatmapStage>

                            <p className="text-muted-foreground text-xs">
                                {
                                    MAP_TYPES.find(
                                        (option) => option.value === mapType,
                                    )?.description
                                }{" "}
                                {mapType !== "scroll" &&
                                    `${points.length.toLocaleString()} density cells from ${(summary?.sessions ?? 0).toLocaleString()} ${deviceOption?.label.toLowerCase()} sessions.`}
                            </p>
                        </CardContent>
                    </Card>

                    <div className="space-y-4">
                        {mapType === "scroll" && (
                            <ScrollReachPanel scroll={scroll} />
                        )}

                        <SummaryPanel
                            summary={summary}
                            scroll={scroll}
                            device={deviceOption}
                        />

                        <Controls
                            mapType={mapType}
                            palette={palette}
                            onPalette={setPalette}
                            intensity={intensity}
                            onIntensity={setIntensity}
                            radius={radius}
                            onRadius={setRadius}
                        />
                    </div>
                </div>
            </div>
        </>
    );
}

function ScreenshotNotice({
    screenshot,
    device,
    capturing,
    onCapture,
}: {
    screenshot: PageScreenshot | null;
    device: DeviceOption | undefined;
    capturing: boolean;
    onCapture: () => void;
}) {
    const label = device?.label.toLowerCase() ?? "this device";

    if (capturing) {
        return (
            <div className="border-border bg-muted/50 flex items-center gap-2 border px-3 py-2 text-xs">
                <Spinner className="size-3.5" />
                Capturing the page at {device?.width}px wide — this takes a few
                seconds.
            </div>
        );
    }

    if (!screenshot || screenshot.status === "failed") {
        return (
            <div className="border-border bg-muted/50 flex flex-wrap items-center justify-between gap-2 border px-3 py-2 text-xs">
                <span className="text-muted-foreground">
                    {screenshot?.status === "failed" ? (
                        <span className="text-foreground flex items-center gap-1.5">
                            <TriangleAlert className="size-3.5 text-amber-600 dark:text-amber-400" />
                            The last capture failed: {screenshot.error}
                        </span>
                    ) : (
                        <>
                            No {label} screenshot yet — the map is shown over a
                            blank page outline.
                        </>
                    )}
                </span>
                <Button size="sm" variant="outline" onClick={onCapture}>
                    <Camera />{" "}
                    {screenshot?.status === "failed"
                        ? "Try again"
                        : "Capture screenshot"}
                </Button>
            </div>
        );
    }

    if (screenshot.status === "pending") {
        return (
            <div className="border-border bg-muted/50 flex items-center gap-2 border px-3 py-2 text-xs">
                <Spinner className="size-3.5" />
                Capture queued — reload in a moment.
            </div>
        );
    }

    return (
        <div className="border-border bg-muted/50 text-muted-foreground flex flex-wrap items-center justify-between gap-2 border px-3 py-2 text-xs">
            <span className="flex flex-wrap items-center gap-x-2 gap-y-1">
                <span>
                    Captured at {screenshot.width}×{screenshot.height}px
                    {screenshot.captured_at &&
                        ` on ${new Date(screenshot.captured_at).toLocaleDateString()}`}
                </span>
                {screenshot.truncated && (
                    <Badge variant="secondary" className="gap-1">
                        <TriangleAlert className="size-3" />
                        Page cut off at {screenshot.height}px of{" "}
                        {screenshot.full_height}px
                    </Badge>
                )}
                {screenshot.stale && (
                    <Badge variant="secondary">
                        Capture may be out of date
                    </Badge>
                )}
            </span>
            <Button size="sm" variant="ghost" onClick={onCapture}>
                <Camera /> Recapture
            </Button>
        </div>
    );
}

/**
 * Reach at every tenth of the page.
 *
 * The map itself encodes reach as a colour wash, which is a continuous scale —
 * so the numbers have to be readable somewhere without hovering or squinting
 * at a gradient. This is that reading.
 */
function ScrollReachPanel({ scroll }: { scroll: ScrollDepth }) {
    const steps = scroll.bands.filter((band) => band.depth % 10 === 0);

    if (steps.length === 0) {
        return null;
    }

    return (
        <Card className="gap-3 py-4">
            <div className="px-4">
                <p className="text-foreground text-xs font-medium">
                    Scroll reach
                </p>
                <p className="text-muted-foreground mt-0.5 text-[11px]">
                    Share of sessions that got at least this far
                </p>
            </div>
            <dl className="space-y-1.5 px-4">
                {steps.map((band) => (
                    <div
                        key={band.depth}
                        className="flex items-center gap-2 text-xs"
                    >
                        <dt className="text-muted-foreground w-8 shrink-0 tabular-nums">
                            {band.depth}%
                        </dt>
                        <dd className="flex flex-1 items-center gap-2">
                            <span className="bg-muted h-1.5 flex-1">
                                <span
                                    className="block h-full bg-[#2a78d6] dark:bg-[#3987e5]"
                                    style={{ width: `${band.reach}%` }}
                                />
                            </span>
                            <span className="text-foreground w-9 shrink-0 text-right font-medium tabular-nums">
                                {band.reach.toFixed(0)}%
                            </span>
                        </dd>
                    </div>
                ))}
            </dl>
        </Card>
    );
}

function SummaryPanel({
    summary,
    scroll,
    device,
}: {
    summary: HeatmapSummary | null;
    scroll: ScrollDepth;
    device: DeviceOption | undefined;
}) {
    if (!summary) {
        return null;
    }

    const rows: [string, string][] = [
        ["Sessions", summary.sessions.toLocaleString()],
        ["Clicks", summary.clicks.toLocaleString()],
        ["Clicks per session", summary.clicks_per_session.toFixed(1)],
        ["Average scroll depth", `${scroll.average.toFixed(0)}%`],
        [
            "Typical viewport",
            summary.viewport_width
                ? `${summary.viewport_width}×${summary.viewport_height}px`
                : "—",
        ],
        [
            "Typical page height",
            summary.doc_height
                ? `${summary.doc_height.toLocaleString()}px`
                : "—",
        ],
    ];

    return (
        <Card className="gap-3 py-4">
            <div className="px-4">
                <p className="text-foreground text-xs font-medium">
                    {device?.label ?? "Device"} summary
                </p>
            </div>
            <dl className="space-y-2 px-4">
                {rows.map(([label, value]) => (
                    <div
                        key={label}
                        className="flex items-baseline justify-between gap-3 text-xs"
                    >
                        <dt className="text-muted-foreground">{label}</dt>
                        <dd className="text-foreground font-medium tabular-nums">
                            {value}
                        </dd>
                    </div>
                ))}
            </dl>
        </Card>
    );
}

function Controls({
    mapType,
    palette,
    onPalette,
    intensity,
    onIntensity,
    radius,
    onRadius,
}: {
    mapType: MapType;
    palette: HeatmapPalette;
    onPalette: (value: HeatmapPalette) => void;
    intensity: number;
    onIntensity: (value: number) => void;
    radius: number;
    onRadius: (value: number) => void;
}) {
    return (
        <Card className="gap-3 py-4">
            <div className="px-4">
                <p className="text-foreground text-xs font-medium">Display</p>
            </div>

            <div className="space-y-4 px-4">
                {mapType !== "scroll" && (
                    <div className="space-y-1.5">
                        <p className="text-muted-foreground text-xs">
                            Colour scale
                        </p>
                        <Tabs
                            value={palette}
                            onValueChange={(value) =>
                                onPalette(value as HeatmapPalette)
                            }
                        >
                            <TabsList className="w-full">
                                <TabsTrigger value="thermal" className="flex-1">
                                    Thermal
                                </TabsTrigger>
                                <TabsTrigger value="mono" className="flex-1">
                                    Mono
                                </TabsTrigger>
                            </TabsList>
                        </Tabs>
                        <p className="text-muted-foreground text-[11px]">
                            Mono is a single-hue ramp for colour-vision-safe
                            reading and printing.
                        </p>
                    </div>
                )}

                <label className="block space-y-1.5">
                    <span className="text-muted-foreground flex items-center justify-between text-xs">
                        Intensity
                        <span className="tabular-nums">
                            {Math.round(intensity * 100)}%
                        </span>
                    </span>
                    <input
                        type="range"
                        min={20}
                        max={100}
                        value={Math.round(intensity * 100)}
                        onChange={(event) =>
                            onIntensity(Number(event.target.value) / 100)
                        }
                        className="accent-primary w-full"
                    />
                </label>

                {mapType !== "scroll" && (
                    <label className="block space-y-1.5">
                        <span className="text-muted-foreground flex items-center justify-between text-xs">
                            Spot size
                            <span className="tabular-nums">{radius}px</span>
                        </span>
                        <input
                            type="range"
                            min={6}
                            max={48}
                            value={radius}
                            onChange={(event) =>
                                onRadius(Number(event.target.value))
                            }
                            className="accent-primary w-full"
                        />
                    </label>
                )}

                {mapType !== "scroll" && <ScaleLegend palette={palette} />}
            </div>
        </Card>
    );
}

/**
 * A continuous colour scale always ships its legend — without it the field is
 * decoration.
 */
function ScaleLegend({ palette }: { palette: HeatmapPalette }) {
    const gradient =
        palette === "thermal"
            ? "linear-gradient(to right, #2b71be, #1baf7a, #eda100, #eb6834, #c81e1e)"
            : "linear-gradient(to right, #cde2fb, #86b6ef, #2a78d6, #0d366b)";

    return (
        <div className="space-y-1">
            <div className="h-2 w-full" style={{ background: gradient }} />
            <div className="text-muted-foreground flex justify-between text-[10px]">
                <span>Few interactions</span>
                <span>Most</span>
            </div>
        </div>
    );
}

HeatmapShow.layout = (props: { account: Company; site: Site }) => ({
    breadcrumbs: [
        { title: props.account.name, href: sitesIndexUrl(props.account.uid) },
        { title: props.site.name, href: siteOverviewUrl(props.account.uid) },
        { title: "Heatmap", href: siteHeatmapUrl(props.account.uid) },
    ],
});
