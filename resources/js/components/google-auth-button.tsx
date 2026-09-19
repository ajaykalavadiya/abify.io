import { usePage } from "@inertiajs/react";
import { Button } from "@/components/ui/button";

/**
 * Google's mark, inlined rather than loaded from a CDN — an auth screen
 * should not depend on a third-party request to render its own button.
 */
function GoogleIcon() {
    return (
        <svg className="size-4" viewBox="0 0 24 24" aria-hidden="true">
            <path
                fill="#4285F4"
                d="M23.06 12.25c0-.85-.08-1.67-.22-2.45H12v4.64h6.2a5.3 5.3 0 0 1-2.3 3.48v2.9h3.72c2.18-2 3.44-4.96 3.44-8.57Z"
            />
            <path
                fill="#34A853"
                d="M12 24c3.11 0 5.72-1.03 7.62-2.79l-3.72-2.89c-1.03.69-2.35 1.1-3.9 1.1-3 0-5.54-2.02-6.45-4.74H1.7v2.98A11.5 11.5 0 0 0 12 24Z"
            />
            <path
                fill="#FBBC05"
                d="M5.55 14.68a6.9 6.9 0 0 1 0-4.36V7.34H1.7a11.5 11.5 0 0 0 0 10.32l3.85-2.98Z"
            />
            <path
                fill="#EA4335"
                d="M12 4.75c1.69 0 3.21.58 4.4 1.72l3.3-3.3C17.72 1.24 15.11 0 12 0 7.46 0 3.54 2.6 1.7 6.4l3.85 2.98C6.46 6.77 9 4.75 12 4.75Z"
            />
        </svg>
    );
}

/**
 * Renders nothing when Google credentials are not configured — better than a
 * button that leads to a 404.
 */
export function GoogleAuthButton({ label }: { label: string }) {
    const { googleAuthEnabled } = usePage().props;

    if (!googleAuthEnabled) return null;

    return (
        <div className="space-y-4">
            <Button
                type="button"
                variant="outline"
                className="w-full"
                // A full page visit, not an Inertia one: this leaves the app
                // for Google's consent screen, and an XHR cannot follow a
                // cross-origin redirect.
                onClick={() => {
                    window.location.href = "/auth/google";
                }}
            >
                <GoogleIcon />
                {label}
            </Button>

            <div className="relative">
                <div className="absolute inset-0 flex items-center">
                    <span className="border-border w-full border-t" />
                </div>
                <div className="relative flex justify-center text-xs uppercase">
                    <span className="bg-background text-muted-foreground px-2">
                        or
                    </span>
                </div>
            </div>
        </div>
    );
}
