import { Head, useForm } from "@inertiajs/react";
import { CheckCircle2 } from "lucide-react";
import Heading from "@/components/heading";
import InputError from "@/components/input-error";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { siteOverviewUrl, sitesIndexUrl } from "@/lib/urls";
import type { Company, Site } from "@/types";

type Integration = {
    id: number;
    external_id: string;
    status: string;
    connected_at: string | null;
};

export default function ShopifyIntegration({
    account,
    site,
    configured,
    integration,
}: {
    account: Company;
    site: Site;
    configured: boolean;
    integration: Integration | null;
}) {
    const { data, setData, post, processing, errors } = useForm({ shop: "" });

    const connected = integration?.status === "active";

    return (
        <>
            <Head title="Shopify" />

            <div className="max-w-3xl space-y-6 p-4">
                <Heading
                    title="Shopify"
                    description={`Attribute orders from your store to the variation each buyer saw on ${site.name}`}
                />

                {!configured && (
                    <Card>
                        <CardContent className="py-6">
                            <p className="text-sm font-medium">
                                Shopify app credentials are not configured
                            </p>
                            <p className="text-muted-foreground mt-1 text-sm">
                                Set <code>SHOPIFY_API_KEY</code> and{" "}
                                <code>SHOPIFY_API_SECRET</code> before
                                connecting a store.
                            </p>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-sm font-medium">
                            {connected ? "Connected store" : "Connect a store"}
                            {connected && (
                                <Badge
                                    variant="secondary"
                                    className="gap-1 text-emerald-600 dark:text-emerald-400"
                                >
                                    <CheckCircle2 className="size-3" /> active
                                </Badge>
                            )}
                        </CardTitle>
                    </CardHeader>

                    <CardContent>
                        {connected ? (
                            <div className="space-y-2">
                                <p className="text-sm">
                                    {integration?.external_id}
                                </p>
                                <p className="text-muted-foreground text-xs">
                                    Paid orders are recorded against any
                                    experiment with a goal keyed{" "}
                                    <code>purchase</code>. Reconnect from this
                                    page to move the store to a different site.
                                </p>
                            </div>
                        ) : (
                            <form
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    post(
                                        `/a/${account.uid}/integrations/shopify`,
                                    );
                                }}
                                className="space-y-3"
                            >
                                <div className="space-y-2">
                                    <Label htmlFor="shop">Store domain</Label>
                                    <Input
                                        id="shop"
                                        placeholder="your-store.myshopify.com"
                                        value={data.shop}
                                        disabled={!configured}
                                        onChange={(event) =>
                                            setData("shop", event.target.value)
                                        }
                                    />
                                    <InputError message={errors.shop} />
                                </div>

                                <Button
                                    type="submit"
                                    disabled={processing || !configured}
                                >
                                    {processing
                                        ? "Redirecting…"
                                        : "Connect Shopify"}
                                </Button>
                            </form>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm font-medium">
                            What to expect
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="text-muted-foreground space-y-3 text-sm">
                        <p>
                            You can test your home, collection, product and cart
                            pages. You <strong>cannot</strong> test the checkout
                            itself on non-Plus stores — Shopify sandboxes it,
                            and no third-party script runs there.
                        </p>
                        <p>
                            Revenue is attributed by writing the visitor id into
                            a cart attribute, which Shopify carries onto the
                            order. A buyer who reaches checkout without the
                            storefront script having run is not attributed to
                            any variation, which is correct — their order is not
                            evidence about a test they were never in.
                        </p>
                        <p>
                            Create a goal keyed <code>purchase</code> on any
                            experiment you want measured by revenue.
                        </p>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

ShopifyIntegration.layout = (props: { account: Company; site: Site }) => ({
    breadcrumbs: [
        { title: props.account.name, href: sitesIndexUrl(props.account.uid) },
        { title: props.site.name, href: siteOverviewUrl(props.account.uid) },
        { title: "Shopify", href: "#" },
    ],
});
