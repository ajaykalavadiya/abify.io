import { Form, Head, Link, router } from '@inertiajs/react';
import { Eye, Pencil, Plus, Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    experimentAnalyticsUrl,
    experimentEditUrl,
    experimentPauseUrl,
    experimentShowUrl,
    experimentStartUrl,
    experimentsIndexUrl,
    goalUrl,
    goalsUrl,
    siteOverviewUrl,
    sitesIndexUrl,
    variationUrl,
    variationsUrl,
} from '@/lib/urls';
import { variationPreviewUrl } from '@/lib/preview';
import type { Company, Experiment, Site } from '@/types';
import type { VariationChange } from '@/types/vwo';

type ChangeRow = { id: number; action: string; selector: string; value: string };

function VariationForm({
    formKey,
    action,
    method,
    experiment,
    maxTrafficWeight,
    initialName = '',
    initialTrafficWeight,
    initialRedirectUrl = '',
    initialCss = '',
    initialChanges = [],
    disableTrafficWeight = false,
    submitLabel,
    onSuccess,
    onCancel,
}: {
    formKey: string;
    action: string;
    method: 'post' | 'patch';
    experiment: Experiment;
    maxTrafficWeight: number;
    initialName?: string;
    initialTrafficWeight?: number;
    initialRedirectUrl?: string;
    initialCss?: string;
    initialChanges?: VariationChange[];
    disableTrafficWeight?: boolean;
    submitLabel: string;
    onSuccess?: () => void;
    onCancel?: () => void;
}) {
    const nextRowId = useRef(0);
    const makeRow = (change?: VariationChange): ChangeRow => ({
        id: nextRowId.current++,
        action: change?.action ?? '',
        selector: change?.selector ?? '',
        value: change?.value ?? '',
    });

    const [changeRows, setChangeRows] = useState<ChangeRow[]>(() =>
        initialChanges.length > 0 ? initialChanges.map((change) => makeRow(change)) : [makeRow()],
    );

    const addChangeRow = () => setChangeRows((rows) => [...rows, makeRow()]);
    const removeChangeRow = (id: number) => setChangeRows((rows) => rows.filter((row) => row.id !== id));
    const setChangeRowAction = (id: number, action: string) =>
        setChangeRows((rows) => rows.map((row) => (row.id === id ? { ...row, action } : row)));

    return (
        <Form
            action={action}
            method={method}
            resetOnSuccess
            onSuccess={onSuccess}
            className="space-y-4"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor={`${formKey}-name`}>Name</Label>
                        <Input id={`${formKey}-name`} name="name" required defaultValue={initialName} />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor={`${formKey}-weight`}>Traffic weight (%)</Label>
                        {disableTrafficWeight ? (
                            <>
                                <Input
                                    id={`${formKey}-weight`}
                                    value={initialTrafficWeight}
                                    disabled
                                    readOnly
                                />
                                <p className="text-muted-foreground text-xs">
                                    The control&apos;s weight is derived automatically from the other
                                    variations.
                                </p>
                            </>
                        ) : (
                            <>
                                <Input
                                    id={`${formKey}-weight`}
                                    name="traffic_weight"
                                    type="number"
                                    min={0}
                                    max={maxTrafficWeight}
                                    defaultValue={initialTrafficWeight ?? maxTrafficWeight}
                                    required
                                />
                                <p className="text-muted-foreground text-xs">
                                    {maxTrafficWeight}% of traffic is unallocated — variations always sum
                                    to 100%.
                                </p>
                                <InputError message={errors.traffic_weight} />
                            </>
                        )}
                    </div>

                    {experiment.type === 'split_url' ? (
                        <div className="grid gap-2">
                            <Label htmlFor={`${formKey}-redirect`}>Redirect URL</Label>
                            <Input
                                id={`${formKey}-redirect`}
                                name="redirect_url"
                                type="url"
                                placeholder="https://example.com/landing-b"
                                required
                                defaultValue={initialRedirectUrl}
                            />
                            <InputError message={errors.redirect_url} />
                        </div>
                    ) : (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor={`${formKey}-css`}>Custom CSS (optional)</Label>
                                <Input
                                    id={`${formKey}-css`}
                                    name="payload[css]"
                                    placeholder="body{...}"
                                    defaultValue={initialCss}
                                />
                            </div>

                            <div className="space-y-4 rounded-md border p-3">
                                <div className="flex items-center justify-between gap-2">
                                    <Label className="text-muted-foreground text-xs uppercase">
                                        Element changes (optional)
                                    </Label>
                                    <Button type="button" variant="outline" size="sm" onClick={addChangeRow}>
                                        <Plus />
                                        Add change
                                    </Button>
                                </div>

                                {changeRows.map((row, index) => (
                                    <div key={row.id} className="space-y-3 rounded-md border p-3">
                                        <div className="flex items-center justify-between gap-2">
                                            <span className="text-muted-foreground text-xs font-medium">
                                                Change {index + 1}
                                            </span>
                                            {changeRows.length > 1 && (
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    className="size-7"
                                                    onClick={() => removeChangeRow(row.id)}
                                                >
                                                    <Trash2 className="size-4" />
                                                </Button>
                                            )}
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor={`${formKey}-selector-${row.id}`}>
                                                CSS selector
                                            </Label>
                                            <Input
                                                id={`${formKey}-selector-${row.id}`}
                                                name={`changes[${index}][selector]`}
                                                placeholder="#hero-headline"
                                                defaultValue={row.selector}
                                            />
                                            <InputError message={errors[`changes.${index}.selector`]} />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor={`${formKey}-action-${row.id}`}>Action</Label>
                                            <Select
                                                name={`changes[${index}][action]`}
                                                value={row.action}
                                                onValueChange={(action) => setChangeRowAction(row.id, action)}
                                            >
                                                <SelectTrigger id={`${formKey}-action-${row.id}`}>
                                                    <SelectValue placeholder="None" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="text">Replace text</SelectItem>
                                                    <SelectItem value="html">Replace HTML</SelectItem>
                                                    <SelectItem value="hide">Hide element</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        {(row.action === 'text' || row.action === 'html') && (
                                            <div className="grid gap-2">
                                                <Label htmlFor={`${formKey}-value-${row.id}`}>New value</Label>
                                                <Input
                                                    id={`${formKey}-value-${row.id}`}
                                                    name={`changes[${index}][value]`}
                                                    defaultValue={row.value}
                                                />
                                                <InputError message={errors[`changes.${index}.value`]} />
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </>
                    )}

                    <div className="flex gap-2">
                        <Button disabled={processing}>{submitLabel}</Button>
                        {onCancel && (
                            <Button type="button" variant="outline" onClick={onCancel}>
                                Cancel
                            </Button>
                        )}
                    </div>
                </>
            )}
        </Form>
    );
}

export default function ExperimentsShow({
    account,
    site,
    experiment,
}: {
    account: Company;
    site: Site;
    experiment: Experiment;
}) {
    const variations = experiment.variations ?? [];
    const goals = experiment.goals ?? [];
    const allocatedWeight = variations.reduce((sum, variation) => sum + variation.traffic_weight, 0);
    const remainingWeight = Math.max(0, 100 - allocatedWeight);
    const [editingVariationId, setEditingVariationId] = useState<number | null>(null);

    const toggleStatus = () => {
        const url =
            experiment.status === 'running'
                ? experimentPauseUrl(account.uid, experiment.id)
                : experimentStartUrl(account.uid, experiment.id);

        router.patch(url, {}, { preserveScroll: true });
    };

    const deleteVariation = (variationId: number) => {
        if (!confirm('Remove this variation?')) return;
        router.delete(variationUrl(account.uid, experiment.id, variationId), {
            preserveScroll: true,
        });
    };

    const deleteGoal = (goalId: number) => {
        if (!confirm('Remove this goal?')) return;
        router.delete(goalUrl(account.uid, experiment.id, goalId), { preserveScroll: true });
    };

    const previewUrl = (variationKey: string) =>
        variationPreviewUrl(site, experiment, variationKey);

    return (
        <>
            <Head title={experiment.name} />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title={experiment.name}
                        description={`${experiment.url_match_type} · ${experiment.url_pattern ?? 'all pages'} · ${experiment.traffic_allocation}% traffic`}
                    />
                    <div className="flex items-center gap-2">
                        <Badge variant={experiment.status === 'running' ? 'default' : 'secondary'}>
                            {experiment.status}
                        </Badge>
                        <Button variant="outline" onClick={toggleStatus}>
                            {experiment.status === 'running' ? 'Pause' : 'Start'}
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={experimentEditUrl(account.uid, experiment.id)}>Edit</Link>
                        </Button>
                        <Button asChild>
                            <Link href={experimentAnalyticsUrl(account.uid, experiment.id)}>
                                Analytics
                            </Link>
                        </Button>
                    </div>
                </div>

                <Tabs defaultValue="variations">
                    <TabsList>
                        <TabsTrigger value="variations">Variations</TabsTrigger>
                        <TabsTrigger value="goals">Goals</TabsTrigger>
                    </TabsList>

                    <TabsContent value="variations" className="space-y-4 pt-4">
                        <p className="text-muted-foreground text-sm">
                            Use the eye icon to open the live site with that variation forced — the tracking
                            script shows a debug panel and doesn't record preview visits as real traffic.
                        </p>

                        <div className="flex items-center gap-3">
                            <div className="bg-muted h-2 flex-1 overflow-hidden rounded-none">
                                <div
                                    className={`h-full rounded-none transition-all ${allocatedWeight === 100 ? 'bg-primary' : 'bg-amber-500'}`}
                                    style={{ width: `${Math.min(100, allocatedWeight)}%` }}
                                />
                            </div>
                            <span className="text-muted-foreground text-xs whitespace-nowrap">
                                {allocatedWeight}% of 100% traffic allocated
                            </span>
                        </div>

                        <div className="space-y-3">
                            {variations.map((variation) =>
                                editingVariationId === variation.id ? (
                                    <Card key={variation.id}>
                                        <CardHeader>
                                            <CardTitle>Edit variation</CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            <VariationForm
                                                formKey={`edit-${variation.id}`}
                                                action={variationUrl(account.uid, experiment.id, variation.id)}
                                                method="patch"
                                                experiment={experiment}
                                                maxTrafficWeight={remainingWeight + variation.traffic_weight}
                                                initialName={variation.name}
                                                initialTrafficWeight={variation.traffic_weight}
                                                initialRedirectUrl={variation.payload?.redirect_url ?? ''}
                                                initialCss={variation.payload?.css ?? ''}
                                                initialChanges={variation.payload?.changes ?? []}
                                                disableTrafficWeight={variation.is_control}
                                                submitLabel="Save variation"
                                                onSuccess={() => setEditingVariationId(null)}
                                                onCancel={() => setEditingVariationId(null)}
                                            />
                                        </CardContent>
                                    </Card>
                                ) : (
                                    <Card key={variation.id}>
                                        <CardContent className="flex items-center justify-between">
                                            <div>
                                                <div className="flex items-center gap-2 font-medium">
                                                    {variation.name}
                                                    {variation.is_control && (
                                                        <Badge variant="secondary">control</Badge>
                                                    )}
                                                </div>
                                                <div className="text-muted-foreground text-sm">
                                                    {variation.traffic_weight}% traffic
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    disabled={!site.domain}
                                                    title={
                                                        site.domain
                                                            ? `Preview "${variation.name}" on ${site.domain}`
                                                            : 'Set a domain on this site to enable preview'
                                                    }
                                                    asChild={!!site.domain}
                                                >
                                                    {site.domain ? (
                                                        <a
                                                            href={previewUrl(variation.key) ?? '#'}
                                                            target="_blank"
                                                            rel="noreferrer"
                                                        >
                                                            <Eye />
                                                        </a>
                                                    ) : (
                                                        <Eye />
                                                    )}
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() => setEditingVariationId(variation.id)}
                                                >
                                                    <Pencil />
                                                </Button>
                                                {!variation.is_control && (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() => deleteVariation(variation.id)}
                                                    >
                                                        <Trash2 />
                                                    </Button>
                                                )}
                                            </div>
                                        </CardContent>
                                    </Card>
                                ),
                            )}
                        </div>

                        <Card>
                            <CardHeader>
                                <CardTitle>Add variation</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <VariationForm
                                    formKey="add"
                                    action={variationsUrl(account.uid, experiment.id)}
                                    method="post"
                                    experiment={experiment}
                                    maxTrafficWeight={remainingWeight}
                                    submitLabel="Add variation"
                                />
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="goals" className="space-y-4 pt-4">
                        <div className="space-y-3">
                            {goals.map((goal) => (
                                <Card key={goal.id}>
                                    <CardContent className="flex items-center justify-between">
                                        <div>
                                            <div className="font-medium">{goal.name}</div>
                                            <div className="text-muted-foreground text-sm">
                                                {goal.type}
                                                {goal.target ? ` — ${goal.target}` : ''}
                                            </div>
                                        </div>
                                        <Button variant="ghost" size="icon" onClick={() => deleteGoal(goal.id)}>
                                            <Trash2 />
                                        </Button>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>

                        <Card>
                            <CardHeader>
                                <CardTitle>Add goal</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <Form
                                    action={goalsUrl(account.uid, experiment.id)}
                                    method="post"
                                    resetOnSuccess
                                    className="space-y-4"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <div className="grid gap-2">
                                                <Label htmlFor="g-name">Name</Label>
                                                <Input id="g-name" name="name" required />
                                                <InputError message={errors.name} />
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="g-type">Type</Label>
                                                <Select name="type" defaultValue="pageview">
                                                    <SelectTrigger id="g-type">
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="pageview">Pageview</SelectItem>
                                                        <SelectItem value="click">Click</SelectItem>
                                                        <SelectItem value="custom_event">Custom event</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                                <InputError message={errors.type} />
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="g-target">
                                                    Target (URL, selector, or event name)
                                                </Label>
                                                <Input id="g-target" name="target" />
                                                <InputError message={errors.target} />
                                            </div>

                                            <Button disabled={processing}>Add goal</Button>
                                        </>
                                    )}
                                </Form>
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>
        </>
    );
}

ExperimentsShow.layout = (props: { account: Company; site: Site; experiment: Experiment }) => ({
    breadcrumbs: [
        { title: props.account.name, href: sitesIndexUrl(props.account.uid) },
        { title: props.site.name, href: siteOverviewUrl(props.account.uid) },
        { title: 'Tests', href: experimentsIndexUrl(props.account.uid) },
        {
            title: props.experiment.name,
            href: experimentShowUrl(props.account.uid, props.experiment.id),
        },
    ],
});
