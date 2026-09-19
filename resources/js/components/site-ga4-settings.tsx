import { useForm } from "@inertiajs/react";
import { CheckCircle2 } from "lucide-react";
import InputError from "@/components/input-error";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

/**
 * Connects the GA4 property that server-side conversions are reported into.
 *
 * Separate from the browser-side GA4 toggle: that one uses the gtag already
 * on the page, while this one needs credentials because it posts from the
 * server, where no page exists.
 */
export function SiteGa4Settings({
    accountUid,
    measurementId,
}: {
    accountUid: string;
    measurementId: string | null;
}) {
    const { data, setData, put, processing, errors, isDirty } = useForm({
        measurement_id: measurementId ?? "",
        api_secret: "",
    });

    const connected = Boolean(measurementId);

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2 text-sm font-medium">
                    Google Analytics 4 — server-side
                    {connected && (
                        <Badge
                            variant="secondary"
                            className="gap-1 text-emerald-600 dark:text-emerald-400"
                        >
                            <CheckCircle2 className="size-3" /> connected
                        </Badge>
                    )}
                </CardTitle>
                <p className="text-muted-foreground mt-1 text-xs">
                    Reports conversions that never touch a browser — a Shopify
                    order webhook arriving hours later — into your GA4 property.
                </p>
            </CardHeader>

            <CardContent>
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        put(`/a/${accountUid}/integrations/ga4`, {
                            preserveScroll: true,
                            onSuccess: () => setData("api_secret", ""),
                        });
                    }}
                    className="space-y-4"
                >
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="measurement_id">
                                Measurement ID
                            </Label>
                            <Input
                                id="measurement_id"
                                placeholder="G-XXXXXXXXXX"
                                value={data.measurement_id}
                                onChange={(event) =>
                                    setData(
                                        "measurement_id",
                                        event.target.value,
                                    )
                                }
                            />
                            <p className="text-muted-foreground text-xs">
                                Clear this field and save to disconnect.
                            </p>
                            <InputError message={errors.measurement_id} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="api_secret">API secret</Label>
                            <Input
                                id="api_secret"
                                type="password"
                                autoComplete="off"
                                placeholder={
                                    connected
                                        ? "Stored — leave blank to keep"
                                        : "From GA4 Admin → Data Streams"
                                }
                                value={data.api_secret}
                                onChange={(event) =>
                                    setData("api_secret", event.target.value)
                                }
                            />
                            <p className="text-muted-foreground text-xs">
                                Encrypted at rest and never shown again.
                            </p>
                            <InputError message={errors.api_secret} />
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
