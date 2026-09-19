<?php

namespace App\Console\Commands;

use App\Actions\Tenancy\ProvisionTenantDatabase;
use App\Models\Company;
use Illuminate\Console\Command;

class ProvisionTenantCommand extends Command
{
    protected $signature = 'tenant:provision {company : The company ID}';

    protected $description = 'Provision (or re-provision) a company\'s tenant database';

    public function handle(ProvisionTenantDatabase $action): int
    {
        $company = Company::findOrFail($this->argument('company'));

        $action->handle($company);

        $this->info("Provisioned tenant database [{$company->database}] for company [{$company->id}].");

        return self::SUCCESS;
    }
}
