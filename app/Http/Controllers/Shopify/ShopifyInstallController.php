<?php

namespace App\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Integration;
use App\Models\Site;
use App\Support\Shopify\ShopifySignature;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Shopify OAuth.
 *
 * Two legs: send the merchant to their own store to approve the scopes, then
 * exchange the returned code for an access token. The `state` nonce carried
 * between them is what stops a third party from completing an install against
 * someone else's account.
 */
class ShopifyInstallController extends Controller
{
    public function create(Company $account, Site $site): Response
    {
        $this->authorize('update', $site);

        return Inertia::render('integrations/shopify', [
            'account' => $account,
            'site' => $site,
            'configured' => (bool) config('shopify.key'),
            'integration' => Integration::where('site_id', $site->id)
                ->where('provider', Integration::SHOPIFY)
                ->first(),
        ]);
    }

    /**
     * Leg one — redirect to the merchant's store for approval.
     */
    public function store(Request $request, ShopifySignature $signatures, Company $account, Site $site): RedirectResponse
    {
        $this->authorize('update', $site);

        $shop = strtolower(trim((string) $request->input('shop')));

        // Merchants type this, and it ends up in a redirect target — so it is
        // validated as a myshopify host before being used, not after.
        if (! $signatures->isValidShopDomain($shop)) {
            return back()->withErrors(['shop' => 'Enter a valid myshopify.com domain.']);
        }

        $state = Str::random(40);

        $request->session()->put('shopify.state', $state);
        $request->session()->put('shopify.site_id', $site->id);

        return redirect()->away('https://'.$shop.'/admin/oauth/authorize?'.http_build_query([
            'client_id' => config('shopify.key'),
            'scope' => config('shopify.scopes'),
            'redirect_uri' => route('shopify.callback'),
            'state' => $state,
        ]));
    }

    /**
     * Leg two — exchange the code for a token and record the connection.
     */
    public function callback(Request $request, ShopifySignature $signatures): RedirectResponse
    {
        $shop = (string) $request->query('shop');

        // Both checks matter and neither substitutes for the other: the HMAC
        // proves Shopify sent this, the state proves it belongs to the
        // install this browser started.
        if (! $signatures->isValidShopDomain($shop) || ! $signatures->verifyOAuth($request->query())) {
            abort(403, 'Invalid Shopify callback signature.');
        }

        if (! hash_equals((string) $request->session()->pull('shopify.state'), (string) $request->query('state'))) {
            abort(403, 'Shopify install state did not match.');
        }

        // whereKey()->first() rather than find(): find()'s signature widens to
        // a Collection, and everything below treats this as a single site.
        $site = Site::whereKey($request->session()->pull('shopify.site_id'))->first();

        if (! $site) {
            abort(404);
        }

        $response = Http::asJson()->post("https://{$shop}/admin/oauth/access_token", [
            'client_id' => config('shopify.key'),
            'client_secret' => config('shopify.secret'),
            'code' => $request->query('code'),
        ]);

        if (! $response->successful() || ! $response->json('access_token')) {
            return redirect()
                ->route('integrations.shopify', [$site->company->uid])
                ->withErrors(['shop' => 'Shopify did not return an access token.']);
        }

        // updateOrCreate on (provider, external_id): reinstalling a store, or
        // moving it to a different site, must replace the connection rather
        // than leave a second one quietly receiving the same orders.
        Integration::updateOrCreate(
            ['provider' => Integration::SHOPIFY, 'external_id' => $shop],
            [
                'company_id' => $site->company_id,
                'site_id' => $site->id,
                'status' => Integration::STATUS_ACTIVE,
                'credentials' => ['access_token' => $response->json('access_token')],
                'meta' => ['scopes' => $response->json('scope')],
                'connected_at' => now(),
            ]
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Shopify store connected.')]);

        return redirect()->route('integrations.shopify', [$site->company->uid]);
    }
}
