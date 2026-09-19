import { Link } from "@inertiajs/react";
import { login, register } from "@/routes";

const columns = [
    {
        title: "Product",
        links: [
            { title: "Features", href: "/features" },
            { title: "Why abify?", href: "/why-abify" },
            { title: "Pricing", href: "/pricing" },
            { title: "Blog", href: "/blog" },
            { title: "Careers", href: "/careers" },
            { title: "Contact", href: "/contact" },
        ],
    },
    {
        title: "Features",
        links: [
            { title: "A/B testing", href: "/features/ab-testing" },
            { title: "Split URL tests", href: "/features/split-url-tests" },
            { title: "Heatmaps", href: "/features/heatmaps" },
            { title: "Analytics", href: "/features/analytics" },
            {
                title: "Heatmaps by variation",
                href: "/features/variation-heatmaps",
            },
            { title: "Revenue tracking", href: "/features/revenue-tracking" },
            { title: "Session recording", href: "/features/session-recording" },
            { title: "Shopify", href: "/features/shopify" },
        ],
    },
    {
        title: "Account",
        links: [
            { title: "Log in", href: login() },
            { title: "Start free trial", href: register() },
        ],
    },
];

export function MarketingFooter() {
    return (
        <footer className="bg-zinc-950 text-zinc-400">
            <div className="mx-auto max-w-6xl px-6 py-16">
                <div className="grid gap-10 sm:grid-cols-[1.4fr_1fr_1fr_1fr]">
                    <div>
                        <Link href="/">
                            <img
                                src="/images/logo-dark.png"
                                alt="abify.io"
                                className="h-8 w-auto"
                            />
                        </Link>
                        <p className="mt-3 max-w-xs text-sm">
                            A/B testing, analytics, and heatmaps — from one
                            script tag.
                        </p>
                        <p className="mt-4 text-xs tracking-widest text-zinc-500 uppercase">
                            Test <span className="mx-1.5">&bull;</span> Analyze{" "}
                            <span className="mx-1.5">&bull;</span> Grow
                        </p>
                    </div>

                    {columns.map((column) => (
                        <div key={column.title}>
                            <div className="mb-3 text-sm font-medium text-white">
                                {column.title}
                            </div>
                            <ul className="space-y-2">
                                {column.links.map((link) => (
                                    <li key={link.title}>
                                        <Link
                                            href={link.href}
                                            className="text-sm hover:text-white"
                                        >
                                            {link.title}
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ))}
                </div>

                <div className="mt-12 flex flex-col items-center justify-between gap-4 border-t border-white/10 pt-6 text-sm sm:flex-row">
                    <span>
                        &copy; {new Date().getFullYear()} abify.io. Built on
                        Laravel, Inertia, and Stripe.
                    </span>
                </div>
            </div>
        </footer>
    );
}
