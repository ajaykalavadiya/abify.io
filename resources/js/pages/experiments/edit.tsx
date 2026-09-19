import { Head } from '@inertiajs/react';
import { ExperimentForm, type ExperimentType } from '@/components/experiments/experiment-form';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import {
    experimentEditUrl,
    experimentShowUrl,
    experimentUpdateUrl,
    experimentsAbIndexUrl,
    experimentsSplitUrlIndexUrl,
    siteOverviewUrl,
    sitesIndexUrl,
} from '@/lib/urls';
import type { Company, Experiment, Site } from '@/types';

export default function ExperimentsEdit({
    account,
    site,
    experiment,
}: {
    account: Company;
    site: Site;
    experiment: Experiment;
}) {
    const type = experiment.type as ExperimentType;

    return (
        <>
            <Head title={`Edit ${experiment.name}`} />

            <div className="max-w-xl space-y-6 p-4">
                <div className="flex items-center gap-2">
                    <Heading title="Edit test" description={experiment.name} />
                    <Badge variant="secondary">
                        {type === 'ab' ? 'A/B' : type === 'mvt' ? 'Multivariate' : 'Split URL'}
                    </Badge>
                </div>

                <p className="text-muted-foreground text-sm">
                    A test&apos;s type can&apos;t be changed after it&apos;s created — its variations are
                    already set up for{' '}
                    {type === 'ab'
                        ? 'element changes'
                        : type === 'mvt'
                          ? 'combinations of element changes'
                          : 'redirects'}
                    . Create a new test to switch types.
                </p>

                <ExperimentForm
                    action={experimentUpdateUrl(account.uid, experiment.id)}
                    method="patch"
                    type={type}
                    site={site}
                    experiment={experiment}
                    submitLabel="Save test"
                />
            </div>
        </>
    );
}

ExperimentsEdit.layout = (props: { account: Company; site: Site; experiment: Experiment }) => ({
    breadcrumbs: [
        { title: props.account.name, href: sitesIndexUrl(props.account.uid) },
        { title: props.site.name, href: siteOverviewUrl(props.account.uid) },
        props.experiment.type === 'ab'
            ? { title: 'A/B', href: experimentsAbIndexUrl(props.account.uid) }
            : { title: 'Split URL', href: experimentsSplitUrlIndexUrl(props.account.uid) },
        {
            title: props.experiment.name,
            href: experimentShowUrl(props.account.uid, props.experiment.id),
        },
        { title: 'Edit', href: experimentEditUrl(props.account.uid, props.experiment.id) },
    ],
});
