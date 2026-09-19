<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Experiment;
use App\Models\Site;
use App\Models\Tenant\GoalConversion;
use App\Models\Tenant\VisitorAssignment;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    /**
     * Show per-variation impressions/conversions for an experiment. The
     * tenant connection is already active — ResolveAccountFromRoute switched
     * it for the whole /a/{account} route group.
     */
    public function show(Company $account, Site $site, Experiment $test): Response
    {
        $this->authorize('view', $test);
        abort_unless($test->site_id === $site->id, 404);

        $test->load(['variations', 'goals']);

        $impressions = VisitorAssignment::where('experiment_id', $test->id)
            ->select('variation_id', DB::raw('count(*) as impressions'))
            ->groupBy('variation_id')
            ->pluck('impressions', 'variation_id');

        // Two different counts, because they answer two different questions.
        // Conversion RATE is about people: a shopper who places three orders
        // converted once against the one impression they were counted for, so
        // counting rows here would push the rate past 100%. REVENUE is about
        // money, and all three orders are real.
        $conversions = GoalConversion::where('experiment_id', $test->id)
            ->select(
                'variation_id',
                'goal_id',
                DB::raw('count(distinct visitor_uuid) as converters'),
                DB::raw('count(*) as conversions'),
                DB::raw('sum(revenue) as revenue'),
            )
            ->groupBy('variation_id', 'goal_id')
            ->get()
            ->groupBy('variation_id');

        // Revenue is stored in the currency it was transacted in and never
        // converted, so a site taking more than one is reported rather than
        // silently summed into a meaningless total.
        $currencies = GoalConversion::where('experiment_id', $test->id)
            ->whereNotNull('currency')
            ->distinct()
            ->pluck('currency')
            ->all();

        $results = $test->variations->map(function ($variation) use ($impressions, $conversions, $test) {
            $variationImpressions = (int) ($impressions[$variation->id] ?? 0);

            /** @var array<int, array<string, mixed>> $goals */
            $goals = $test->goals->map(function ($goal) use ($variation, $conversions, $variationImpressions) {
                $row = ($conversions[$variation->id] ?? collect())->firstWhere('goal_id', $goal->id);

                $converters = $row ? (int) $row->converters : 0;
                $count = $row ? (int) $row->conversions : 0;
                $revenue = $row && $row->revenue !== null ? (float) $row->revenue : null;

                return [
                    'goal' => $goal,
                    'conversions' => $count,
                    'converters' => $converters,
                    'rate' => $variationImpressions > 0 ? round($converters / $variationImpressions * 100, 2) : 0,
                    'revenue' => $revenue,
                    // The metric a merchant actually decides on: a variation
                    // can convert less often and still win on money.
                    'revenue_per_visitor' => $revenue !== null && $variationImpressions > 0
                        ? round($revenue / $variationImpressions, 2)
                        : null,
                    'average_order_value' => $revenue !== null && $count > 0
                        ? round($revenue / $count, 2)
                        : null,
                ];
            })->all();

            return [
                'variation' => $variation,
                'impressions' => $variationImpressions,
                'goals' => $goals,
            ];
        });

        return Inertia::render('analytics/show', [
            'account' => $account,
            'site' => $site,
            'experiment' => $test,
            'results' => $results,
            'currencies' => $currencies,
        ]);
    }
}
