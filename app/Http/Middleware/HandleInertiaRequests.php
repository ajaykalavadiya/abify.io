<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Models\Company;
use App\Models\Site;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            // So the auth screens can hide "Continue with Google" rather than
            // offering a button that leads to a 404.
            'googleAuthEnabled' => GoogleAuthController::configured(),
            'auth' => [
                'user' => $request->user(),
            ],
            // All resolved lazily: this middleware's share() runs before the
            // route-specific ResolveAccountFromRoute has set these attributes
            // (and before the tenant connection is active), so reading them
            // eagerly here always yields null.
            'company' => fn () => $request->attributes->get('company'),
            'companyRole' => fn () => $request->attributes->get('companyRole'),
            'sites' => fn () => $this->sitesFor($request),
            'currentSite' => fn () => $this->currentSite($request),
            // One-off success markers set with ->with('status', ...), e.g. the
            // marketing contact form telling the page to toast.
            'status' => fn () => $request->session()->get('status'),
            'impersonating' => $request->session()->has('impersonator_id'),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * Every site in the resolved account, for the sidebar's site switcher.
     *
     * @return array<int, array<string, mixed>>|null
     */
    protected function sitesFor(Request $request): ?array
    {
        $company = $request->attributes->get('company');

        if (! $company instanceof Company) {
            return null;
        }

        return $company->sites()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Site $site): array => ['id' => $site->id, 'name' => $site->name])
            ->all();
    }

    /**
     * The user's active site for this account, resolved by
     * ResolveAccountFromRoute from users.current_site_id.
     *
     * @return array<string, mixed>|null
     */
    protected function currentSite(Request $request): ?array
    {
        $site = $request->attributes->get('site');

        return $site instanceof Site ? ['id' => $site->id, 'name' => $site->name] : null;
    }
}
