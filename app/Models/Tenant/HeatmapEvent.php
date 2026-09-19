<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property int $id
 * @property int $heatmap_session_id
 * @property int $site_id
 * @property string $url
 * @property string $url_hash
 * @property string $type
 * @property string|null $device_type
 * @property int|null $viewport_width
 * @property int|null $x_pct
 * @property int|null $y_pct
 * @property int|null $scroll_depth_pct
 */
#[Fillable(['heatmap_session_id', 'site_id', 'url', 'url_hash', 'type', 'device_type', 'viewport_width', 'x_pct', 'y_pct', 'scroll_depth_pct'])]
class HeatmapEvent extends TenantModel
{
    const UPDATED_AT = null;
}
