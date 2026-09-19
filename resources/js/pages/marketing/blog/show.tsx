import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ArrowRight } from 'lucide-react';
import { MarketingFooter } from '@/components/marketing-footer';
import { MarketingHeader } from '@/components/marketing-header';
import { Button } from '@/components/ui/button';
import { register } from '@/routes';

type Post = {
    slug: string;
    title: string;
    excerpt: string;
    date: string;
    topic: string;
    image: string;
    body: string;
};

export default function BlogShow({ post }: { post: Post }) {
    return (
        <>
            <Head title={post.title} />

            <div className="bg-background text-foreground min-h-screen">
                <MarketingHeader />

                <section className="border-border border-b">
                    <div className="mx-auto max-w-3xl px-6 pt-10 pb-12">
                        <Link
                            href="/blog"
                            className="text-muted-foreground hover:text-foreground mb-8 inline-flex items-center gap-1 text-sm"
                        >
                            <ArrowLeft className="size-4" />
                            Back to blog
                        </Link>

                        <div className="text-muted-foreground flex items-center gap-3 text-xs">
                            <span className="bg-primary/10 text-primary px-2 py-1 font-semibold tracking-wide uppercase">
                                {post.topic}
                            </span>
                            {new Date(`${post.date}T00:00:00`).toLocaleDateString(undefined, {
                                year: 'numeric',
                                month: 'long',
                                day: 'numeric',
                            })}
                        </div>

                        <h1 className="mt-4 text-4xl font-bold tracking-tight text-balance">
                            {post.title}
                        </h1>
                        <p className="text-muted-foreground mt-4 text-lg text-balance">
                            {post.excerpt}
                        </p>
                    </div>
                </section>

                <article className="mx-auto max-w-3xl px-6 py-12">
                    <div className="border-border bg-muted/40 flex items-center justify-center border p-8">
                        <img src={post.image} alt="" className="max-h-80 w-auto object-contain" />
                    </div>

                    <div className="mt-10">
                        {post.body.split('\n\n').map((paragraph, index) => (
                            <p
                                key={index}
                                className="text-foreground/80 mb-5 text-[1.0625rem] leading-relaxed"
                            >
                                {paragraph}
                            </p>
                        ))}
                    </div>

                    <div className="border-border mt-12 flex flex-col items-start gap-4 border p-8 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div className="text-lg font-semibold">Try it on your own pages</div>
                            <p className="text-muted-foreground mt-1 text-sm">
                                14-day free trial, no card required.
                            </p>
                        </div>
                        <Button size="lg" asChild>
                            <Link href={register()}>
                                Start free trial
                                <ArrowRight />
                            </Link>
                        </Button>
                    </div>
                </article>

                <MarketingFooter />
            </div>
        </>
    );
}
