<?php

namespace App\Providers;

use App\Support\Tenancy\TenantManager;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        RateLimiter::for('tracking', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));

        // Keyed on the presented write key, not the IP: every order for a
        // store arrives from the same address (its server, or Shopify's), so
        // an IP limit would throttle a busy merchant rather than an abuser.
        RateLimiter::for('conversions', fn (Request $request) => Limit::perMinute(600)
            ->by($request->bearerToken() ? sha1($request->bearerToken()) : $request->ip()));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
