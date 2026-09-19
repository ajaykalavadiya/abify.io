<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Chrome binary
    |--------------------------------------------------------------------------
    |
    | Page screenshots are captured by driving a local Chrome/Chromium over the
    | DevTools Protocol (see App\Support\Screenshots\PageScreenshotter). The
    | binary is looked up here so containers can point at chromium instead.
    |
    */

    'binary' => env('CHROME_BINARY', '/usr/bin/google-chrome'),

    /*
    |--------------------------------------------------------------------------
    | Capture behaviour
    |--------------------------------------------------------------------------
    |
    | 'queue' => false runs the capture inline on the request that asked for it
    | (a few seconds, with the UI showing a spinner). Set it to true once a
    | queue worker is running so the browser work happens off-request.
    |
    */

    'queue' => env('SCREENSHOT_QUEUE', false),

    'disk' => env('SCREENSHOT_DISK', 'local'),

    // Seconds to allow a single capture (launch + navigate + paint) before
    // giving up and killing the browser.
    'timeout' => (int) env('SCREENSHOT_TIMEOUT', 60),

    // Extra settle time after the load event, for fonts/animations.
    'settle_ms' => (int) env('SCREENSHOT_SETTLE_MS', 1200),

    // Screenshots are stored as JPEG: a full-page capture of a long page is
    // several megapixels and lossless PNG runs to tens of megabytes.
    'quality' => (int) env('SCREENSHOT_QUALITY', 72),

    // Hard ceiling on captured page height in CSS pixels. Chrome's compositor
    // fails outright past roughly 16k, and heatmap data that deep is noise.
    'max_height' => (int) env('SCREENSHOT_MAX_HEIGHT', 12000),

    // A capture older than this is considered stale and offered for refresh.
    'stale_after_days' => (int) env('SCREENSHOT_STALE_AFTER_DAYS', 14),

    /*
    |--------------------------------------------------------------------------
    | Devices
    |--------------------------------------------------------------------------
    |
    | Heatmaps are segmented by device because a click at "40% across the page"
    | lands somewhere completely different in a 390px column than in a 1440px
    | grid. Each device gets its own capture at its own emulated viewport, and
    | its own aggregated point set.
    |
    | 'max_width' is the upper bound of the viewport widths that classify as
    | this device — see App\Support\Analytics\DeviceType.
    |
    */

    'devices' => [
        'desktop' => [
            'label' => 'Desktop',
            'width' => 1440,
            'height' => 900,
            'mobile' => false,
            'max_width' => null,
        ],
        'tablet' => [
            'label' => 'Tablet',
            'width' => 834,
            'height' => 1112,
            'mobile' => true,
            'max_width' => 1024,
        ],
        'mobile' => [
            'label' => 'Mobile',
            'width' => 390,
            'height' => 844,
            'mobile' => true,
            'max_width' => 768,
        ],
    ],

];
