<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExperimentStoreRequest;
use App\Http\Requests\ExperimentUpdateRequest;
use App\Models\Company;
use App\Models\Experiment;
use App\Models\Site;
use App\Support\Testing\CombinationBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ExperimentController extends Controller
{
    /**
     * The test list, optionally narrowed to one test type. The type comes
     * from the route's default (see routes/dashboard.php), which is what
     * gives A/B and split URL tests their own sidebar entries and URLs.
     */
    public function index(Company $account, Site $site, ?string $type = null): Response
    {
        $this->authorize('view', $site);

        return Inertia::render('experiments/index', [
            'account' => $account,
            'site' => $site,
            'type' => $type,
            'experiments' => $site->experiments()
                ->when($type, fn ($query) => $query->where('type', $type))
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    /**
     * @param  string  $type  'ab', 'split_url' or 'mvt', from the route —
     *                        each type is created through its own form.
     */
    public function create(Company $account, Site $site, string $type): Response
    {
        $this->authorize('view', $site);

        // Multivariate needs a different form entirely: factors and their
        // options, rather than a single change.
        $page = $type === 'mvt' ? 'experiments/create-mvt' : 'experiments/create';

        return Inertia::render($page, [
            'account' => $account,
            'site' => $site,
            'type' => $type,
            'planLimits' => $account->planLimits(),
            'testsCount' => $site->experiments()->count(),
            'maxCombinations' => CombinationBuilder::MAX_COMBINATIONS,
        ]);
    }

    public function store(
        ExperimentStoreRequest $request,
        Company $account,
        Site $site,
        string $type,
        CombinationBuilder $combinations,
    ): RedirectResponse {
        $this->authorize('view', $site);

        $limits = $account->planLimits();

        if ($site->experiments()->count() >= $limits['tests_per_site']) {
            return back()->withErrors([
                'name' => "Your {$limits['label']} plan allows up to {$limits['tests_per_site']} test(s) per site. Upgrade to add more.",
            ]);
        }

        $experiment = $site->experiments()->create([
            ...$request->validated(),
            'type' => $type,
            'company_id' => $account->id,
            'key' => Str::slug($request->input('name')).'-'.Str::random(4),
        ]);

        if ($type === 'mvt') {
            // Every combination of the factors' options becomes a variation,
            // each carrying one element change per factor.
            foreach ($combinations->build($request->validated('factors')) as $combination) {
                $experiment->variations()->create($combination);
            }
        } else {
            $experiment->variations()->create([
                'key' => 'control',
                'name' => 'Control',
                'is_control' => true,
                'traffic_weight' => 100,
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Test created.')]);

        return to_route('experiments.show', [$account->uid, $experiment]);
    }

    public function show(Company $account, Site $site, Experiment $test): Response
    {
        $this->authorize('view', $test);
        abort_unless($test->site_id === $site->id, 404);

        return Inertia::render('experiments/show', [
            'account' => $account,
            'site' => $site,
            'experiment' => $test->load(['variations', 'goals']),
        ]);
    }

    public function edit(Company $account, Site $site, Experiment $test): Response
    {
        $this->authorize('update', $test);
        abort_unless($test->site_id === $site->id, 404);

        return Inertia::render('experiments/edit', [
            'account' => $account,
            'site' => $site,
            'experiment' => $test,
        ]);
    }

    public function update(ExperimentUpdateRequest $request, Company $account, Site $site, Experiment $test): RedirectResponse
    {
        $this->authorize('update', $test);
        abort_unless($test->site_id === $site->id, 404);

        // Type is deliberately not editable: an existing variation's payload
        // (element changes vs. a redirect URL) is shaped by it.
        $test->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Test updated.')]);

        return to_route('experiments.show', [$account->uid, $test]);
    }

    public function start(Company $account, Site $site, Experiment $test): RedirectResponse
    {
        $this->authorize('update', $test);
        abort_unless($test->site_id === $site->id, 404);

        $test->update([
            'status' => 'running',
            'started_at' => $test->started_at ?? now(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Test started.')]);

        return back();
    }

    public function pause(Company $account, Site $site, Experiment $test): RedirectResponse
    {
        $this->authorize('update', $test);
        abort_unless($test->site_id === $site->id, 404);

        $test->update(['status' => 'paused']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Test paused.')]);

        return back();
    }
}
