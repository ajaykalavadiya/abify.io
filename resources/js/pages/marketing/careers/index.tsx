import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    Clock,
    Globe2,
    HeartHandshake,
    MessageSquare,
    Ruler,
    Users,
} from 'lucide-react';
import { MarketingFooter } from '@/components/marketing-footer';
import { MarketingHeader } from '@/components/marketing-header';

type Opening = {
    slug: string;
    title: string;
    team: string;
    type: string;
    location: string;
    summary: string;
};

const values = [
    {
        icon: Ruler,
        title: 'Small team, real ownership',
        body: 'Both of these roles are the first of their kind here. You will be deciding how the work gets done, not inheriting a process.',
        chip: 'bg-primary/10 text-primary',
    },
    {
        icon: Globe2,
        title: 'Remote, asynchronous by default',
        body: 'We write things down. Meetings are the exception, and your hours are yours as long as customers are covered.',
        chip: 'bg-violet-500/10 text-violet-500',
    },
    {
        icon: HeartHandshake,
        title: 'Close to the product',
        body: 'Everyone uses the dashboard daily. Support and sales feedback go straight to the people building it — there is no relay.',
        chip: 'bg-rose-500/10 text-rose-500',
    },
];

const process = [
    {
        title: 'Apply by email',
        body: 'A short note about why this role, plus anything that shows how you work. A CV is fine; a link is better.',
    },
    {
        title: 'A 30-minute call',
        body: 'Mutual questions. We will tell you honestly what is unfinished about the company and the product.',
    },
    {
        title: 'A paid exercise',
        body: 'A small, realistic task — an outreach sequence or a set of support replies. Paid at your day rate, not spec work.',
    },
    {
        title: 'Offer',
        body: 'Compensation is discussed on the first call, so nobody wastes time. No take-home marathons after this point.',
    },
];

export default function CareersIndex({ openings }: { openings: Opening[] }) {
    return (
        <>
            <Head title="Careers" />

            <div className="bg-background text-foreground min-h-screen">
                <MarketingHeader />

                <section className="border-border border-b">
                    <div className="mx-auto max-w-4xl px-6 py-16 text-center">
                        <h1 className="text-4xl font-bold tracking-tight text-balance sm:text-5xl">
                            Come help people stop guessing
                        </h1>
                        <p className="text-muted-foreground mx-auto mt-5 max-w-2xl text-lg text-balance">
                            We are a small team building A/B testing, heatmaps, and analytics that
                            fit in one script tag. Two roles are open, both remote, both the first
                            of their kind here.
                        </p>
                    </div>
                </section>

                <section className="mx-auto max-w-6xl px-6 py-16">
                    <div className="grid gap-4 md:grid-cols-3">
                        {values.map((value) => (
                            <div key={value.title} className="border-border border p-7">
                                <span
                                    className={`mb-5 flex size-11 items-center justify-center ${value.chip}`}
                                >
                                    <value.icon className="size-5 text-inherit" />
                                </span>
                                <h2 className="text-lg font-semibold">{value.title}</h2>
                                <p className="text-muted-foreground mt-2 text-sm leading-relaxed">
                                    {value.body}
                                </p>
                            </div>
                        ))}
                    </div>
                </section>

                <section id="openings" className="bg-muted/40 border-border border-y">
                    <div className="mx-auto max-w-4xl px-6 py-16">
                        <h2 className="text-2xl font-bold tracking-tight">
                            Open roles
                            <span className="text-muted-foreground ml-3 text-base font-normal">
                                {openings.length} position{openings.length === 1 ? '' : 's'}
                            </span>
                        </h2>

                        <div className="mt-8 space-y-4">
                            {openings.map((opening) => (
                                <Link
                                    key={opening.slug}
                                    href={`/careers/${opening.slug}`}
                                    className="border-border hover:border-primary bg-background group flex flex-col gap-4 border p-6 transition-colors sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div>
                                        <h3 className="group-hover:text-primary text-lg font-semibold transition-colors">
                                            {opening.title}
                                        </h3>
                                        <p className="text-muted-foreground mt-1 text-sm leading-relaxed">
                                            {opening.summary}
                                        </p>
                                        <div className="text-muted-foreground mt-3 flex flex-wrap items-center gap-4 text-xs">
                                            <span className="bg-primary/10 text-primary px-2 py-1 font-semibold tracking-wide uppercase">
                                                {opening.team}
                                            </span>
                                            <span className="inline-flex items-center gap-1">
                                                <Globe2 className="size-3.5" />
                                                {opening.location}
                                            </span>
                                            <span className="inline-flex items-center gap-1">
                                                <Clock className="size-3.5" />
                                                {opening.type}
                                            </span>
                                        </div>
                                    </div>

                                    <span className="text-primary inline-flex shrink-0 items-center gap-1 text-sm font-semibold">
                                        View role
                                        <ArrowRight className="size-4 transition-transform group-hover:translate-x-0.5" />
                                    </span>
                                </Link>
                            ))}
                        </div>

                        <p className="text-muted-foreground mt-6 text-sm">
                            Nothing that fits?{' '}
                            <a
                                href="mailto:hello@abify.io?subject=Speculative%20application"
                                className="text-primary font-medium hover:underline"
                            >
                                Write to us anyway
                            </a>{' '}
                            — we would rather hear from you early than miss you.
                        </p>
                    </div>
                </section>

                <section className="mx-auto max-w-4xl px-6 py-16">
                    <h2 className="text-2xl font-bold tracking-tight">How hiring works here</h2>
                    <p className="text-muted-foreground mt-3 max-w-2xl">
                        Four steps, usually inside two weeks. You will always know what the next one
                        is and when to expect it.
                    </p>

                    <ol className="mt-8 grid gap-4 sm:grid-cols-2">
                        {process.map((step, index) => (
                            <li key={step.title} className="border-border border p-6">
                                <div className="text-primary text-sm font-semibold">
                                    Step {index + 1}
                                </div>
                                <div className="mt-1 font-semibold">{step.title}</div>
                                <p className="text-muted-foreground mt-2 text-sm leading-relaxed">
                                    {step.body}
                                </p>
                            </li>
                        ))}
                    </ol>
                </section>

                <section className="border-border border-t">
                    <div className="mx-auto max-w-3xl px-6 py-16 text-center">
                        <span className="bg-primary/10 text-primary mx-auto mb-5 flex size-11 items-center justify-center">
                            <Users className="size-5" />
                        </span>
                        <h2 className="text-2xl font-bold tracking-tight text-balance">
                            Questions before you apply?
                        </h2>
                        <p className="text-muted-foreground mt-3">
                            Ask anything — about the roles, the company, or what is genuinely hard
                            about working here right now.
                        </p>
                        <Link
                            href="/contact?topic=other"
                            className="text-primary mt-5 inline-flex items-center gap-2 font-semibold hover:underline"
                        >
                            <MessageSquare className="size-4" />
                            Get in touch
                        </Link>
                    </div>
                </section>

                <MarketingFooter />
            </div>
        </>
    );
}
