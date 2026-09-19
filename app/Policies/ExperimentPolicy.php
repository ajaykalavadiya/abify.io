<?php

namespace App\Policies;

use App\Models\Experiment;
use App\Models\User;

class ExperimentPolicy
{
    public function view(User $user, Experiment $experiment): bool
    {
        return $user->is_super_admin || $user->companies()->where('companies.id', $experiment->company_id)->exists();
    }

    public function update(User $user, Experiment $experiment): bool
    {
        return $user->is_super_admin || $user->companies()->where('companies.id', $experiment->company_id)->exists();
    }

    public function delete(User $user, Experiment $experiment): bool
    {
        return $user->is_super_admin || $user->companies()->where('companies.id', $experiment->company_id)->exists();
    }
}
