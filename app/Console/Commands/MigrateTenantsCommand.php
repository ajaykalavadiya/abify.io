<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Support\Tenancy\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * Runs database/migrations/tenant against every provisioned company.
 *
 * `php artisan migrate` only touches the central database, so a tenant schema
 * change ships as a separate step — one that has to keep going when a single
 * company's database is unreachable rather than leaving the rest unmigrated.
 */
class MigrateTenantsCommand extends Command
{
    protected $signature = 'tenant:migrate
                            {--company=* : Limit the run to specific company IDs}
                            {--pretend : Dump the SQL instead of running it}';

    protected $description = 'Run the tenant migrations against every provisioned company database';

    public function handle(TenantManager $tenants): int
    {
        $companies = Company::whereNotNull('database')
            ->when($this->option('company'), fn ($query, array $ids) => $query->whereIn('id', $ids))
            ->orderBy('id')
            ->get();

        if ($companies->isEmpty()) {
            $this->components->warn('No provisioned tenant databases found.');

            return self::SUCCESS;
        }

        $failures = [];

        foreach ($companies as $company) {
            $this->components->info("Migrating {$company->name} [{$company->database}]");

            try {
                $tenants->forCompany($company);

                Artisan::call('migrate', [
                    '--database' => 'tenant',
                    '--path' => 'database/migrations/tenant',
                    '--force' => true,
                    '--pretend' => (bool) $this->option('pretend'),
                ], $this->output);
            } catch (Throwable $exception) {
                $failures[] = $company->database;

                $this->components->error("{$company->database}: {$exception->getMessage()}");
            }
        }

        if ($failures) {
            $this->components->error(count($failures).' tenant database(s) failed: '.implode(', ', $failures));

            return self::FAILURE;
        }

        $this->components->info('Migrated '.$companies->count().' tenant database(s).');

        return self::SUCCESS;
    }
}
