<?php

namespace App\Http\Controllers;

use App\Http\Requests\InvitationAcceptRequest;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    /**
     * Show the public "accept invitation" page. No auth required — the
     * token itself is the credential to view this.
     */
    public function show(string $token): Response
    {
        $invitation = Invitation::where('token', $token)->firstOrFail();

        return Inertia::render('invitations/show', [
            'token' => $token,
            'invitation' => [
                'email' => $invitation->email,
                'role' => $invitation->role,
                'companyName' => $invitation->company->name,
                'expired' => $invitation->isExpired(),
                'accepted' => $invitation->isAccepted(),
            ],
            'existingAccount' => User::where('email', $invitation->email)->exists(),
        ]);
    }

    /**
     * Accept the invitation: attach (creating the user first if this email
     * has no account yet) to the inviting company, then log them in.
     */
    public function accept(InvitationAcceptRequest $request, string $token): RedirectResponse
    {
        $invitation = Invitation::where('token', $token)->firstOrFail();

        abort_if($invitation->isExpired(), 410, 'This invitation has expired.');
        abort_if($invitation->isAccepted(), 410, 'This invitation has already been accepted.');

        $user = User::where('email', $invitation->email)->first();

        if ($user) {
            // Existing account — the submitted password must match theirs;
            // it's not setting a new one. The invitation link alone isn't
            // proof of identity for an account that already has a password.
            if (! Hash::check($request->validated('password'), $user->password)) {
                return back()->withErrors([
                    'password' => 'That email already has an account — enter its existing password to accept this invitation.',
                ])->onlyInput();
            }
        } else {
            $user = User::create([
                'name' => $request->validated('name'),
                'email' => $invitation->email,
                'password' => $request->validated('password'),
                'email_verified_at' => now(),
            ]);
        }

        if (! $user->belongsToCompany($invitation->company)) {
            $invitation->company->users()->attach($user->id, ['role' => $invitation->role]);
        }

        $invitation->forceFill(['accepted_at' => now()])->save();

        Auth::login($user);

        return to_route('sites.index', $invitation->company->uid);
    }
}
