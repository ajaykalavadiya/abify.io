<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the routes that operate on the user's active site (tests, heatmap,
 * overview). Runs after ResolveAccountFromRoute, which puts the resolved site
 * on the request; an account with no sites yet is sent to create one.
 */
class RequireActiveSite
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->attributes->get('site')) {
            return $next($request);
        }

        $company = $request->attributes->get('company');

        abort_unless($company instanceof Company, 404);

        return redirect()
            ->route('sites.create', $company->uid)
            ->with('toast', ['type' => 'info', 'message' => __('Create a site to get started.')]);
    }
}
