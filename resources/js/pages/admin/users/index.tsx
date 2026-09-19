import { Head, Link, router } from '@inertiajs/react';
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
import { adminImpersonateUrl, adminUsersUrl, sitesIndexUrl } from '@/lib/urls';

type CompanyMembership = {
    id: number;
    name: string;
    uid: string;
    role: string;
};

type PlatformUser = {
    id: number;
    name: string;
    email: string;
    is_super_admin: boolean;
    companies: CompanyMembership[];
};

export default function AdminUsersIndex({ users }: { users: PlatformUser[] }) {
    const impersonate = (user: PlatformUser) => {
        if (!confirm(`Log in as ${user.name} (${user.email})?`)) return;
        router.post(adminImpersonateUrl(user.id));
    };

    return (
        <>
            <Head title="All users" />

            <div className="space-y-6 p-4">
                <Heading title="All users" description="Every user on the platform, across every account" />

                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Email</TableHead>
                            <TableHead>Accounts</TableHead>
                            <TableHead />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {users.map((user) => (
                            <TableRow key={user.id}>
                                <TableCell className="font-medium">
                                    {user.name}
                                    {user.is_super_admin && (
                                        <Badge variant="destructive" className="ml-2">
                                            super-admin
                                        </Badge>
                                    )}
                                </TableCell>
                                <TableCell>{user.email}</TableCell>
                                <TableCell>
                                    <div className="flex flex-wrap gap-1">
                                        {user.companies.map((company) => (
                                            <Link key={company.id} href={sitesIndexUrl(company.uid)}>
                                                <Badge variant="secondary">
                                                    {company.name} · {company.role}
                                                </Badge>
                                            </Link>
                                        ))}
                                    </div>
                                </TableCell>
                                <TableCell className="text-right">
                                    {!user.is_super_admin && (
                                        <Button variant="outline" size="sm" onClick={() => impersonate(user)}>
                                            Login as
                                        </Button>
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

AdminUsersIndex.layout = {
    breadcrumbs: [{ title: 'All users', href: adminUsersUrl() }],
};
