import { Head, Link } from "@inertiajs/react";
// Imported statically, not alongside the dynamic JS import: Vite then ties
// the stylesheet to this page's chunk and Inertia loads it with the page. A
// dynamic CSS import left the controller rendering unstyled.
import "rrweb-player/dist/style.css";
import { AlertTriangle, ArrowLeft, MousePointerClick } from "lucide-react";
import { useEffect, useRef, useState } from "react";
import Heading from "@/components/heading";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Spinner } from "@/components/ui/spinner";
import {
    recordingEventsUrl,
    siteOverviewUrl,
    siteRecordingsUrl,
    sitesIndexUrl,
} from "@/lib/urls";
import type { eventWithTime } from "@rrweb/types";
import type { Company, Site } from "@/types";

/**
 * rrweb-player is a Svelte component, but Svelte itself is not a dependency
 * here, so its published types degrade to something without the component
 * lifecycle on it. This is the only part of the instance we touch.
 */
type PlayerInstance = { $destroy?: () => void };

type Recording = {
    id: number;
    session_id: string;
    started_at: string;
    duration_ms: number;
    event_count: number;
    chunk_count: number;
    bytes: number;
    has_rage_click: boolean;
    has_error: boolean;
    entry_url: string | null;
    exit_url: string | null;
    pageview_count: number;
    device_type: string | null;
    referrer: string | null;
};

type PlayerState = "loading" | "ready" | "empty" | "failed";

function Meta({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <dt className="text-muted-foreground text-xs">{label}</dt>
            <dd className="mt-0.5 text-sm break-words">{value}</dd>
        </div>
    );
}

export default function RecordingsShow({
    account,
    site,
    recording,
}: {
    account: Company;
    site: Site;
    recording: Recording;
}) {
    const wrapper = useRef<HTMLDivElement>(null);
    const mount = useRef<HTMLDivElement>(null);
    const [state, setState] = useState<PlayerState>("loading");

    useEffect(() => {
        let cancelled = false;
        let player: PlayerInstance | null = null;

        const load = async () => {
            try {
                // Both imported lazily: rrweb-player pulls in its own copy of
                // the replayer and its stylesheet, and none of it is worth
                // shipping to anyone who never opens a recording.
                const { default: RrwebPlayer } = await import("rrweb-player");

                const response = await fetch(
                    recordingEventsUrl(account.uid, recording.id),
                );

                if (!response.ok) throw new Error("Failed to load replay");

                const { events } = (await response.json()) as {
                    events: eventWithTime[];
                };

                if (cancelled) return;

                // rrweb cannot build a replay without at least a snapshot and
                // something to apply to it.
                if (!events || events.length < 2) {
                    setState("empty");
                    return;
                }

                if (!mount.current) return;

                // Measured from the wrapper, which is always laid out. The
                // mount itself must never be display:none at this point —
                // rrweb-player reads the width once, at construction, and a
                // hidden ancestor reports zero, which builds a player with no
                // viewport at all.
                const available =
                    mount.current.clientWidth ||
                    wrapper.current?.clientWidth ||
                    960;

                // Cast through unknown: with Svelte absent the published
                // Player type carries none of the lifecycle members, so this
                // is the narrowest honest way to keep hold of $destroy.
                player = new RrwebPlayer({
                    target: mount.current,
                    props: {
                        events,
                        // The recorded page had its own dimensions; the player
                        // scales the replay to fit whatever box it is given.
                        width: available,
                        height: Math.round(available * 0.62),
                        autoPlay: false,
                        showController: true,
                    },
                }) as unknown as PlayerInstance;

                setState("ready");
            } catch {
                if (!cancelled) setState("failed");
            }
        };

        void load();

        return () => {
            cancelled = true;
            player?.$destroy?.();
        };
    }, [account.uid, recording.id]);

    const seconds = Math.round(recording.duration_ms / 1000);
    const duration = `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, "0")}`;

    return (
        <>
            <Head title="Replay" />

            <div className="space-y-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-2">
                    <Heading
                        title="Replay"
                        description={`${new Date(recording.started_at).toLocaleString()} · ${duration} · ${recording.pageview_count} page${recording.pageview_count === 1 ? "" : "s"}`}
                    />

                    <Button variant="outline" size="sm" asChild>
                        <Link href={siteRecordingsUrl(account.uid)}>
                            <ArrowLeft /> All recordings
                        </Link>
                    </Button>
                </div>

                {(recording.has_rage_click || recording.has_error) && (
                    <div className="flex gap-2">
                        {recording.has_rage_click && (
                            <Badge
                                variant="secondary"
                                className="gap-1 text-rose-600 dark:text-rose-400"
                            >
                                <MousePointerClick className="size-3" />
                                Rage click
                            </Badge>
                        )}
                        {recording.has_error && (
                            <Badge variant="secondary" className="gap-1">
                                <AlertTriangle className="size-3" />
                                JS error
                            </Badge>
                        )}
                    </div>
                )}

                <Card>
                    <CardContent className="p-3">
                        {state === "loading" && (
                            <div className="flex items-center justify-center gap-2 py-16 text-sm">
                                <Spinner /> Loading replay…
                            </div>
                        )}

                        {state === "empty" && (
                            <p className="text-muted-foreground py-16 text-center text-sm">
                                This visit ended before enough was captured to
                                replay it.
                            </p>
                        )}

                        {state === "failed" && (
                            <p className="text-muted-foreground py-16 text-center text-sm">
                                The replay could not be loaded. Its recording
                                may have passed the retention window.
                            </p>
                        )}

                        {/* Always mounted and never display:none — the
                            player attaches here and measures this node's
                            width at construction. */}
                        <div ref={wrapper} className="w-full">
                            <div ref={mount} className="overflow-x-auto" />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent>
                        <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <Meta
                                label="Landed on"
                                value={recording.entry_url ?? "—"}
                            />
                            <Meta
                                label="Left from"
                                value={recording.exit_url ?? "—"}
                            />
                            <Meta
                                label="Device"
                                value={recording.device_type ?? "—"}
                            />
                            <Meta
                                label="Referrer"
                                value={recording.referrer ?? "Direct"}
                            />
                        </dl>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

RecordingsShow.layout = (props: {
    account: Company;
    site: Site;
    recording: Recording;
}) => ({
    breadcrumbs: [
        { title: props.account.name, href: sitesIndexUrl(props.account.uid) },
        { title: props.site.name, href: siteOverviewUrl(props.account.uid) },
        { title: "Recordings", href: siteRecordingsUrl(props.account.uid) },
        { title: "Replay", href: "#" },
    ],
});
