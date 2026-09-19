<?php

namespace App\Http\Controllers\Tracking;

use App\Actions\Tracking\ReserveRecordingSlot;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tracking\RecordingChunkRequest;
use App\Models\Site;
use App\Models\Tenant\SessionRecording;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

/**
 * Receives replay chunks from the recorder.
 *
 * Kept off /api/t/collect deliberately: that endpoint batches small analytics
 * events under a 200-event cap, while a replay chunk is a single large
 * payload with completely different size and frequency characteristics.
 */
class RecordingChunkController extends Controller
{
    public function store(RecordingChunkRequest $request, ReserveRecordingSlot $quota): JsonResponse
    {
        /** @var Site $site */
        $site = $request->attributes->get('site');

        $sessionId = $request->validated('session_id');

        $recording = SessionRecording::where('session_id', $sessionId)->first();

        if (! $recording) {
            // First chunk of this visit — this is the moment a daily slot is
            // claimed, and the only moment recording can be refused. Once a
            // visit is admitted its remaining chunks are always accepted, so
            // a recording can never end up truncated halfway by the quota
            // rolling over mid-visit.
            if (! $quota->reserve($site)) {
                return response()->json(['recording' => false], 200);
            }

            $recording = SessionRecording::create([
                'site_id' => $site->id,
                'session_id' => $sessionId,
                'visitor_uuid' => $request->validated('visitor_uuid'),
                'status' => SessionRecording::STATUS_RECORDING,
                'disk' => config('recordings.disk'),
                'path_prefix' => sprintf('recordings/%s/%s', $site->public_id, $sessionId),
                'started_at' => now(),
                'expires_at' => now()->addDays((int) config('recordings.retention_days')),
                // Set explicitly rather than relying on the column defaults:
                // a freshly created model does not read them back, so these
                // attributes would be null on this first request and the
                // accumulation below would be doing arithmetic on nulls.
                'chunk_count' => 0,
                'event_count' => 0,
                'bytes' => 0,
                'duration_ms' => 0,
                'has_rage_click' => false,
                'has_error' => false,
            ]);
        }

        $payload = gzencode(json_encode($request->validated('events')) ?: '[]', 6);

        if ($payload === false || strlen($payload) > (int) config('recordings.max_chunk_bytes')) {
            return response()->json(['recording' => true, 'stored' => false], 413);
        }

        // The client's `seq` is advisory only — it restarts at zero on every
        // page load, so it cannot be trusted to name a file uniquely across a
        // multi-page visit.
        Storage::disk($recording->disk)->put($recording->newChunkPath(), $payload);

        $bytes = strlen($payload);

        $recording->forceFill([
            'chunk_count' => $recording->chunk_count + 1,
            'event_count' => $recording->event_count + count($request->validated('events')),
            'bytes' => $recording->bytes + $bytes,
            // Both cast before comparing — max(null, 0) evaluates to null in
            // PHP, not 0, which is a NOT NULL violation rather than a number.
            'duration_ms' => max((int) $recording->duration_ms, (int) $request->validated('duration_ms')),
            // Sticky: a visit that raged once stays flagged even if later
            // chunks are calm.
            'has_rage_click' => $recording->has_rage_click || $request->boolean('rage_click'),
            'has_error' => $recording->has_error || $request->boolean('error'),
            'last_chunk_at' => now(),
        ])->save();

        $quota->addBytes($site, $bytes);

        // Tells the recorder whether to keep going. A visit that has run past
        // the duration ceiling is cut off here rather than trusting the page
        // to stop itself.
        $expired = $recording->started_at->diffInMinutes(now())
            >= (int) config('recordings.max_duration_minutes');

        return response()->json(['recording' => ! $expired], 202);
    }
}
