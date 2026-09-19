import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
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
import { accountShowUrl, accountsCreateUrl, accountsIndexUrl } from '@/lib/urls';
import type { Company } from '@/types';

export default function AccountsIndex({ accounts }: { accounts: Company[] }) {
    return (
        <>
            <Head title="Accounts" />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Accounts"
                        description="Every company on the platform, with their sites and running tests"
                    />
                    <Button asChild>
                        <Link href={accountsCreateUrl()}>
                            <Plus /> New account
                        </Link>
                    </Button>
                </div>

                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Account</TableHead>
                            <TableHead>UID</TableHead>
                            <TableHead>Sites</TableHead>
                            <TableHead>Active tests</TableHead>
                            <TableHead>Tenant DB</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {accounts.map((account) => (
                            <TableRow key={account.id}>
                                <TableCell className="font-medium">
                                    <Link
                                        href={accountShowUrl(account.uid)}
                                        className="hover:underline"
                                    >
                                        {account.name}
                                    </Link>
                                </TableCell>
                                <TableCell className="font-mono text-xs">{account.uid}</TableCell>
                                <TableCell>{account.sites_count ?? 0}</TableCell>
                                <TableCell>{account.active_tests_count ?? 0}</TableCell>
                                <TableCell>
                                    {account.database ? (
                                        <Badge variant="secondary">{account.database}</Badge>
                                    ) : (
                                        <Badge variant="destructive">not provisioned</Badge>
                                    )}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>
        </>
    );
}

AccountsIndex.layout = {
    breadcrumbs: [{ title: 'Accounts', href: accountsIndexUrl() }],
};
