import { Head, Link } from "@inertiajs/react";
import {
    ArrowRight,
    ArrowUpRight,
    BarChart3,
    CalendarCheck,
    Check,
    ChevronDown,
    Code2,
    Eye,
    FlaskConical,
    KeyRound,
    LineChart,
    Mail,
    Megaphone,
    MousePointerClick,
    Rocket,
    ShieldCheck,
    ShoppingCart,
    Split,
    Users,
    Video,
} from "lucide-react";
import { type FormEvent, useState } from "react";
import { toast } from "sonner";
import { HeroBackground } from "@/components/marketing/hero-background";
import { TestimonialsCarousel } from "@/components/marketing/testimonials-carousel";
import { MarketingFooter } from "@/components/marketing-footer";
import { MarketingHeader } from "@/components/marketing-header";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { register } from "@/routes";

const chips = [
    { bg: "bg-primary/10", text: "text-primary" },
    { bg: "bg-violet-500/10", text: "text-violet-500" },
    { bg: "bg-rose-500/10", text: "text-rose-500" },
];

const testimonials = [
    {
        initials: "JR",
        name: "Jordan Reyes",
        role: "Growth lead, early access user",
        quote: "We finally stopped guessing. Shipping a test now takes minutes instead of a sprint, and the heatmap makes it obvious when a 'winning' variation only won because of one confused visitor.",
    },
    {
        initials: "PN",
        name: "Priya Nandakumar",
        role: "Founder, solo SaaS",
        quote: "One script tag and I had a real headline test running in under an hour — no deploy, no waiting on an engineer who had five other things to do first.",
    },
    {
        initials: "MW",
        name: "Marcus Webb",
        role: "Head of growth, early access user",
        quote: "Split URL tests were the missing piece for us. We could finally compare a full page rebuild against the original without hacking it together as a giant CSS override.",
    },
    {
        initials: "EO",
        name: "Elena Ortiz",
        role: "E-commerce lead, early access user",
        quote: "The click heatmap showed people trying to tap a product image that wasn't a link. Fixed it in an afternoon — that alone paid for the subscription.",
    },
    {
        initials: "SO",
        name: "Sam Okafor",
        role: "Product manager, early access user",
        quote: "Being able to belong to two accounts and switch between them from one login sounds small until you're consulting for more than one team. It just works.",
    },
    {
        initials: "TL",
        name: "Tara Lindqvist",
        role: "Marketing manager, early access user",
        quote: "Impressions and conversion per variation, right next to each other, no spreadsheet required. It's the first analytics view our whole team actually opens.",
    },
];

const stats = [
    { value: "3", label: "test types — element, CSS/JS, split URL" },
    { value: "1 line", label: "script tag to install" },
    { value: "14 days", label: "free trial, no card required" },
    { value: "<60s", label: "to bucket, apply, and reveal a page" },
];

const features = [
    {
        slug: "ab-testing",
        icon: FlaskConical,
        title: "A/B testing",
        description:
            "Swap CSS, text, or HTML on any element by selector — no code deploy needed. Target specific pages by exact URL, a substring, or a regex pattern.",
    },
    {
        slug: "split-url-tests",
        icon: Split,
        title: "Split URL tests",
        description:
            "Send a percentage of visitors to a completely different page and compare conversion, redirect-based, sticky per visitor.",
    },
    {
        slug: "heatmaps",
        icon: MousePointerClick,
        title: "Heatmaps",
        description:
            "Clicks, mouse movement, and scroll depth, aggregated per page automatically from the same one-line tracking script.",
    },
    {
        slug: "analytics",
        icon: BarChart3,
        title: "Analytics",
        description:
            "Impressions and conversion rate per variation, compared against control, so you know which version actually wins.",
    },
    {
        slug: "revenue-tracking",
        icon: ShoppingCart,
        title: "Revenue tracking",
        description:
            "Attribute orders to the variation the buyer actually saw. Reported as revenue per visitor, because the version that converts more often is not always the one that earns more.",
    },
    {
        slug: "session-recording",
        icon: Video,
        title: "Session recording",
        description:
            "Replay real visits, filtered by variation, so you can see why a test lost. Capped per day and masked by default.",
    },
];

