import { router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { adminStopImpersonatingUrl } from '@/lib/urls';

export function ImpersonationBanner() {
    const { impersonating, auth } = usePage().props;

    if (!impersonating) return null;

    return (
        <div className="bg-destructive flex items-center justify-between px-4 py-2 text-sm text-white">
            <span>Logged in as {auth.user.name} — support/debug session.</span>
            <Button
                variant="secondary"
                size="sm"
                onClick={() => router.post(adminStopImpersonatingUrl())}
            >
                Stop impersonating
            </Button>
        </div>
    );
}
