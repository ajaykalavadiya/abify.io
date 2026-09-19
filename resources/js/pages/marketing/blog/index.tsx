import { Head, Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { MarketingFooter } from '@/components/marketing-footer';
import { MarketingHeader } from '@/components/marketing-header';

type PostSummary = {
    slug: string;
    title: string;
    excerpt: string;
    date: string;
    topic: string;
    image: string;
};

function formatDate(date: string): string {
    // Parsed with an explicit time so it isn't read as UTC midnight and
    // rendered as the previous day in western timezones.
    return new Date(`${date}T00:00:00`).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
}

export default function BlogIndex({ posts }: { posts: PostSummary[] }) {
    return (
        <>
            <Head title="Blog" />

            <div className="bg-background text-foreground min-h-screen">
                <MarketingHeader />

                <section className="border-border border-b">
                    <div className="mx-auto max-w-6xl px-6 py-16 text-center">
                        <h1 className="text-4xl font-bold tracking-tight">Blog</h1>
                        <p className="text-muted-foreground mx-auto mt-4 max-w-xl text-lg text-balance">
                            Notes on testing, heatmaps, and reading results without fooling
                            yourself.
                        </p>
                    </div>
                </section>

                <section className="mx-auto max-w-6xl px-6 pt-12 pb-24">
                    <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                        {posts.map((post) => (
                            <Link
                                key={post.slug}
                                href={`/blog/${post.slug}`}
                                className="border-border hover:border-primary group flex flex-col border transition-colors"
                            >
                                <div className="bg-muted/40 border-border flex h-44 items-center justify-center border-b p-6">
                                    <img
                                        src={post.image}
                                        alt=""
                                        loading="lazy"
                                        className="max-h-full w-auto object-contain"
                                    />
                                </div>

                                <div className="flex flex-1 flex-col p-6">
                                    <div className="text-muted-foreground flex items-center gap-3 text-xs">
                                        <span className="bg-primary/10 text-primary px-2 py-1 font-semibold tracking-wide uppercase">
                                            {post.topic}
                                        </span>
                                        {formatDate(post.date)}
                                    </div>

                                    <h2 className="group-hover:text-primary mt-3 text-lg leading-snug font-semibold transition-colors">
                                        {post.title}
                                    </h2>
                                    <p className="text-muted-foreground mt-2 flex-1 text-sm leading-relaxed">
                                        {post.excerpt}
                                    </p>

                                    <span className="text-primary mt-4 inline-flex items-center gap-1 text-sm font-semibold">
                                        Read more
                                        <ArrowRight className="size-4 transition-transform group-hover:translate-x-0.5" />
                                    </span>
                                </div>
                            </Link>
                        ))}
                    </div>
                </section>

                <MarketingFooter />
            </div>
        </>
    );
}
