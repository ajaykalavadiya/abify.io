<?php

namespace App\Policies;

use App\Models\Site;
use App\Models\User;

class SitePolicy
{
    public function view(User $user, Site $site): bool
    {
        return $user->is_super_admin || $user->companies()->where('companies.id', $site->company_id)->exists();
    }

    public function update(User $user, Site $site): bool
    {
        return $user->is_super_admin || $user->companies()->where('companies.id', $site->company_id)->exists();
    }

    public function delete(User $user, Site $site): bool
    {
        return $user->is_super_admin || $user->companies()->where('companies.id', $site->company_id)->exists();
    }
}
