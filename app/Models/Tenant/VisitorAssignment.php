<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property int $id
 * @property int $experiment_id
 * @property int $variation_id
 * @property string $visitor_uuid
 */
#[Fillable(['experiment_id', 'variation_id', 'visitor_uuid'])]
class VisitorAssignment extends TenantModel
{
    const UPDATED_AT = null;
}
