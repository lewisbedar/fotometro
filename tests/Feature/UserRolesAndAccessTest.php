<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRolesAndAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_admin_factory_default_still_reaches_admin_routes(): void
    {
        $admin = User::factory()->create();

        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($admin->isApproved());

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.photo-categories.index'))
            ->assertOk();
    }

    public function test_regular_user_is_forbidden_from_admin_only_routes(): void
    {
        $user = User::factory()->regularUser()->create();

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('admin.photo-categories.index'))
            ->assertForbidden();
    }

    public function test_moderator_reaches_dashboard_but_not_admin_only_catalog_management(): void
    {
        $moderator = User::factory()->moderator()->create();

        $this->actingAs($moderator)
            ->get(route('admin.dashboard'))
            ->assertOk();

        $this->actingAs($moderator)
            ->get(route('admin.photo-categories.index'))
            ->assertForbidden();
    }

    public function test_unapproved_account_is_logged_out_and_redirected_when_hitting_a_gated_route(): void
    {
        $pendingUser = User::factory()->regularUser()->pending()->create();

        $response = $this->actingAs($pendingUser)->get(route('admin.dashboard'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

}
