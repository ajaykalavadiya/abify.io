<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Tenancy\CreateCompanyWithInvitedOwner;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AccountStoreRequest;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    /**
     * List every company ("account") with its site count and active-test
     * count. Pure central-DB query — no tenant connection switching needed,
     * since experiments live centrally.
     */
    public function index(): Response
    {
        $accounts = Company::query()
            ->withCount('sites')
            ->withCount(['experiments as active_tests_count' => function ($query) {
                $query->where('status', 'running');
            }])
            ->orderBy('name')
            ->get();

        return Inertia::render('accounts/index', [
            'accounts' => $accounts,
        ]);
    }

    /**
     * Super-admin account detail: company info plus its users and pending
     * invitations, in one place (rather than clicking into the site/user
     * pages meant for the company's own members).
     */
    public function show(Company $account): Response
    {
        $account->loadCount('sites');
        $account->loadCount(['experiments as active_tests_count' => function ($query) {
            $query->where('status', 'running');
        }]);

        return Inertia::render('accounts/show', [
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
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('accounts/create');
    }

    public function store(AccountStoreRequest $request, CreateCompanyWithInvitedOwner $action): RedirectResponse
    {
        $action->handle(
            ['name' => $request->validated('name')],
            $request->validated('owner_email'),
            $request->user()->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Account created — an invitation was sent to the owner.')]);

        return to_route('accounts.index');
    }
}
