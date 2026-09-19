<?php

namespace App\Support\Analytics;

use App\Models\Experiment;
use App\Models\Site;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds the site overview dashboard: traffic over time, where it came from,
 * what it looked at, and how the running tests are doing.
 *
 * Reads the tenant connection, which the request has already switched
 * (ResolveAccountFromRoute), plus the central experiments table.
 */
class SiteOverviewMetrics
{
    /** Rows shown in the "top" lists before the tail is folded into Other. */
    protected const TOP_LIMIT = 8;

    /**
     * @return array<string, mixed>
     */
    public function build(Site $site, DateRange $range): array
    {
        $current = $this->totals($site, $range->start, $range->end);
        $previous = $this->totals($site, $range->previousStart, $range->previousEnd);

        return [
            'totals' => $this->withDeltas($current, $previous),
            'series' => $this->dailySeries($site, $range),
            'devices' => $this->deviceBreakdown($site, $range),
            'topPages' => $this->topPages($site, $range),
            'topReferrers' => $this->topReferrers($site, $range),
            'activity' => $this->activity($site, $range),
            'experiments' => $this->experiments($site),
        ];
    }

    /**
     * @return array<string, int|float>
     */
    protected function totals(Site $site, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $row = $this->pageviews($site)
            ->whereBetween('occurred_at', [$start, $end])
            ->selectRaw('COUNT(*) as pageviews')
            ->selectRaw('COUNT(DISTINCT visitor_uuid) as visitors')
            ->selectRaw('COUNT(DISTINCT session_id) as sessions')
            ->first();

        $pageviews = (int) ($row->pageviews ?? 0);
        $sessions = (int) ($row->sessions ?? 0);

        $conversions = DB::connection('tenant')
            ->table('goal_conversions')
            ->whereIn('experiment_id', $this->experimentIds($site))
            ->whereBetween('converted_at', [$start, $end])
            ->count();

        return [
            'pageviews' => $pageviews,
            'visitors' => (int) ($row->visitors ?? 0),
            'sessions' => $sessions,
            'conversions' => $conversions,
            // One-page sessions are the closest thing to a bounce this
            // tracker can measure honestly — it records no dwell time, so
            // calling it "bounce rate" would overstate what the data says.
            'pages_per_session' => $sessions > 0 ? round($pageviews / $sessions, 2) : 0.0,
        ];
    }

    /**
     * Pair each total with its previous-period counterpart and the percentage
     * change between them.
     *
     * @param  array<string, int|float>  $current
     * @param  array<string, int|float>  $previous
     * @return array<string, array{value: int|float, previous: int|float, change: float|null}>
     */
    protected function withDeltas(array $current, array $previous): array
    {
        $result = [];

        foreach ($current as $key => $value) {
            $before = $previous[$key] ?? 0;

            $result[$key] = [
                'value' => $value,
                'previous' => $before,
                // Null, not 0 or Infinity: growth from nothing has no
                // meaningful percentage, and the tile shows a dash for it.
                'change' => $before > 0 ? round((($value - $before) / $before) * 100, 1) : null,
            ];
        }

        return $result;
    }

    /**
     * Daily visitors / pageviews / sessions across the range, gap-filled.
     *
     * @return array<int, array{date: string, visitors: int, pageviews: int, sessions: int}>
     */
    protected function dailySeries(Site $site, DateRange $range): array
    {
        $rows = $this->pageviews($site)
            ->whereBetween('occurred_at', [$range->start, $range->end])
            ->selectRaw('DATE(occurred_at) as day')
            ->selectRaw('COUNT(*) as pageviews')
            ->selectRaw('COUNT(DISTINCT visitor_uuid) as visitors')
            ->selectRaw('COUNT(DISTINCT session_id) as sessions')
            ->groupBy('day')
            ->get()
            ->keyBy(fn ($row) => (string) $row->day);

        return collect($range->eachDay())
            ->map(fn (string $day) => [
                'date' => $day,
                'visitors' => (int) ($rows[$day]->visitors ?? 0),
                'pageviews' => (int) ($rows[$day]->pageviews ?? 0),
                'sessions' => (int) ($rows[$day]->sessions ?? 0),
            ])
            ->all();
    }

