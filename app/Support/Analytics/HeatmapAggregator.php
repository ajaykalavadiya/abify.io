<?php

namespace App\Support\Analytics;

use App\Models\Site;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Turns raw heatmap_events into the aggregates the heatmap view renders.
 *
 * Everything here runs against the tenant connection, which the request has
 * already switched (ResolveAccountFromRoute) — these methods never switch it
 * themselves.
 *
 * Coordinates are stored as hundredths of a percent of the full document
 * (0–10000 on each axis, see the tracking script), so they stay meaningful
 * whatever the visitor's window size. They are bucketed here rather than sent
 * raw: a busy page produces millions of events, and the client only needs
 * enough resolution to paint a blurred field.
 *
 * @phpstan-type HeatmapPageRow array{url: string, url_hash: string, sessions: int, clicks: int, events: int, last_seen: string|null, devices: array<string, int>}
 */
class HeatmapAggregator
{
    /**
     * The reporting window every query here is scoped to, or null for all
     * time.
     *
     * Held as state rather than threaded through each method's signature:
     * a heatmap is only coherent when every layer of it — the points, the
     * scroll bands, the fold line and the session counts — is drawn from the
     * same window. Passing it per call would make it possible to paint one
     * month's clicks over another month's scroll depth.
     */
    protected ?DateRange $range = null;

    /**
     * Restrict every query to visitors who saw one particular variation, or
     * null for all traffic.
     *
     * Held as state for the same reason as the window above: a map is only
     * honest when its points, scroll bands and session counts all come from
     * the same people.
     */
    protected ?int $variationId = null;

    /**
     * Scope every subsequent query to a window. Returns a fresh instance so
     * one request cannot leak its range into another through the container.
     */
    public function forRange(?DateRange $range): self
    {
        $scoped = clone $this;
        $scoped->range = $range;

        return $scoped;
    }

    /**
     * Scope every subsequent query to one variation's visitors.
     */
    public function forVariation(?int $variationId): self
    {
        $scoped = clone $this;
        $scoped->variationId = $variationId ?: null;

        return $scoped;
    }

    /**
     * Bucket sizes in hundredths of a percent. X is coarser than Y on
     * purpose: pages are far taller than they are wide, so an even grid would
     * squash hundreds of vertical pixels into one row while splitting hairs
     * horizontally.
     */
    protected const BUCKET_X = 50;   // 0.5% of page width

    protected const BUCKET_Y = 20;   // 0.2% of page height

    /**
     * Ceiling on cells returned to the browser. Real pages concentrate clicks
     * in a handful of places, so the tail past this is single-visit noise
     * that would not be visible in the render anyway.
     */
    protected const MAX_CELLS = 4000;

    /** Scroll-depth reporting granularity, in percent. */
    protected const SCROLL_STEP = 2;

    /**
     * Every page that has heatmap data, with its per-device volumes, so the
     * page picker can show where the traffic actually is.
     *
     * @return array<int, HeatmapPageRow>
     */
    public function pages(Site $site, int $limit = 100): array
    {
        $rows = $this->events($site)
            ->selectRaw('url_hash, MIN(url) as url, COUNT(*) as events')
            ->selectRaw('COUNT(DISTINCT heatmap_session_id) as sessions')
            ->selectRaw("SUM(CASE WHEN type = 'click' THEN 1 ELSE 0 END) as clicks")
            ->selectRaw('MAX(created_at) as last_seen')
            ->groupBy('url_hash')
            ->orderByDesc('sessions')
            ->limit($limit)
            ->get();

        $deviceCounts = $this->deviceSessionCounts($site);

        return $rows->map(fn ($row) => [
            'url' => (string) $row->url,
            'url_hash' => (string) $row->url_hash,
            'sessions' => (int) $row->sessions,
            'clicks' => (int) $row->clicks,
            'events' => (int) $row->events,
            'last_seen' => $row->last_seen === null ? null : (string) $row->last_seen,
            'devices' => $deviceCounts[(string) $row->url_hash] ?? [],
        ])->all();
    }

    /**
     * Session counts per (page, device), keyed by url_hash.
     *
     * One query per device rather than a single grouped CASE: there are three
     * of them, each is a plain indexed scan, and it keeps the bucket
     * definition in one place instead of duplicating it as SQL.
     *
     * @return array<string, array<string, int>>
     */
    protected function deviceSessionCounts(Site $site): array
    {
        $counts = [];

        foreach (DeviceType::all() as $device) {
            $rows = $this->scopeToDevice($this->events($site), $device)
                ->selectRaw('url_hash')
                ->selectRaw('COUNT(DISTINCT heatmap_session_id) as sessions')
                ->groupBy('url_hash')
                ->get();

            foreach ($rows as $row) {
                $counts[(string) $row->url_hash][$device] = (int) $row->sessions;
            }
        }

        return $counts;
    }

