import { useForm } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import InputError from "@/components/input-error";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Checkbox } from "@/components/ui/checkbox";

export type RecordingSettings = {
    enabled: boolean;
    daily_limit: number;
    sample_rate: number;
    remaining_today: number;
};

export type IntegrationSettings = {
    gtm: boolean;
    ga4: boolean;
    /** Null when no GA4 property is connected. The secret is never sent here. */
    ga4_measurement_id: string | null;
};

/**
 * The two dials that decide what session recording costs: how many visits a
 * day are kept, and what share of traffic is even considered for capture.
 */
export function SiteRecordingSettings({
    accountUid,
    recording,
    integrations,
}: {
    accountUid: string;
    recording: RecordingSettings;
    integrations: IntegrationSettings;
}) {
    const { data, setData, put, processing, errors, isDirty } = useForm({
        enabled: recording.enabled,
        daily_limit: recording.daily_limit,
        sample_rate: recording.sample_rate,
        gtm: integrations.gtm,
        ga4: integrations.ga4,
    });

    return (
        <Card>
            <CardHeader className="flex flex-row items-start justify-between space-y-0">
                <div>
                    <CardTitle className="text-sm font-medium">
                        Session recording
                    </CardTitle>
                    <p className="text-muted-foreground mt-1 text-xs">
                        Replays of real visits, capped so storage and billing
                        stay predictable.
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <Checkbox
                        id="recording_enabled"
                        checked={data.enabled}
                        onCheckedChange={(checked) =>
                            setData("enabled", checked === true)
                        }
                    />
                    <Label
                        htmlFor="recording_enabled"
                        className="text-xs font-normal"
                    >
                        Enabled
                    </Label>
                </div>
            </CardHeader>

            <CardContent>
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        put(`/a/${accountUid}/recording`, {
                            preserveScroll: true,
                        });
                    }}
                    className="space-y-4"
                >
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="daily_limit">
                                Recordings per day
                            </Label>
                            <Input
                                id="daily_limit"
                                type="number"
                                min={0}
                                max={10000}
                                value={data.daily_limit}
                                disabled={!data.enabled}
                                onChange={(event) =>
                                    setData(
                                        "daily_limit",
                                        Number(event.target.value),
                                    )
                                }
                            />
                            <p className="text-muted-foreground text-xs">
                                {data.enabled
                                    ? `${recording.remaining_today} left today. Recording stops for the day once the cap is reached.`
                                    : "Recording is off — no visits are captured."}
                            </p>
                            <InputError message={errors.daily_limit} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="sample_rate">
                                Share of visits considered (%)
                            </Label>
                            <Input
                                id="sample_rate"
                                type="number"
                                min={1}
                                max={100}
                                value={data.sample_rate}
                                disabled={!data.enabled}
                                onChange={(event) =>
                                    setData(
                                        "sample_rate",
                                        Number(event.target.value),
                                    )
                                }
                            />
                            <p className="text-muted-foreground text-xs">
                                At 100% the day's recordings all come from
                                whoever arrives first. Lower it to spread them
                                across the day.
                            </p>
                            <InputError message={errors.sample_rate} />
                        </div>
                    </div>

                    <div className="space-y-3 border-t pt-4">
                        <p className="text-sm font-medium">Analytics</p>

                        <div className="flex items-start gap-2">
                            <Checkbox
                                id="gtm"
                                checked={data.gtm}
                                onCheckedChange={(checked) =>
                                    setData("gtm", checked === true)
                                }
                            />
                            <div>
                                <Label
                                    htmlFor="gtm"
                                    className="text-sm font-normal"
                                >
                                    Push assignments to the GTM dataLayer
                                </Label>
                                <p className="text-muted-foreground text-xs">
                                    Fires an <code>abify.experimentViewed</code>{" "}
                                    event so you can segment your existing
                                    reports by variation.
                                </p>
                            </div>
                        </div>

                        <div className="flex items-start gap-2">
                            <Checkbox
                                id="ga4"
                                checked={data.ga4}
                                onCheckedChange={(checked) =>
                                    setData("ga4", checked === true)
                                }
                            />
                            <div>
                                <Label
                                    htmlFor="ga4"
                                    className="text-sm font-normal"
                                >
                                    Send an event to Google Analytics 4
                                </Label>
                                <p className="text-muted-foreground text-xs">
                                    Uses the gtag already on your page — abify
                                    never loads its own GA4. Register{" "}
                                    <code>experiment_key</code> and{" "}
                                    <code>variation_key</code> as custom
                                    dimensions in GA4 or they will not appear in
                                    reports.
                                </p>
                            </div>
                        </div>
                    </div>

                    <Button type="submit" disabled={processing || !isDirty}>
                        {processing ? "Saving…" : "Save"}
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}
