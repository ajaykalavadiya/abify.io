import { Form, Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { accountsCreateUrl, accountsIndexUrl } from '@/lib/urls';

export default function AccountsCreate() {
    return (
        <>
            <Head title="New account" />

            <div className="max-w-xl space-y-6 p-4">
                <Heading
                    title="New account"
                    description="Create a company and provision its tenant database — the owner sets their own password by accepting an emailed invitation, same as inviting anyone else."
                />

                <Form action={accountsIndexUrl()} method="post" className="space-y-4">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Company name</Label>
                                <Input id="name" name="name" required autoFocus />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="owner_email">Owner email</Label>
                                <Input id="owner_email" type="email" name="owner_email" required />
                                <InputError message={errors.owner_email} />
                            </div>

                            <Button disabled={processing}>Create account &amp; send invitation</Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

AccountsCreate.layout = () => ({
    breadcrumbs: [
        { title: 'Accounts', href: accountsIndexUrl() },
        { title: 'New account', href: accountsCreateUrl() },
    ],
});
