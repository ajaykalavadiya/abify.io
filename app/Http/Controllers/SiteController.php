<?php

namespace App\Http\Controllers;

use App\Actions\Tracking\ReserveRecordingSlot;
use App\Http\Requests\SiteGa4Request;
use App\Http\Requests\SiteRecordingRequest;
use App\Http\Requests\SiteStoreRequest;
use App\Models\Company;
use App\Models\Integration;
use App\Models\Site;
use App\Support\Analytics\DateRange;
use App\Support\Analytics\SiteOverviewMetrics;
use App\Support\Tracking\EmbedSnippetBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SiteController extends Controller
{
    public function index(Company $account): Response
    {
        return Inertia::render('sites/index', [
            'account' => $account,
            'sites' => $account->sites()->withCount('experiments')->orderBy('name')->get(),
            'planLimits' => $account->planLimits(),
        ]);
    }

    public function create(Company $account): Response
    {
        return Inertia::render('sites/create', [
            'account' => $account,
            'planLimits' => $account->planLimits(),
            'sitesCount' => $account->sites()->count(),
        ]);
    }

    public function store(SiteStoreRequest $request, Company $account): RedirectResponse
    {
        $limits = $account->planLimits();

        if ($account->sites()->count() >= $limits['sites']) {
            return back()->withErrors([
                'name' => "Your {$limits['label']} plan allows up to {$limits['sites']} site(s). Upgrade to add more.",
            ]);
        }

        $site = $account->sites()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Site created.')]);

        return to_route('site.overview', $account->uid);
    }

    /**
     * The site dashboard: traffic, sources, behaviour and test performance for
     * the active site over a chosen window.
     */
    public function show(
        Request $request,
        Company $account,
        Site $site,
        EmbedSnippetBuilder $snippetBuilder,
        SiteOverviewMetrics $metrics,
    ): Response {
        $this->authorize('view', $site);

        $range = DateRange::fromRequest(
            $request->string('range')->toString() ?: null,
            $request->string('from')->toString() ?: null,
            $request->string('to')->toString() ?: null,
        );

        return Inertia::render('sites/show', [
            'account' => $account,
            'site' => $site,
            'embedSnippet' => $snippetBuilder->build($site),
            'range' => [
                'value' => $range->key,
                'label' => $range->label(),
                'days' => $range->days,
                'from' => $range->start->toDateString(),
                'to' => $range->end->toDateString(),
                'options' => DateRange::options(),
            ],
            'metrics' => $metrics->build($site, $range),
            // Whether any data has ever arrived, as opposed to none in the
            // selected window — an empty last-7-days on a live site is a very
            // different message from "you haven't installed the snippet".
            'hasData' => $this->hasEverReceivedData($site),
            'recording' => [
                'enabled' => (bool) ($site->settings['recording']['enabled'] ?? false),
                'daily_limit' => app(ReserveRecordingSlot::class)->dailyLimitFor($site),
                'sample_rate' => app(ReserveRecordingSlot::class)->sampleRateFor($site),
                'remaining_today' => app(ReserveRecordingSlot::class)->remainingToday($site),
            ],
            'integrations' => [
                'gtm' => (bool) ($site->settings['integrations']['gtm'] ?? false),
                'ga4' => (bool) ($site->settings['integrations']['ga4'] ?? false),
                // The secret itself is never sent back to the browser.
                'ga4_measurement_id' => Integration::where('site_id', $site->id)
                    ->where('provider', Integration::GA4)
                    ->value('external_id'),
            ],
        ]);
    }

    /**
     * Update how much of this site's traffic is recorded.
     */
    public function updateRecording(
        SiteRecordingRequest $request,
        Company $account,
        Site $site,
    ): RedirectResponse {
        $this->authorize('update', $site);

        $validated = $request->validated();

        $site->forceFill([
            'settings' => [
                ...($site->settings ?? []),
                'recording' => [
                    'enabled' => $validated['enabled'],
                    'daily_limit' => $validated['daily_limit'],
                    'sample_rate' => $validated['sample_rate'],
                ],
                'integrations' => [
                    ...($site->settings['integrations'] ?? []),
                    'gtm' => $validated['gtm'],
                    'ga4' => $validated['ga4'],
                ],
            ],
        ])->save();

        // The tracking script embeds these settings and is cached per site,
        // so without this the change would not reach visitors for up to a
        // minute and the customer would think the toggle was broken.
        Cache::forget("site:{$site->public_id}:script");

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Recording settings saved.')]);

        return back();
    }

    /**
     * Connect or disconnect the GA4 property that server-side conversions are
     * reported into.
     */
    public function updateGa4(SiteGa4Request $request, Company $account, Site $site): RedirectResponse
    {
        $this->authorize('update', $site);

        $measurementId = $request->validated('measurement_id');
        $apiSecret = $request->validated('api_secret');

        if (! $measurementId) {
            Integration::where('site_id', $site->id)
                ->where('provider', Integration::GA4)
                ->delete();

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Google Analytics disconnected.')]);

            return back();
        }

        $existing = Integration::where('site_id', $site->id)
            ->where('provider', Integration::GA4)
            ->first();

        // An empty secret on an existing connection means "leave it alone".
        // The form never renders the stored secret back, so requiring it on
        // every save would force the merchant to re-enter it to change the
        // measurement id.
        $secret = $apiSecret ?: $existing?->apiSecret();

        if (! $secret) {
            return back()->withErrors(['api_secret' => 'An API secret is required to connect a property.']);
        }

        Integration::updateOrCreate(
            ['provider' => Integration::GA4, 'external_id' => $measurementId],
            [
                'company_id' => $site->company_id,
                'site_id' => $site->id,
                'status' => Integration::STATUS_ACTIVE,
                'credentials' => ['api_secret' => $secret],
                'connected_at' => now(),
            ]
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Google Analytics connected.')]);

        return back();
    }

    /**
     * Cached briefly: this exists only to pick which empty state to show, and
     * it would otherwise put a COUNT over the whole events table on every
     * dashboard load.
     */
    protected function hasEverReceivedData(Site $site): bool
    {
        return (bool) Cache::remember(
            "site:{$site->id}:has-analytics",
            now()->addMinutes(5),
            fn () => DB::connection('tenant')
                ->table('analytics_events')
                ->where('site_id', $site->id)
                ->exists(),
        );
    }
}
