import { Head, Link } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { accountsIndexUrl, sitesIndexUrl } from '@/lib/urls';

type CompanyOption = {
    id: number;
    name: string;
    uid: string;
    role: 'owner' | 'member';
};

export default function SelectAccount({
    companies,
    isSuperAdmin,
}: {
    companies: CompanyOption[];
    isSuperAdmin: boolean;
}) {
    return (
        <>
            <Head title="Select account" />

            <div className="flex flex-col gap-6">
                {isSuperAdmin && (
                    <>
                        <Button
                            asChild
                            variant="secondary"
                            size="lg"
                            className="h-auto w-full justify-between py-3"
                        >
                            <Link href={accountsIndexUrl()}>
                                <span className="flex items-center gap-2 font-medium">
                                    <ShieldCheck className="size-4" />
                                    Platform administration
                                </span>
                                <Badge variant="destructive">super-admin</Badge>
                            </Link>
                        </Button>

                        {companies.length > 0 && <Separator />}
                    </>
                )}

                {companies.length === 0 ? (
                    !isSuperAdmin && (
                        <p className="text-muted-foreground text-center text-sm">
                            You don't belong to any account yet. Ask an owner to invite you, or contact
                            support.
                        </p>
                    )
                ) : (
                    <div className="flex flex-col gap-3">
                        {companies.map((company) => (
                            <Button
                                key={company.id}
                                asChild
                                variant="outline"
                                size="lg"
                                className="h-auto w-full justify-between py-3"
                            >
                                <Link href={sitesIndexUrl(company.uid)}>
                                    <span className="font-medium">{company.name}</span>
                                    <Badge variant={company.role === 'owner' ? 'default' : 'secondary'}>
                                        {company.role}
                                    </Badge>
                                </Link>
                            </Button>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

SelectAccount.layout = {
    title: 'Select an account',
    description: 'Choose where to go',
};
