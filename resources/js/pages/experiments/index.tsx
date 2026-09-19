import { Head, Link } from '@inertiajs/react';
import { Eye, Plus } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    experimentShowUrl,
    experimentsAbCreateUrl,
    experimentsAbIndexUrl,
    experimentsIndexUrl,
    experimentsMvtCreateUrl,
    experimentsMvtIndexUrl,
    experimentsSplitUrlCreateUrl,
    experimentsSplitUrlIndexUrl,
    siteOverviewUrl,
    sitesIndexUrl,
} from '@/lib/urls';
import { experimentPreviewUrl } from '@/lib/preview';
import type { Company, Experiment, Site } from '@/types';

export default function ExperimentsIndex({
    account,
    site,
    type,
    experiments,
}: {
    account: Company;
    site: Site;
    type: 'ab' | 'split_url' | 'mvt' | null;
    experiments: Experiment[];
}) {
    // Which list this is — the unfiltered "Tests" view offers both.
    const title =
        type === 'ab'
            ? 'A/B tests'
            : type === 'split_url'
              ? 'Split URL tests'
              : type === 'mvt'
                ? 'Multivariate tests'
                : 'Tests';
    const debugUrl = (experiment: Experiment) => experimentPreviewUrl(site, experiment);

    return (
        <>
            <Head title="Tests" />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading title={title} description={`${title} for ${site.name}`} />
                    <div className="flex gap-2">
                        {type !== 'split_url' && (
                            <Button asChild>
                                <Link href={experimentsAbCreateUrl(account.uid)}>
                                    <Plus /> New A/B test
                                </Link>
                            </Button>
                        )}
                        {type !== 'ab' && type !== 'split_url' && (
                            <Button asChild variant={type === null ? 'outline' : 'default'}>
                                <Link href={experimentsMvtCreateUrl(account.uid)}>
                                    <Plus /> New multivariate test
                                </Link>
                            </Button>
                        )}
                        {type !== 'ab' && type !== 'mvt' && (
                            <Button asChild variant={type === null ? 'outline' : 'default'}>
                                <Link href={experimentsSplitUrlCreateUrl(account.uid)}>
                                    <Plus /> New split URL test
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                {experiments.length === 0 ? (
                    <p className="text-muted-foreground text-sm">No tests yet.</p>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Traffic</TableHead>
                                <TableHead />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {experiments.map((experiment) => {
                                const url = debugUrl(experiment);

                                return (
                                    <TableRow key={experiment.id}>
                                        <TableCell className="font-medium">
                                            <Link
                                                href={experimentShowUrl(account.uid, experiment.id)}
                                                className="hover:underline"
                                            >
                                                {experiment.name}
                                            </Link>
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant={experiment.status === 'running' ? 'default' : 'secondary'}>
                                                {experiment.status}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>{experiment.traffic_allocation}%</TableCell>
                                        <TableCell className="text-right">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                disabled={!url}
                                                title={
                                                    url
                                                        ? `View "${experiment.name}" with the debug panel`
                                                        : 'Set a domain (or an exact-match URL) to enable this'
                                                }
                                                asChild={!!url}
                                            >
                                                {url ? (
                                                    <a href={url} target="_blank" rel="noreferrer">
                                                        <Eye />
                                                    </a>
                                                ) : (
                                                    <Eye />
                                                )}
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                );
                            })}
                        </TableBody>
                    </Table>
                )}
            </div>
        </>
    );
}

ExperimentsIndex.layout = (props: { account: Company; site: Site; type: 'ab' | 'split_url' | 'mvt' | null }) => ({
    breadcrumbs: [
        { title: props.account.name, href: sitesIndexUrl(props.account.uid) },
        { title: props.site.name, href: siteOverviewUrl(props.account.uid) },
        props.type === 'ab'
            ? { title: 'A/B', href: experimentsAbIndexUrl(props.account.uid) }
            : props.type === 'split_url'
              ? { title: 'Split URL', href: experimentsSplitUrlIndexUrl(props.account.uid) }
              : props.type === 'mvt'
                ? { title: 'Multivariate', href: experimentsMvtIndexUrl(props.account.uid) }
                : { title: 'Tests', href: experimentsIndexUrl(props.account.uid) },
    ],
});
