import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Experiment, Site } from '@/types';

export type ExperimentType = 'ab' | 'split_url' | 'mvt';

/**
 * Copy that differs between the two test types. The fields themselves are
 * the same — what changes is what the URL means and what happens next.
 */
const typeCopy: Record<ExperimentType, { urlLabel: string; urlHelp: string; nextStep: string }> = {
    ab: {
        urlLabel: 'Test URL',
        urlHelp: 'The page(s) this test runs on — visitors are only bucketed, and element changes only applied, when the current page matches.',
        nextStep: 'A "Control" variation is created automatically. Add variations next, each with its own element changes or custom CSS.',
    },
    split_url: {
        urlLabel: 'Original URL',
        urlHelp: 'The page visitors start on. Matching visitors are split between it and the variation URLs you add next.',
        nextStep: 'A "Control" variation is created automatically for the original page. Add variations next, each redirecting to a different URL.',
    },
    mvt: {
        urlLabel: 'Test URL',
        urlHelp: 'The page every element under test lives on — visitors are only bucketed into a combination when the current page matches.',
        nextStep: 'Combinations are generated from the elements you define, each becoming a variation.',
    },
};

export function ExperimentForm({
    action,
    method,
    type,
    site,
    experiment,
    submitLabel,
    disabled = false,
}: {
    action: string;
    method: 'post' | 'patch';
    type: ExperimentType;
    site: Site;
    experiment?: Experiment;
    submitLabel: string;
    disabled?: boolean;
}) {
    const copy = typeCopy[type];

    return (
        <Form action={action} method={method} className="space-y-4">
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="name">Name</Label>
                        <Input
                            id="name"
                            name="name"
                            required
                            autoFocus
                            disabled={disabled}
                            defaultValue={experiment?.name ?? ''}
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="url_pattern">{copy.urlLabel}</Label>
                        <div className="flex gap-2">
                            <Select
                                name="url_match_type"
                                defaultValue={experiment?.url_match_type ?? 'contains'}
                            >
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
                                disabled={disabled}
                                defaultValue={experiment?.url_pattern ?? ''}
                            />
                        </div>
                        <p className="text-muted-foreground text-xs">{copy.urlHelp}</p>
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
                            defaultValue={experiment?.traffic_allocation ?? 100}
                            required
                            disabled={disabled}
                        />
                        <p className="text-muted-foreground text-xs">
                            The share of matching visitors who take part in this test.
                        </p>
                        <InputError message={errors.traffic_allocation} />
                    </div>

                    {!experiment && <p className="text-muted-foreground text-sm">{copy.nextStep}</p>}

                    <Button disabled={processing || disabled}>{submitLabel}</Button>
                </>
            )}
        </Form>
    );
}
