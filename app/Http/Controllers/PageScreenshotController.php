<?php

namespace App\Http\Controllers;

use App\Jobs\Screenshots\CapturePageScreenshotJob;
use App\Models\Company;
use App\Models\PageScreenshot;
use App\Models\Site;
use App\Support\Analytics\DeviceType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Captures and serves the page screenshots a heatmap is drawn on top of.
 */
class PageScreenshotController extends Controller
{
    /**
     * Queue (or run) a capture of one page at one device size.
     */
    public function store(Request $request, Company $account, Site $site): RedirectResponse
    {
        $this->authorize('update', $site);

        $validated = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
            'device' => ['required', Rule::in(DeviceType::all())],
        ]);

        $screenshot = PageScreenshot::updateOrCreate(
            [
                'site_id' => $site->id,
                'url_hash' => hash('sha256', $validated['url']),
                'device' => $validated['device'],
            ],
            [
                'url' => $validated['url'],
                'status' => PageScreenshot::STATUS_PENDING,
                'error' => null,
            ],
        );

        // Running a browser inline holds the request open for several
        // seconds, which is why config('screenshots.queue') exists — but a
        // pending row with no worker to pick it up is worse, so inline is the
        // default and offloading is the opt-in.
        config('screenshots.queue')
            ? CapturePageScreenshotJob::dispatch($screenshot)
            : CapturePageScreenshotJob::dispatchSync($screenshot);

        return back();
    }

    /**
     * Stream a captured screenshot.
     *
     * Screenshots are of the customer's own pages, which may be behind a
     * login or otherwise not public, so they live on a private disk and are
     * served through this authorized route rather than a public URL.
     */
    public function show(Company $account, Site $site, PageScreenshot $screenshot): StreamedResponse
    {
        $this->authorize('view', $site);

        abort_unless($screenshot->site_id === $site->id, 404);
        abort_unless($screenshot->isReady(), 404);

        $disk = Storage::disk((string) $screenshot->disk);

        abort_unless($disk->exists((string) $screenshot->path), 404);

        return $disk->response((string) $screenshot->path, null, [
            'Content-Type' => 'image/jpeg',
            // A capture is immutable — a re-capture writes a new path — so it
            // can be cached hard by the browser.
            'Cache-Control' => 'private, max-age=604800',
        ]);
    }
}
