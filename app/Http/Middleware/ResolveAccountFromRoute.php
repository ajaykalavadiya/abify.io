<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\Site;
use App\Models\User;
use App\Support\Tenancy\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the company ("account") from the {account} route segment (its
 * public uid — see routes/dashboard.php), authorizes the current user
 * against it, and activates its tenant database connection for the request.
 *
 * Unlike a scheme that derives the tenant from the logged-in user's own
 * company, this lets a super-admin open any account's dashboard through the
 * same routes.
 */
class ResolveAccountFromRoute
{
    public function __construct(protected TenantManager $tenantManager) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Laravel's SubstituteBindings middleware (part of the default 'web'
        // group, running before this route-specific one) already resolves
        // {account} to a Company for any controller that type-hints it —
        // see Company::getRouteKeyName(). Fall back to a manual lookup for
        // routes/controllers that don't.
        $company = $request->route('account');

        if (! $company instanceof Company) {
            $company = Company::where('uid', $company)->firstOrFail();
        }

        $user = $request->user();

        $role = $user->roleFor($company);

        abort_unless($user->is_super_admin || $role !== null, 403);

        $this->tenantManager->forCompany($company);

        $request->attributes->set('company', $company);
        // Null for a super-admin viewing an account they don't belong to —
        // callers that need an owner-only gate (e.g. BillingController)
        // should treat null as "not an owner", not as "unrestricted".
        $request->attributes->set('companyRole', $role);
        $request->route()->setParameter('account', $company);

        $site = $this->resolveActiveSite($company, $user);

        $request->attributes->set('site', $site);

        if ($site) {
            // Bound so controllers can type-hint Site without it being a
            // route parameter (the site now comes from the user's stored
            // selection rather than the URL).
            app()->instance(Site::class, $site);
        }

        return $next($request);
    }

    /**
     * The site the user is working in for this account: their stored choice
     * when it still belongs to this company, otherwise the first site —
     * persisted so the selection sticks across accounts and sessions.
     */
    protected function resolveActiveSite(Company $company, User $user): ?Site
    {
        $site = $user->current_site_id
            ? $company->sites()->whereKey($user->current_site_id)->first()
            : null;

        if (! $site) {
            $site = $company->sites()->orderBy('name')->first();

            if ($site && $user->current_site_id !== $site->id) {
                $user->forceFill(['current_site_id' => $site->id])->save();
            }
        }

        return $site;
    }
}
