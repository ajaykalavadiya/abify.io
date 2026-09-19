<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $experiment_id
 * @property int $goal_id
 * @property int $variation_id
 * @property string $visitor_uuid
 * @property array<string, mixed>|null $meta
 * @property Carbon $converted_at
 */
#[Fillable(['experiment_id', 'goal_id', 'variation_id', 'visitor_uuid', 'meta', 'converted_at'])]
class GoalConversion extends TenantModel
{
    const CREATED_AT = null;

    const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'converted_at' => 'datetime',
        ];
    }
}
