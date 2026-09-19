<?php

namespace App\Http\Middleware;

use App\Models\Site;
use App\Support\Tenancy\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates a server-to-server ingestion request by its site write key
 * and activates that site's tenant database connection.
 *
 * The public tracking endpoints identify a site by `public_id`, which is
 * published in the snippet on every page of the customer's site. That is
 * acceptable for browser beacons — they are trivially forgeable either way —
 * but not for the order webhooks that carry revenue, where a forged request
 * silently changes which variation a merchant ships.
 */
class AuthenticateSiteWriteKey
{
    public function __construct(protected TenantManager $tenantManager) {}

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->bearerToken();

        $site = $key ? Site::findByWriteKey($key) : null;

        if (! $site || $site->status !== 'active') {
            // Deliberately identical for a missing, malformed, unknown or
            // suspended key — a caller probing keys learns nothing about
            // which part was wrong.
            return response()->json([
                'message' => 'Invalid or missing site write key.',
            ], 401);
        }

        $this->tenantManager->forSite($site);

        $request->attributes->set('site', $site);

        return $next($request);
    }
}
