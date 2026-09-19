<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Stripe Price IDs
    |--------------------------------------------------------------------------
    |
    | Create one Stripe Product ("Basic") with two recurring Prices — $10/mo
    | and $100/yr — in the Stripe Dashboard (or via `stripe products create` /
    | `stripe prices create` with the CLI), then paste the two Price IDs
    | (price_...) here via .env. Both map to the same plan tier below; only
    | the billing interval differs.
    |
    */

    'stripe_price_monthly' => env('STRIPE_PRICE_BASIC_MONTHLY'),
    'stripe_price_yearly' => env('STRIPE_PRICE_BASIC_YEARLY'),

    // Growth: the tier that includes behavioural data at volume.
    'stripe_price_growth_monthly' => env('STRIPE_PRICE_GROWTH_MONTHLY'),
    'stripe_price_growth_yearly' => env('STRIPE_PRICE_GROWTH_YEARLY'),

    /*
    |--------------------------------------------------------------------------
    | Advertised prices
    |--------------------------------------------------------------------------
    |
    | Shown on the pricing page. Stripe holds the amounts that are actually
    | charged — these exist so the marketing page and the plan table cannot
    | drift apart, and they must be kept in step with the Stripe prices by
    | hand when either changes.
    |
    */

    'prices' => [
        'basic' => ['monthly' => 10, 'yearly' => 100],
        'growth' => ['monthly' => 50, 'yearly' => 500],
    ],

    /*
    |--------------------------------------------------------------------------
    | Trial length
    |--------------------------------------------------------------------------
    */

    'trial_days' => 14,

    /*
    |--------------------------------------------------------------------------
    | Users per company
    |--------------------------------------------------------------------------
    |
    | Flat cap for now (accepted + pending invitations count toward it) —
    | not yet tiered by plan. Easy to move under 'plans' per-tier later.
    |
    */

    'max_users_per_company' => env('MAX_USERS_PER_COMPANY', 2),

    /*
    |--------------------------------------------------------------------------
    | Plan limits
    |--------------------------------------------------------------------------
    |
    | Keyed by App\Models\Company::planTier(). Only one paid tier exists today
    | ("basic", monthly or yearly billing) — more tiers can be added here
    | later without touching the enforcement code in SiteController /
    | ExperimentController, which just reads $company->planLimits().
    |
    */

    'plans' => [
        'trial' => [
            'label' => 'Trial',
            'sites' => 1,
            'tests_per_site' => 1,
            'heatmaps_per_month' => 1000,
            'recordings_per_day' => 10,
        ],
        'basic' => [
            'label' => 'Basic',
            'sites' => 5,
            'tests_per_site' => 50,
            // Heatmaps are captured but only lightly: enough to read a page,
            // not enough to run them across a whole store.
            'heatmaps_per_month' => 2000,
            'recordings_per_day' => 0,
        ],
        'growth' => [
            'label' => 'Growth',
            'sites' => 15,
            'tests_per_site' => 200,
            // The headline number for this tier. Counted as heatmap sessions
            // — one visit to one page — because that is the unit that costs
            // storage, not the number of maps drawn from them.
            'heatmaps_per_month' => 10000,
            'recordings_per_day' => 100,
        ],
        'expired' => [
            'label' => 'Trial expired',
            'sites' => 0,
            'tests_per_site' => 0,
            'heatmaps_per_month' => 0,
            'recordings_per_day' => 0,
        ],
    ],

];