const orgTeams = [
    {
        value: "marketers",
        label: "Marketers",
        icon: Megaphone,
        iconText: "text-violet-500",
        panelBg: "bg-violet-500/5",
        pitch: "Test headlines, offers, and full page redesigns without waiting on a deploy. Ship the version that actually converts, backed by real data.",
        bullets: [
            "Launch a test on any page in minutes, no engineering ticket required",
            "Compare a full redesign against the original with split URL tests",
            "See conversion lift per variation before you commit to a change",
        ],
    },
    {
        value: "product",
        label: "Product managers",
        icon: Rocket,
        iconText: "text-rose-500",
        panelBg: "bg-rose-500/5",
        pitch: "Validate a new flow or feature before it ships to everyone. Roll it out to a slice of users, watch the numbers, then decide.",
        bullets: [
            "Trial a new flow with a percentage of visitors before a full launch",
            "Force any variation live to walk stakeholders through it",
            "See a debug panel of exactly what matched before you trust a result",
        ],
    },
    {
        value: "engineers",
        label: "Engineers",
        icon: Code2,
        iconText: "text-primary",
        panelBg: "bg-primary/5",
        pitch: "One script tag, no separate stack to maintain. Give other teams a safe way to ship changes without touching your deploy pipeline.",
        bullets: [
            "A single tracking script covers testing, heatmaps, and analytics",
            "CSS/selector-based changes ship without a code deploy",
            "A debug panel shows exactly what matched — nothing silently breaks",
        ],
    },
    {
        value: "analysts",
        label: "Analysts",
        icon: LineChart,
        iconText: "text-violet-500",
        panelBg: "bg-violet-500/5",
        pitch: "Impressions, conversion rate, and behavior data per variation — all from the same visitor bucket, so nothing needs reconciling.",
        bullets: [
            "Compare conversion rate per variation directly against control",
            "See clicks, scroll depth, and mouse movement per page",
            "Pageview or custom-event goals, tracked from the same script",
        ],
    },
];

const steps = [
    {
        title: "Add the script",
        description:
            "Create a site, paste one snippet in your <head>. It hides, buckets, applies, reveals.",
    },
    {
        title: "Create a test",
        description:
            "Target a URL, add variations — a text/HTML swap, custom CSS, or a split URL redirect — and start it.",
    },
    {
        title: "Read the results",
        description:
            "Watch impressions and conversion rate per variation roll in, compared against control.",
    },
];

const team = [
    {
        icon: Users,
        title: "Invite your team",
        description:
            "Add teammates by email as owner or member — they set their own password to join.",
    },
    {
        icon: Mail,
        title: "One person, many accounts",
        description:
            "Belong to more than one company? Switch between them from a single login.",
    },
    {
        icon: Eye,
        title: "Preview before you ship",
        description:
            "Force any variation live and see a debug panel of exactly what matched and applied.",
    },
];

const security = [
    {
        icon: ShieldCheck,
        title: "Isolated tenant databases",
        description:
            "Each account's tests, analytics, and heatmap data live in their own physical database.",
    },
    {
        icon: KeyRound,
        title: "Passkeys & two-factor",
        description:
            "Sign in with a passkey or add two-factor authentication on top of your password.",
    },
];

const posts = [
    {
        slug: "why-url-targeting-matters",
        title: "Why URL targeting matters more than your test design",
        excerpt:
            "A test that runs on the wrong page is worse than no test at all — here's how exact, contains, and regex matching actually work.",
        date: "2026-08-12",
    },
    {
        slug: "reading-a-heatmap-without-lying-to-yourself",
        title: "Reading a heatmap without lying to yourself",
        excerpt:
            "A red cluster isn't automatically a win or a problem. Here's what to actually check before you change anything.",
        date: "2026-07-30",
    },
    {
        slug: "split-url-vs-element-changes",
        title: "Split URL tests vs. element changes: when to use which",
        excerpt:
            "Redirecting to a whole new page and swapping an element on the same page solve different problems — mixing them up wastes both.",
        date: "2026-07-08",
    },
];

