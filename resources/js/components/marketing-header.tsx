import { Link, usePage } from "@inertiajs/react";
import {
    ArrowRight,
    BarChart3,
    Bug,
    Flame,
    FlaskConical,
    Menu,
    MousePointerClick,
    ShoppingBag,
    ShoppingCart,
    Split,
    Target,
    Video,
} from "lucide-react";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import {
    NavigationMenu,
    NavigationMenuContent,
    NavigationMenuItem,
    NavigationMenuLink,
    NavigationMenuList,
    NavigationMenuTrigger,
} from "@/components/ui/navigation-menu";
import {
    Sheet,
    SheetContent,
    SheetTitle,
    SheetTrigger,
} from "@/components/ui/sheet";
import { dashboard, login, register } from "@/routes";

const featureLinks = [
    {
        slug: "ab-testing",
        icon: FlaskConical,
        title: "A/B testing",
        description: "Swap text, HTML, or CSS on any element.",
        chip: "bg-primary/10 text-primary",
    },
    {
        slug: "url-targeting",
        icon: Target,
        title: "URL targeting",
        description: "Exact, contains, or regex page matching.",
        chip: "bg-violet-500/10 text-violet-500",
    },
    {
        slug: "split-url-tests",
        icon: Split,
        title: "Split URL tests",
        description: "Redirect a slice of visitors to a new page.",
        chip: "bg-rose-500/10 text-rose-500",
    },
    {
        slug: "heatmaps",
        icon: MousePointerClick,
        title: "Heatmaps",
        description: "Clicks, mouse movement, and scroll depth.",
        chip: "bg-primary/10 text-primary",
    },
    {
        slug: "analytics",
        icon: BarChart3,
        title: "Analytics",
        description: "Impressions and conversion per variation.",
        chip: "bg-violet-500/10 text-violet-500",
    },
    {
        slug: "variation-heatmaps",
        icon: Flame,
        title: "Heatmaps by variation",
        description: "See the behaviour behind each result.",
        chip: "bg-orange-500/10 text-orange-500",
    },
    {
        slug: "revenue-tracking",
        icon: ShoppingCart,
        title: "Revenue tracking",
        description: "Test against money, not just clicks.",
        chip: "bg-emerald-500/10 text-emerald-500",
    },
    {
        slug: "session-recording",
        icon: Video,
        title: "Session recording",
        description: "Replay a visit, filtered by variation.",
        chip: "bg-sky-500/10 text-sky-500",
    },
    {
        slug: "shopify",
        icon: ShoppingBag,
        title: "Shopify",
        description: "Attribute real orders to a variation.",
        chip: "bg-lime-500/10 text-lime-600",
    },
    {
        slug: "live-preview-debugging",
        icon: Bug,
        title: "Live preview & debugging",
        description: "Force a variation live and see what matched.",
        chip: "bg-rose-500/10 text-rose-500",
    },
];

const navLinks = [
    { title: "Why abify?", href: "/why-abify" },
    { title: "Pricing", href: "/pricing" },
    { title: "Blog", href: "/blog" },
    { title: "Contact", href: "/contact" },
];

const navTriggerClassName =
    "text-foreground data-[state=open]:text-primary hover:text-primary h-auto bg-transparent px-3 py-2 text-base font-medium hover:bg-transparent focus:bg-transparent data-[state=open]:bg-transparent";

/* Layout for a mega-menu row. It has to live on NavigationMenuLink's own
   className — that one goes through cn()/tailwind-merge, so `flex-row` wins
   over the base `flex-col`. Put on the child <Link> instead, Radix's Slot
   just concatenates the strings and the base direction sticks. */
const featureRowClassName =
    "group flex-row items-start gap-3 rounded-none border border-transparent p-3 hover:border-border hover:bg-accent/60";

