<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\MarketingController;
use App\Http\Controllers\Shopify\ShopifyInstallController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::inertia('/', 'welcome')->name('home');
Route::get('pricing', [MarketingController::class, 'pricing'])->name('pricing');
Route::get('features', [MarketingController::class, 'features'])->name('features');
Route::get('features/{slug}', [MarketingController::class, 'featureShow'])->name('features.show');
Route::get('blog', [MarketingController::class, 'blogIndex'])->name('blog.index');
Route::get('blog/{slug}', [MarketingController::class, 'blogShow'])->name('blog.show');
Route::get('why-abify', [MarketingController::class, 'why'])->name('why');
Route::get('careers', [MarketingController::class, 'careers'])->name('careers.index');
Route::get('careers/{slug}', [MarketingController::class, 'careersShow'])->name('careers.show');
Route::get('contact', [MarketingController::class, 'contact'])->name('contact');
Route::post('contact', [MarketingController::class, 'contactStore'])
    ->middleware('throttle:5,1')
    ->name('contact.store');

// Sign in and sign up are one route: from the visitor's side "Continue with
// Google" is a single action, and asking them to know in advance whether they
// already have an account is a question they often get wrong.
Route::middleware('guest')->group(function () {
    Route::get('auth/google', [GoogleAuthController::class, 'redirect'])
        ->middleware('throttle:10,1')
        ->name('auth.google');

    Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])
        ->middleware('throttle:10,1')
        ->name('auth.google.callback');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        $user = request()->user();

        // A super-admin might *also* belong to specific companies (e.g. the
        // one they registered with) — always show the picker for them so
        // "Platform administration" is a visible choice, not an automatic
        // redirect that hides their own company memberships.
        if (! $user->is_super_admin && $user->companies->count() === 1) {
            return to_route('sites.index', $user->companies->first()->uid);
        }

        return to_route('select-account');
    })->name('dashboard');

    // Lands here when a user belongs to zero or multiple companies, or is a
    // super-admin — with exactly one company and no admin access, `dashboard`
    // above skips straight past this.
    Route::get('select-account', function () {
        $user = request()->user();

        $companies = $user->companies->map(fn ($company) => [
            'id' => $company->id,
            'name' => $company->name,
            'uid' => $company->uid,
            'role' => $company->pivot->role,
        ]);

        return Inertia::render('select-account', [
            'companies' => $companies,
            'isSuperAdmin' => $user->is_super_admin,
        ]);
    })->name('select-account');
});

Route::get('invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');
Route::post('invitations/{token}/accept', [InvitationController::class, 'accept'])->name('invitations.accept');

require __DIR__.'/settings.php';
require __DIR__.'/accounts.php';
require __DIR__.'/dashboard.php';

// Shopify returns the merchant here after they approve the scopes. Needs the
// session (the install's state nonce lives there) but no account in the URL —
// Shopify decides the redirect target, not us.
Route::get('/shopify/callback', [ShopifyInstallController::class, 'callback'])
    ->middleware(['web', 'auth'])
    ->name('shopify.callback');
