<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $site_id
 * @property string $visitor_uuid
 * @property string|null $session_id
 * @property string $event_type
 * @property string|null $event_name
 * @property string $url
 * @property string|null $device_type
 * @property string|null $referrer
 * @property array<string, mixed>|null $meta
 * @property Carbon $occurred_at
 */
#[Fillable(['site_id', 'visitor_uuid', 'session_id', 'event_type', 'event_name', 'url', 'device_type', 'referrer', 'meta', 'occurred_at'])]
class AnalyticsEvent extends TenantModel
{
    const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
