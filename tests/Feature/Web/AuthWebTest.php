<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_guest_redirects_to_login(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('login'));
    }

    public function test_login_valid_credentials_redirects_to_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_wrong_password_returns_back_with_error(): void
    {
        $user = User::factory()->create();

        $response = $this->from(route('login'))->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_pending_renter_succeeds_and_sees_waiting_message(): void
    {
        $renter = User::factory()->pending()->create();

        $response = $this->post(route('login.store'), [
            'email' => $renter->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($renter);
        $this->get(route('dashboard'))->assertSee('Your account is awaiting admin verification.');
    }

    public function test_login_rejected_renter_fails_with_rejected_message(): void
    {
        $renter = User::factory()->rejected()->create();

        $response = $this->from(route('login'))->post(route('login.store'), [
            'email' => $renter->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['email' => 'Your registration was rejected.']);
        $this->assertGuest();
    }

    public function test_login_rejected_renter_wrong_password_shows_generic_error(): void
    {
        $renter = User::factory()->rejected()->create();

        $response = $this->post(route('login.store'), [
            'email' => $renter->email,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors(['email' => 'Incorrect email or password.']);
    }

    public function test_login_too_many_attempts_returns429(): void
    {
        $payload = ['email' => 'nobody@example.com', 'password' => 'wrong'];

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.store'), $payload);
        }
        $response = $this->post(route('login.store'), $payload);

        $response->assertStatus(429);
    }

    public function test_logout_authenticated_user_becomes_guest(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
