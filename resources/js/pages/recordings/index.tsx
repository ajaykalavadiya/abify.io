import { Head, Link, router } from "@inertiajs/react";
import { AlertTriangle, MousePointerClick, Play, Video } from "lucide-react";
import Heading from "@/components/heading";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
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
    recordingShowUrl,
    siteOverviewUrl,
    siteRecordingsUrl,
    sitesIndexUrl,
} from "@/lib/urls";
import type { Company, Site } from "@/types";

type Recording = {
    id: number;
    session_id: string;
    started_at: string | null;
    duration_ms: number;
    event_count: number;
    bytes: number;
    has_rage_click: boolean;
    has_error: boolean;
    entry_url: string | null;
    exit_url: string | null;
    pageview_count: number;
    device_type: string | null;
    referrer: string | null;
};

const FILTERS = [
    { value: "all", label: "All recordings" },
    { value: "rage", label: "Rage clicks" },
    { value: "errors", label: "JS errors" },
];

function duration(ms: number) {
    if (!ms) return "—";

    const total = Math.round(ms / 1000);
    const minutes = Math.floor(total / 60);

    return `${minutes}:${String(total % 60).padStart(2, "0")}`;
}

function path(url: string | null) {
    if (!url) return "—";

    try {
        return new URL(url).pathname || "/";
    } catch {
        return url;
    }
}

export default function RecordingsIndex({
    account,
    site,
    filter,
    variationId,
    variations,
    recordings,
}: {
    account: Company;
    site: Site;
    filter: string;
    variationId: number | null;
    variations: { id: number; label: string }[];
    recordings: {
        data: Recording[];
        current_page: number;
        last_page: number;
        total: number;
    };
}) {
    const go = (params: {
        filter?: string;
        variation?: number;
        page?: number;
    }) =>
        router.get(
            siteRecordingsUrl(account.uid, {
                filter,
                variation: variationId ?? undefined,
                ...params,
            }),
            {},
            { preserveState: true, preserveScroll: true },
        );

    return (
        <>
            <Head title="Recordings" />

            <div className="space-y-6 p-4">
                <Heading
                    title="Recordings"
                    description={`${recordings.total} recorded visit${recordings.total === 1 ? "" : "s"} on ${site.name}`}
                />

                <div className="flex flex-wrap gap-2">
                    <Select
                        value={filter}
                        onValueChange={(value) =>
                            go({ filter: value, page: 1 })
                        }
                    >
                        <SelectTrigger className="w-52">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {FILTERS.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    {variations.length > 0 && (
                        <Select
                            value={variationId ? String(variationId) : "all"}
                            onValueChange={(value) =>
                                go({
                                    variation:
                                        value === "all"
                                            ? undefined
                                            : Number(value),
                                    page: 1,
                                })
                            }
                        >
                            <SelectTrigger className="w-72">
                                <SelectValue placeholder="Any variation" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    Any variation
                                </SelectItem>
                                {variations.map((variation) => (
                                    <SelectItem
                                        key={variation.id}
                                        value={String(variation.id)}
                                    >
                                        {variation.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    )}
                </div>

                <Card>
                    <CardContent>
                        {recordings.data.length === 0 ? (
                            <div className="text-muted-foreground py-10 text-center text-sm">
                                <Video className="mx-auto mb-3 size-6 opacity-40" />
                                <p>No recordings yet.</p>
                                <p className="mt-1 text-xs">
                                    Turn recording on for this site from the{" "}
                                    <Link
                                        href={`/a/${account.uid}/overview`}
                                        className="underline underline-offset-4"
                                    >
                                        site overview
                                    </Link>
                                    , then visits will start appearing here.
                                </p>
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Started</TableHead>
                                        <TableHead>Duration</TableHead>
                                        <TableHead>Pages</TableHead>
                                        <TableHead>Landed on</TableHead>
                                        <TableHead>Device</TableHead>
                                        <TableHead>Signals</TableHead>
                                        <TableHead className="text-right">
                                            Watch
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {recordings.data.map((recording) => (
                                        <TableRow key={recording.id}>
                                            <TableCell className="whitespace-nowrap">
                                                {recording.started_at
                                                    ? new Date(
                                                          recording.started_at,
                                                      ).toLocaleString()
                                                    : "—"}
                                            </TableCell>
                                            <TableCell>
                                                {duration(
                                                    recording.duration_ms,
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {recording.pageview_count}
                                            </TableCell>
                                            <TableCell
                                                className="max-w-[18rem] truncate"
                                                title={
                                                    recording.entry_url ??
                                                    undefined
                                                }
                                            >
                                                {path(recording.entry_url)}
                                            </TableCell>
                                            <TableCell className="capitalize">
                                                {recording.device_type ?? "—"}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex gap-1">
                                                    {recording.has_rage_click && (
                                                        <Badge
                                                            variant="secondary"
                                                            className="gap-1 text-rose-600 dark:text-rose-400"
                                                        >
                                                            <MousePointerClick className="size-3" />
                                                            rage
                                                        </Badge>
                                                    )}
                                                    {recording.has_error && (
                                                        <Badge
                                                            variant="secondary"
                                                            className="gap-1"
                                                        >
                                                            <AlertTriangle className="size-3" />
                                                            error
                                                        </Badge>
                                                    )}
                                                    {!recording.has_rage_click &&
                                                        !recording.has_error && (
                                                            <span className="text-muted-foreground">
                                                                —
                                                            </span>
                                                        )}
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link
                                                        href={recordingShowUrl(
                                                            account.uid,
                                                            recording.id,
                                                        )}
                                                    >
                                                        <Play /> Watch
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

                {recordings.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={recordings.current_page <= 1}
                            onClick={() =>
                                go({ page: recordings.current_page - 1 })
                            }
                        >
                            Previous
                        </Button>
                        <span className="text-muted-foreground text-xs">
                            Page {recordings.current_page} of{" "}
                            {recordings.last_page}
                        </span>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={
                                recordings.current_page >= recordings.last_page
                            }
                            onClick={() =>
                                go({ page: recordings.current_page + 1 })
                            }
                        >
                            Next
                        </Button>
                    </div>
                )}
            </div>
        </>
    );
}

RecordingsIndex.layout = (props: { account: Company; site: Site }) => ({
    breadcrumbs: [
        { title: props.account.name, href: sitesIndexUrl(props.account.uid) },
        { title: props.site.name, href: siteOverviewUrl(props.account.uid) },
        { title: "Recordings", href: siteRecordingsUrl(props.account.uid) },
    ],
});
