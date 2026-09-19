import { Head, Link, router, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { activeSiteUrl, sitesCreateUrl, sitesIndexUrl } from '@/lib/urls';
import type { Company, Site } from '@/types';

export default function SitesIndex({ account, sites }: { account: Company; sites: Site[] }) {
    const { currentSite } = usePage().props;

    // Opening a site makes it the active one — every site-scoped page then
    // follows that selection rather than carrying an id in the URL.
    const openSite = (siteId: number) =>
        router.put(activeSiteUrl(account.uid), { site_id: siteId }, { preserveScroll: true });

    return (
        <>
            <Head title="Sites" />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading title="Sites" description={`Sites belonging to ${account.name}`} />
                    <Button asChild>
                        <Link href={sitesCreateUrl(account.uid)}>
                            <Plus /> New site
                        </Link>
                    </Button>
                </div>

                {sites.length === 0 ? (
                    <p className="text-muted-foreground text-sm">
                        No sites yet. Create one to get an embed script.
                    </p>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Domain</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Tests</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {sites.map((site) => (
                                <TableRow key={site.id}>
                                    <TableCell className="font-medium">
                                        <button
                                            type="button"
                                            onClick={() => openSite(site.id)}
                                            className="hover:underline"
                                        >
                                            {site.name}
                                        </button>
                                        {site.id === currentSite?.id && (
                                            <span className="text-muted-foreground ml-2 text-xs">
                                                active
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell>{site.domain ?? '—'}</TableCell>
                                    <TableCell>{site.status}</TableCell>
                                    <TableCell>{site.experiments_count ?? 0}</TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </div>
        </>
    );
}

SitesIndex.layout = (props: { account: Company }) => ({
    breadcrumbs: [{ title: props.account.name, href: sitesIndexUrl(props.account.uid) }],
});
