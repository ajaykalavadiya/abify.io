import { Head, Link } from '@inertiajs/react';
import { ExperimentForm, type ExperimentType } from '@/components/experiments/experiment-form';
import Heading from '@/components/heading';
import {
    billingUrl,
    experimentsAbCreateUrl,
    experimentsAbIndexUrl,
    experimentsAbStoreUrl,
    experimentsSplitUrlCreateUrl,
    experimentsSplitUrlIndexUrl,
    experimentsSplitUrlStoreUrl,
    siteOverviewUrl,
    sitesIndexUrl,
} from '@/lib/urls';
import type { Company, PlanLimits, Site } from '@/types';

const heading: Record<ExperimentType, { title: string; description: string }> = {
    ab: {
        title: 'New A/B test',
        description: 'Change text, HTML, or CSS on a page and compare against the original.',
    },
    split_url: {
        title: 'New split URL test',
        description: 'Send a share of visitors to a different page and compare conversion.',
    },
    // Multivariate has its own page (create-mvt) — listed here only so the
    // map covers every type.
    mvt: {
        title: 'New multivariate test',
        description: 'Test several elements at once and compare every combination.',
    },
};

export default function ExperimentsCreate({
    account,
    site,
    type,
    planLimits,
    testsCount,
}: {
    account: Company;
    site: Site;
    type: ExperimentType;
    planLimits: PlanLimits;
    testsCount: number;
}) {
    const atLimit = testsCount >= planLimits.tests_per_site;
    const copy = heading[type];
    const action =
        type === 'ab' ? experimentsAbStoreUrl(account.uid) : experimentsSplitUrlStoreUrl(account.uid);

    return (
        <>
            <Head title={copy.title} />

            <div className="max-w-xl space-y-6 p-4">
                <Heading title={copy.title} description={`${copy.description} — on ${site.name}`} />

                {atLimit ? (
                    <p className="text-sm text-red-600 dark:text-red-400">
                        Your {planLimits.label} plan allows up to {planLimits.tests_per_site} test(s) per site
                        — this site is at {testsCount}.{' '}
                        <Link href={billingUrl(account.uid)} className="underline">
                            Upgrade
                        </Link>{' '}
                        to add more.
                    </p>
                ) : (
                    <p className="text-muted-foreground text-sm">
                        {testsCount} of {planLimits.tests_per_site} tests used on this site (
                        {planLimits.label} plan).
                    </p>
                )}

                <ExperimentForm
                    action={action}
                    method="post"
                    type={type}
                    site={site}
                    submitLabel={type === 'ab' ? 'Create A/B test' : 'Create split URL test'}
                    disabled={atLimit}
                />
            </div>
        </>
    );
}

ExperimentsCreate.layout = (props: { account: Company; site: Site; type: ExperimentType }) => ({
    breadcrumbs: [
        { title: props.account.name, href: sitesIndexUrl(props.account.uid) },
        { title: props.site.name, href: siteOverviewUrl(props.account.uid) },
        props.type === 'ab'
            ? { title: 'A/B', href: experimentsAbIndexUrl(props.account.uid) }
            : { title: 'Split URL', href: experimentsSplitUrlIndexUrl(props.account.uid) },
        {
            title: props.type === 'ab' ? 'New A/B test' : 'New split URL test',
            href:
                props.type === 'ab'
                    ? experimentsAbCreateUrl(props.account.uid)
                    : experimentsSplitUrlCreateUrl(props.account.uid),
        },
    ],
});