    /**
     * Bucketed click or move density for one page on one device.
     *
     * @return array<int, array{x: int, y: int, weight: int}>
     */
    public function points(Site $site, string $urlHash, string $device, string $type): array
    {
        return $this->scopeToDevice(
            $this->events($site)->where('url_hash', $urlHash)->where('type', $type),
            $device
        )
            ->whereNotNull('x_pct')
            ->whereNotNull('y_pct')
            ->selectRaw('FLOOR(x_pct / ?) * ? as gx', [self::BUCKET_X, self::BUCKET_X])
            ->selectRaw('FLOOR(y_pct / ?) * ? as gy', [self::BUCKET_Y, self::BUCKET_Y])
            ->selectRaw('COUNT(*) as weight')
            ->groupBy('gx', 'gy')
            ->orderByDesc('weight')
            ->limit(self::MAX_CELLS)
            ->get()
            ->map(fn ($row) => [
                'x' => (int) $row->gx,
                'y' => (int) $row->gy,
                'weight' => (int) $row->weight,
            ])
            ->all();
    }

    /**
     * How far down the page visitors actually got: for each depth band, the
     * share of sessions that reached it.
     *
     * The tracker only emits a scroll event when a visitor beats their own
     * previous best, so a session's furthest point is the MAX of its events —
     * and a session with no scroll event at all never left the first screen,
     * which is exactly the kind of session a scroll map exists to show. Those
     * sessions are counted in the denominator rather than dropped.
     *
     * @return array{bands: array<int, array{depth: int, reach: float, sessions: int}>, sessions: int, average: float, fold: float|null}
     */
    public function scrollDepth(Site $site, string $urlHash, string $device): array
    {
        // Reduced to one row per depth band in the database: a busy page has
        // millions of events across tens of thousands of sessions, and pulling
        // a row per session back into PHP just to bucket it is the kind of
        // query that falls over exactly when the page is worth looking at.
        $sessionDepths = $this->scopeToDevice(
            $this->events($site)->where('url_hash', $urlHash),
            $device
        )
            ->selectRaw('heatmap_session_id')
            ->selectRaw("MAX(CASE WHEN type = 'scroll' THEN scroll_depth_pct ELSE 0 END) as depth")
            ->groupBy('heatmap_session_id');

        $step = self::SCROLL_STEP * 100;

        $buckets = DB::connection('tenant')
            ->query()
            ->fromSub($sessionDepths, 'session_depths')
            ->selectRaw('FLOOR(depth / ?) as band, COUNT(*) as sessions, SUM(depth) as total_depth', [$step])
            ->groupBy('band')
            ->get();

        $sessions = (int) $buckets->sum('sessions');

        if ($sessions === 0) {
            return [
                'bands' => [],
                'sessions' => 0,
                'average' => 0.0,
                'fold' => $this->foldPosition($site, $urlHash, $device),
            ];
        }

        $countByBand = $buckets->mapWithKeys(fn ($row) => [(int) $row->band => (int) $row->sessions]);
        $totalDepth = (int) $buckets->sum('total_depth');

        // Reach is cumulative from the bottom up: a session that got 80% down
        // also reached every band above it.
        $bands = [];
        $reached = 0;

        for ($band = (int) floor(10000 / $step); $band >= 0; $band--) {
            $reached += $countByBand[$band] ?? 0;

            $bands[] = [
                'depth' => (int) round($band * self::SCROLL_STEP),
                'sessions' => $reached,
                'reach' => round($reached / $sessions * 100, 1),
            ];
        }

        return [
            'bands' => array_reverse($bands),
            'sessions' => $sessions,
            'average' => round($totalDepth / $sessions / 100, 1),
            'fold' => $this->foldPosition($site, $urlHash, $device),
        ];
    }

    /**
     * Where the fold sits as a share of the whole document, averaged over the
     * sessions on this page/device — the line under which content is only
     * seen by someone who scrolls.
     *
     * Null when no session reported its document height (data recorded before
     * the tracker started sending it).
     */
    protected function foldPosition(Site $site, string $urlHash, string $device): ?float
    {
        $row = $this->scopeToDevice($this->sessions($site)->where('url_hash', $urlHash), $device)
            ->whereNotNull('doc_height')
            ->where('doc_height', '>', 0)
            ->selectRaw('AVG(viewport_height) as viewport, AVG(doc_height) as document')
            ->first();

        if (! $row || ! $row->document) {
            return null;
        }

        return round(min(100, (float) $row->viewport / (float) $row->document * 100), 1);
    }