    /**
     * @return array<int, array{device: string, label: string, visitors: int, pageviews: int, share: float}>
     */
    protected function deviceBreakdown(Site $site, DateRange $range): array
    {
        $rows = $this->pageviews($site)
            ->whereBetween('occurred_at', [$range->start, $range->end])
            ->selectRaw("COALESCE(device_type, '".DeviceType::DESKTOP."') as device")
            ->selectRaw('COUNT(*) as pageviews')
            ->selectRaw('COUNT(DISTINCT visitor_uuid) as visitors')
            ->groupBy('device')
            ->get();

        $total = max(1, (int) $rows->sum('visitors'));

        // Config order, not result order, so the same device keeps the same
        // colour whether or not it had traffic today.
        return collect(DeviceType::all())
            ->map(function (string $device) use ($rows, $total) {
                $row = $rows->firstWhere('device', $device);

                return [
                    'device' => $device,
                    'label' => DeviceType::label($device),
                    'visitors' => (int) ($row->visitors ?? 0),
                    'pageviews' => (int) ($row->pageviews ?? 0),
                    'share' => round(((int) ($row->visitors ?? 0)) / $total * 100, 1),
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array{url: string, path: string, pageviews: int, visitors: int}>
     */
    protected function topPages(Site $site, DateRange $range): array
    {
        return $this->pageviews($site)
            ->whereBetween('occurred_at', [$range->start, $range->end])
            ->selectRaw('url')
            ->selectRaw('COUNT(*) as pageviews')
            ->selectRaw('COUNT(DISTINCT visitor_uuid) as visitors')
            ->groupBy('url')
            ->orderByDesc('pageviews')
            ->limit(self::TOP_LIMIT)
            ->get()
            ->map(fn ($row) => [
                'url' => $row->url,
                'path' => $this->pathOf($row->url),
                'pageviews' => (int) $row->pageviews,
                'visitors' => (int) $row->visitors,
            ])
            ->all();
    }

    /**
     * Referrers grouped by host — one row per site that sends traffic, rather
     * than one per landing URL. Visits with no referrer are reported as
     * "Direct" instead of being dropped.
     *
     * @return array<int, array{source: string, visitors: int}>
     */
    protected function topReferrers(Site $site, DateRange $range): array
    {
        $rows = $this->pageviews($site)
            ->whereBetween('occurred_at', [$range->start, $range->end])
            ->selectRaw('referrer')
            ->selectRaw('COUNT(DISTINCT visitor_uuid) as visitors')
            ->groupBy('referrer')
            ->get();

        $siteHost = $site->domain ? $this->hostOf($site->domain) : null;

        return $rows
            ->groupBy(function ($row) use ($siteHost) {
                $host = $this->hostOf((string) ($row->referrer ?? ''));

                if ($host === null) {
                    return 'Direct';
                }

                // A link from the site to itself is navigation, not a source.
                return $host === $siteHost ? 'Direct' : $host;
            })
            ->map(fn (Collection $group, string $source) => [
                'source' => $source,
                'visitors' => (int) $group->sum('visitors'),
            ])
            ->sortByDesc('visitors')
            ->take(self::TOP_LIMIT)
            ->values()
            ->all();
    }

    /**
     * Pageviews by weekday and hour — when this site's audience is actually
     * awake, which is the thing a test's start date should respect.
     *
     * @return array{cells: array<int, array{day: int, hour: int, pageviews: int}>, max: int}
     */
    protected function activity(Site $site, DateRange $range): array
    {
        $rows = $this->pageviews($site)
            ->whereBetween('occurred_at', [$range->start, $range->end])
            // MySQL DAYOFWEEK is 1=Sunday; shift to 0=Monday so the grid reads
            // as a working week.
            ->selectRaw('((DAYOFWEEK(occurred_at) + 5) % 7) as day, HOUR(occurred_at) as hour')
            ->selectRaw('COUNT(*) as pageviews')
            ->groupBy('day', 'hour')
            ->get();

        $byCell = $rows->keyBy(fn ($row) => $row->day.':'.$row->hour);

        $cells = [];
        $max = 0;

        for ($day = 0; $day < 7; $day++) {
            for ($hour = 0; $hour < 24; $hour++) {
                $pageviews = (int) ($byCell["{$day}:{$hour}"]->pageviews ?? 0);
                $max = max($max, $pageviews);

                $cells[] = ['day' => $day, 'hour' => $hour, 'pageviews' => $pageviews];
            }
        }

        return ['cells' => $cells, 'max' => $max];
    }

    /**
     * Every test on the site with its lifetime traffic and conversions.
     *
     * Lifetime rather than range-scoped on purpose: a test's numbers are only
     * meaningful over its whole run, and slicing them to "last 7 days" would
     * invite reading significance off a window the test never targeted.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function experiments(Site $site): array
    {
        $experiments = $site->experiments()
            ->orderByRaw("FIELD(status, 'running', 'paused', 'draft', 'completed')")
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        if ($experiments->isEmpty()) {
            return [];
        }

        $ids = $experiments->pluck('id');

        $visitors = DB::connection('tenant')
            ->table('visitor_assignments')
            ->whereIn('experiment_id', $ids)
            ->selectRaw('experiment_id, COUNT(*) as visitors')
            ->groupBy('experiment_id')
            ->pluck('visitors', 'experiment_id');

        $conversions = DB::connection('tenant')
            ->table('goal_conversions')
            ->whereIn('experiment_id', $ids)
            ->selectRaw('experiment_id, COUNT(DISTINCT visitor_uuid) as conversions')
            ->groupBy('experiment_id')
            ->pluck('conversions', 'experiment_id');

        return $experiments->map(function (Experiment $experiment) use ($visitors, $conversions) {
            $experimentVisitors = (int) ($visitors[$experiment->id] ?? 0);
            $experimentConversions = (int) ($conversions[$experiment->id] ?? 0);

            return [
                'id' => $experiment->id,
                'name' => $experiment->name,
                'type' => $experiment->type,
                'status' => $experiment->status,
                'started_at' => $experiment->started_at,
                'visitors' => $experimentVisitors,
                'conversions' => $experimentConversions,
                'rate' => $experimentVisitors > 0
                    ? round($experimentConversions / $experimentVisitors * 100, 2)
                    : 0.0,
            ];
        })->all();
    }

    /**
     * @return Collection<int, int>
     */
    protected function experimentIds(Site $site): Collection
    {
        return $site->experiments()->pluck('id');
    }

    protected function pageviews(Site $site): Builder
    {
        return DB::connection('tenant')
            ->table('analytics_events')
            ->where('site_id', $site->id)
            ->where('event_type', 'pageview');
    }

    protected function pathOf(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        $query = parse_url($url, PHP_URL_QUERY);

        return $path.($query ? '?'.$query : '');
    }

    protected function hostOf(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        // A bare domain (sites.domain is stored without a scheme) has no host
        // component until one is bolted on.
        $host = parse_url(str_contains($value, '//') ? $value : "https://{$value}", PHP_URL_HOST);

        return $host ? preg_replace('/^www\./', '', $host) : null;
    }
}