export function MarketingHeader() {
    const { auth } = usePage().props;
    const [mobileOpen, setMobileOpen] = useState(false);

    return (
        <header className="sticky top-0 z-40 w-full border-transparent [background:linear-gradient(var(--background),var(--background))_padding-box,linear-gradient(90deg,#2F6FED,#7C3AED)_border-box]">
            <div className="mx-auto flex h-16 max-w-6xl items-center gap-8 px-6">
                <Link href="/" className="shrink-0">
                    <img
                        src="/images/logo.png"
                        alt="abify.io"
                        className="h-7 w-auto dark:hidden"
                    />
                    <img
                        src="/images/logo-dark.png"
                        alt="abify.io"
                        className="hidden h-7 w-auto dark:block"
                    />
                </Link>

                <NavigationMenu viewport={false} className="hidden md:flex">
                    <NavigationMenuList className="gap-1">
                        <NavigationMenuItem>
                            <NavigationMenuTrigger
                                className={navTriggerClassName}
                            >
                                Features
                            </NavigationMenuTrigger>
                            <NavigationMenuContent className="p-0">
                                <div className="w-[46rem]">
                                    <div className="grid grid-cols-2 gap-1 p-2">
                                        {featureLinks.map((feature) => (
                                            <NavigationMenuLink
                                                key={feature.slug}
                                                asChild
                                                className={featureRowClassName}
                                            >
                                                <Link
                                                    href={`/features/${feature.slug}`}
                                                >
                                                    <span
                                                        className={`flex size-9 shrink-0 items-center justify-center ${feature.chip}`}
                                                    >
                                                        <feature.icon className="size-[1.125rem] text-inherit" />
                                                    </span>
                                                    <span className="flex flex-col gap-0.5">
                                                        <span className="text-foreground text-base leading-tight font-semibold">
                                                            {feature.title}
                                                        </span>
                                                        <span className="text-muted-foreground text-[0.8125rem] leading-snug">
                                                            {
                                                                feature.description
                                                            }
                                                        </span>
                                                    </span>
                                                </Link>
                                            </NavigationMenuLink>
                                        ))}
                                    </div>

                                    <div className="border-border bg-muted/40 flex items-center justify-between border-t px-5 py-3">
                                        <span className="text-muted-foreground text-[0.8125rem]">
                                            Everything you need to ship a test
                                            today.
                                        </span>
                                        <NavigationMenuLink
                                            asChild
                                            className="text-primary w-auto flex-row items-center gap-1 p-0 text-[0.8125rem] font-semibold whitespace-nowrap hover:bg-transparent hover:underline"
                                        >
                                            <Link href="/features">
                                                See all features
                                                <ArrowRight className="size-3.5 text-primary" />
                                            </Link>
                                        </NavigationMenuLink>
                                    </div>
                                </div>
                            </NavigationMenuContent>
                        </NavigationMenuItem>

                        {navLinks.map((link) => (
                            <NavigationMenuItem key={link.href}>
                                <NavigationMenuLink
                                    asChild
                                    className={navTriggerClassName}
                                >
                                    <Link href={link.href}>{link.title}</Link>
                                </NavigationMenuLink>
                            </NavigationMenuItem>
                        ))}
                    </NavigationMenuList>
                </NavigationMenu>

                <div className="ml-auto flex items-center gap-2">
                    {auth.user ? (
                        <Button asChild>
                            <Link href={dashboard()}>Dashboard</Link>
                        </Button>
                    ) : (
                        <>
                            <Link
                                href={login()}
                                className="text-foreground hover:text-primary hidden px-3 text-base font-medium sm:inline-flex"
                            >
                                Log in
                            </Link>
                            <Button asChild className="hidden sm:inline-flex">
                                <Link href={register()}>Start free trial</Link>
                            </Button>
                        </>
                    )}

                    <Sheet open={mobileOpen} onOpenChange={setMobileOpen}>
                        <SheetTrigger asChild>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="md:hidden"
                                aria-label="Open menu"
                            >
                                <Menu />
                            </Button>
                        </SheetTrigger>
                        <SheetContent
                            side="right"
                            aria-describedby={undefined}
                            className="w-[19rem] gap-0 p-0"
                        >
                            <SheetTitle className="border-border border-b px-5 py-4 text-base">
                                Menu
                            </SheetTitle>

                            <nav className="flex flex-col overflow-y-auto px-2 py-2">
                                {featureLinks.map((feature) => (
                                    <Link
                                        key={feature.slug}
                                        href={`/features/${feature.slug}`}
                                        onClick={() => setMobileOpen(false)}
                                        className="hover:bg-accent flex items-center gap-3 px-3 py-2.5"
                                    >
                                        <span
                                            className={`flex size-8 shrink-0 items-center justify-center ${feature.chip}`}
                                        >
                                            <feature.icon className="size-4 text-inherit" />
                                        </span>
                                        <span className="text-base font-medium">
                                            {feature.title}
                                        </span>
                                    </Link>
                                ))}

                                <div className="border-border my-2 border-t" />

                                {navLinks.map((link) => (
                                    <Link
                                        key={link.href}
                                        href={link.href}
                                        onClick={() => setMobileOpen(false)}
                                        className="hover:bg-accent px-3 py-2.5 text-base font-medium"
                                    >
                                        {link.title}
                                    </Link>
                                ))}
                            </nav>

                            <div className="border-border mt-auto flex flex-col gap-2 border-t p-4">
                                {auth.user ? (
                                    <Button asChild>
                                        <Link href={dashboard()}>
                                            Dashboard
                                        </Link>
                                    </Button>
                                ) : (
                                    <>
                                        <Button variant="outline" asChild>
                                            <Link href={login()}>Log in</Link>
                                        </Button>
                                        <Button asChild>
                                            <Link href={register()}>
                                                Start free trial
                                            </Link>
                                        </Button>
                                    </>
                                )}
                            </div>
                        </SheetContent>
                    </Sheet>
                </div>
            </div>
        </header>
    );
}
