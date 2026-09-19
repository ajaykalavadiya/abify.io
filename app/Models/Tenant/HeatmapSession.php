<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $site_id
 * @property string $session_id
 * @property string $visitor_uuid
 * @property string $url
 * @property string $url_hash
 * @property int $viewport_width
 * @property int $viewport_height
 * @property int|null $doc_width
 * @property int|null $doc_height
 * @property string|null $device_type
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 */
#[Fillable(['site_id', 'session_id', 'visitor_uuid', 'url', 'url_hash', 'viewport_width', 'viewport_height', 'doc_width', 'doc_height', 'device_type', 'started_at', 'ended_at'])]
class HeatmapSession extends TenantModel
{
    const CREATED_AT = null;

    const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }
}
