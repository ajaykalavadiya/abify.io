<?php

namespace App\Actions\Tracking;

use App\Jobs\Analytics\SendGa4ConversionJob;
use App\Models\ExperimentGoal;
use App\Models\Site;
use App\Models\Tenant\GoalConversion;
use App\Models\Tenant\VisitorAssignment;
use App\Models\Tenant\VisitorSession;
use Illuminate\Support\Carbon;

/**
 * Records a conversion against every running experiment the visitor is
 * actually enrolled in.
 *
 * Both ingestion paths land here: the browser beacon (which names a single
 * goal id it already knows) and the server-side API used by the Shopify,
 * WooCommerce and Magento integrations (which names a goal *key*, because a
 * merchant's store knows it sold something but not abify's internal ids).
 *
 * Callers must have the tenant connection active for the site's company.
 */
class RecordConversion
{
    /**
     * Fan a keyed event out across the site's running experiments.
     *
     * A store fires one `purchase`; every experiment currently measuring a
     * goal keyed `purchase` should see it, but only for the experiments this
     * visitor was actually bucketed into — an unenrolled visitor's order is
     * not evidence about any variation.
     *
     * @param  array<string, mixed>  $props
     * @return int number of conversions recorded
     */
    public function byKey(
        Site $site,
        string $visitorUuid,
        string $goalKey,
        ?string $externalId = null,
        ?float $revenue = null,
        ?string $currency = null,
        array $props = [],
        ?string $occurredAt = null,
    ): int {
        $goals = ExperimentGoal::query()
            ->where('key', $goalKey)
            ->whereHas('experiment', fn ($query) => $query
                ->where('site_id', $site->id)
                ->where('status', 'running'))
            ->get();

        $recorded = 0;

        foreach ($goals as $goal) {
            $recorded += $this->record(
                experimentId: $goal->experiment_id,
                goalId: $goal->id,
                visitorUuid: $visitorUuid,
                externalId: $externalId,
                revenue: $revenue,
                currency: $currency,
                props: $props,
                occurredAt: $occurredAt,
            ) ? 1 : 0;
        }

        // Reported to GA4 once for the event, not once per experiment: the
        // merchant made one sale, and fanning it out across every test the
        // visitor happened to be in would multiply their revenue.
        if ($recorded > 0) {
            $this->reportToGa4($site, $visitorUuid, $goalKey, $revenue, $currency);
        }

        return $recorded;
    }

    /**
     * Record one conversion for one goal.
     *
     * @param  array<string, mixed>  $props
     * @return bool false when the visitor was never enrolled, or the event
     *              was a duplicate
     */
    public function record(
        int $experimentId,
        int $goalId,
        string $visitorUuid,
        ?string $externalId = null,
        ?float $revenue = null,
        ?string $currency = null,
        array $props = [],
        ?string $occurredAt = null,
    ): bool {
        $assignment = VisitorAssignment::where('experiment_id', $experimentId)
            ->where('visitor_uuid', $visitorUuid)
            ->first();

        if (! $assignment) {
            return false;
        }

        // insertOrIgnore, not create(): the unique index on
        // (goal_id, external_id) is what makes a redelivered order webhook a
        // no-op. Shopify, WooCommerce and Magento all retry, and a double
        // count here is a silently wrong experiment result rather than a
        // visible error.
        $inserted = GoalConversion::query()->insertOrIgnore([
            'experiment_id' => $experimentId,
            'goal_id' => $goalId,
            'variation_id' => $assignment->variation_id,
            'visitor_uuid' => $visitorUuid,
            'revenue' => $revenue,
            'currency' => $currency ? strtoupper($currency) : null,
            'external_id' => $externalId,
            'meta' => $props ? json_encode($props) : null,
            // Parsed rather than passed through: this bypasses Eloquent (and
            // so the model's datetime cast), and the ISO-8601 strings both
            // the browser and the platform webhooks send are not a format
            // MySQL accepts directly.
            'converted_at' => $occurredAt
                ? Carbon::parse($occurredAt)->setTimezone(config('app.timezone'))
                : now(),
        ]);

        return $inserted > 0;
    }

    /**
     * Mirror the conversion into the merchant's GA4.
     *
     * Needs the GA4 client id captured during the visit — a conversion from a
     * visitor who never had GA4's cookie set cannot be addressed, and is
     * skipped rather than invented.
     */
    protected function reportToGa4(
        Site $site,
        string $visitorUuid,
        string $goalKey,
        ?float $revenue,
        ?string $currency,
    ): void {
        $clientId = VisitorSession::where('visitor_uuid', $visitorUuid)
            ->whereNotNull('ga_client_id')
            ->orderByDesc('started_at')
            ->value('ga_client_id');

        if (! $clientId) {
            return;
        }

        SendGa4ConversionJob::dispatch(
            siteId: $site->id,
            gaClientId: $clientId,
            eventName: 'abify_conversion',
            params: array_filter([
                'goal_key' => $goalKey,
                'value' => $revenue,
                'currency' => $currency ? strtoupper($currency) : null,
            ], fn ($value) => $value !== null),
        );
    }
}
