<?php

namespace App\Jobs\Analytics;

use App\Models\Integration;
use App\Models\Site;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Reports one abify conversion into a merchant's GA4 via the Measurement
 * Protocol.
 *
 * The browser-side integration already sends `experiment_viewed` through the
 * page's own gtag, but that only covers what happens in a browser. A Shopify
 * order arrives as a webhook hours later with no page attached — without this
 * the merchant's GA4 would show which variation a visitor saw and never show
 * the purchase it led to.
 *
 * Queued, and failures are swallowed after logging: GA4 being unreachable
 * must never fail the ingestion that produced the conversion, because abify's
 * own numbers are the ones that decide the experiment.
 */
class SendGa4ConversionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60];

    /**
     * @param  array<string, mixed>  $params
     */
    public function __construct(
        public int $siteId,
        public string $gaClientId,
        public string $eventName,
        public array $params = [],
    ) {}

    public function handle(): void
    {
        $site = Site::whereKey($this->siteId)->first();

        if (! $site) {
            return;
        }

        $integration = Integration::where('site_id', $site->id)
            ->where('provider', Integration::GA4)
            ->where('status', Integration::STATUS_ACTIVE)
            ->first();

        $secret = $integration?->apiSecret();

        if (! $integration || ! $secret) {
            return;
        }

        $response = Http::asJson()
            ->timeout(5)
            ->post('https://www.google-analytics.com/mp/collect?'.http_build_query([
                'measurement_id' => $integration->external_id,
                'api_secret' => $secret,
            ]), [
                'client_id' => $this->gaClientId,
                // GA4 rejects events dated more than 72 hours back, so the
                // timestamp is left to Google rather than backdated to the
                // order — a late webhook would otherwise be dropped silently.
                'events' => [[
                    'name' => $this->eventName,
                    'params' => $this->params,
                ]],
            ]);

        // The Measurement Protocol answers 204 for anything it accepts and
        // never reports a validation problem here, so only transport failures
        // are visible.
        if (! $response->successful()) {
            Log::warning('GA4 Measurement Protocol rejected an event', [
                'site_id' => $site->id,
                'status' => $response->status(),
            ]);
        }
    }
}
