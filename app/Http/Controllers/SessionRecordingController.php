<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Experiment;
use App\Models\Site;
use App\Models\Tenant\SessionRecording;
use App\Models\Tenant\VisitorAssignment;
use App\Models\Tenant\VisitorSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The recordings list.
 *
 * The point of this screen is triage, not browsing: at any real traffic level
 * there are more recordings than anyone will watch, so the default order is
 * most recent first and the filters are the signals that make a visit worth
 * opening — rage clicks, JS errors, and which variation the visitor saw.
 */
class SessionRecordingController extends Controller
{
    public function index(Request $request, Company $account, Site $site): Response
    {
        $this->authorize('view', $site);

        $filter = $request->string('filter')->toString();
        $variationId = (int) $request->integer('variation');

        $query = SessionRecording::where('site_id', $site->id)
            ->when($filter === 'rage', fn ($q) => $q->where('has_rage_click', true))
            ->when($filter === 'errors', fn ($q) => $q->where('has_error', true))
            // A recording with no chunks is a visit that was admitted and then
            // left immediately; there is nothing to play.
            ->where('chunk_count', '>', 0)
            ->when($variationId, function ($q) use ($variationId) {
                $visitors = VisitorAssignment::where('variation_id', $variationId)
                    ->pluck('visitor_uuid');

                return $q->whereIn('visitor_uuid', $visitors);
            })
            ->orderByDesc('started_at');

        $recordings = $query->paginate(25)->withQueryString();

        // Visit context lives on visitor_sessions, which is a different table
        // in the same database — fetched in one go for the page rather than
        // joined, so the list query stays a plain index scan.
        $sessions = VisitorSession::whereIn('session_id', $recordings->pluck('session_id'))
            ->get()
            ->keyBy('session_id');

        return Inertia::render('recordings/index', [
            'account' => $account,
            'site' => $site,
            'filter' => $filter ?: 'all',
            'variationId' => $variationId ?: null,
            'variations' => $this->variationOptions($site),
            'recordings' => [
                'data' => collect($recordings->items())->map(function (SessionRecording $recording) use ($sessions) {
                    $session = $sessions->get($recording->session_id);

                    return [
                        'id' => $recording->id,
                        'session_id' => $recording->session_id,
                        'started_at' => $recording->started_at->toIso8601String(),
                        'duration_ms' => $recording->duration_ms,
                        'event_count' => $recording->event_count,
                        'bytes' => $recording->bytes,
                        'has_rage_click' => $recording->has_rage_click,
                        'has_error' => $recording->has_error,
                        'entry_url' => $session?->entry_url,
                        'exit_url' => $session?->exit_url,
                        'pageview_count' => $session->pageview_count ?? 0,
                        'device_type' => $session?->device_type,
                        'referrer' => $session?->referrer,
                    ];
                })->all(),
                'current_page' => $recordings->currentPage(),
                'last_page' => $recordings->lastPage(),
                'total' => $recordings->total(),
            ],
        ]);
    }

    /**
     * Variations of this site's running tests, for the "who saw what" filter.
     * These live in the CENTRAL database, unlike everything else here.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function variationOptions(Site $site): array
    {
        return Experiment::where('site_id', $site->id)
            ->whereIn('status', ['running', 'paused'])
            ->with('variations')
            ->get()
            ->flatMap(fn (Experiment $experiment) => $experiment->variations->map(fn ($variation) => [
                'id' => $variation->id,
                'label' => "{$experiment->name} — {$variation->name}",
            ]))
            ->values()
            ->all();
    }

    /**
     * The player page for one recording.
     */
    public function show(Company $account, Site $site, int $recording): Response
    {
        $this->authorize('view', $site);

        $model = SessionRecording::where('site_id', $site->id)
            ->where('id', $recording)
            ->firstOrFail();

        $session = VisitorSession::where('session_id', $model->session_id)->first();

        return Inertia::render('recordings/show', [
            'account' => $account,
            'site' => $site,
            'recording' => [
                'id' => $model->id,
                'session_id' => $model->session_id,
                'started_at' => $model->started_at->toIso8601String(),
                'duration_ms' => $model->duration_ms,
                'event_count' => $model->event_count,
                'chunk_count' => $model->chunk_count,
                'bytes' => $model->bytes,
                'has_rage_click' => $model->has_rage_click,
                'has_error' => $model->has_error,
                'entry_url' => $session?->entry_url,
                'exit_url' => $session?->exit_url,
                'pageview_count' => $session->pageview_count ?? 0,
                'device_type' => $session?->device_type,
                'referrer' => $session?->referrer,
            ],
        ]);
    }

    /**
     * The replay payload: every chunk, decompressed and concatenated in
     * capture order.
     *
     * Served from here rather than as a public file because it is a recording
     * of the customer's own site, which may never have been public — the same
     * reasoning as heatmap screenshots.
     */
    public function events(Company $account, Site $site, int $recording): JsonResponse
    {
        $this->authorize('view', $site);

        $model = SessionRecording::where('site_id', $site->id)
            ->where('id', $recording)
            ->firstOrFail();

        $disk = Storage::disk($model->disk);
        $events = [];

        foreach ($model->chunkPaths() as $path) {
            if (! $disk->exists($path)) {
                // A pruned or half-written chunk is a gap in the replay, not
                // a reason to fail the whole thing — rrweb plays what it has.
                continue;
            }

            $decoded = @gzdecode($disk->get($path));

            if ($decoded === false) {
                continue;
            }

            $chunk = json_decode($decoded, true);

            if (is_array($chunk)) {
                array_push($events, ...$chunk);
            }
        }

        // rrweb requires events in timestamp order. Chunks are numbered in
        // capture order, but they are uploaded over sendBeacon and can be
        // written out of sequence, so the final ordering is decided here.
        usort($events, fn ($a, $b) => ($a['timestamp'] ?? 0) <=> ($b['timestamp'] ?? 0));

        return response()->json([
            'events' => $events,
        ])->header('Cache-Control', 'private, max-age=300');
    }
}
