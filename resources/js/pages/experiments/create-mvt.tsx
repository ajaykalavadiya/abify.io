import { Form, Head, Link } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    billingUrl,
    experimentsMvtCreateUrl,
    experimentsMvtIndexUrl,
    experimentsMvtStoreUrl,
    siteOverviewUrl,
    sitesIndexUrl,
} from '@/lib/urls';
import type { Company, PlanLimits, Site } from '@/types';

type Option = { id: number; label: string };
type Factor = { id: number; action: string; options: Option[] };

let nextId = 1;
const makeOption = (): Option => ({ id: nextId++, label: '' });
const makeFactor = (): Factor => ({ id: nextId++, action: 'text', options: [makeOption(), makeOption()] });

export default function ExperimentsCreateMvt({
    account,
    site,
    planLimits,
    testsCount,
    maxCombinations,
}: {
    account: Company;
    site: Site;
    planLimits: PlanLimits;
    testsCount: number;
    maxCombinations: number;
}) {
    const atLimit = testsCount >= planLimits.tests_per_site;
    const [factors, setFactors] = useState<Factor[]>([makeFactor(), makeFactor()]);

    const combinations = factors.reduce((total, factor) => total * Math.max(1, factor.options.length), 1);
    const overCombinationLimit = combinations > maxCombinations;

    const addFactor = () => setFactors((current) => [...current, makeFactor()]);
    const removeFactor = (id: number) => setFactors((current) => current.filter((f) => f.id !== id));
    const setAction = (id: number, action: string) =>
        setFactors((current) => current.map((f) => (f.id === id ? { ...f, action } : f)));
    const addOption = (id: number) =>
        setFactors((current) =>
            current.map((f) => (f.id === id ? { ...f, options: [...f.options, makeOption()] } : f)),
        );
    const removeOption = (factorId: number, optionId: number) =>
        setFactors((current) =>
            current.map((f) =>
                f.id === factorId ? { ...f, options: f.options.filter((o) => o.id !== optionId) } : f,
            ),
        );

    return (
        <>
            <Head title="New multivariate test" />

            <div className="max-w-2xl space-y-6 p-4">
                <Heading
                    title="New multivariate test"
                    description={`Test several elements at once and compare every combination — on ${site.name}`}
                />

                {atLimit ? (
                    <p className="text-sm text-red-600 dark:text-red-400">
                        Your {planLimits.label} plan allows up to {planLimits.tests_per_site} test(s) per site
                        — this site is at {testsCount}.{' '}
                        <Link href={billingUrl(account.uid)} className="underline">
                            Upgrade
                        </Link>{' '}
                        to add more.
                    </p>
                ) : (
                    <p className="text-muted-foreground text-sm">
                        {testsCount} of {planLimits.tests_per_site} tests used on this site (
                        {planLimits.label} plan).
                    </p>
                )}

                <Form action={experimentsMvtStoreUrl(account.uid)} method="post" className="space-y-4">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>
                                <Input id="name" name="name" required autoFocus disabled={atLimit} />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="url_pattern">Test URL</Label>
                                <div className="flex gap-2">
                                    <Select name="url_match_type" defaultValue="contains">
                                        <SelectTrigger className="w-36 shrink-0" id="url_match_type">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="exact">Exact match</SelectItem>
                                            <SelectItem value="contains">Contains</SelectItem>
                                            <SelectItem value="regex">Regex</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <Input
                                        id="url_pattern"
                                        name="url_pattern"
                                        placeholder={
                                            site.domain
                                                ? `https://${site.domain}/landing`
                                                : 'https://example.com/landing'
                                        }
                                        required
                                        disabled={atLimit}
                                    />
                                </div>
                                <p className="text-muted-foreground text-xs">
                                    The page every element below lives on.
                                </p>
                                <InputError message={errors.url_match_type} />
                                <InputError message={errors.url_pattern} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="traffic_allocation">Traffic allocation (%)</Label>
                                <Input
                                    id="traffic_allocation"
                                    name="traffic_allocation"
                                    type="number"
                                    min={1}
                                    max={100}
                                    defaultValue={100}
                                    required
                                    disabled={atLimit}
                                />
                                <InputError message={errors.traffic_allocation} />
                            </div>

                            <div className="space-y-3">
                                <div className="flex items-center justify-between gap-2">
                                    <div>
                                        <Label>Elements to test</Label>
                                        <p className="text-muted-foreground text-xs">
                                            Each element gets its own options — every combination becomes a
                                            variation.
                                        </p>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={addFactor}
                                        disabled={factors.length >= 4}
                                    >
                                        <Plus />
                                        Add element
                                    </Button>
                                </div>

                                {factors.map((factor, factorIndex) => (
                                    <Card key={factor.id}>
                                        <CardContent className="space-y-3">
                                            <div className="flex items-center justify-between gap-2">
                                                <span className="text-muted-foreground text-xs font-medium">
                                                    Element {factorIndex + 1}
                                                </span>
                                                {factors.length > 2 && (
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-7"
                                                        onClick={() => removeFactor(factor.id)}
                                                    >
                                                        <Trash2 className="size-4" />
                                                    </Button>
                                                )}
                                            </div>

                                            <div className="grid gap-2 sm:grid-cols-3">
                                                <div className="grid gap-2">
                                                    <Label htmlFor={`factor-${factor.id}-name`}>Name</Label>
                                                    <Input
                                                        id={`factor-${factor.id}-name`}
                                                        name={`factors[${factorIndex}][name]`}
                                                        placeholder="Headline"
                                                        required
                                                        disabled={atLimit}
                                                    />
                                                </div>
                                                <div className="grid gap-2">
                                                    <Label htmlFor={`factor-${factor.id}-selector`}>
                                                        CSS selector
                                                    </Label>
                                                    <Input
                                                        id={`factor-${factor.id}-selector`}
                                                        name={`factors[${factorIndex}][selector]`}
                                                        placeholder="#hero h1"
                                                        required
                                                        disabled={atLimit}
                                                    />
                                                </div>
                                                <div className="grid gap-2">
                                                    <Label htmlFor={`factor-${factor.id}-action`}>Action</Label>
                                                    <Select
                                                        name={`factors[${factorIndex}][action]`}
                                                        value={factor.action}
                                                        onValueChange={(value) => setAction(factor.id, value)}
                                                    >
                                                        <SelectTrigger id={`factor-${factor.id}-action`}>
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="text">Replace text</SelectItem>
                                                            <SelectItem value="html">Replace HTML</SelectItem>
                                                            <SelectItem value="hide">Hide element</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            </div>

                                            <InputError message={errors[`factors.${factorIndex}.name`]} />
                                            <InputError message={errors[`factors.${factorIndex}.selector`]} />

                                            <div className="space-y-2">
                                                {factor.options.map((option, optionIndex) => (
                                                    <div key={option.id} className="flex items-end gap-2">
                                                        <div className="grid flex-1 gap-2">
                                                            <Label
                                                                htmlFor={`option-${option.id}-label`}
                                                                className="text-xs"
                                                            >
                                                                Option {optionIndex + 1} name
                                                            </Label>
                                                            <Input
                                                                id={`option-${option.id}-label`}
                                                                name={`factors[${factorIndex}][options][${optionIndex}][label]`}
                                                                placeholder={optionIndex === 0 ? 'H1-A' : 'H1-B'}
                                                                required
                                                                disabled={atLimit}
                                                            />
                                                        </div>
                                                        {factor.action !== 'hide' && (
                                                            <div className="grid flex-[2] gap-2">
                                                                <Label
                                                                    htmlFor={`option-${option.id}-value`}
                                                                    className="text-xs"
                                                                >
                                                                    Value
                                                                </Label>
                                                                <Input
                                                                    id={`option-${option.id}-value`}
                                                                    name={`factors[${factorIndex}][options][${optionIndex}][value]`}
                                                                    placeholder={
                                                                        optionIndex === 0
                                                                            ? 'The current text'
                                                                            : 'The new text'
                                                                    }
                                                                    disabled={atLimit}
                                                                />
                                                            </div>
                                                        )}
                                                        {factor.options.length > 2 && (
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                className="size-9"
                                                                onClick={() => removeOption(factor.id, option.id)}
                                                            >
                                                                <Trash2 className="size-4" />
                                                            </Button>
                                                        )}
                                                    </div>
                                                ))}

                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => addOption(factor.id)}
                                                    disabled={factor.options.length >= 4}
                                                >
                                                    <Plus />
                                                    Add option
                                                </Button>
                                            </div>
                                        </CardContent>
                                    </Card>
                                ))}

                                <InputError message={errors.factors} />
                            </div>

                            <p
                                className={
                                    overCombinationLimit
                                        ? 'text-sm text-red-600 dark:text-red-400'
                                        : 'text-muted-foreground text-sm'
                                }
                            >
                                {factors.map((f) => Math.max(1, f.options.length)).join(' × ')} ={' '}
                                <strong>{combinations} combinations</strong>
                                {overCombinationLimit
                                    ? ` — over the limit of ${maxCombinations}. Each combination gets its own slice of traffic, so fewer elements or options will reach a result sooner.`
                                    : ', each becoming a variation with traffic split evenly. The first option of every element is treated as the original.'}
                            </p>

                            <Button disabled={processing || atLimit || overCombinationLimit}>
                                Create multivariate test
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

ExperimentsCreateMvt.layout = (props: { account: Company; site: Site }) => ({
    breadcrumbs: [
        { title: props.account.name, href: sitesIndexUrl(props.account.uid) },
        { title: props.site.name, href: siteOverviewUrl(props.account.uid) },
        { title: 'Multivariate', href: experimentsMvtIndexUrl(props.account.uid) },
        { title: 'New multivariate test', href: experimentsMvtCreateUrl(props.account.uid) },
    ],
});
