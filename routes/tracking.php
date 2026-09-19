<?php

use App\Http\Controllers\Api\ConversionController;
use App\Http\Controllers\Shopify\ShopifyWebhookController;
use App\Http\Controllers\Tracking\RecorderScriptController;
use App\Http\Controllers\Tracking\RecordingChunkController;
use App\Http\Controllers\Tracking\TrackingAssignController;
use App\Http\Controllers\Tracking\TrackingCollectController;
use App\Http\Controllers\Tracking\TrackingScriptController;
use App\Http\Middleware\AuthenticateSiteWriteKey;
use App\Http\Middleware\ResolveTenantFromSite;
use Illuminate\Support\Facades\Route;

// No tenant resolution: the recorder bundle is the same file for every site,
// and it is requested before any site context matters.
Route::get('/t/recorder.js', [RecorderScriptController::class, 'show'])->name('tracking.recorder');

Route::middleware(ResolveTenantFromSite::class)->group(function () {
    Route::get('/t/{siteId}.js', [TrackingScriptController::class, 'show'])->name('tracking.script');
    Route::post('/api/t/assign', [TrackingAssignController::class, 'store'])->name('tracking.assign');
    Route::post('/api/t/collect', [TrackingCollectController::class, 'store'])->name('tracking.collect');

    // Replay chunks. Separate from collect above — a single chunk is larger
    // than a whole batch of analytics events and arrives on its own cadence.
    Route::post('/api/t/rec', [RecordingChunkController::class, 'store'])->name('tracking.recording');
});

// Server-to-server ingestion. Authenticated by the site's write key rather
// than the public site id above, and throttled per site instead of per IP —
// a store's whole order volume arrives from one server address.
Route::middleware([AuthenticateSiteWriteKey::class, 'throttle:conversions'])->group(function () {
    Route::post('/api/v1/conversions', [ConversionController::class, 'store'])->name('api.conversions.store');
});

// Shopify posts here with no session and no CSRF token — the HMAC over the
// raw body is the authenticity check. Outside the throttle group too: a busy
// store's order volume is Shopify's to pace, not ours to drop.
Route::post('/shopify/webhook', ShopifyWebhookController::class)
    ->withoutMiddleware('throttle:tracking')
    ->name('shopify.webhook');
