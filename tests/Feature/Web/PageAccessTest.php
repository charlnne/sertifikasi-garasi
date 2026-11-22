<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_pages_guest_return_ok(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Login');
        $this->get(route('register'))->assertOk()->assertSee('Register');
    }

    public function test_all_pages_admin_return_ok(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        $this->get(route('dashboard'))->assertOk()->assertSee('Renter Verification');
        $this->get(route('admin.renters.index'))->assertOk();
        $this->get(route('admin.garages.index'))->assertOk();
        $this->get(route('admin.garages.create'))->assertOk();
        $this->get(route('admin.payments.index'))->assertOk();
        $this->get(route('admin.extensions.index'))->assertOk();
        $this->get(route('admin.reports.index'))->assertOk();
    }

    public function test_dashboard_renter_hides_admin_menu(): void
    {
        $renter = User::factory()->create();

        $response = $this->actingAs($renter)->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('Renter Verification');
    }

    public function test_unknown_page_returns_custom404(): void
    {
        $response = $this->get('/page-that-does-not-exist');

        $response->assertNotFound();
        $response->assertSee('Page Not Found');
    }
}
