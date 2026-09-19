<?php

namespace Tests\Concerns;

use App\Models\Company;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Gives a test a working tenant database.
 *
 * ProvisionTenantDatabase no-ops under the testing environment, so a factory
 * company has no tenant database and TenantManager throws the moment any
 * tracking endpoint is hit — which is why none of the ingestion path had test
 * coverage. This repoints the tenant connection at a SQLite file for the
 * duration of the test.
 *
 * A file rather than :memory: on purpose: TenantManager purges and reconnects
 * the tenant connection on every resolve, and an in-memory SQLite database is
 * discarded the instant its connection closes, so every switch would hand
 * back an empty schema.
 */
trait InteractsWithTenantDatabase
{
    protected ?string $tenantDatabasePath = null;

    protected function setUpTenantDatabase(): void
    {
        $this->tenantDatabasePath = tempnam(sys_get_temp_dir(), 'abify_tenant_').'.sqlite';
        touch($this->tenantDatabasePath);

        config([
            'database.connections.tenant.driver' => 'sqlite',
            'database.connections.tenant.database' => $this->tenantDatabasePath,
            'database.connections.tenant.prefix' => '',
            'database.connections.tenant.foreign_key_constraints' => false,
        ]);

        DB::purge('tenant');

        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]);
    }

    /**
     * A company whose tenant database is the SQLite file set up above.
     */
    protected function tenantCompany(): Company
    {
        $company = Company::factory()->create();

        $company->forceFill([
            'database' => $this->tenantDatabasePath,
            'tenant_provisioned_at' => now(),
        ])->save();

        return $company;
    }

    protected function tearDownTenantDatabase(): void
    {
        DB::purge('tenant');

        if ($this->tenantDatabasePath && file_exists($this->tenantDatabasePath)) {
            unlink($this->tenantDatabasePath);
        }

        $this->tenantDatabasePath = null;
    }
}
