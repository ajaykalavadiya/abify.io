import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { invitationAcceptUrl } from '@/lib/urls';

type InvitationInfo = {
    email: string;
    role: string;
    companyName: string;
    expired: boolean;
    accepted: boolean;
};

export default function InvitationsShow({
    invitation,
    existingAccount,
    token,
}: {
    invitation: InvitationInfo;
    existingAccount: boolean;
    token: string;
}) {
    if (invitation.expired || invitation.accepted) {
        return (
            <>
                <Head title="Invitation" />
                <p className="text-muted-foreground text-sm">
                    {invitation.accepted
                        ? 'This invitation has already been accepted.'
                        : 'This invitation has expired — ask for a new one.'}
                </p>
            </>
        );
    }

    return (
        <>
            <Head title="Accept invitation" />

            <Form action={invitationAcceptUrl(token)} method="post" className="flex flex-col gap-6">
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label>Email</Label>
                                <Input value={invitation.email} disabled />
                            </div>

                            {!existingAccount && (
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Name</Label>
                                    <Input id="name" name="name" required autoFocus />
                                    <InputError message={errors.name} />
                                </div>
                            )}

                            <div className="grid gap-2">
                                <Label htmlFor="password">
                                    {existingAccount ? 'Your existing password' : 'Password'}
                                </Label>
                                <PasswordInput id="password" name="password" required autoFocus={existingAccount} />
                                <InputError message={errors.password} />
                                {existingAccount && (
                                    <p className="text-muted-foreground text-xs">
                                        An account already exists for this email — enter its password to accept
                                        the invitation on that account.
                                    </p>
                                )}
                            </div>

                            {!existingAccount && (
                                <div className="grid gap-2">
                                    <Label htmlFor="password_confirmation">Confirm password</Label>
                                    <PasswordInput
                                        id="password_confirmation"
                                        name="password_confirmation"
                                        required
                                    />
                                </div>
                            )}

                            <Button type="submit" className="w-full" disabled={processing}>
                                Accept invitation to {invitation.companyName}
                            </Button>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}

InvitationsShow.layout = () => ({
    title: 'Accept invitation',
    description: 'Join the account below',
});
