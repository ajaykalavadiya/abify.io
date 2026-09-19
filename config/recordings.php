<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | Replay chunks are written whole to a disk and never into the database —
    | a single recorded visit runs to hundreds of kilobytes, which is an
    | object-store payload, not a row.
    |
    | 'local' while the feature is being built; switch to 's3' (already
    | configured in config/filesystems.php) by setting RECORDING_DISK, with no
    | code change — only the `disk` recorded against each recording differs,
    | so existing recordings keep resolving from wherever they were written.
    |
    */

    'disk' => env('RECORDING_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Daily capture quota
    |--------------------------------------------------------------------------
    |
    | The per-site ceiling on how many visits are recorded per day. Recording
    | every session is neither affordable nor useful, and this is also the
    | figure billing is calculated from — so the count is kept in a durable
    | table rather than a cache that could evaporate mid-month.
    |
    | A site may override this in its own settings; this is the default for a
    | site that has never been configured.
    |
    */

    'daily_limit' => (int) env('RECORDING_DAILY_LIMIT', 100),

    /*
    |--------------------------------------------------------------------------
    | Client sampling
    |--------------------------------------------------------------------------
    |
    | The quota above is enforced on the server, but it would be wasteful to
    | have every visitor download the recorder and start capturing only to be
    | turned away. This is the fraction of visits (0-100) that even attempt to
    | record, thinning the traffic before it reaches the quota check.
    |
    | Left at 100 the quota alone decides, which means the day's recordings
    | are all captured from whoever happens to arrive first each morning. A
    | lower rate spreads them across the day instead.
    |
    */

    'sample_rate' => (int) env('RECORDING_SAMPLE_RATE', 100),

    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    */

    // A visit longer than this stops recording. Guards against a tab left
    // open overnight writing chunks until the disk fills.
    'max_duration_minutes' => (int) env('RECORDING_MAX_DURATION_MINUTES', 30),

    // Rejected above this, per chunk, before anything is written.
    'max_chunk_bytes' => (int) env('RECORDING_MAX_CHUNK_BYTES', 2 * 1024 * 1024),

    // Recordings are deleted once they pass this age. Storage would otherwise
    // grow without bound, and a retention window is also what makes a GDPR
    // erasure request answerable.
    'retention_days' => (int) env('RECORDING_RETENTION_DAYS', 30),

];
