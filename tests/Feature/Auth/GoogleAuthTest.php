<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
        ]);
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function fakeGoogleUser(
        string $id = 'google-123',
        string $email = 'someone@example.com',
        string $name = 'Ada Lovelace',
        array $raw = ['email_verified' => true],
    ): void {
        $user = (new SocialiteUser)->setRaw($raw)->map([
            'id' => $id,
            'name' => $name,
            'email' => $email,
        ]);

        Socialite::shouldReceive('driver->user')->andReturn($user);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_the_routes_are_hidden_when_google_is_not_configured(): void
    {
        config(['services.google.client_id' => null, 'services.google.client_secret' => null]);

        $this->get('/auth/google')->assertNotFound();
        $this->get('/auth/google/callback')->assertNotFound();
    }

    public function test_a_new_google_user_gets_an_account_and_a_company(): void
    {
        $this->fakeGoogleUser();

        $this->get('/auth/google/callback')->assertRedirect();

        $user = User::where('email', 'someone@example.com')->first();

        $this->assertNotNull($user);
        $this->assertSame('google-123', $user->google_id);
        $this->assertNotNull($user->email_verified_at, 'Google has already verified the address.');
        $this->assertNotNull($user->companies->first(), 'A Google sign-up should get a company like any other.');
        $this->assertTrue(Auth::check());
    }

    /**
     * Linking, not duplicating: the person already has an account and cannot
     * tell two of them apart.
     */
    public function test_an_existing_account_with_the_same_email_is_linked(): void
    {
        $existing = User::factory()->create(['email' => 'someone@example.com']);

        $this->fakeGoogleUser(email: 'someone@example.com');

        $this->get('/auth/google/callback')->assertRedirect();

        $this->assertSame(1, User::where('email', 'someone@example.com')->count());
        $this->assertSame('google-123', $existing->fresh()->google_id);
        $this->assertTrue(Auth::check());
    }

    /**
     * The subject id is matched before the email, so a user who changes their
     * Google address signs into the same account rather than creating a new
     * one.
     */
    public function test_a_changed_google_email_still_signs_into_the_same_account(): void
    {
        $existing = User::factory()->create(['email' => 'old@example.com']);
        $existing->forceFill(['google_id' => 'google-123'])->save();

        $this->fakeGoogleUser(id: 'google-123', email: 'new@example.com');

        $this->get('/auth/google/callback')->assertRedirect();

        $this->assertSame($existing->id, Auth::id());
        $this->assertSame(1, User::count());
    }

    /**
     * Google will issue a token for an account whose email it has not
     * verified. Trusting that would let someone claim an existing abify
     * account by registering its address with an unverified Google profile.
     */
    public function test_an_unverified_google_email_is_refused(): void
    {
        $this->fakeGoogleUser(raw: ['email_verified' => false]);

        $this->get('/auth/google/callback')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertFalse(Auth::check());
        $this->assertSame(0, User::count());
    }

    public function test_a_missing_verification_flag_is_treated_as_unverified(): void
    {
        $this->fakeGoogleUser(raw: []);

        $this->get('/auth/google/callback')->assertSessionHasErrors('email');

        $this->assertFalse(Auth::check());
    }

    public function test_the_legacy_verified_email_field_is_also_accepted(): void
    {
        $this->fakeGoogleUser(raw: ['verified_email' => true]);

        $this->get('/auth/google/callback')->assertRedirect();

        $this->assertTrue(Auth::check());
    }

    /**
     * A Google-only account must not be reachable with a guessable password.
     */
    public function test_a_google_created_account_has_no_usable_password(): void
    {
        $this->fakeGoogleUser();

        $this->get('/auth/google/callback');

        $user = User::where('email', 'someone@example.com')->first();

        foreach (['', 'password', 'google-123', 'someone@example.com'] as $guess) {
            $this->assertFalse(
                Auth::validate(['email' => $user->email, 'password' => $guess]),
                "A Google account accepted the password [{$guess}]."
            );
        }
    }

    public function test_an_authenticated_user_is_not_sent_through_the_google_flow(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/auth/google')->assertRedirect();
    }
}
