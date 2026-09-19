<?php

namespace App\Http\Controllers\Api;

use App\Actions\Tracking\RecordConversion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ConversionRequest;
use App\Models\Site;
use Illuminate\Http\JsonResponse;

/**
 * Server-to-server conversion ingestion, used by the Shopify, WooCommerce and
 * Magento integrations.
 *
 * Deliberately synchronous rather than queued like the browser beacon: the
 * caller is a webhook handler that needs to know whether to retry, and these
 * arrive at order volume, not pageview volume.
 */
class ConversionController extends Controller
{
    public function store(ConversionRequest $request, RecordConversion $recorder): JsonResponse
    {
        /** @var Site $site */
        $site = $request->attributes->get('site');

        $recorded = $recorder->byKey(
            site: $site,
            visitorUuid: $request->validated('visitor_uuid'),
            goalKey: $request->validated('goal'),
            externalId: $request->validated('external_id'),
            revenue: $request->validated('revenue') !== null
                ? (float) $request->validated('revenue')
                : null,
            currency: $request->validated('currency'),
            props: $request->validated('props') ?? [],
            occurredAt: $request->validated('occurred_at'),
        );

        // 202 with recorded: 0 is a success, not an error. A visitor who was
        // never bucketed into any running experiment still buys things, and
        // a duplicate delivery is the dedupe working. Returning an error
        // either way would make every integration retry forever.
        return response()->json([
            'recorded' => $recorded,
        ], 202);
    }
}
