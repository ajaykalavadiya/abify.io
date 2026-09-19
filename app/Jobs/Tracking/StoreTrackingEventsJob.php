<?php

namespace App\Jobs\Tracking;

use App\Actions\Tracking\RecordConversion;
use App\Models\Company;
use App\Models\Tenant\AnalyticsEvent;
use App\Models\Tenant\HeatmapEvent;
use App\Models\Tenant\HeatmapSession;
use App\Models\Tenant\VisitorSession;
use App\Support\Analytics\DeviceType;
use App\Support\Tenancy\TenantManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Queued on the CENTRAL `database` queue connection (framework infra), but
 * writes to the resolved company's TENANT database — switches connection
 * itself since a queue worker has no request-scoped tenant context.
 */
class StoreTrackingEventsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<int, array<string, mixed>>  $events
     */
    public function __construct(
        public int $companyId,
        public int $siteId,
        public array $events,
        public string $visitorUuid,
        public ?string $sessionId,
        public ?string $gaClientId = null,
    ) {}

    public function handle(TenantManager $tenantManager): void
    {
        $company = Company::findOrFail($this->companyId);

        $tenantManager->forCompany($company);

        $now = now();

        $analyticsRows = [];
        $heatmapRows = [];
        $sessionMeta = null;

        foreach ($this->events as $event) {
            $type = $event['type'];

            if (in_array($type, ['pageview', 'custom'], true)) {
                $analyticsRows[] = [
                    'site_id' => $this->siteId,
                    'visitor_uuid' => $this->visitorUuid,
                    'session_id' => $this->sessionId,
                    'event_type' => $type,
                    'event_name' => $event['name'] ?? null,
                    'url' => $event['url'] ?? '',
                    // Classified here rather than in the browser so the rule
                    // stays in one place and can be changed for historical
                    // data without shipping a new tracking script.
                    'device_type' => DeviceType::fromViewportWidth($event['viewport_width'] ?? null),
                    'referrer' => $event['referrer'] ?? null,
                    'meta' => isset($event['props']) ? json_encode($event['props']) : null,
                    'occurred_at' => $this->occurredAt($event['occurred_at'] ?? null, $now),
                    'created_at' => $now,
                ];
            } elseif (str_starts_with($type, 'heatmap_')) {
                $url = $event['url'] ?? '';
                $viewportWidth = (int) ($event['viewport_width'] ?? 0);

                $heatmapRows[] = [
                    'url' => $url,
                    'url_hash' => hash('sha256', $url),
                    'type' => substr($type, strlen('heatmap_')),
                    // Per event, not just per session: a visitor who rotates a
                    // tablet mid-visit is clicking on two different layouts,
                    // and each click belongs with the one it happened on.
                    'device_type' => DeviceType::fromViewportWidth($viewportWidth),
                    'viewport_width' => $viewportWidth ?: null,
                    'x_pct' => $event['x_pct'] ?? null,
                    'y_pct' => $event['y_pct'] ?? null,
                    'scroll_depth_pct' => $event['scroll_depth_pct'] ?? null,
                ];

                $sessionMeta ??= [
                    'url' => $url,
                    'viewport_width' => $viewportWidth,
                    'viewport_height' => $event['viewport_height'] ?? 0,
                    'doc_width' => $event['doc_width'] ?? null,
                    'doc_height' => $event['doc_height'] ?? null,
                ];
            } elseif ($type === 'goal_conversion') {
                $this->recordGoalConversion($event);
            }
        }

        if ($analyticsRows) {
            AnalyticsEvent::insert($analyticsRows);
        }

        $this->recordSession($this->events, $now);

        if ($heatmapRows && $this->sessionId) {
            $session = HeatmapSession::firstOrCreate(
                ['session_id' => $this->sessionId],
                [
                    'site_id' => $this->siteId,
                    'visitor_uuid' => $this->visitorUuid,
                    'url' => $sessionMeta['url'] ?? '',
                    'url_hash' => hash('sha256', $sessionMeta['url'] ?? ''),
                    'viewport_width' => $sessionMeta['viewport_width'] ?? 0,
                    'viewport_height' => $sessionMeta['viewport_height'] ?? 0,
                    'doc_width' => $sessionMeta['doc_width'] ?? null,
                    'doc_height' => $sessionMeta['doc_height'] ?? null,
                    'device_type' => DeviceType::fromViewportWidth($sessionMeta['viewport_width'] ?? null),
                    'started_at' => $now,
                ]
            );

            $heatmapRows = array_map(fn ($row) => [
                ...$row,
                'heatmap_session_id' => $session->id,
                'site_id' => $this->siteId,
                'created_at' => $now,
            ], $heatmapRows);

            HeatmapEvent::insert($heatmapRows);
        }
    }

    /**
     * Turn a browser-supplied timestamp into one the database will accept.
     *
     * These rows are written with insert()/raw fills, which bypass Eloquent's
     * datetime casting — so the ISO-8601 string the tracking script sends
     * ("2026-09-14T06:09:48.868Z") reaches MySQL verbatim and is rejected
     * with "Incorrect datetime value". SQLite accepts it, which is why this
     * only ever surfaced against a real tenant database.
     *
     * The value is also clamped, because it comes from a clock this server
     * does not control: a device set to next year would otherwise file events
     * into the future, where they sit above every report's date range.
     */
    protected function occurredAt(mixed $value, mixed $now): mixed
    {
        if (! $value) {
            return $now;
        }

        try {
            // Normalised to the app timezone, not stored as sent. Shopify and
            // friends send offsets ("...T06:09:48+05:30"), and keeping the
            // wall-clock reading would file that event 5.5 hours from when it
            // actually happened — enough to land it in the wrong report day.
            $parsed = Carbon::parse($value)->setTimezone(config('app.timezone'));
        } catch (Throwable) {
            return $now;
        }

        $nowAt = Carbon::parse($now);

        // Anything ahead of now is skew; anything older than the batch could
        // plausibly have been queued for (a tab left open, then reopened) is
        // treated the same way.
        if ($parsed->greaterThan($nowAt) || $parsed->lessThan($nowAt->copy()->subDays(7))) {
            return $now;
        }

        return $parsed;
    }

    /**
     * Create or roll forward the visit this batch belongs to.
     *
     * Counters are incremented rather than recomputed: a session spans many
     * batches (and many page loads), so the job only ever sees the slice in
     * front of it.
     *
     * @param  array<int, array<string, mixed>>  $events
     */
    protected function recordSession(array $events, mixed $now): void
    {
        if (! $this->sessionId) {
            return;
        }

        $pageviews = array_values(array_filter($events, fn ($event) => ($event['type'] ?? null) === 'pageview'));

        // The last event carrying a URL is where the visitor had got to when
        // this batch was sent.
        $lastUrl = null;
        foreach ($events as $event) {
            if (! empty($event['url'])) {
                $lastUrl = $event['url'];
            }
        }

        $first = $pageviews[0] ?? $events[0] ?? [];

        $session = VisitorSession::firstOrNew(['session_id' => $this->sessionId]);

        if (! $session->exists) {
            $session->fill([
                'site_id' => $this->siteId,
                'visitor_uuid' => $this->visitorUuid,
                'entry_url' => $first['url'] ?? ($lastUrl ?? ''),
                'referrer' => $first['referrer'] ?? null,
                'device_type' => DeviceType::fromViewportWidth($first['viewport_width'] ?? null),
                'viewport_width' => ($first['viewport_width'] ?? null) ?: null,
                'started_at' => $this->occurredAt($first['occurred_at'] ?? null, $now),
                'pageview_count' => 0,
                'event_count' => 0,
            ]);
        }

        // Written whenever it turns up rather than only at session start: GA4
        // sets its cookie on first load, which can land after our first batch
        // has already gone.
        if ($this->gaClientId && ! $session->ga_client_id) {
            $session->ga_client_id = $this->gaClientId;
        }

        $session->fill([
            'exit_url' => $lastUrl ?? $session->exit_url,
            'last_seen_at' => $now,
            'pageview_count' => $session->pageview_count + count($pageviews),
            'event_count' => $session->event_count + count($events),
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $event
     */
    protected function recordGoalConversion(array $event): void
    {
        $experimentId = $event['experiment_id'] ?? null;
        $goalId = $event['goal_id'] ?? null;

        if (! $experimentId || ! $goalId) {
            return;
        }

        $props = $event['props'] ?? [];

        // Revenue can ride along on a browser-side conversion (a JS purchase
        // call on a thank-you page), but the server-side order webhooks are
        // the trustworthy source — hence external_id, which lets the two
        // paths report the same order without double counting it.
        app(RecordConversion::class)->record(
            experimentId: (int) $experimentId,
            goalId: (int) $goalId,
            visitorUuid: $this->visitorUuid,
            externalId: isset($props['order_id']) ? (string) $props['order_id'] : null,
            revenue: isset($props['revenue']) ? (float) $props['revenue'] : null,
            currency: isset($props['currency']) ? (string) $props['currency'] : null,
            props: is_array($props) ? $props : [],
            occurredAt: $event['occurred_at'] ?? null,
        );
    }

    public function failed(?Throwable $exception): void
    {
        report($exception);
    }
}
