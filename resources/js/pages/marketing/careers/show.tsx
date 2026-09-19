import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Check, Clock, Globe2, Mail, Plus } from 'lucide-react';
import { MarketingFooter } from '@/components/marketing-footer';
import { MarketingHeader } from '@/components/marketing-header';
import { Button } from '@/components/ui/button';

type Role = {
    slug: string;
    title: string;
    team: string;
    type: string;
    location: string;
    summary: string;
    about: string;
    responsibilities: string[];
    requirements: string[];
    nice: string[];
};

export default function CareersShow({ role }: { role: Role }) {
    const applyHref = `mailto:hello@abify.io?subject=${encodeURIComponent(`Application: ${role.title}`)}`;

    return (
        <>
            <Head title={`${role.title} — Careers`} />

            <div className="bg-background text-foreground min-h-screen">
                <MarketingHeader />

                <section className="border-border border-b">
                    <div className="mx-auto max-w-4xl px-6 pt-10 pb-12">
                        <Link
                            href="/careers"
                            className="text-muted-foreground hover:text-foreground mb-8 inline-flex items-center gap-1 text-sm"
                        >
                            <ArrowLeft className="size-4" />
                            All open roles
                        </Link>

                        <h1 className="text-4xl font-bold tracking-tight text-balance">
                            {role.title}
                        </h1>
                        <p className="text-muted-foreground mt-4 text-lg text-balance">
                            {role.summary}
                        </p>

                        <div className="text-muted-foreground mt-6 flex flex-wrap items-center gap-4 text-sm">
                            <span className="bg-primary/10 text-primary px-2 py-1 text-xs font-semibold tracking-wide uppercase">
                                {role.team}
                            </span>
                            <span className="inline-flex items-center gap-1.5">
                                <Globe2 className="size-4" />
                                {role.location}
                            </span>
                            <span className="inline-flex items-center gap-1.5">
                                <Clock className="size-4" />
                                {role.type}
                            </span>
                        </div>

                        <Button size="lg" variant="pink" className="mt-8" asChild>
                            <a href={applyHref}>
                                <Mail />
                                Apply for this role
                            </a>
                        </Button>
                    </div>
                </section>

                <section className="mx-auto max-w-4xl px-6 py-14">
                    <div className="grid gap-12 lg:grid-cols-[1.5fr_1fr]">
                        <div>
                            <h2 className="text-xl font-semibold">About the role</h2>
                            {role.about.split('\n\n').map((paragraph, index) => (
                                <p key={index} className="text-foreground/80 mt-4 leading-relaxed">
                                    {paragraph}
                                </p>
                            ))}

                            <h2 className="mt-10 text-xl font-semibold">What you would do</h2>
                            <ul className="mt-4 space-y-3">
                                {role.responsibilities.map((item) => (
                                    <li key={item} className="flex items-start gap-3 text-sm">
                                        <Check className="text-primary mt-0.5 size-4 shrink-0" />
                                        <span className="leading-relaxed">{item}</span>
                                    </li>
                                ))}
                            </ul>
                        </div>

                        <aside className="space-y-6">
                            <div className="border-border h-fit border p-6">
                                <h2 className="text-sm font-semibold tracking-wide uppercase">
                                    What we are looking for
                                </h2>
                                <ul className="mt-4 space-y-3">
                                    {role.requirements.map((item) => (
                                        <li key={item} className="flex items-start gap-3 text-sm">
                                            <Check className="text-primary mt-0.5 size-4 shrink-0" />
                                            <span className="leading-relaxed">{item}</span>
                                        </li>
                                    ))}
                                </ul>
                            </div>

                            <div className="border-border bg-muted/40 h-fit border p-6">
                                <h2 className="text-sm font-semibold tracking-wide uppercase">
                                    Nice to have
                                </h2>
                                <ul className="mt-4 space-y-3">
                                    {role.nice.map((item) => (
                                        <li key={item} className="flex items-start gap-3 text-sm">
                                            <Plus className="text-muted-foreground mt-0.5 size-4 shrink-0" />
                                            <span className="text-muted-foreground leading-relaxed">
                                                {item}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                                <p className="text-muted-foreground mt-4 text-xs leading-relaxed">
                                    These are genuinely optional. Apply if the section above fits,
                                    even if none of these do.
                                </p>
                            </div>
                        </aside>
                    </div>
                </section>

                <section className="border-border border-t">
                    <div className="mx-auto max-w-3xl px-6 py-16 text-center">
                        <h2 className="text-2xl font-bold tracking-tight text-balance">
                            Apply for {role.title}
                        </h2>
                        <p className="text-muted-foreground mx-auto mt-3 max-w-xl">
                            Email us a short note about why this role, plus a CV or a link that
                            shows how you work. We reply to every application, including the ones we
                            turn down.
                        </p>
                        <div className="mt-7 flex flex-wrap justify-center gap-3">
                            <Button size="lg" variant="pink" asChild>
                                <a href={applyHref}>
                                    <Mail />
                                    Apply by email
                                </a>
                            </Button>
                            <Button size="lg" variant="outline" asChild>
                                <Link href="/careers">See the other role</Link>
                            </Button>
                        </div>
                    </div>
                </section>

                <MarketingFooter />
            </div>
        </>
    );
}
