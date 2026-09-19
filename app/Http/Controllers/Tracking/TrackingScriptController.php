<?php

namespace App\Http\Controllers\Tracking;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Support\Tracking\TrackingScriptRenderer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use MatthiasMullie\Minify\JS as JsMinifier;

class TrackingScriptController extends Controller
{
    /**
     * Serve the per-site tracking script. Experiments/variations are read
     * from the CENTRAL connection (the default — no tenant switch needed to
     * read definitions). The final minified script is cached per site — this
     * response is fetched by every page load on every customer site, so it's
     * worth avoiding both the DB round-trip and the minify pass on repeat
     * requests within the cache window.
     */
    public function show(Request $request, TrackingScriptRenderer $renderer): Response
    {
        /** @var Site $site */
        $site = $request->attributes->get('site');

        $js = Cache::remember("site:{$site->public_id}:script", 60, function () use ($site, $renderer) {
            // Built as plain arrays throughout (not left as Collections) —
            // this closure's return value must round-trip cleanly through
            // the database cache store's serialize()/unserialize(), which
            // can silently corrupt nested Eloquent Collection objects across
            // processes.
            $experiments = $site->experiments()
                ->where('status', 'running')
                ->with('variations')
                ->get()
                ->map(fn ($experiment) => [
                    'id' => $experiment->id,
                    'key' => $experiment->key,
                    'type' => $experiment->type,
                    'url_match_type' => $experiment->url_match_type,
                    'url_pattern' => $experiment->url_pattern,
                    'traffic_allocation' => $experiment->traffic_allocation,
                    'variations' => $experiment->variations->map(fn ($variation) => [
                        'id' => $variation->id,
                        'key' => $variation->key,
                        'traffic_weight' => $variation->traffic_weight,
                        'payload' => $variation->payload,
                    ])->all(),
                ])
                ->all();

            $settings = [
                'siteId' => $site->public_id,
                'timeoutMs' => (int) ($site->settings['timeout_ms'] ?? 2000),
                'experiments' => $experiments,
                // Whether this visit should even attempt to record. The daily
                // quota is enforced server-side on the first chunk; this only
                // thins the traffic that gets that far, so most visitors
                // never download the recorder at all.
                'recording' => [
                    'enabled' => (bool) ($site->settings['recording']['enabled'] ?? false),
                    'sampleRate' => (int) ($site->settings['recording']['sample_rate']
                        ?? config('recordings.sample_rate')),
                ],
                // Off unless the merchant turns them on: pushing to a
                // dataLayer a site does not use is harmless, but firing GA4
                // events they never asked for is not.
                'integrations' => [
                    'gtm' => (bool) ($site->settings['integrations']['gtm'] ?? false),
                    'ga4' => (bool) ($site->settings['integrations']['ga4'] ?? false),
                ],
            ];

            $raw = $renderer->render(
                $settings,
                url('/api/t/assign'),
                url('/api/t/collect'),
                url('/api/t/rec'),
                url('/t/recorder.js'),
            );

            return (new JsMinifier($raw))->minify();
        });

        // Matches the server-side cache window above, so an edited test goes
        // live within about a minute rather than being pinned in browsers.
        return response($js, 200, [
            'Content-Type' => 'application/javascript',
            'Cache-Control' => 'public, max-age=60',
        ]);
    }
}
