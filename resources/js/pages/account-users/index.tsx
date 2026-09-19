import { Form, Head, router, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { accountUserToggleSuperAdminUrl, accountUsersUrl, sitesIndexUrl } from '@/lib/urls';
import type { AccountUser, Company, Invitation } from '@/types';

export default function AccountUsersIndex({
    account,
    users,
    invitations,
    maxUsers,
}: {
    account: Company;
    users: AccountUser[];
    invitations: Invitation[];
    maxUsers: number;
}) {
    const { auth } = usePage().props;
    const atLimit = users.length + invitations.length >= maxUsers;

    const toggleSuperAdmin = (user: AccountUser) => {
        const message = user.is_super_admin
            ? `Revoke super-admin from ${user.name}?`
            : `Grant super-admin to ${user.name}? They'll be able to open every account on the platform.`;

        if (!confirm(message)) return;

        router.patch(accountUserToggleSuperAdminUrl(account.uid, user.id), {}, { preserveScroll: true });
    };

    return (
        <>
            <Head title="Users" />

            <div className="space-y-6 p-4">
                <Heading title="Users" description={`People with access to ${account.name}`} />

                <div className="space-y-3">
                    {users.map((user) => (
                        <Card key={user.id}>
                            <CardContent className="flex items-center justify-between">
                                <div>
                                    <div className="font-medium">{user.name}</div>
                                    <div className="text-muted-foreground text-sm">{user.email}</div>
                                </div>
                                <div className="flex items-center gap-2">
                                    {user.is_super_admin && <Badge variant="destructive">super-admin</Badge>}
                                    <Badge variant={user.role === 'owner' ? 'default' : 'secondary'}>
                                        {user.role}
                                    </Badge>
                                    {auth.user.is_super_admin && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => toggleSuperAdmin(user)}
                                        >
                                            {user.is_super_admin ? 'Revoke super-admin' : 'Make super-admin'}
                                        </Button>
                                    )}
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
                                        Invited — expires {new Date(invitation.expires_at).toLocaleDateString()}
                                    </div>
                                </div>
                                <Badge variant="outline">pending · {invitation.role}</Badge>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Invite a user</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {atLimit && (
                            <p className="text-sm text-red-600 dark:text-red-400">
                                This account allows up to {maxUsers} user(s) (including pending invitations) —
                                you're at the limit.
                            </p>
                        )}
                        <Form
                            action={accountUsersUrl(account.uid)}
                            method="post"
                            resetOnSuccess
                            className="space-y-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="email">Email</Label>
                                        <Input id="email" type="email" name="email" required disabled={atLimit} />
                                        <InputError message={errors.email} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="role">Role</Label>
                                        <Select name="role" defaultValue="member">
                                            <SelectTrigger id="role" disabled={atLimit}>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="member">Member</SelectItem>
                                                <SelectItem value="owner">Owner</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.role} />
                                    </div>

                                    <Button disabled={processing || atLimit}>Send invitation</Button>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AccountUsersIndex.layout = (props: { account: Company }) => ({
    breadcrumbs: [
        { title: props.account.name, href: sitesIndexUrl(props.account.uid) },
        { title: 'Users', href: accountUsersUrl(props.account.uid) },
    ],
});
