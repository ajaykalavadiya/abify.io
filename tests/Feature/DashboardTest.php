<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_are_redirected_to_their_account_sites()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('sites.index', $user->companies->first()->uid));
    }

    public function test_super_admins_are_sent_to_the_account_picker_not_straight_to_accounts()
    {
        // A super-admin can also belong to their own companies, so
        // /dashboard sends them to the picker (which offers "Platform
        // administration" as one option) rather than skipping straight to
        // /accounts and hiding their company memberships.
        $user = User::factory()->create();
        $user->forceFill(['is_super_admin' => true])->save();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('select-account'));
    }

    public function test_select_account_offers_platform_administration_to_super_admins()
    {
        $user = User::factory()->create();
        $user->forceFill(['is_super_admin' => true])->save();
        $this->actingAs($user);

        $response = $this->get(route('select-account'));
        $response->assertInertia(fn ($page) => $page->where('isSuperAdmin', true));
    }
}
