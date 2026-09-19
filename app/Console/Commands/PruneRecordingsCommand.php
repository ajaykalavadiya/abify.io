<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Tenant\SessionRecording;
use App\Support\Tenancy\TenantManager;
use Illuminate\Console\Command;
use Throwable;

/**
 * Deletes replay payloads past their retention window.
 *
 * Runs across every tenant because recordings live in per-company databases.
 * Not optional housekeeping: without it storage grows without bound, and a
 * retention window is also what makes a GDPR erasure request answerable.
 */
class PruneRecordingsCommand extends Command
{
    protected $signature = 'recordings:prune
                            {--company=* : Limit the run to specific company IDs}
                            {--dry-run : Report what would be deleted without deleting it}';

    protected $description = 'Delete session recordings past their retention window';

    public function handle(TenantManager $tenants): int
    {
        $companies = Company::whereNotNull('database')
            ->when($this->option('company'), fn ($query, array $ids) => $query->whereIn('id', $ids))
            ->orderBy('id')
            ->get();

        $dryRun = (bool) $this->option('dry-run');
        $deleted = 0;
        $failures = [];

        foreach ($companies as $company) {
            try {
                $tenants->forCompany($company);

                SessionRecording::where('expires_at', '<=', now())
                    ->chunkById(100, function ($recordings) use (&$deleted, $dryRun) {
                        foreach ($recordings as $recording) {
                            if (! $dryRun) {
                                // Payload first. An orphaned row is a missing
                                // replay; an orphaned directory is a bill
                                // nobody can trace back to anything.
                                $recording->deletePayload();
                                $recording->delete();
                            }

                            $deleted++;
                        }
                    });
            } catch (Throwable $exception) {
                $failures[] = $company->database;
                $this->components->error("{$company->database}: {$exception->getMessage()}");
            }
        }

        $verb = $dryRun ? 'Would delete' : 'Deleted';
        $this->components->info("{$verb} {$deleted} recording(s) across {$companies->count()} tenant(s).");

        if ($failures) {
            $this->components->error(count($failures).' tenant(s) failed: '.implode(', ', $failures));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
