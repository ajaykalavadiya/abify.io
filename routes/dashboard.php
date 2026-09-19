<?php

use App\Http\Controllers\AccountUserController;
use App\Http\Controllers\ActiveSiteController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\ExperimentController;
use App\Http\Controllers\ExperimentGoalController;
use App\Http\Controllers\ExperimentVariationController;
use App\Http\Controllers\HeatmapController;
use App\Http\Controllers\PageScreenshotController;
use App\Http\Controllers\SessionRecordingController;
use App\Http\Controllers\Shopify\ShopifyInstallController;
use App\Http\Controllers\SiteController;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\RequireActiveSite;
use App\Http\Middleware\ResolveAccountFromRoute;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])
    ->prefix('a/{account}')
    ->group(function () {
        Route::middleware(ResolveAccountFromRoute::class)->group(function () {
            Route::get('users', [AccountUserController::class, 'index'])->name('account.users.index');
            Route::post('users', [AccountUserController::class, 'store'])->name('account.users.store');
            Route::patch('users/{user}/super-admin', [AccountUserController::class, 'toggleSuperAdmin'])
                ->middleware(EnsureSuperAdmin::class)
                ->name('account.users.toggle-super-admin');

            Route::get('billing', [BillingController::class, 'show'])->name('billing.show');
            Route::get('billing/checkout/{plan}', [BillingController::class, 'checkout'])->name('billing.checkout');
            Route::get('billing/portal', [BillingController::class, 'portal'])->name('billing.portal');

            Route::get('sites', [SiteController::class, 'index'])->name('sites.index');
            Route::get('sites/create', [SiteController::class, 'create'])->name('sites.create');
            Route::post('sites', [SiteController::class, 'store'])->name('sites.store');
            Route::put('active-site', [ActiveSiteController::class, 'update'])->name('active-site.update');

            // Everything below works on the user's active site (stored on
            // users.current_site_id, resolved by ResolveAccountFromRoute) —
            // no {site} segment in the URL.
            Route::middleware(RequireActiveSite::class)->group(function () {
                Route::get('overview', [SiteController::class, 'show'])->name('site.overview');
                Route::put('recording', [SiteController::class, 'updateRecording'])->name('site.recording.update');
                Route::put('integrations/ga4', [SiteController::class, 'updateGa4'])->name('site.ga4.update');

                Route::get('integrations/shopify', [ShopifyInstallController::class, 'create'])->name('integrations.shopify');
                Route::post('integrations/shopify', [ShopifyInstallController::class, 'store'])->name('integrations.shopify.store');

                Route::get('recordings', [SessionRecordingController::class, 'index'])->name('recordings.index');
                Route::get('recordings/{recording}', [SessionRecordingController::class, 'show'])
                    ->whereNumber('recording')->name('recordings.show');
                // The replay payload itself, fetched by the player.
                Route::get('recordings/{recording}/events', [SessionRecordingController::class, 'events'])
                    ->whereNumber('recording')->name('recordings.events');

                Route::get('heatmap', [HeatmapController::class, 'show'])->name('heatmap.show');
                // The screenshot a heatmap is drawn on. Served through the app
                // rather than a public disk — it's a picture of the customer's
                // own page, which may never have been public.
                Route::post('heatmap/screenshots', [PageScreenshotController::class, 'store'])
                    ->name('heatmap.screenshot.store');
                Route::get('heatmap/screenshots/{screenshot}', [PageScreenshotController::class, 'show'])
                    ->whereNumber('screenshot')
                    ->name('heatmap.screenshot.show');

                Route::get('tests', [ExperimentController::class, 'index'])->name('experiments.index');
                Route::get('tests/ab', [ExperimentController::class, 'index'])
                    ->defaults('type', 'ab')
                    ->name('experiments.ab');
                Route::get('tests/split-url', [ExperimentController::class, 'index'])
                    ->defaults('type', 'split_url')
                    ->name('experiments.split-url');
                Route::get('tests/mvt', [ExperimentController::class, 'index'])
                    ->defaults('type', 'mvt')
                    ->name('experiments.mvt');
                // A/B and split URL tests are set up differently, so each
                // type gets its own create route and form; the type comes
                // from the route rather than a field on the form.
                Route::get('tests/ab/create', [ExperimentController::class, 'create'])
                    ->defaults('type', 'ab')
                    ->name('experiments.ab.create');
                Route::post('tests/ab', [ExperimentController::class, 'store'])
                    ->defaults('type', 'ab')
                    ->name('experiments.ab.store');

                Route::get('tests/split-url/create', [ExperimentController::class, 'create'])
                    ->defaults('type', 'split_url')
                    ->name('experiments.split-url.create');
                Route::post('tests/split-url', [ExperimentController::class, 'store'])
                    ->defaults('type', 'split_url')
                    ->name('experiments.split-url.store');

                Route::get('tests/mvt/create', [ExperimentController::class, 'create'])
                    ->defaults('type', 'mvt')
                    ->name('experiments.mvt.create');
                Route::post('tests/mvt', [ExperimentController::class, 'store'])
                    ->defaults('type', 'mvt')
                    ->name('experiments.mvt.store');

                // Numeric constraint keeps the literal segments above from
                // being swallowed by this one.
                Route::get('tests/{test}', [ExperimentController::class, 'show'])
                    ->whereNumber('test')
                    ->name('experiments.show');
                Route::get('tests/{test}/edit', [ExperimentController::class, 'edit'])->name('experiments.edit');
                Route::patch('tests/{test}', [ExperimentController::class, 'update'])
                    ->whereNumber('test')
                    ->name('experiments.update');
                Route::patch('tests/{test}/start', [ExperimentController::class, 'start'])->name('experiments.start');
                Route::patch('tests/{test}/pause', [ExperimentController::class, 'pause'])->name('experiments.pause');
                Route::get('tests/{test}/analytics', [AnalyticsController::class, 'show'])->name('analytics.show');

                Route::post('tests/{test}/variations', [ExperimentVariationController::class, 'store'])->name('variations.store');
                Route::patch('tests/{test}/variations/{variation}', [ExperimentVariationController::class, 'update'])->name('variations.update');
                Route::delete('tests/{test}/variations/{variation}', [ExperimentVariationController::class, 'destroy'])->name('variations.destroy');

                Route::post('tests/{test}/goals', [ExperimentGoalController::class, 'store'])->name('goals.store');
                Route::patch('tests/{test}/goals/{goal}', [ExperimentGoalController::class, 'update'])->name('goals.update');
                Route::delete('tests/{test}/goals/{goal}', [ExperimentGoalController::class, 'destroy'])->name('goals.destroy');
            });
        });
    });
