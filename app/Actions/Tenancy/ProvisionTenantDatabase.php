<?php

namespace App\Actions\Tenancy;

use App\Models\Company;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class ProvisionTenantDatabase
{
    /**
     * Create the company's physical tenant database and run the tenant
     * migrations against it.
     */
    public function handle(Company $company): void
    {
        // Safe no-op under the `testing` environment (see phpunit.xml), so
        // registration and factories keep working without touching a real
        // MySQL server — company->database simply stays null. Deliberately
        // checked via app()->environment() rather than the tenant
        // connection's driver: that driver is hardcoded to 'mysql' in
        // config/database.php regardless of environment, so it can't be used
        // as the signal here.
        if (app()->environment('testing')) {
            return;
        }

        $database = "tenant_{$company->id}";

        // Issued while database.connections.tenant.database is still unset,
        // so this connects without a schema selected but with CREATE privileges.
        DB::connection('tenant')->statement(
            "CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        );

        config(['database.connections.tenant.database' => $database]);
        DB::purge('tenant');
        DB::reconnect('tenant');

        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]);

        $company->forceFill([
            'database' => $database,
            'tenant_provisioned_at' => now(),
        ])->save();
    }
}
