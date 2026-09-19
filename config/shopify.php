<?php

return [

    /*
    |--------------------------------------------------------------------------
    | App credentials
    |--------------------------------------------------------------------------
    |
    | From the app's setup in the Shopify Partner dashboard. The secret is
    | also what signs every webhook Shopify sends, so it is the only thing
    | standing between a merchant's real orders and a forged one.
    |
    */

    'key' => env('SHOPIFY_API_KEY'),
    'secret' => env('SHOPIFY_API_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    |
    | Kept to the minimum that makes revenue attribution work. read_orders is
    | the whole point; read_products is needed to describe what was bought.
    | Nothing here can write to the merchant's store.
    |
    */

    'scopes' => env('SHOPIFY_SCOPES', 'read_orders,read_products'),

    /*
    |--------------------------------------------------------------------------
    | API version
    |--------------------------------------------------------------------------
    |
    | Shopify dates its API quarterly and supports each version for a year.
    | Pinned rather than tracking "latest" so a Shopify release cannot change
    | the shape of an order payload underneath a running experiment.
    |
    */

    'api_version' => env('SHOPIFY_API_VERSION', '2025-07'),

    /*
    |--------------------------------------------------------------------------
    | Order attribution
    |--------------------------------------------------------------------------
    |
    | The cart attribute the storefront writes the visitor id into, which
    | Shopify carries through checkout and onto the order. This is the only
    | link between a browser that saw a variation and an order webhook that
    | arrives hours later on a different connection.
    |
    */

    'visitor_attribute' => '_abify_vid',

    /*
    |--------------------------------------------------------------------------
    | Goal key
    |--------------------------------------------------------------------------
    |
    | Orders are recorded against goals with this key. A merchant creates a
    | goal keyed 'purchase' on any experiment they want measured by revenue.
    |
    */

    'purchase_goal_key' => env('SHOPIFY_PURCHASE_GOAL_KEY', 'purchase'),

];
