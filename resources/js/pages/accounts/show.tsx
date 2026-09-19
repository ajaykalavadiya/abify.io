import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { accountShowUrl, accountUsersUrl, accountsIndexUrl, sitesIndexUrl } from '@/lib/urls';
import type { AccountUser, Company, Invitation } from '@/types';

export default function AccountsShow({
    account,
    users,
    invitations,
}: {
    account: Company;
    users: AccountUser[];
    invitations: Invitation[];
}) {
    return (
        <>
            <Head title={account.name} />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading title={account.name} description={`Account UID: ${account.uid}`} />
                    <Button asChild>
                        <Link href={sitesIndexUrl(account.uid)}>Open account</Link>
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Overview</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-wrap gap-4 text-sm">
                        <div>
                            <div className="text-muted-foreground">Sites</div>
                            <div className="font-medium">{account.sites_count ?? 0}</div>
                        </div>
                        <div>
                            <div className="text-muted-foreground">Active tests</div>
                            <div className="font-medium">{account.active_tests_count ?? 0}</div>
                        </div>
                        <div>
                            <div className="text-muted-foreground">Tenant database</div>
                            <div className="font-medium">
                                {account.database ? (
                                    <Badge variant="secondary">{account.database}</Badge>
                                ) : (
                                    <Badge variant="destructive">not provisioned</Badge>
                                )}
                            </div>
                        </div>
                        {account.trial_ends_at && (
                            <div>
                                <div className="text-muted-foreground">Trial ends</div>
                                <div className="font-medium">
                                    {new Date(account.trial_ends_at).toLocaleDateString()}
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <div className="space-y-3">
                    <div className="flex items-center justify-between">
                        <Heading variant="small" title="Users" />
                        <Button variant="outline" size="sm" asChild>
                            <Link href={accountUsersUrl(account.uid)}>Manage users</Link>
                        </Button>
                    </div>

                    {users.length === 0 && invitations.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No users yet.</p>
                    ) : (
                        <div className="space-y-2">
                            {users.map((user) => (
                                <Card key={user.id}>
                                    <CardContent className="flex items-center justify-between">
                                        <div>
                                            <div className="font-medium">{user.name}</div>
                                            <div className="text-muted-foreground text-sm">{user.email}</div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            {user.is_super_admin && (
                                                <Badge variant="destructive">super-admin</Badge>
                                            )}
                                            <Badge variant={user.role === 'owner' ? 'default' : 'secondary'}>
                                                {user.role}
                                            </Badge>
                                        </div>
                                    </CardContent>
                                </Card>
                            ))}

                            {invitations.map((invitation) => (
                                <Card key={invitation.id} className="border-dashed">
                                    <CardContent className="flex items-center justify-between">
                                        <div>
                                            <div className="font-medium">{invitation.email}</div>
                                            <div className="text-muted-foreground text-sm">
                                                Invited — expires{' '}
                                                {new Date(invitation.expires_at).toLocaleDateString()}
                                            </div>
                                        </div>
                                        <Badge variant="outline">pending · {invitation.role}</Badge>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

AccountsShow.layout = (props: { account: Company }) => ({
    breadcrumbs: [
        { title: 'Accounts', href: accountsIndexUrl() },
        { title: props.account.name, href: accountShowUrl(props.account.uid) },
    ],
});
