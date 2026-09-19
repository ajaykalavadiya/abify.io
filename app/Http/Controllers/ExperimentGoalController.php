<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExperimentGoalRequest;
use App\Models\Company;
use App\Models\Experiment;
use App\Models\ExperimentGoal;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ExperimentGoalController extends Controller
{
    public function store(ExperimentGoalRequest $request, Company $account, Site $site, Experiment $test): RedirectResponse
    {
        $this->authorize('update', $test);
        abort_unless($test->site_id === $site->id, 404);

        $test->goals()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Goal added.')]);

        return back();
    }

    public function update(ExperimentGoalRequest $request, Company $account, Site $site, Experiment $test, ExperimentGoal $goal): RedirectResponse
    {
        $this->authorize('update', $test);
        abort_unless($test->site_id === $site->id && $goal->experiment_id === $test->id, 404);

        $goal->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Goal updated.')]);

        return back();
    }

    public function destroy(Company $account, Site $site, Experiment $test, ExperimentGoal $goal): RedirectResponse
    {
        $this->authorize('update', $test);
        abort_unless($test->site_id === $site->id && $goal->experiment_id === $test->id, 404);

        $goal->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Goal removed.')]);

        return back();
    }
}
