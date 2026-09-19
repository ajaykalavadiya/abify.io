<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class BillingController extends Controller
{
    public function show(Company $account): Response
    {
        $this->authorizeBillingAccess($account);

        return Inertia::render('billing/show', [
            'account' => $account,
            'planTier' => $account->planTier(),
            'planLimits' => $account->planLimits(),
            'usage' => [
                'sites' => $account->sites()->count(),
            ],
            'subscribed' => $account->subscribed('default'),
            'onTrial' => $account->onGenericTrial(),
            'trialEndsAt' => $account->trial_ends_at,
            'pricesConfigured' => (bool) (config('billing.stripe_price_monthly') && config('billing.stripe_price_yearly')),
        ]);
    }

    public function checkout(Company $account, string $plan): SymfonyResponse
    {
        $this->authorizeBillingAccess($account);

        $priceId = match ($plan) {
            'monthly' => config('billing.stripe_price_monthly'),
            'yearly' => config('billing.stripe_price_yearly'),
            default => abort(404),
        };

        abort_if(! $priceId, 422, 'Stripe prices are not configured yet — set STRIPE_PRICE_BASIC_MONTHLY / STRIPE_PRICE_BASIC_YEARLY in .env.');

        return $account->newSubscription('default', $priceId)->checkout([
            'success_url' => route('billing.show', $account->uid).'?checkout=success',
            'cancel_url' => route('billing.show', $account->uid).'?checkout=cancel',
        ]);
    }

    public function portal(Company $account): RedirectResponse
    {
        $this->authorizeBillingAccess($account);

        return $account->redirectToBillingPortal(route('billing.show', $account->uid));
    }

    protected function authorizeBillingAccess(Company $account): void
    {
        $user = request()->user();

        abort_unless($user->is_super_admin || $user->isOwnerOf($account), 403);
    }
}