const faqs = [
    {
        question:
            "What's the difference between an element change and a split URL test?",
        answer: "An element change swaps text, HTML, or CSS on a specific element by selector — no new page needed. A split URL test redirects a percentage of visitors to a completely different, real page. Use element changes for incremental questions, and split URL tests for bigger structural changes.",
    },
    {
        question: "How does the script avoid a flash of the original page?",
        answer: "The snippet hides the page, buckets the visitor, applies the matching variation, then reveals the page — all before the browser paints anything the visitor would notice, and with a timeout fallback so a slow network never leaves the page hidden.",
    },
    {
        question: "Can one person belong to more than one account?",
        answer: "Yes. You can be invited to multiple accounts by email and switch between them from a single login, without needing separate credentials for each.",
    },
    {
        question: "What happens after the 14-day trial ends?",
        answer: "You can upgrade to the Basic plan — $10/month or $100/year — to keep running tests. If you don't upgrade, your account simply stays paused; nothing is deleted.",
    },
    {
        question: "Is heatmap data tied to the same script as A/B testing?",
        answer: "Yes — clicks, mouse movement, and scroll depth are captured from the same one-line tracking script used for tests and analytics, so there's nothing extra to install.",
    },
    {
        question: "Can I test against revenue, not just clicks?",
        answer: "Yes. Orders can be sent from your store to an authenticated API, attributed to the variation that visitor actually saw, and reported as revenue per visitor and average order value. Retried order webhooks are deduplicated on your own order id, so an order is never counted twice.",
    },
    {
        question: "How much does session recording capture?",
        answer: "As much as you tell it to. You set a daily cap per site and what share of visits are considered, so cost stays predictable rather than scaling with your traffic. Inputs are masked by default, and recordings are deleted automatically once they pass your retention window.",
    },
    {
        question: "How is my data isolated from other accounts?",
        answer: "Each account's tests, analytics, and heatmap data live in their own physical database, not just a row-level filter in a shared one.",
    },
];

const plans = [
    {
        name: "14-day trial",
        price: "Free",
        period: "",
        description: "1 site, 1 test — enough to see it work.",
        cta: "Start free trial",
        href: register(),
    },
    {
        name: "Basic — monthly",
        price: "$10",
        period: "/month",
        description: "5 sites, 50 tests per site.",
        cta: "Start free trial",
        href: register(),
        highlighted: true,
    },
    {
        name: "Basic — yearly",
        price: "$100",
        period: "/year",
        description: "5 sites, 50 tests per site — 2 months free.",
        cta: "Start free trial",
        href: register(),
    },
];

function Eyebrow({ children }: { children: React.ReactNode }) {
    return (
        <Badge className="bg-primary/15 text-primary mb-4 border-transparent font-semibold uppercase">
            {children}
        </Badge>
    );
}

function NewsletterForm() {
    const [email, setEmail] = useState("");

    function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        toast.success("You're on the list — we'll be in touch.");
        setEmail("");
    }

    return (
        <form
            onSubmit={handleSubmit}
            className="mx-auto mt-6 flex w-full max-w-md flex-col gap-3 sm:flex-row"
        >
            <Input
                type="email"
                required
                value={email}
                onChange={(event) => setEmail(event.target.value)}
                placeholder="you@company.com"
                className="h-11 flex-1 rounded-none px-5"
            />
            <Button type="submit" size="lg">
                Subscribe
            </Button>
        </form>
    );
}

