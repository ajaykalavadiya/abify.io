import { Link, usePage } from "@inertiajs/react";
import AppLogoIcon from "@/components/app-logo-icon";
import { home } from "@/routes";
import type { AuthLayoutProps } from "@/types";

/**
 * Full-height two-panel auth screen: the form on the left, a product image on
 * the right.
 *
 * The image panel is hidden below `lg` rather than stacked. On a phone it
 * would push the form below the fold, and the form is the only thing on this
 * screen anyone came to use.
 */
export default function AuthImageLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { name } = usePage().props;

    return (
        <div className="grid min-h-dvh lg:grid-cols-2">
            {/* Panel one — the form */}
            <div className="flex flex-col gap-6 p-6 md:p-10">
                <div className="flex justify-center lg:justify-start">
                    <Link
                        href={home()}
                        className="flex items-center gap-2 font-medium"
                    >
                        <AppLogoIcon className="size-8" />
                        {name}
                    </Link>
                </div>

                <div className="flex flex-1 items-center justify-center">
                    <div className="w-full max-w-sm space-y-6">
                        <div className="space-y-2 text-center lg:text-left">
                            <h1 className="text-2xl font-semibold tracking-tight">
                                {title}
                            </h1>
                            {description && (
                                <p className="text-muted-foreground text-sm text-balance">
                                    {description}
                                </p>
                            )}
                        </div>

                        {children}
                    </div>
                </div>
            </div>

            {/* Panel two — the image */}
            <div className="bg-muted relative hidden overflow-hidden lg:block">
                <img
                    src="/images/hero-ab-testing.png"
                    // Decorative: the form beside it already says what this
                    // screen is, so announcing the image adds noise for a
                    // screen reader rather than information.
                    alt=""
                    aria-hidden="true"
                    className="absolute inset-0 size-full object-cover"
                />

                {/* Keeps the caption legible whatever the image does behind
                    it, and stops a bright image fighting the form. */}
                <div className="absolute inset-0 bg-gradient-to-t from-zinc-950/80 via-zinc-950/20 to-transparent" />

                <div className="absolute inset-x-0 bottom-0 p-10">
                    <p className="max-w-md text-lg font-medium text-white">
                        Ship experiments without the headache
                    </p>
                    <p className="mt-2 max-w-md text-sm text-white/70">
                        A/B testing, revenue tracking, heatmaps and session
                        replay in one lightweight script.
                    </p>
                </div>
            </div>
        </div>
    );
}
