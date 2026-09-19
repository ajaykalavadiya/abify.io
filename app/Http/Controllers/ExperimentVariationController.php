<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExperimentVariationRequest;
use App\Models\Company;
use App\Models\Experiment;
use App\Models\ExperimentVariation;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ExperimentVariationController extends Controller
{
    public function store(ExperimentVariationRequest $request, Company $account, Site $site, Experiment $test): RedirectResponse
    {
        $this->authorize('update', $test);
        abort_unless($test->site_id === $site->id, 404);

        $trafficWeight = (int) $request->validated('traffic_weight');
        $control = $test->variations()->where('is_control', true)->first();

        // Traffic always sums to 100% across a test's variations: the
        // control starts at 100 and every new variation carves its weight
        // out of the control's remaining share (see destroy(), which gives
        // it back). Reject a request for more than what's left.
        if ($control && $trafficWeight > $control->traffic_weight) {
            return back()->withErrors([
                'traffic_weight' => "Only {$control->traffic_weight}% of traffic is unallocated — reduce this variation's weight or another variation's first.",
            ]);
        }

        $test->variations()->create([
            'name' => $request->validated('name'),
            'traffic_weight' => $trafficWeight,
            'key' => Str::slug($request->input('name')).'-'.Str::random(4),
            'payload' => $this->buildPayload($test, $request->validated()),
        ]);

        $control?->decrement('traffic_weight', $trafficWeight);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Variation added.')]);

        return back();
    }

    public function update(ExperimentVariationRequest $request, Company $account, Site $site, Experiment $test, ExperimentVariation $variation): RedirectResponse
    {
        $this->authorize('update', $test);
        abort_unless($test->site_id === $site->id && $variation->experiment_id === $test->id, 404);

        $attributes = [
            'name' => $request->validated('name'),
            'payload' => $this->buildPayload($test, $request->validated()),
        ];

        // The control's weight is derived (100 minus every other variation's
        // share — see store()/destroy()), not directly editable.
        if (! $variation->is_control) {
            $trafficWeight = (int) $request->validated('traffic_weight');
            $control = $test->variations()->where('is_control', true)->first();
            $delta = $trafficWeight - $variation->traffic_weight;

            if ($control && $delta > $control->traffic_weight) {
                return back()->withErrors([
                    'traffic_weight' => "Only {$control->traffic_weight}% more traffic is unallocated.",
                ]);
            }

            $attributes['traffic_weight'] = $trafficWeight;
            $control?->decrement('traffic_weight', $delta);
        }

        $variation->update($attributes);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Variation updated.')]);

        return back();
    }

    /**
     * Build the variation's stored payload from the flat form fields, shaped
     * by the parent experiment's type:
     * - split_url: { redirect_url }
     * - ab: { css?, js?, changes?: [{ selector, action, value }] } — any
     *   number of declarative element-targeted changes (swap text/HTML, or
     *   hide an element by selector), alongside freeform CSS/JS.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>|null
     */
    protected function buildPayload(Experiment $test, array $validated): ?array
    {
        if ($test->type === 'split_url') {
            return isset($validated['redirect_url']) ? ['redirect_url' => $validated['redirect_url']] : null;
        }

        $payload = array_filter([
            'css' => $validated['payload']['css'] ?? null,
            'js' => $validated['payload']['js'] ?? null,
        ]);

        $rawChanges = is_array($validated['changes'] ?? null) ? $validated['changes'] : [];

        $changes = array_values(array_map(
            fn (array $change): array => [
                'selector' => $change['selector'],
                'action' => $change['action'],
                'value' => $change['value'] ?? null,
            ],
            array_filter(
                $rawChanges,
                fn ($change): bool => is_array($change) && ! empty($change['selector']) && ! empty($change['action'])
            )
        ));

        if (! empty($changes)) {
            $payload['changes'] = $changes;
        }

        return $payload ?: null;
    }

    public function destroy(Company $account, Site $site, Experiment $test, ExperimentVariation $variation): RedirectResponse
    {
        $this->authorize('update', $test);
        abort_unless($test->site_id === $site->id && $variation->experiment_id === $test->id, 404);
        abort_if($variation->is_control, 422, 'The control variation cannot be removed.');

        $control = $test->variations()->where('is_control', true)->first();

        $variation->delete();

        // Give the removed variation's share back to the control so the
        // test's variations still sum to 100%.
        $control?->increment('traffic_weight', $variation->traffic_weight);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Variation removed.')]);

        return back();
    }
}
