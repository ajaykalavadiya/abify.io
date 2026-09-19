import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { billingCheckoutUrl, billingPortalUrl, billingUrl, sitesIndexUrl } from '@/lib/urls';
import type { Company, PlanLimits } from '@/types';

export default function BillingShow({
    account,
    planTier,
    planLimits,
    usage,
    subscribed,
    onTrial,
    trialEndsAt,
    pricesConfigured,
}: {
    account: Company;
    planTier: 'trial' | 'basic' | 'expired';
    planLimits: PlanLimits;
    usage: { sites: number };
    subscribed: boolean;
    onTrial: boolean;
    trialEndsAt: string | null;
    pricesConfigured: boolean;
}) {
    return (
        <>
            <Head title="Billing" />

            <div className="max-w-2xl space-y-6 p-4">
                <Heading title="Billing" description={`Plan and usage for ${account.name}`} />

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            {planLimits.label}
                            <Badge variant={planTier === 'expired' ? 'destructive' : 'default'}>{planTier}</Badge>
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        {onTrial && trialEndsAt && (
                            <p className="text-muted-foreground text-sm">
                                Trial ends {new Date(trialEndsAt).toLocaleDateString()}.
                            </p>
                        )}
                        {planTier === 'expired' && (
                            <p className="text-sm text-red-600 dark:text-red-400">
                                Your trial has ended. Subscribe below to create more sites or tests — existing
                                ones keep working.
                            </p>
                        )}
                        <p className="text-sm">
                            Sites: {usage.sites} / {planLimits.sites} · Tests per site: up to{' '}
                            {planLimits.tests_per_site}
                        </p>
                    </CardContent>
                </Card>

                {subscribed ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>Manage subscription</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Button asChild>
                                <a href={billingPortalUrl(account.uid)}>Manage billing</a>
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <Card>
                        <CardHeader>
                            <CardTitle>Subscribe to Basic</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {!pricesConfigured && (
                                <p className="text-muted-foreground text-sm">
                                    Checkout isn't configured yet — set STRIPE_KEY/STRIPE_SECRET and
                                    STRIPE_PRICE_BASIC_MONTHLY/STRIPE_PRICE_BASIC_YEARLY in .env.
                                </p>
                            )}
                            <div className="flex gap-4">
                                <Card className="flex-1">
                                    <CardHeader>
                                        <CardTitle>$10 / month</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <Button asChild disabled={!pricesConfigured}>
                                            {/* Plain <a>, not <Link> — this must be a full browser
                                                navigation so the redirect to Stripe's hosted checkout
                                                page works (Inertia intercepts SPA link clicks). */}
                                            <a href={pricesConfigured ? billingCheckoutUrl(account.uid, 'monthly') : undefined}>
                                                Subscribe monthly
                                            </a>
                                        </Button>
                                    </CardContent>
                                </Card>
                                <Card className="flex-1">
                                    <CardHeader>
                                        <CardTitle>$100 / year</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <Button asChild disabled={!pricesConfigured}>
                                            <a href={pricesConfigured ? billingCheckoutUrl(account.uid, 'yearly') : undefined}>
                                                Subscribe yearly
                                            </a>
                                        </Button>
                                    </CardContent>
                                </Card>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

BillingShow.layout = (props: { account: Company }) => ({
    breadcrumbs: [
        { title: props.account.name, href: sitesIndexUrl(props.account.uid) },
        { title: 'Billing', href: billingUrl(props.account.uid) },
    ],
});
