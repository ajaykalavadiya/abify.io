<?php

namespace App\Support\Tenancy;

use App\Models\Company;
use App\Models\Site;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TenantManager
{
    protected ?Company $current = null;

    /**
     * Point the 'tenant' database connection at the given company's physical
     * database and reconnect it. Safe to call multiple times per request
     * (e.g. once per resolved tenant) — always purges the stale connection.
     */
    public function forCompany(Company $company): void
    {
        if (! $company->database) {
            throw new RuntimeException("Company [{$company->id}] has no provisioned tenant database.");
        }

        config(['database.connections.tenant.database' => $company->database]);

        DB::purge('tenant');
        DB::reconnect('tenant');

        $this->current = $company;
    }

    public function forSite(Site $site): void
    {
        $this->forCompany($site->company);
    }

    public function current(): ?Company
    {
        return $this->current;
    }
}