export default function Welcome() {
    return (
        <>
            <Head title="A/B testing, revenue tracking & session recording" />

            <div className="bg-background text-foreground min-h-screen">
                <MarketingHeader />

                <section className="border-border relative z-0 overflow-hidden border-b">
                    <HeroBackground />

                    <div className="mx-auto grid max-w-6xl items-center gap-12 px-6 pt-16 pb-20 lg:grid-cols-[1fr_1.25fr] lg:gap-4">
                        <div>
                            <Badge variant="secondary" className="mb-6">
                                14-day free trial · no card required
                            </Badge>
                            <h1 className="text-gradient-animated text-4xl font-bold tracking-tight text-balance sm:text-5xl">
                                Run A/B tests, track analytics, and see heatmaps
                                — from one script tag.
                            </h1>
                            <p className="text-muted-foreground mt-6 text-lg text-balance">
                                Drop a snippet on your site, target pages by
                                URL, and change text, HTML, or CSS on any
                                element — or send visitors down a split URL. See
                                what wins with real conversion data.
                            </p>
                            <div className="mt-8 flex flex-wrap items-center gap-3">
                                <Button size="lg" asChild>
                                    <Link href={register()}>
                                        Start free trial
                                    </Link>
                                </Button>
                                <Button size="lg" variant="pink" asChild>
                                    <Link href="/contact?topic=sales">
                                        <CalendarCheck />
                                        Book a consultation call
                                    </Link>
                                </Button>
                            </div>

                            <div className="mt-12 grid max-w-md grid-cols-2 gap-x-8 gap-y-6">
                                {stats.map((stat) => (
                                    <div key={stat.label}>
                                        <div className="text-primary text-2xl font-bold">
                                            {stat.value}
                                        </div>
                                        <div className="text-muted-foreground mt-1 text-sm">
                                            {stat.label}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        <div className="relative lg:-mr-16 xl:-mr-28">
                            <img
                                src="/images/hero-ab-testing.png"
                                alt="Visitors split between two page variations, with the resulting conversion rates compared side by side"
                                className="w-full"
                                width={1481}
                                height={1062}
                                fetchPriority="high"
                            />
                        </div>
                    </div>
                </section>

                <section className="relative mx-auto max-w-6xl overflow-hidden px-6 pt-24 pb-20">
                    <div className="mb-12 text-center">
                        <Eyebrow>Features</Eyebrow>
                        <h2 className="text-3xl font-semibold text-balance sm:text-4xl">
                            Everything you need to run a real test
                        </h2>
                        <p className="text-muted-foreground mx-auto mt-3 max-w-xl text-balance">
                            One script, four tools — testing, redirects,
                            heatmaps, and analytics all read from the same
                            visitor bucket.
                        </p>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {features.map((feature, index) => {
                            const chip = chips[index % chips.length];
                            return (
                                <Card
                                    key={feature.title}
                                    className="rounded-2xl transition-colors hover:border-primary/50"
                                >
                                    <CardHeader>
                                        <div
                                            className={`flex size-10 items-center justify-center rounded-xl ${chip.bg} ${chip.text}`}
                                        >
                                            <feature.icon className="size-5" />
                                        </div>
                                        <CardTitle className="pt-3">
                                            {feature.title}
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <p className="text-muted-foreground text-sm">
                                            {feature.description}
                                        </p>
                                        <Link
                                            href={`/features/${feature.slug}`}
                                            className="text-primary mt-4 inline-flex items-center gap-1 text-sm font-medium hover:underline"
                                        >
                                            Learn more
                                            <ArrowUpRight className="size-3.5" />
                                        </Link>
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>
                </section>

                <section className="mx-auto max-w-6xl px-6 pb-24">
                    <div className="mb-12 text-center">
                        <Eyebrow>Use cases</Eyebrow>
                        <h2 className="text-3xl font-semibold text-balance sm:text-4xl">
                            Build your optimization program across all teams
                        </h2>
                        <p className="text-muted-foreground mx-auto mt-3 max-w-2xl text-balance">
                            An impactful optimization program extends across
                            teams and benefits the whole organization. Our
                            platform is designed to empower all teams by solving
                            problems specific to them.
                        </p>
                    </div>

                    <Tabs defaultValue="marketers">
                        <TabsList className="mx-auto h-auto flex-wrap justify-center gap-1 p-1.5">
                            {orgTeams.map((team) => (
                                <TabsTrigger
                                    key={team.value}
                                    value={team.value}
                                    className="gap-1.5 px-4 py-2"
                                >
                                    <team.icon className="size-4" />
                                    {team.label}
                                </TabsTrigger>
                            ))}
                        </TabsList>

                        {orgTeams.map((team) => (
                            <TabsContent
                                key={team.value}
                                value={team.value}
                                className="mt-8"
                            >
                                <div className="grid items-center gap-8 lg:grid-cols-2">
                                    <div>
                                        <p className="text-lg text-balance">
                                            {team.pitch}
                                        </p>
                                        <ul className="mt-6 space-y-3">
                                            {team.bullets.map((bullet) => (
                                                <li
                                                    key={bullet}
                                                    className="flex items-start gap-2 text-sm"
                                                >
                                                    <Check className="text-primary mt-0.5 size-4 shrink-0" />
                                                    {bullet}
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                    <div
                                        className={`border-border flex aspect-4/3 items-center justify-center rounded-3xl border ${team.panelBg}`}
                                    >
                                        <team.icon
                                            className={`size-20 ${team.iconText}`}
                                            strokeWidth={1.25}
                                        />
                                    </div>
                                </div>
                            </TabsContent>
                        ))}
                    </Tabs>
                </section>

                <section className="bg-muted/40 relative overflow-hidden border-y">
                    <div
                        aria-hidden
                        className="bg-primary/10 absolute -top-24 -left-24 -z-10 size-72 rounded-none blur-3xl"
                    />
                    <div className="mx-auto max-w-6xl px-6 py-20">
                        <div className="mb-10 text-center">
                            <Eyebrow>How it works</Eyebrow>
                            <h2 className="text-3xl font-semibold">
                                Three steps, one script
                            </h2>
                        </div>
                        <div className="grid gap-8 sm:grid-cols-3">
                            {steps.map((step, index) => (
                                <div key={step.title} className="relative">
                                    <div className="bg-primary text-primary-foreground mb-4 flex size-11 items-center justify-center rounded-none text-base font-semibold">
                                        {index + 1}
                                    </div>
                                    <h3 className="font-semibold">
                                        {step.title}
                                    </h3>
                                    <p className="text-muted-foreground mt-2 text-sm">
                                        {step.description}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                <section className="relative mx-auto max-w-5xl overflow-hidden px-6 py-24">
                    <div
                        aria-hidden
                        className="bg-violet-400/15 absolute top-1/2 right-0 -z-10 size-72 -translate-y-1/2 rounded-none blur-3xl"
                    />
                    <Card className="overflow-hidden rounded-3xl">
                        <CardContent className="grid gap-8 lg:grid-cols-2 lg:items-center">
                            <div>
                                <Eyebrow>Install</Eyebrow>
                                <h2 className="text-2xl font-semibold">
                                    One snippet. Every page.
                                </h2>
                                <p className="text-muted-foreground mt-3 text-sm">
                                    Paste it once in your{" "}
                                    <code>&lt;head&gt;</code>. It buckets
                                    visitors, applies the right variation, and
                                    reveals the page — no flash of the original.
                                </p>
                                <Button
                                    className="mt-6"
                                    variant="outline"
                                    asChild
                                >
                                    <Link href={register()}>
                                        Get your snippet
                                        <ArrowRight />
                                    </Link>
                                </Button>
                            </div>
                            <div className="border-border overflow-hidden rounded-2xl border bg-zinc-950">
                                <div className="flex items-center gap-1.5 border-b border-white/10 px-4 py-2.5">
                                    <span className="size-2.5 rounded-none bg-red-500/70" />
                                    <span className="size-2.5 rounded-none bg-yellow-500/70" />
                                    <span className="size-2.5 rounded-none bg-green-500/70" />
                                </div>
                                <pre className="overflow-x-auto p-4 text-xs text-zinc-100">
                                    {`<!-- Start abify.io code -->
<script type="text/javascript" id="abifyCode">
window._abify_code || (function () {
  var site_id = 'YOUR_SITE_ID',
      settings_tolerance = 2000,
      hide_element = 'body';
  /* DO NOT EDIT BELOW THIS LINE */
  ...
})();
</script>
<!-- End abify.io code -->`}
                                </pre>
                            </div>
                        </CardContent>
                    </Card>
                </section>

                <section className="relative mx-auto max-w-6xl overflow-hidden px-6 pb-20">
                    <div
                        aria-hidden
                        className="bg-primary/10 absolute top-0 left-1/2 -z-10 size-96 -translate-x-1/2 rounded-none blur-3xl"
                    />
                    <div className="mb-10 text-center">
                        <Eyebrow>Team</Eyebrow>
                        <h2 className="mb-2 text-3xl font-semibold">
                            Built for teams
                        </h2>
                        <p className="text-muted-foreground mx-auto max-w-xl">
                            One account can hold multiple sites, and one person
                            can belong to multiple accounts.
                        </p>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-3">
                        {team.map((item, index) => {
                            const chip = chips[index % chips.length];
                            return (
                                <Card
                                    key={item.title}
                                    className="rounded-2xl transition-colors hover:border-primary/50"
                                >
                                    <CardHeader>
                                        <div
                                            className={`flex size-10 items-center justify-center rounded-xl ${chip.bg} ${chip.text}`}
                                        >
                                            <item.icon className="size-5" />
                                        </div>
                                        <CardTitle className="pt-3">
                                            {item.title}
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <p className="text-muted-foreground text-sm">
                                            {item.description}
                                        </p>
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>
                </section>

                <section className="bg-muted/40 border-y">
                    <div className="mx-auto max-w-6xl px-6 py-20">
                        <div className="mb-10 text-center">
                            <Eyebrow>Security</Eyebrow>
                            <h2 className="text-3xl font-semibold">
                                Private by design
                            </h2>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            {security.map((item, index) => {
                                const chip = chips[index % chips.length];
                                return (
                                    <div
                                        key={item.title}
                                        className="bg-card flex gap-4 rounded-2xl border p-5"
                                    >
                                        <div
                                            className={`flex size-10 shrink-0 items-center justify-center rounded-xl ${chip.bg} ${chip.text}`}
                                        >
                                            <item.icon className="size-5" />
                                        </div>
                                        <div>
                                            <h3 className="font-semibold">
                                                {item.title}
                                            </h3>
                                            <p className="text-muted-foreground mt-1 text-sm">
                                                {item.description}
                                            </p>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </section>

                <section
                    id="pricing"
                    className="mx-auto max-w-6xl px-6 pt-24 pb-24"
                >
                    <div className="mb-10 text-center">
                        <Eyebrow>Pricing</Eyebrow>
                        <h2 className="text-3xl font-semibold">
                            Simple pricing
                        </h2>
                        <p className="text-muted-foreground mt-2">
                            Start on a 14-day trial, upgrade to Basic whenever
                            you're ready.
                        </p>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-3">
                        {plans.map((plan) => (
                            <Card
                                key={plan.name}
                                className={
                                    "rounded-3xl" +
                                    (plan.highlighted
                                        ? " ring-primary relative ring-2"
                                        : "")
                                }
                            >
                                {plan.highlighted && (
                                    <Badge className="absolute -top-3 left-1/2 -translate-x-1/2">
                                        Most popular
                                    </Badge>
                                )}
                                <CardHeader>
                                    <CardTitle>{plan.name}</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div>
                                        <span className="text-3xl font-bold">
                                            {plan.price}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {plan.period}
                                        </span>
                                    </div>
                                    <p className="text-muted-foreground flex items-start gap-2 text-sm">
                                        <Check className="text-primary mt-0.5 size-4 shrink-0" />
                                        {plan.description}
                                    </p>
                                    <Button
                                        className="w-full"
                                        variant={
                                            plan.highlighted
                                                ? "default"
                                                : "outline"
                                        }
                                        asChild
                                    >
                                        <Link href={plan.href}>{plan.cta}</Link>
                                    </Button>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                </section>

                <section className="mx-auto max-w-3xl px-6 pb-24">
                    <div className="mb-10 text-center">
                        <Eyebrow>FAQ</Eyebrow>
                        <h2 className="text-3xl font-semibold">
                            Questions, answered
                        </h2>
                    </div>
                    <div className="border-border divide-border bg-card divide-y overflow-hidden rounded-2xl border">
                        {faqs.map((faq) => (
                            <details
                                key={faq.question}
                                className="group p-5 open:bg-muted/30"
                            >
                                <summary className="marker:content-none flex cursor-pointer list-none items-center justify-between gap-4 font-medium [&::-webkit-details-marker]:hidden">
                                    {faq.question}
                                    <ChevronDown className="text-muted-foreground size-4 shrink-0 transition-transform group-open:rotate-180" />
                                </summary>
                                <p className="text-muted-foreground mt-3 text-sm leading-relaxed">
                                    {faq.answer}
                                </p>
                            </details>
                        ))}
                    </div>
                </section>

                <section className="mx-auto max-w-6xl px-6 pb-20">
                    <div className="mb-10 text-center">
                        <Eyebrow>Loved by early users</Eyebrow>
                        <h2 className="text-3xl font-semibold">
                            What people are saying
                        </h2>
                    </div>
                    <TestimonialsCarousel items={testimonials} chips={chips} />
                </section>

                <section className="bg-muted/40 border-y">
                    <div className="mx-auto max-w-6xl px-6 py-20">
                        <div className="mb-10 flex flex-wrap items-end justify-between gap-4">
                            <div>
                                <Eyebrow>Blog</Eyebrow>
                                <h2 className="text-3xl font-semibold">
                                    From the blog
                                </h2>
                            </div>
                            <Link
                                href="/blog"
                                className="text-primary inline-flex items-center gap-1 text-sm font-medium hover:underline"
                            >
                                View all posts
                                <ArrowUpRight className="size-3.5" />
                            </Link>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-3">
                            {posts.map((post, index) => {
                                const chip = chips[index % chips.length];
                                return (
                                    <Link
                                        key={post.slug}
                                        href={`/blog/${post.slug}`}
                                    >
                                        <Card className="h-full rounded-2xl transition-colors hover:border-primary/50">
                                            <CardHeader>
                                                <Badge
                                                    className={`w-fit border-transparent ${chip.bg} ${chip.text}`}
                                                >
                                                    {new Date(
                                                        `${post.date}T00:00:00`,
                                                    ).toLocaleDateString(
                                                        "en-US",
                                                        {
                                                            month: "short",
                                                            day: "numeric",
                                                            year: "numeric",
                                                        },
                                                    )}
                                                </Badge>
                                                <CardTitle className="pt-3 text-base">
                                                    {post.title}
                                                </CardTitle>
                                            </CardHeader>
                                            <CardContent>
                                                <p className="text-muted-foreground text-sm">
                                                    {post.excerpt}
                                                </p>
                                                <span className="text-primary mt-4 inline-flex items-center gap-1 text-sm font-medium">
                                                    Read post
                                                    <ArrowUpRight className="size-3.5" />
                                                </span>
                                            </CardContent>
                                        </Card>
                                    </Link>
                                );
                            })}
                        </div>
                    </div>
                </section>

                <section className="mx-auto max-w-4xl px-6 pt-24 pb-16 text-center">
                    <div className="from-primary relative overflow-hidden rounded-3xl bg-gradient-to-br to-violet-600 px-6 py-14 text-white">
                        <span
                            aria-hidden
                            className="absolute -top-10 -left-10 size-40 rounded-none bg-white/10 blur-2xl"
                        />
                        <span
                            aria-hidden
                            className="absolute -right-8 -bottom-10 size-40 rounded-none bg-rose-300/20 blur-2xl"
                        />
                        <h2 className="text-2xl font-semibold sm:text-3xl">
                            Get started today
                        </h2>
                        <p className="mx-auto mt-2 max-w-md text-sm text-white/80">
                            Create your first test in a few minutes — no card
                            required for the 14-day trial.
                        </p>
                        <Button
                            size="lg"
                            variant="secondary"
                            className="mt-6"
                            asChild
                        >
                            <Link href={register()}>
                                Start free trial
                                <ArrowRight />
                            </Link>
                        </Button>
                    </div>
                </section>

                <section className="mx-auto max-w-3xl px-6 pb-24 text-center">
                    <Eyebrow>Newsletter</Eyebrow>
                    <h2 className="text-2xl font-semibold sm:text-3xl">
                        Testing tips, once in a while
                    </h2>
                    <p className="text-muted-foreground mx-auto mt-2 max-w-md text-sm">
                        Short, practical notes on running better experiments —
                        no spam, unsubscribe anytime.
                    </p>
                    <NewsletterForm />
                </section>

                <MarketingFooter />
            </div>
        </>
    );
}
