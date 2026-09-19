import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Check } from 'lucide-react';
import { MarketingFooter } from '@/components/marketing-footer';
import { MarketingHeader } from '@/components/marketing-header';
import { Button } from '@/components/ui/button';
import { register } from '@/routes';

type Feature = {
    slug: string;
    title: string;
    tagline: string;
    body: string;
    highlights: string[];
    // Only the features an illustration exists for carry one.
    image?: string | null;
};

export default function FeatureShow({ feature }: { feature: Feature }) {
    return (
        <>
            <Head title={feature.title} />

            <div className="bg-background text-foreground min-h-screen">
                <MarketingHeader />

                <section className="border-border border-b">
                    <div className="mx-auto max-w-6xl px-6 pt-10 pb-14">
                        <Link
                            href="/features"
                            className="text-muted-foreground hover:text-foreground mb-8 inline-flex items-center gap-1 text-sm"
                        >
                            <ArrowLeft className="size-4" />
                            Back to features
                        </Link>

                        {/* Only half the features have an illustration; without one
                            the copy takes the full width instead of leaving a hole. */}
                        <div
                            className={`grid items-center gap-10 ${feature.image ? 'lg:grid-cols-2' : 'max-w-3xl'}`}
                        >
                            <div>
                                <h1 className="text-4xl font-bold tracking-tight text-balance">
                                    {feature.title}
                                </h1>
                                <p className="text-muted-foreground mt-4 text-lg text-balance">
                                    {feature.tagline}
                                </p>
                                <Button size="lg" className="mt-7" asChild>
                                    <Link href={register()}>
                                        Start free trial
                                        <ArrowRight />
                                    </Link>
                                </Button>
                            </div>

                            {feature.image && (
                                <div className="border-border bg-muted/40 flex items-center justify-center border p-6">
                                    <img
                                        src={feature.image}
                                        alt={`${feature.title} illustrated`}
                                        className="max-h-80 w-auto object-contain"
                                        loading="lazy"
                                    />
                                </div>
                            )}
                        </div>
                    </div>
                </section>

                <section className="mx-auto max-w-6xl px-6 py-16">
                    <div className="grid gap-10 lg:grid-cols-[1.4fr_1fr] lg:gap-16">
                        <div>
                            {feature.body.split('\n\n').map((paragraph, index) => (
                                <p
                                    key={index}
                                    className="text-foreground/80 mb-5 text-[1.0625rem] leading-relaxed"
                                >
                                    {paragraph}
                                </p>
                            ))}
                        </div>

                        <aside className="border-border h-fit border p-7">
                            <h2 className="text-sm font-semibold tracking-wide uppercase">
                                What you get
                            </h2>
                            <ul className="mt-5 space-y-3">
                                {feature.highlights.map((highlight) => (
                                    <li key={highlight} className="flex items-start gap-3 text-sm">
                                        <Check className="text-primary mt-0.5 size-4 shrink-0" />
                                        <span className="leading-relaxed">{highlight}</span>
                                    </li>
                                ))}
                            </ul>
                        </aside>
                    </div>
                </section>

                <section className="border-border border-t">
                    <div className="mx-auto max-w-4xl px-6 py-16 text-center">
                        <h2 className="text-2xl font-bold tracking-tight text-balance">
                            Everything else ships in the same script tag
                        </h2>
                        <p className="text-muted-foreground mt-3">
                            Testing, heatmaps, and analytics run off one snippet and one visitor
                            bucket.
                        </p>
                        <div className="mt-7 flex flex-wrap justify-center gap-3">
                            <Button size="lg" variant="outline" asChild>
                                <Link href="/features">See all features</Link>
                            </Button>
                            <Button size="lg" asChild>
                                <Link href={register()}>
                                    Start free trial
                                    <ArrowRight />
                                </Link>
                            </Button>
                        </div>
                    </div>
                </section>

                <MarketingFooter />
            </div>
        </>
    );
}
