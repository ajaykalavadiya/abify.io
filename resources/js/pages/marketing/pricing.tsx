import { Head, Link } from "@inertiajs/react";
import { ArrowRight, Check, ChevronDown, Minus } from "lucide-react";
import { useState } from "react";
import { MarketingFooter } from "@/components/marketing-footer";
import { MarketingHeader } from "@/components/marketing-header";
import { Button } from "@/components/ui/button";
import { register } from "@/routes";

type PlanLimits = {
    label: string;
    sites: number;
    tests_per_site: number;
    heatmaps_per_month: number;
    recordings_per_day: number;
};

type Pricing = {
    trialDays: number;
    maxUsers: number;
    monthly: number;
    yearly: number;
    growthMonthly: number;
    growthYearly: number;
    trial: PlanLimits;
    basic: PlanLimits;
    growth: PlanLimits;
};

type Period = "monthly" | "yearly";

const included = [
    "A/B tests — text, HTML, and CSS changes by selector",
    "Split URL tests with sticky, redirect-based assignment",
    "Multivariate tests across factors and combinations",
    "Exact, contains, and regex URL targeting",
    "Click, movement, and scroll-depth heatmaps",
    "Impressions and conversion rate per variation",
    "Pageview and custom-event goals",
    "Forced preview links and the match debugger",
];

const faqs = [
    {
        question: "What happens when my trial ends?",
        answer: "You keep everything you've built, and tests that are already running keep running. You just can't create new sites or tests until you subscribe.",
    },
    {
        question: 'What counts as a "site"?',
        answer: "One domain running the tracking script. Each site holds its own tests, heatmaps, and analytics, up to your plan's per-site test limit.",
    },
    {
        question: "What's the difference between monthly and yearly?",
        answer: "Identical plan and identical limits — yearly is billed once at $100 instead of $10 twelve times, so you pay for ten months and get twelve.",
    },
    {
        question: "Do you charge by traffic or by test volume?",
        answer: "No. The price is the price regardless of how many visitors get bucketed or how much heatmap data a site collects. The only limits are sites and tests per site.",
    },
    {
        question: "Can I change plans or cancel later?",
        answer: "Yes — both are handled from the billing page in your account through the Stripe customer portal, and cancelling leaves your data in place.",
    },
    {
        question: "Do I need a card to start the trial?",
        answer: "No. The trial starts on registration with no card, and nothing charges automatically when it ends.",
    },
];

