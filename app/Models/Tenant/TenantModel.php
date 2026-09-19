<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

/**
 * Base class for all tenant-scoped models. These tables live in a per-company
 * physical database, never the central one — see App\Support\Tenancy\TenantManager.
 */
abstract class TenantModel extends Model
{
    protected $connection = 'tenant';
}
