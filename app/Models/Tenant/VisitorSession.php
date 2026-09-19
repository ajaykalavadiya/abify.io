<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Support\Carbon;

/**
 * One visit by one visitor — see the create migration for why this is
 * separate from HeatmapSession.
 *
 * @property int $id
 * @property int $site_id
 * @property string $session_id
 * @property string $visitor_uuid
 * @property string $entry_url
 * @property string|null $exit_url
 * @property string|null $referrer
 * @property string|null $device_type
 * @property int|null $viewport_width
 * @property int $pageview_count
 * @property int $event_count
 * @property Carbon $started_at
 * @property Carbon $last_seen_at
 */
#[Fillable([
    'site_id', 'session_id', 'visitor_uuid', 'entry_url', 'exit_url', 'referrer', 'ga_client_id',
    'device_type', 'viewport_width', 'pageview_count', 'event_count',
    'started_at', 'last_seen_at',
])]
class VisitorSession extends TenantModel
{
    const CREATED_AT = null;

    const UPDATED_AT = null;

    /**
     * How long the visit lasted, in seconds.
     *
     * Derived rather than stored: a browser that is simply closed never sends
     * an "ended" signal, so the last batch received is the only honest end.
     */
    public function durationSeconds(): int
    {
        return (int) max(0, $this->last_seen_at->diffInSeconds($this->started_at, absolute: true));
    }

    /**
     * A visit that never went past its entry page.
     */
    public function isBounce(): bool
    {
        return $this->pageview_count <= 1;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }
}
