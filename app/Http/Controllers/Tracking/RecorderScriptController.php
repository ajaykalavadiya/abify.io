<?php

namespace App\Http\Controllers\Tracking;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

/**
 * Serves the session replay recorder bundle at a stable, unhashed URL.
 *
 * The build emits a content-hashed filename, which is right for the
 * dashboard's own assets but useless here: this URL is written into the
 * tracking script served to third-party sites, and it cannot change every
 * time the bundle is rebuilt. So the hash is resolved from the Vite manifest
 * at request time and the contents are served from this fixed path instead.
 *
 * Kept deliberately short-cached rather than immutable for the same reason —
 * the URL is stable, so the file behind it is what changes on deploy.
 */
class RecorderScriptController extends Controller
{
    /** Matches the tracking script's own cache window. */
    private const CACHE_SECONDS = 300;

    public function show(): Response
    {
        $js = Cache::remember('tracking:recorder-bundle', self::CACHE_SECONDS, function () {
            $path = $this->resolveBundlePath();

            return $path && File::exists($path) ? File::get($path) : null;
        });

        if ($js === null) {
            // A 404 here is a deploy problem, not a visitor problem — the
            // tracking script treats a failed recorder load as "no recording"
            // and carries on, so tests and analytics are unaffected.
            return response('/* recorder bundle unavailable */', 404, [
                'Content-Type' => 'application/javascript',
            ]);
        }

        return response($js, 200, [
            'Content-Type' => 'application/javascript',
            'Cache-Control' => 'public, max-age='.self::CACHE_SECONDS,
        ]);
    }

    /**
     * Absolute path of the built recorder bundle, via the Vite manifest.
     */
    protected function resolveBundlePath(): ?string
    {
        $manifestPath = public_path('build/manifest.json');

        if (! File::exists($manifestPath)) {
            return null;
        }

        /** @var array<string, array{file?: string}> $manifest */
        $manifest = json_decode(File::get($manifestPath), true) ?: [];

        $file = $manifest['resources/js/recorder.ts']['file'] ?? null;

        return $file ? public_path('build/'.$file) : null;
    }
}