export default function Pricing({ pricing }: { pricing: Pricing }) {
    const [period, setPeriod] = useState<Period>("monthly");

    const yearlyMonthly = (pricing.yearly / 12).toFixed(2).replace(/\.00$/, "");
    const yearlySaving = pricing.monthly * 12 - pricing.yearly;

    const growthYearlyMonthly = (pricing.growthYearly / 12)
        .toFixed(2)
        .replace(/\.00$/, "");
    const growthYearlySaving =
        pricing.growthMonthly * 12 - pricing.growthYearly;

    const count = (value: number) => value.toLocaleString();

    const comparison: {
        label: string;
        trial: string | boolean;
        basic: string | boolean;
        growth: string | boolean;
    }[] = [
        {
            label: "Sites",
            trial: `${pricing.trial.sites}`,
            basic: `${pricing.basic.sites}`,
            growth: `${pricing.growth.sites}`,
        },
        {
            label: "Tests per site",
            trial: `${pricing.trial.tests_per_site}`,
            basic: `${pricing.basic.tests_per_site}`,
            growth: `${pricing.growth.tests_per_site}`,
        },
        {
            label: "Heatmap sessions / month",
            trial: count(pricing.trial.heatmaps_per_month),
            basic: count(pricing.basic.heatmaps_per_month),
            growth: count(pricing.growth.heatmaps_per_month),
        },
        {
            label: "Session recordings / day",
            trial: count(pricing.trial.recordings_per_day),
            // A zero would render as a tick-shaped "0"; spelled out it reads
            // as what it is.
            basic: pricing.basic.recordings_per_day
                ? count(pricing.basic.recordings_per_day)
                : "Not included",
            growth: count(pricing.growth.recordings_per_day),
        },
        {
            label: "Team members",
            trial: `${pricing.maxUsers}`,
            basic: `${pricing.maxUsers}`,
            growth: `${pricing.maxUsers}`,
        },
        {
            label: "A/B and split URL tests",
            trial: true,
            basic: true,
            growth: true,
        },
        { label: "Multivariate tests", trial: true, basic: true, growth: true },
        {
            label: "Heatmaps and analytics",
            trial: true,
            basic: true,
            growth: true,
        },
        {
            label: "Heatmaps split by variation",
            trial: false,
            basic: false,
            growth: true,
        },
        { label: "Revenue tracking", trial: false, basic: false, growth: true },
        {
            label: "Shopify integration",
            trial: false,
            basic: false,
            growth: true,
        },
        {
            label: "Preview links and debugging",
            trial: true,
            basic: true,
            growth: true,
        },
        // Spelled out rather than a tick/dash: a checkmark against "card
        // required" reads as a benefit, which is the opposite of the meaning.
        {
            label: "Card required to start",
            trial: "No",
            basic: "At upgrade",
            growth: "At upgrade",
        },
    ];

    return (
        <>
            <Head title="Pricing" />

            <div className="bg-background text-foreground min-h-screen">
                <MarketingHeader />

                <section className="border-border border-b">
                    <div className="mx-auto max-w-3xl px-6 py-16 text-center">
                        <h1 className="text-4xl font-bold tracking-tight text-balance sm:text-5xl">
                            Simple plans. No sales call.
                        </h1>
                        <p className="text-muted-foreground mx-auto mt-5 max-w-xl text-lg text-balance">
                            Start with a {pricing.trialDays}-day trial, no card.
                            Upgrade when you need more than one site — the price
                            doesn't move with your traffic.
                        </p>

                        {/* Segmented control rather than two separate yearly/monthly
                            cards, so the plan itself reads as one thing. */}
                        <div
                            role="radiogroup"
                            aria-label="Billing period"
                            className="border-border mt-8 inline-flex border p-1"
                        >
                            {(["monthly", "yearly"] as const).map((value) => (
                                <button
                                    key={value}
                                    type="button"
                                    role="radio"
                                    aria-checked={period === value}
                                    onClick={() => setPeriod(value)}
                                    className={`px-5 py-2 text-sm font-medium capitalize transition-colors ${
                                        period === value
                                            ? "bg-primary text-primary-foreground"
                                            : "text-muted-foreground hover:text-foreground"
                                    }`}
                                >
                                    {value}
                                    {value === "yearly" && (
                                        <span
                                            className={
                                                period === "yearly"
                                                    ? "ml-2 text-xs opacity-90"
                                                    : "text-primary ml-2 text-xs font-semibold"
                                            }
                                        >
                                            save ${yearlySaving}
                                        </span>
                                    )}
                                </button>
                            ))}
                        </div>
                    </div>
                </section>

                <section className="mx-auto max-w-4xl px-6 py-16">
                    <div className="grid gap-6 md:grid-cols-3">
                        <div className="border-border flex flex-col border p-8">
                            <div className="text-sm font-semibold tracking-wide uppercase">
                                {pricing.trial.label}
                            </div>
                            <div className="mt-4 flex items-baseline gap-1">
                                <span className="text-4xl font-bold">Free</span>
                                <span className="text-muted-foreground">
                                    for {pricing.trialDays} days
                                </span>
                            </div>
                            <p className="text-muted-foreground mt-3 text-sm leading-relaxed">
                                Enough to install the snippet and run a real
                                test end to end before you decide anything.
                            </p>

                            <ul className="mt-6 flex-1 space-y-3 text-sm">
                                <PlanPoint>
                                    {pricing.trial.sites} site,{" "}
                                    {pricing.trial.tests_per_site} test
                                </PlanPoint>
                                <PlanPoint>
                                    Heatmaps and analytics included
                                </PlanPoint>
                                <PlanPoint>No card required</PlanPoint>
                                <PlanPoint>
                                    Your data stays if you don't subscribe
                                </PlanPoint>
                            </ul>

                            <Button
                                variant="outline"
                                size="lg"
                                className="mt-8 w-full"
                                asChild
                            >
                                <Link href={register()}>Start free trial</Link>
                            </Button>
                        </div>

                        <div className="border-border relative flex flex-col border p-8">
                            <div className="text-sm font-semibold tracking-wide uppercase">
                                {pricing.basic.label}
                            </div>
                            <div className="mt-4 flex items-baseline gap-1">
                                <span className="text-4xl font-bold">
                                    $
                                    {period === "monthly"
                                        ? pricing.monthly
                                        : pricing.yearly}
                                </span>
                                <span className="text-muted-foreground">
                                    {period === "monthly" ? "/month" : "/year"}
                                </span>
                            </div>
                            <p className="text-muted-foreground mt-3 text-sm leading-relaxed">
                                {period === "monthly"
                                    ? `Billed monthly, cancel anytime. Yearly works out to $${yearlyMonthly} a month.`
                                    : `Billed once a year — $${yearlyMonthly} a month, two months cheaper than monthly.`}
                            </p>

                            <ul className="mt-6 flex-1 space-y-3 text-sm">
                                <PlanPoint>
                                    {pricing.basic.sites} sites,{" "}
                                    {pricing.basic.tests_per_site} tests per
                                    site
                                </PlanPoint>
                                <PlanPoint>
                                    Every test type — A/B, split URL,
                                    multivariate
                                </PlanPoint>
                                <PlanPoint>
                                    Heatmaps and analytics on every site
                                </PlanPoint>
                                <PlanPoint>
                                    Priced per account, never per visitor
                                </PlanPoint>
                            </ul>

                            <Button
                                variant="outline"
                                size="lg"
                                className="mt-8 w-full"
                                asChild
                            >
                                <Link href={register()}>Start free trial</Link>
                            </Button>
                        </div>

                        <div className="border-primary relative flex flex-col border-2 p-8">
                            <span className="bg-primary text-primary-foreground absolute -top-3 left-8 px-2 py-1 text-xs font-semibold tracking-wide uppercase">
                                For stores
                            </span>

                            <div className="text-sm font-semibold tracking-wide uppercase">
                                {pricing.growth.label}
                            </div>
                            <div className="mt-4 flex items-baseline gap-1">
                                <span className="text-4xl font-bold">
                                    $
                                    {period === "monthly"
                                        ? pricing.growthMonthly
                                        : pricing.growthYearly}
                                </span>
                                <span className="text-muted-foreground">
                                    {period === "monthly" ? "/month" : "/year"}
                                </span>
                            </div>
                            <p className="text-muted-foreground mt-3 text-sm leading-relaxed">
                                {period === "monthly"
                                    ? `Billed monthly, cancel anytime. Yearly works out to $${growthYearlyMonthly} a month.`
                                    : `Billed once a year — $${growthYearlyMonthly} a month, saving $${growthYearlySaving}.`}
                            </p>

                            <ul className="mt-6 flex-1 space-y-3 text-sm">
                                <PlanPoint>
                                    {count(pricing.growth.heatmaps_per_month)}{" "}
                                    heatmap sessions a month
                                </PlanPoint>
                                <PlanPoint>
                                    Heatmaps split by variation — see why a test
                                    lost
                                </PlanPoint>
                                <PlanPoint>
                                    {count(pricing.growth.recordings_per_day)}{" "}
                                    session recordings a day
                                </PlanPoint>
                                <PlanPoint>
                                    Revenue tracking and the Shopify integration
                                </PlanPoint>
                                <PlanPoint>
                                    {pricing.growth.sites} sites,{" "}
                                    {pricing.growth.tests_per_site} tests per
                                    site
                                </PlanPoint>
                            </ul>

                            <Button size="lg" className="mt-8 w-full" asChild>
                                <Link href={register()}>
                                    Start free trial
                                    <ArrowRight />
                                </Link>
                            </Button>
                            <p className="text-muted-foreground mt-3 text-center text-xs">
                                Trial first — you subscribe from inside the
                                account.
                            </p>
                        </div>
                    </div>
                </section>

                <section className="bg-muted/40 border-border border-y">
                    <div className="mx-auto max-w-4xl px-6 py-16">
                        <h2 className="text-center text-2xl font-bold tracking-tight">
                            Compare the plans
                        </h2>

                        <div className="border-border bg-background mt-8 overflow-x-auto border">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-border border-b">
                                        <th className="px-5 py-4 font-semibold">
                                            &nbsp;
                                        </th>
                                        <th className="px-5 py-4 font-semibold">
                                            {pricing.trial.label}
                                        </th>
                                        <th className="px-5 py-4 font-semibold">
                                            {pricing.basic.label}
                                        </th>
                                        <th className="text-primary px-5 py-4 font-semibold">
                                            {pricing.growth.label}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {comparison.map((row) => (
                                        <tr
                                            key={row.label}
                                            className="border-border border-b last:border-0"
                                        >
                                            <td className="px-5 py-4 font-medium">
                                                {row.label}
                                            </td>
                                            <td className="text-muted-foreground px-5 py-4">
                                                <Cell value={row.trial} />
                                            </td>
                                            <td className="text-muted-foreground px-5 py-4">
                                                <Cell value={row.basic} />
                                            </td>
                                            <td className="px-5 py-4">
                                                <Cell
                                                    value={row.growth}
                                                    highlight
                                                />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <p className="text-muted-foreground mt-4 text-center text-xs">
                            Limits are read from the same configuration the app
                            enforces them with.
                        </p>
                    </div>
                </section>

                <section className="mx-auto max-w-5xl px-6 py-16">
                    <h2 className="text-center text-2xl font-bold tracking-tight">
                        In both plans, always
                    </h2>
                    <p className="text-muted-foreground mx-auto mt-3 max-w-xl text-center">
                        There is no feature gate between the trial and the paid
                        plan — only how many sites and tests you can have.
                    </p>

                    <div className="mt-8 grid gap-3 sm:grid-cols-2">
                        {included.map((item) => (
                            <div
                                key={item}
                                className="border-border flex items-start gap-3 border p-4 text-sm"
                            >
                                <Check className="text-primary mt-0.5 size-4 shrink-0" />
                                <span className="leading-relaxed">{item}</span>
                            </div>
                        ))}
                    </div>
                </section>

                <section className="border-border border-t">
                    <div className="mx-auto max-w-3xl px-6 py-16">
                        <h2 className="mb-8 text-center text-2xl font-bold tracking-tight">
                            Frequently asked questions
                        </h2>

                        <div className="border-border divide-border divide-y border">
                            {faqs.map((faq) => (
                                <details
                                    key={faq.question}
                                    className="group open:bg-muted/30 p-5"
                                >
                                    <summary className="flex cursor-pointer list-none items-center justify-between gap-4 font-medium marker:content-none [&::-webkit-details-marker]:hidden">
                                        {faq.question}
                                        <ChevronDown className="text-muted-foreground size-4 shrink-0 transition-transform group-open:rotate-180" />
                                    </summary>
                                    <p className="text-muted-foreground mt-3 text-sm leading-relaxed">
                                        {faq.answer}
                                    </p>
                                </details>
                            ))}
                        </div>

                        <p className="text-muted-foreground mt-8 text-center text-sm">
                            Still deciding?{" "}
                            <Link
                                href="/contact"
                                className="text-primary font-medium hover:underline"
                            >
                                Ask us directly
                            </Link>{" "}
                            — or read{" "}
                            <Link
                                href="/why-abify"
                                className="text-primary font-medium hover:underline"
                            >
                                why abify.io exists
                            </Link>
                            .
                        </p>
                    </div>
                </section>

                <MarketingFooter />
            </div>
        </>
    );
}

function PlanPoint({ children }: { children: React.ReactNode }) {
    return (
        <li className="flex items-start gap-3">
            <Check className="text-primary mt-0.5 size-4 shrink-0" />
            <span className="leading-relaxed">{children}</span>
        </li>
    );
}

function Cell({
    value,
    highlight = false,
}: {
    value: string | boolean;
    highlight?: boolean;
}) {
    if (typeof value === "string") {
        return (
            <span className={highlight ? "text-foreground font-medium" : ""}>
                {value}
            </span>
        );
    }

    return value ? (
        <Check className="text-primary size-4" aria-label="Included" />
    ) : (
        <Minus
            className="text-muted-foreground size-4"
            aria-label="Not included"
        />
    );
}
