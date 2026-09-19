import { Head, Link } from "@inertiajs/react";
import {
    ArrowUpRight,
    BarChart3,
    Bug,
    FlaskConical,
    Flame,
    MousePointerClick,
    PlugZap,
    ShoppingBag,
    ShoppingCart,
    Split,
    Target,
    Video,
} from "lucide-react";
import { MarketingFooter } from "@/components/marketing-footer";
import { MarketingHeader } from "@/components/marketing-header";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { register } from "@/routes";

const sections = [
    {
        slug: "ab-testing",
        icon: FlaskConical,
        title: "A/B testing",
        description:
            "Swap text, HTML, or CSS on any element by CSS selector — no code deploy needed. Add multiple variations with independent traffic weights, and a control is created automatically so you always have a baseline.",
    },
    {
        slug: "url-targeting",
        icon: Target,
        title: "URL targeting",
        description:
            "Every test is scoped to the page(s) it actually applies to — exact match, contains, or full regex. Visitors are only bucketed and see variations on pages that match, so your results stay clean.",
    },
    {
        slug: "split-url-tests",
        icon: Split,
        title: "Split URL tests",
        description:
            "For changes too big for an element swap, redirect a percentage of visitors to a completely different page. Assignment is sticky per visitor, so nobody bounces between versions.",
    },
    {
        slug: "heatmaps",
        icon: MousePointerClick,
        title: "Heatmaps",
        description:
            "Clicks, mouse movement, and scroll depth are captured from the same tracking script and aggregated per page, so you can see where attention actually goes without a separate integration.",
    },
    {
        slug: "analytics",
        icon: BarChart3,
        title: "Analytics",
        description:
            "Impressions and conversion rate per variation, compared directly against control, with pageview and custom-event goals — so you can tell whether a result is real or noise.",
    },
    {
        slug: "variation-heatmaps",
        icon: Flame,
        title: "Heatmaps by variation",
        description:
            "A conversion rate tells you a variation lost; it does not tell you why. See the click, attention and scroll maps drawn only from the visitors who saw that version \u2014 straight from the variation's row in the test report.",
    },
    {
        slug: "shopify",
        icon: ShoppingBag,
        title: "Shopify",
        description:
            "Install from the theme editor as an app embed \u2014 no theme code to edit. Paid orders are attributed to the variation the buyer saw, bridged through a cart attribute because Shopify sandboxes its own checkout.",
    },
    {
        slug: "revenue-tracking",
        icon: ShoppingCart,
        title: "Revenue tracking",
        description:
            "Test against money, not just clicks. Orders are attributed to the variation the buyer actually saw, and reported as revenue per visitor and average order value — because a variation can convert less often and still win on revenue.",
    },
    {
        slug: "server-side-conversions",
        icon: PlugZap,
        title: "Server-side conversions",
        description:
            "Send orders straight from your store with an authenticated API call, so revenue is recorded even where a browser cannot reach — like a hosted checkout. Retried webhooks are deduplicated on your order id, so nothing is ever counted twice.",
    },
    {
        slug: "session-recording",
        icon: Video,
        title: "Session recording",
        description:
            "Watch real visits back, filtered by the variation the visitor was shown — so you can see why a test lost, not just that it did. Capture is capped per day and inputs are masked by default, so cost and privacy stay under control.",
    },
    {
        slug: "live-preview-debugging",
        icon: Bug,
        title: "Live preview & debugging",
        description:
            "Force any variation on your live site with one link and see a debug panel showing exactly what matched, what was applied, and what wasn't found — without it counting as real traffic.",
    },
];

export default function Features() {
    return (
        <>
            <Head title="Features" />

            <div className="bg-background text-foreground min-h-screen">
                <MarketingHeader />

                <section className="mx-auto max-w-3xl px-6 pt-16 pb-12 text-center">
                    <h1 className="text-4xl font-bold tracking-tight">
                        Everything you need to ship a test
                    </h1>
                    <p className="text-muted-foreground mt-4 text-lg">
                        One tracking script covers testing, analytics, and
                        heatmaps — no separate tools to wire together.
                    </p>
                </section>

                <section className="mx-auto max-w-6xl px-6 pb-24">
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {sections.map((section) => (
                            <Link
                                key={section.slug}
                                href={`/features/${section.slug}`}
                            >
                                <Card className="h-full rounded-2xl transition-colors hover:border-primary/50">
                                    <CardHeader>
                                        <section.icon className="text-primary size-6" />
                                        <CardTitle className="pt-2">
                                            {section.title}
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <p className="text-muted-foreground text-sm">
                                            {section.description}
                                        </p>
                                        <span className="text-primary mt-4 inline-flex items-center gap-1 text-sm font-medium">
                                            Learn more
                                            <ArrowUpRight className="size-3.5" />
                                        </span>
                                    </CardContent>
                                </Card>
                            </Link>
                        ))}
                    </div>
                </section>

                <section className="mx-auto max-w-3xl px-6 pb-24 text-center">
                    <Button size="lg" asChild>
                        <Link href={register()}>Start your free trial</Link>
                    </Button>
                </section>

                <MarketingFooter />
            </div>
        </>
    );
}
