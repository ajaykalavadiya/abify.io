<?php

namespace App\Http\Controllers\Tracking;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tracking\TrackingAssignRequest;
use App\Models\Experiment;
use App\Models\Tenant\VisitorAssignment;
use App\Support\Tracking\VariationBucketer;
use Illuminate\Http\Response;

class TrackingAssignController extends Controller
{
    /**
     * Persist (or return the existing) sticky variation assignment for a
     * visitor. Fire-and-forget from the client (sendBeacon) — the tenant
     * connection is already active via ResolveTenantFromSite.
     */
    public function store(TrackingAssignRequest $request, VariationBucketer $bucketer): Response
    {
        $experimentId = (int) $request->validated('experiment_id');
        $visitorUuid = $request->validated('visitor_uuid');

        $existing = VisitorAssignment::where('experiment_id', $experimentId)
            ->where('visitor_uuid', $visitorUuid)
            ->first();

        if (! $existing) {
            $experiment = Experiment::with('variations')->find($experimentId);

            if ($experiment) {
                $variation = $bucketer->bucket(
                    $visitorUuid,
                    $experimentId,
                    $experiment->variations,
                    (int) $experiment->traffic_allocation,
                );

                if ($variation) {
                    VisitorAssignment::firstOrCreate([
                        'experiment_id' => $experimentId,
                        'visitor_uuid' => $visitorUuid,
                    ], [
                        'variation_id' => $variation->id,
                    ]);
                }
            }
        }

        return response()->noContent();
    }
}
