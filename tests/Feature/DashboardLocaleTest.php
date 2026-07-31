<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsErp;
use Tests\TestCase;

/**
 * The ERP dashboard (docs feedback: was a mix of English labels and Indonesian
 * labels) now renders fully in whichever locale is set — default 'en', switchable
 * via GET /locale/{en,id} which persists the choice in session.
 */
class DashboardLocaleTest extends TestCase
{
    use RefreshDatabase, SeedsErp;

    public function test_dashboard_renders_in_english_by_default(): void
    {
        $this->seedRolesAndAccounts();
        $admin = $this->makeUser('Admin');

        $response = $this->actingAs($admin)->get('/dashboard/erp-dashboard');

        $response->assertOk();
        $response->assertSee('Cash Balance');
        $response->assertSee('Opening Stock');
        $response->assertDontSee('Saldo Kas');
    }

    public function test_locale_switch_persists_in_session_and_renders_indonesian(): void
    {
        $this->seedRolesAndAccounts();
        $admin = $this->makeUser('Admin');
        $this->actingAs($admin);

        $this->get('/locale/id')->assertRedirect();

        $response = $this->get('/dashboard/erp-dashboard');

        $response->assertOk();
        $response->assertSee('Saldo Kas');
        $response->assertSee('Total Stock Awal');
        $response->assertDontSee('Cash Balance');
    }

    public function test_locale_switch_rejects_unsupported_locale(): void
    {
        $this->seedRolesAndAccounts();
        $admin = $this->makeUser('Admin');
        $this->actingAs($admin);

        $this->get('/locale/fr')->assertNotFound();
    }
}
