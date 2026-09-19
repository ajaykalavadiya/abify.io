import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Check, Gauge, Layers, Minus, ShieldCheck, Tag, Wrench } from 'lucide-react';
import { MarketingFooter } from '@/components/marketing-footer';
import { MarketingHeader } from '@/components/marketing-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { register } from '@/routes';

const principles = [
    {
        icon: Layers,
        title: 'One script, three tools',
        body: 'Testing, heatmaps, and analytics run off the same snippet and the same visitor bucket. The conversion number for a variation and the heatmap for that variation are describing the same people — no reconciling two dashboards that disagree.',
        chip: 'bg-primary/10 text-primary',
    },
    {
        icon: Gauge,
        title: 'Fails toward your page',
        body: 'The snippet hides the page, applies changes, and reveals — but a timeout reveals it anyway if the network is slow or the request never lands. A testing tool that can white-screen your site is worse than no testing tool.',
        chip: 'bg-violet-500/10 text-violet-500',
    },
    {
        icon: Wrench,
        title: 'Set up in minutes, not a sprint',
        body: 'A test targets elements by CSS selector, so most changes are a selector and a string. Control is created for you, traffic weights always total 100%, and nothing needs a deploy to go live.',
        chip: 'bg-rose-500/10 text-rose-500',
    },
    {
        icon: Tag,
        title: 'Pricing you can predict',
        body: 'Ten dollars a month for five sites and fifty tests each — not a quote, not a sales call, not a percentage of the traffic you send through it. The free trial runs 14 days without a card.',
        chip: 'bg-primary/10 text-primary',
    },
];

const comparison = [
    {
        need: 'Run an A/B test on an existing page',
        stitched: 'Testing tool, plus a developer to ship the variant',
        abify: 'Pick the element, describe the change, start it',
    },
    {
        need: 'See where people click and scroll',
        stitched: 'A second vendor and a second snippet on every page',
        abify: 'Already on — same script, same visitors',
    },
    {
        need: 'Know whether a variation actually won',
        stitched: 'Export to a spreadsheet, hope the bucketing matched',
        abify: 'Impressions and conversion per variation vs. control',
    },
    {
        need: 'QA a variation before real traffic sees it',
        stitched: 'Clear cookies and refresh until you get bucketed',
        abify: 'A preview link forces it, excluded from results',
    },
    {
        need: 'Test a full redesign, not one element',
        stitched: 'A wall of injected CSS and JS pretending to be a page',
        abify: 'A split URL test against the real second page',
    },
];

const honest = [
    'No visual editor yet — changes are made by CSS selector, which suits teams comfortable with the DOM.',
    'No server-side or feature-flag testing. This is client-side testing for web pages.',
    'Multivariate testing caps combinations, because a factorial explosion needs traffic nobody has.',
];

