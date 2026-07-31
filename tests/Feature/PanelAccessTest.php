<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsErp;
use Tests\TestCase;

/**
 * Without User implementing FilamentUser::canAccessPanel(), Filament's Authenticate
 * middleware falls back to allowing panel access only when config('app.env') is
 * 'local' — locking every user out of the admin panel in staging/production
 * regardless of role. Regression coverage for that fix.
 */
class PanelAccessTest extends TestCase
{
    use RefreshDatabase, SeedsErp;

    protected function setUp(): void
    {
        parent::setUp();
        // Simulate a non-local deployment — the exact condition that exposed the bug.
        config(['app.env' => 'production']);
    }

    public function test_active_user_with_role_can_access_panel_outside_local_env(): void
    {
        $this->seedRolesAndAccounts();
        $admin = $this->makeUser('Admin');

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertOk();
    }

    public function test_inactive_user_is_denied_panel_access(): void
    {
        $this->seedRolesAndAccounts();
        $admin = $this->makeUser('Admin');
        $admin->update(['is_active' => false]);

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertForbidden();
    }

    public function test_user_without_any_role_is_denied_panel_access(): void
    {
        $this->seedRolesAndAccounts();
        $user = User::factory()->create(['is_active' => true]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertForbidden();
    }
}
