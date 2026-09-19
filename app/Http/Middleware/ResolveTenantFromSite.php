<?php

namespace App\Http\Middleware;

use App\Models\Site;
use App\Support\Tenancy\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the Site from the public tracking id (route segment for the
 * script, request field for the ingestion endpoints) and activates its
 * company's tenant database connection. No authentication — these are the
 * public endpoints third-party pages call.
 */
class ResolveTenantFromSite
{
    public function __construct(protected TenantManager $tenantManager) {}

    public function handle(Request $request, Closure $next): Response
    {
        $publicId = $request->route('siteId') ?? $request->input('site_id');

        $site = Site::where('public_id', $publicId)->first();

        abort_if(! $site || $site->status !== 'active', 404);

        $this->tenantManager->forSite($site);

        $request->attributes->set('site', $site);

        return $next($request);
    }
}