export default function Why() {
    return (
        <>
            <Head title="Why abify.io" />

            <div className="bg-background text-foreground min-h-screen">
                <MarketingHeader />

                <section className="border-border border-b">
                    <div className="mx-auto max-w-4xl px-6 py-20 text-center">
                        <Badge className="bg-primary/15 text-primary mb-5 border-transparent font-semibold uppercase">
                            Why abify
                        </Badge>
                        <h1 className="text-4xl font-bold tracking-tight text-balance sm:text-5xl">
                            Optimization tooling that fits on one line
                        </h1>
                        <p className="text-muted-foreground mx-auto mt-5 max-w-2xl text-lg text-balance">
                            Most teams stop testing not because they stopped caring, but because the
                            setup cost more than the answers were worth. abify.io is built around
                            removing that cost.
                        </p>
                    </div>
                </section>

                <section className="mx-auto max-w-6xl px-6 py-20">
                    <div className="grid gap-4 sm:grid-cols-2">
                        {principles.map((principle) => (
                            <div key={principle.title} className="border-border border p-7">
                                <span
                                    className={`mb-5 flex size-11 items-center justify-center ${principle.chip}`}
                                >
                                    <principle.icon className="size-5 text-inherit" />
                                </span>
                                <h2 className="text-lg font-semibold">{principle.title}</h2>
                                <p className="text-muted-foreground mt-2 leading-relaxed">
                                    {principle.body}
                                </p>
                            </div>
                        ))}
                    </div>
                </section>

                <section className="bg-muted/40 border-border border-y">
                    <div className="mx-auto max-w-6xl px-6 py-20">
                        <h2 className="text-center text-3xl font-bold tracking-tight">
                            The stitched-together stack, and the alternative
                        </h2>
                        <p className="text-muted-foreground mx-auto mt-3 max-w-2xl text-center">
                            Nothing here needs a competitor's name on it — this is just what the
                            work looks like when the pieces don't share a visitor bucket.
                        </p>

                        <div className="border-border bg-background mt-10 overflow-x-auto border">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-border border-b">
                                        <th className="px-5 py-4 font-semibold">What you need</th>
                                        <th className="text-muted-foreground px-5 py-4 font-semibold">
                                            Pieced together
                                        </th>
                                        <th className="text-primary px-5 py-4 font-semibold">
                                            With abify.io
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {comparison.map((row) => (
                                        <tr
                                            key={row.need}
                                            className="border-border border-b last:border-0"
                                        >
                                            <td className="px-5 py-4 font-medium">{row.need}</td>
                                            <td className="text-muted-foreground px-5 py-4">
                                                <span className="flex items-start gap-2">
                                                    <Minus className="mt-0.5 size-4 shrink-0" />
                                                    {row.stitched}
                                                </span>
                                            </td>
                                            <td className="px-5 py-4">
                                                <span className="flex items-start gap-2">
                                                    <Check className="text-primary mt-0.5 size-4 shrink-0" />
                                                    {row.abify}
                                                </span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <section className="mx-auto max-w-6xl px-6 py-20">
                    <div className="grid gap-10 lg:grid-cols-[1fr_1fr] lg:gap-16">
                        <div>
                            <h2 className="text-3xl font-bold tracking-tight">
                                What abify.io is not
                            </h2>
                            <p className="text-muted-foreground mt-3 leading-relaxed">
                                Knowing where a tool stops is worth more than another paragraph
                                about where it shines. If one of these is a dealbreaker, better to
                                find out on this page than three weeks in.
                            </p>
                            <ul className="mt-6 space-y-3">
                                {honest.map((item) => (
                                    <li key={item} className="flex items-start gap-3 text-sm">
                                        <Minus className="text-muted-foreground mt-0.5 size-4 shrink-0" />
                                        <span className="text-muted-foreground leading-relaxed">
                                            {item}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </div>

                        <div className="border-border border p-8">
                            <span className="bg-primary/10 text-primary mb-5 flex size-11 items-center justify-center">
                                <ShieldCheck className="size-5" />
                            </span>
                            <h2 className="text-xl font-semibold">Your data stays yours</h2>
                            <p className="text-muted-foreground mt-2 leading-relaxed">
                                Analytics and heatmap data are written to a database per company,
                                not pooled into a shared table with everyone else's traffic.
                                Visitors are identified by a first-party cookie holding a random id
                                — no cross-site profile, nothing sold onward.
                            </p>
                            <Button variant="outline" className="mt-6" asChild>
                                <Link href="/features/live-preview-debugging">
                                    See how bucketing works
                                    <ArrowRight />
                                </Link>
                            </Button>
                        </div>
                    </div>
                </section>

                <section className="border-border border-t">
                    <div className="mx-auto max-w-4xl px-6 py-20 text-center">
                        <h2 className="text-3xl font-bold tracking-tight text-balance">
                            Fourteen days is enough to run a real test
                        </h2>
                        <p className="text-muted-foreground mt-4 text-lg">
                            No card required. Install the snippet, ship one test, and judge it on
                            the numbers it gives you.
                        </p>
                        <div className="mt-8 flex flex-wrap justify-center gap-3">
                            <Button size="lg" asChild>
                                <Link href={register()}>
                                    Start free trial
                                    <ArrowRight />
                                </Link>
                            </Button>
                            <Button size="lg" variant="outline" asChild>
                                <Link href="/contact">Talk to us first</Link>
                            </Button>
                        </div>
                    </div>
                </section>

                <MarketingFooter />
            </div>
        </>
    );
}
