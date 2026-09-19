<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * List every user on the platform, across every company, for
     * super-admin support/debugging use.
     */
    public function index(): Response
    {
        $users = User::query()
            ->with('companies:id,name,uid')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_super_admin' => $user->is_super_admin,
                'companies' => $user->companies->map(fn ($company) => [
                    'id' => $company->id,
                    'name' => $company->name,
                    'uid' => $company->uid,
                    'role' => $company->pivot->role,
                ]),
            ]);

        return Inertia::render('admin/users/index', [
            'users' => $users,
        ]);
    }
}
