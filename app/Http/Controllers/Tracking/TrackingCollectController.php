<?php

namespace App\Http\Controllers\Tracking;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tracking\TrackingCollectRequest;
use App\Jobs\Tracking\StoreTrackingEventsJob;
use App\Models\Site;
use Illuminate\Http\Response;

class TrackingCollectController extends Controller
{
    public function store(TrackingCollectRequest $request): Response
    {
        /** @var Site $site */
        $site = $request->attributes->get('site');

        StoreTrackingEventsJob::dispatch(
            $site->company_id,
            $site->id,
            $request->validated('events'),
            $request->validated('visitor_uuid'),
            $request->validated('session_id'),
            $request->validated('ga_client_id'),
        );

        return response()->noContent(202);
    }
}
