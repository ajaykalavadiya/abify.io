<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Experiment;
use App\Models\PageScreenshot;
use App\Models\Site;
use App\Support\Analytics\DateRange;
use App\Support\Analytics\DeviceType;
use App\Support\Analytics\HeatmapAggregator;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HeatmapController extends Controller
{
    public function show(
        Request $request,
        Company $account,
        Site $site,
        HeatmapAggregator $aggregator,
    ): Response {
        $this->authorize('view', $site);

        $range = DateRange::fromRequest(
            $request->string('range')->toString() ?: null,
            $request->string('from')->toString() ?: null,
            $request->string('to')->toString() ?: null,
        );

        $variationId = (int) $request->integer('variation');

        // Scoped once, here, so every layer of the map below — points, scroll
        // bands, fold line, page list — is drawn from the same window and the
        // same people.
        $aggregator = $aggregator->forRange($range)->forVariation($variationId);

        $pages = $aggregator->pages($site);

        $url = $request->string('url')->toString() ?: (string) ($pages[0]['url'] ?? '');
        $urlHash = $url !== '' ? hash('sha256', $url) : null;

        $activePage = collect($pages)->firstWhere('url', $url);

        $device = $request->string('device')->toString();
        $device = DeviceType::isValid($device)
            ? $device
            : $this->busiestDevice($activePage['devices'] ?? []);

        // 'scroll' is a view of data that always ships with the response, but
        // it lives in the URL alongside the others so every map is linkable.
        $type = in_array($request->string('type')->toString(), ['click', 'move', 'scroll'], true)
            ? $request->string('type')->toString()
            : 'click';

        return Inertia::render('heatmap/show', [
            'account' => $account,
            'site' => $site,
            'range' => [
                'value' => $range->key,
                'label' => $range->label(),
                'days' => $range->days,
                'from' => $range->start->toDateString(),
                'to' => $range->end->toDateString(),
                'options' => DateRange::options(),
            ],
            'variationId' => $variationId ?: null,
            'variations' => $this->variationOptions($site),
            'pages' => $pages,
            'selectedUrl' => $url ?: null,
            'device' => $device,
            'type' => $type,
            'devices' => $this->deviceOptions(),
            'points' => $urlHash && $type !== 'scroll'
                ? $aggregator->points($site, $urlHash, $device, $type)
                : [],
            'scroll' => $urlHash
                ? $aggregator->scrollDepth($site, $urlHash, $device)
                : ['bands' => [], 'sessions' => 0, 'average' => 0.0, 'fold' => null],
            'summary' => $urlHash
                ? $aggregator->summary($site, $urlHash, $device)
                : null,
            'screenshot' => $urlHash ? $this->screenshotPayload($account, $site, $urlHash, $device) : null,
        ]);
    }

    /**
     * Default to whichever device actually has the most sessions on this
     * page, rather than always opening on desktop — for a lot of sites the
     * desktop map is the minority view and opening on it hides the story.
     *
     * @param  array<string, int>  $deviceCounts
     */
    protected function busiestDevice(array $deviceCounts): string
    {
        if ($deviceCounts === []) {
            return DeviceType::DESKTOP;
        }

        arsort($deviceCounts);

        return (string) array_key_first($deviceCounts);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function deviceOptions(): array
    {
        $options = [];

        foreach (DeviceType::all() as $device) {
            $options[] = [
                'value' => $device,
                'label' => DeviceType::label($device),
                'width' => (int) config("screenshots.devices.{$device}.width"),
                'height' => (int) config("screenshots.devices.{$device}.height"),
            ];
        }

        return $options;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function screenshotPayload(Company $account, Site $site, string $urlHash, string $device): ?array
    {
        $screenshot = PageScreenshot::where('site_id', $site->id)
            ->where('url_hash', $urlHash)
            ->where('device', $device)
            ->first();

        if (! $screenshot) {
            return null;
        }

        return [
            'id' => $screenshot->id,
            'status' => $screenshot->status,
            'width' => $screenshot->width,
            'height' => $screenshot->height,
            'full_height' => $screenshot->full_height,
            'truncated' => $screenshot->isTruncated(),
            'stale' => $screenshot->isStale(),
            'error' => $screenshot->error,
            'captured_at' => $screenshot->captured_at,
            'src' => $screenshot->isReady()
                ? route('heatmap.screenshot.show', [$account->uid, $screenshot->id])
                : null,
        ];
    }

    /**
     * Variations of this site's tests, grouped by experiment, for the
     * "whose behaviour am I looking at" filter.
     *
     * Experiments and variations live in the CENTRAL database; the heatmap
     * data they filter does not.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function variationOptions(Site $site): array
    {
        return Experiment::where('site_id', $site->id)
            ->whereIn('status', ['running', 'paused', 'completed'])
            ->with('variations')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Experiment $experiment) => [
                'id' => $experiment->id,
                'name' => $experiment->name,
                'variations' => $experiment->variations
                    ->map(fn ($variation) => [
                        'id' => $variation->id,
                        'name' => $variation->name,
                        'is_control' => (bool) $variation->is_control,
                    ])
                    ->values()
                    ->all(),
            ])
            ->filter(fn (array $experiment) => $experiment['variations'] !== [])
            ->values()
            ->all();
    }
}