    /**
     * Headline numbers for the selected page/device.
     *
     * @return array<string, mixed>
     */
    public function summary(Site $site, string $urlHash, string $device): array
    {
        $row = $this->scopeToDevice($this->events($site)->where('url_hash', $urlHash), $device)
            ->selectRaw('COUNT(DISTINCT heatmap_session_id) as sessions')
            ->selectRaw("SUM(CASE WHEN type = 'click' THEN 1 ELSE 0 END) as clicks")
            ->selectRaw("SUM(CASE WHEN type = 'move' THEN 1 ELSE 0 END) as moves")
            ->selectRaw('MIN(created_at) as first_seen, MAX(created_at) as last_seen')
            ->first();

        $viewport = $this->scopeToDevice($this->sessions($site)->where('url_hash', $urlHash), $device)
            ->selectRaw('AVG(viewport_width) as width, AVG(viewport_height) as height')
            ->selectRaw('AVG(doc_width) as doc_width, AVG(doc_height) as doc_height')
            ->first();

        $sessions = (int) ($row->sessions ?? 0);
        $clicks = (int) ($row->clicks ?? 0);

        return [
            'sessions' => $sessions,
            'clicks' => $clicks,
            'moves' => (int) ($row->moves ?? 0),
            'clicks_per_session' => $sessions > 0 ? round($clicks / $sessions, 1) : 0.0,
            'first_seen' => $row->first_seen ?? null,
            'last_seen' => $row->last_seen ?? null,
            'viewport_width' => $viewport?->width ? (int) round($viewport->width) : null,
            'viewport_height' => $viewport?->height ? (int) round($viewport->height) : null,
            'doc_width' => $viewport?->doc_width ? (int) round($viewport->doc_width) : null,
            'doc_height' => $viewport?->doc_height ? (int) round($viewport->doc_height) : null,
        ];
    }

    protected function events(Site $site): Builder
    {
        $query = $this->scopeToRange(
            DB::connection('tenant')
                ->table('heatmap_events')
                ->where('site_id', $site->id),
            'created_at',
        );

        // Events carry no visitor of their own, so the variation filter has
        // to reach them through the session that produced them.
        if ($this->variationId) {
            $query->whereIn('heatmap_session_id', function (Builder $sessions) use ($site) {
                $sessions->select('id')
                    ->from('heatmap_sessions')
                    ->where('site_id', $site->id);

                $this->scopeToVariation($sessions);
            });
        }

        return $query;
    }

    protected function sessions(Site $site): Builder
    {
        // Sessions are dated by when the visit began, not by created_at,
        // which the table does not carry.
        $query = $this->scopeToRange(
            DB::connection('tenant')
                ->table('heatmap_sessions')
                ->where('site_id', $site->id),
            'started_at',
        );

        $this->scopeToVariation($query);

        return $query;
    }

    /**
     * Narrow a visitor-bearing query to the people who saw this variation.
     *
     * Resolved at read time against visitor_assignments rather than stamped
     * onto heatmap rows when they are written. Two reasons: a visitor is
     * bucketed once but produces heatmap events for the whole visit, so the
     * assignment is the authoritative record either way — and doing it here
     * means every heatmap already captured can be segmented by variation
     * immediately, with no backfill and nothing lost for tests that were
     * already running.
     */
    protected function scopeToVariation(Builder $query): Builder
    {
        if (! $this->variationId) {
            return $query;
        }

        return $query->whereIn('visitor_uuid', function (Builder $assignments) {
            $assignments->select('visitor_uuid')
                ->from('visitor_assignments')
                ->where('variation_id', $this->variationId);
        });
    }

    /**
     * Apply the active window, if there is one.
     */
    protected function scopeToRange(Builder $query, string $column): Builder
    {
        if (! $this->range) {
            return $query;
        }

        return $query->whereBetween($column, [$this->range->start, $this->range->end]);
    }

    /**
     * Scope a query to one device bucket.
     *
     * A plain equality filter on an indexed column, because the migration
     * that added device_type backfilled the history rather than leaving the
     * read path to work it out from viewport width every time. The one
     * concession is unclassified rows falling to the catch-all bucket: a row
     * that somehow arrives without a device still has to be readable
     * somewhere, and quietly dropping clicks is worse than filing them under
     * a best guess.
     */
    protected function scopeToDevice(Builder $query, string $device): Builder
    {
        [, $maximum] = DeviceType::widthRange($device);

        if ($maximum === null) {
            return $query->where(function (Builder $query) use ($device) {
                $query->where('device_type', $device)->orWhereNull('device_type');
            });
        }

        return $query->where('device_type', $device);
    }
}
