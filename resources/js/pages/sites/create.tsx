import { Form, Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { billingUrl, sitesCreateUrl, sitesIndexUrl } from '@/lib/urls';
import type { Company, PlanLimits } from '@/types';

export default function SitesCreate({
    account,
    planLimits,
    sitesCount,
}: {
    account: Company;
    planLimits: PlanLimits;
    sitesCount: number;
}) {
    const atLimit = sitesCount >= planLimits.sites;

    return (
        <>
            <Head title="New site" />

            <div className="max-w-xl space-y-6 p-4">
                <Heading title="New site" description="Add a site to generate its tracking script" />

                {atLimit ? (
                    <p className="text-sm text-red-600 dark:text-red-400">
                        Your {planLimits.label} plan allows up to {planLimits.sites} site(s) — you're at{' '}
                        {sitesCount}.{' '}
                        <Link href={billingUrl(account.uid)} className="underline">
                            Upgrade
                        </Link>{' '}
                        to add more.
                    </p>
                ) : (
                    <p className="text-muted-foreground text-sm">
                        {sitesCount} of {planLimits.sites} sites used on the {planLimits.label} plan.
                    </p>
                )}

                <Form action={sitesIndexUrl(account.uid)} method="post" className="space-y-4">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>
                                <Input id="name" name="name" required autoFocus disabled={atLimit} />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="domain">Domain</Label>
                                <Input id="domain" name="domain" placeholder="example.com" disabled={atLimit} />
                                <InputError message={errors.domain} />
                            </div>

                            <Button disabled={processing || atLimit}>Create site</Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

SitesCreate.layout = (props: { account: Company }) => ({
    breadcrumbs: [
        { title: props.account.name, href: sitesIndexUrl(props.account.uid) },
        { title: 'New site', href: sitesCreateUrl(props.account.uid) },
    ],
});
