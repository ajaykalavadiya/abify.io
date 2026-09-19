<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * One recorded visit. The replay payload lives on a disk; this row is the
 * index that says where and what is worth watching.
 *
 * @property int $id
 * @property int $site_id
 * @property string $session_id
 * @property string $visitor_uuid
 * @property string $status
 * @property string $disk
 * @property string $path_prefix
 * @property int $chunk_count
 * @property int $event_count
 * @property int $bytes
 * @property int $duration_ms
 * @property bool $has_rage_click
 * @property bool $has_error
 * @property Carbon $started_at
 * @property Carbon|null $last_chunk_at
 * @property Carbon $expires_at
 */
#[Fillable([
    'site_id', 'session_id', 'visitor_uuid', 'status', 'disk', 'path_prefix',
    'chunk_count', 'event_count', 'bytes', 'duration_ms',
    'has_rage_click', 'has_error', 'started_at', 'last_chunk_at', 'expires_at',
])]
class SessionRecording extends TenantModel
{
    /**
     * The lifecycle here is started_at / last_chunk_at, which carry meaning
     * Eloquent's created_at / updated_at would only duplicate.
     */
    public $timestamps = false;

    public const STATUS_RECORDING = 'recording';

    public const STATUS_READY = 'ready';

    /**
     * Storage path for a newly arrived chunk.
     *
     * Named by arrival time plus a random suffix rather than by the client's
     * sequence number. A visit spans page loads — the session survives
     * navigation, but the recorder in the new page starts its own counter at
     * zero, so sequence-named files from the second page silently overwrote
     * the first page's. Every chunk now gets a name nothing else can claim.
     *
     * The timestamp prefix keeps a directory listing roughly chronological,
     * which is convenient but not load-bearing: playback sorts on the events'
     * own timestamps.
     */
    public function newChunkPath(): string
    {
        return sprintf(
            '%s/%s-%s.json.gz',
            $this->path_prefix,
            now()->format('YmdHisv'),
            Str::random(6),
        );
    }

    /**
     * Every stored chunk for this recording.
     *
     * Read from the directory rather than reconstructed from chunk_count:
     * that column counts uploads, which is the billing question, not which
     * files exist.
     *
     * @return array<int, string>
     */
    public function chunkPaths(): array
    {
        $paths = Storage::disk($this->disk)->files($this->path_prefix);

        sort($paths);

        return $paths;
    }

    /**
     * Remove the payload from disk. The row is deleted separately — an
     * orphaned row is a missing replay, but an orphaned directory is a bill.
     */
    public function deletePayload(): void
    {
        Storage::disk($this->disk)->deleteDirectory($this->path_prefix);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'has_rage_click' => 'boolean',
            'has_error' => 'boolean',
            'started_at' => 'datetime',
            'last_chunk_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
