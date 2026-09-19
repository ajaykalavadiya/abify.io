<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Tenancy\CreateCompanyWithOwner;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

/**
 * Sign in (and sign up) with Google.
 *
 * Registration and login are one route on purpose: from the visitor's side
 * "Continue with Google" is a single action, and asking them to know in
 * advance whether they already have an account is a question they often get
 * wrong.
 */
class GoogleAuthController extends Controller
{
    public function redirect(): SymfonyRedirectResponse
    {
        abort_unless($this->configured(), 404);

        // Socialite returns Symfony's redirect, not Laravel's subclass.
        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        abort_unless($this->configured(), 404);

        try {
            /** @var SocialiteUser $googleUser */
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $exception) {
            // A denied consent screen lands here too, which is not an error
            // worth showing a stack trace for.
            Log::info('Google sign-in did not complete', ['reason' => $exception->getMessage()]);

            return redirect()->route('login')->withErrors([
                'email' => __('Google sign-in was cancelled or failed. Please try again.'),
            ]);
        }

        $email = $googleUser->getEmail();

        // Google can return a token for an account with no verified email on
        // it. Trusting that would let someone claim an existing abify account
        // by registering its address with an unverified Google profile.
        if (! $email || $this->emailUnverified($googleUser)) {
            return redirect()->route('login')->withErrors([
                'email' => __('Your Google account has no verified email address.'),
            ]);
        }

        $user = $this->resolveUser($googleUser, $email);

        Auth::login($user, remember: true);

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Find the user this Google identity belongs to, linking or creating one
     * as needed.
     */
    protected function resolveUser(SocialiteUser $googleUser, string $email): User
    {
        $googleId = (string) $googleUser->getId();

        // Preferred match: the stable subject id, which survives the user
        // changing their Google email address.
        $user = User::where('google_id', $googleId)->first();

        if ($user) {
            return $user;
        }

        // Existing password account with the same verified address — link the
        // two rather than creating a duplicate the person cannot tell apart.
        $user = User::where('email', $email)->first();

        if ($user) {
            $user->forceFill([
                'google_id' => $googleId,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();

            return $user;
        }

        $name = $googleUser->getName() ?: Str::before($email, '@');

        // Same path self-serve registration takes, so a Google sign-up gets a
        // company and a provisioned tenant database exactly like any other.
        [, $user] = app(CreateCompanyWithOwner::class)->handle(
            ['name' => "{$name}'s Company"],
            [
                'name' => $name,
                'email' => $email,
                // Never usable as a password: this account signs in with
                // Google, and a blank or guessable value here would be a way
                // in. A password reset sets a real one if they want both.
                'password' => Str::random(64),
            ],
        );

        $user->forceFill([
            'google_id' => $googleId,
            // Google has already verified it; asking again is friction with
            // no security value.
            'email_verified_at' => now(),
        ])->save();

        return $user;
    }

    /**
     * Google returns `verified_email` (v2) or `email_verified` (OIDC) in the
     * raw payload. Absent means we cannot confirm it, which is treated the
     * same as unverified.
     */
    protected function emailUnverified(SocialiteUser $googleUser): bool
    {
        $raw = $googleUser->getRaw();

        $verified = $raw['email_verified'] ?? $raw['verified_email'] ?? false;

        return ! filter_var($verified, FILTER_VALIDATE_BOOLEAN);
    }

    public static function configured(): bool
    {
        return (bool) config('services.google.client_id')
            && (bool) config('services.google.client_secret');
    }
}
