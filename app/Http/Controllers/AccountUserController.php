<?php

namespace App\Http\Controllers;

use App\Http\Requests\AccountUserStoreRequest;
use App\Models\Company;
use App\Models\User;
use App\Notifications\CompanyInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class AccountUserController extends Controller
{
    /**
     * List the users belonging to the resolved account, plus any pending
     * invitations.
     */
    public function index(Company $account): Response
    {
        return Inertia::render('account-users/index', [
            'account' => $account,
            'users' => $account->users()->orderBy('name')->get()->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->pivot->role,
                'is_super_admin' => $user->is_super_admin,
            ]),
            'invitations' => $account->invitations()
                ->whereNull('accepted_at')
                ->where('expires_at', '>', now())
                ->orderByDesc('id')
                ->get(),
            'maxUsers' => config('billing.max_users_per_company'),
        ]);
    }

    /**
     * Invite a user to the resolved account by email — they set their own
     * password by accepting the invitation link, rather than an admin
     * choosing it for them.
     */
    public function store(AccountUserStoreRequest $request, Company $account): RedirectResponse
    {
        $maxUsers = config('billing.max_users_per_company');
        $currentCount = $account->users()->count()
            + $account->invitations()->whereNull('accepted_at')->where('expires_at', '>', now())->count();

        if ($currentCount >= $maxUsers) {
            return back()->withErrors([
                'email' => "This account allows up to {$maxUsers} user(s) (including pending invitations).",
            ]);
        }

        $email = $request->validated('email');

        if ($account->users()->where('email', $email)->exists()) {
            return back()->withErrors(['email' => 'This user already belongs to the account.']);
        }

        if ($account->invitations()->whereNull('accepted_at')->where('email', $email)->where('expires_at', '>', now())->exists()) {
            return back()->withErrors(['email' => 'An invitation is already pending for this email.']);
        }

        $invitation = $account->invitations()->create([
            'email' => $email,
            'role' => $request->validated('role'),
            'invited_by' => $request->user()->id,
        ]);

        Notification::route('mail', $email)->notify(new CompanyInvitation($invitation));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation sent.')]);

        return to_route('account.users.index', $account->uid);
    }

    /**
     * Grant/revoke super-admin. Route-level middleware already restricts
     * this to super-admins (see routes/dashboard.php) — is_super_admin is
     * deliberately excluded from User's mass-assignable attributes, so it's
     * set here via forceFill rather than a generic update() call.
     */
    public function toggleSuperAdmin(Company $account, User $user): RedirectResponse
    {
        abort_unless($user->belongsToCompany($account), 404);

        $user->forceFill(['is_super_admin' => ! $user->is_super_admin])->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $user->is_super_admin ? __('Granted super-admin.') : __('Revoked super-admin.'),
        ]);

        return back();
    }
}
