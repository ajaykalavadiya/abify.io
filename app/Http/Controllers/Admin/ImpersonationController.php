<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImpersonationController extends Controller
{
    /**
     * Log in as another user for support/debugging. Stores the original
     * super-admin's id in the session so stop() can restore it.
     */
    public function start(Request $request, User $user): RedirectResponse
    {
        $admin = $request->user();

        abort_if($user->is($admin), 422, "You're already logged in as this user.");
        abort_if($user->is_super_admin, 422, "Can't impersonate another super-admin.");

        $request->session()->put('impersonator_id', $admin->id);

        Auth::login($user);

        return to_route('dashboard');
    }

    /**
     * Restore the original super-admin session. Deliberately NOT gated by
     * EnsureSuperAdmin — while impersonating, the authenticated user is the
     * impersonated (possibly non-admin) user, not the admin.
     */
    public function stop(Request $request): RedirectResponse
    {
        $adminId = $request->session()->pull('impersonator_id');

        abort_unless($adminId, 404);

        Auth::login(User::findOrFail($adminId));

        return to_route('accounts.index');
    }
}
