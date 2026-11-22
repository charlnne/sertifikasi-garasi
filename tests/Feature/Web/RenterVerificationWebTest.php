<?php

namespace Tests\Feature\Web;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RenterVerificationWebTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_index_admin_sees_pending_renter_nik(): void
    {
        $renter = User::factory()->pending()->create(['nik' => '3171010101900099']);

        $response = $this->actingAs($this->admin)->get(route('admin.renters.index'));

        $response->assertOk();
        $response->assertSee($renter->name);
        $response->assertSee('3171010101900099');
    }

    public function test_index_pending_tab_shows_renter_data_and_actions(): void
    {
        $renter = User::factory()->pending()->create(['phone' => '081299998888', 'email' => 'applicant@example.com']);

        $response = $this->actingAs($this->admin)->get(route('admin.renters.index'));

        $response->assertSee('081299998888');
        $response->assertSee('applicant@example.com');
        $response->assertSee(route('admin.renters.ktp', $renter));
        $response->assertSee(route('admin.renters.approve', $renter));
        $response->assertSee(route('admin.renters.reject', $renter));
    }

    public function test_index_tabs_show_count_per_status(): void
    {
        User::factory()->pending()->count(2)->create();
        User::factory()->count(3)->create();
        User::factory()->rejected()->create();

        $response = $this->actingAs($this->admin)->get(route('admin.renters.index'));

        $response->assertViewHas('counts', ['pending' => 2, 'approved' => 3, 'rejected' => 1]);
        $response->assertSeeInOrder(['Pending', '2', 'Approved', '3', 'Rejected', '1']);
    }

    public function test_index_approved_tab_hides_action_buttons(): void
    {
        $renter = User::factory()->create();

        $response = $this->actingAs($this->admin)->get(route('admin.renters.index', ['status' => 'approved']));

        $response->assertSee($renter->name);
        $response->assertDontSee(route('admin.renters.reject', $renter));
    }

    public function test_index_renter_returns403(): void
    {
        $renter = User::factory()->create();

        $response = $this->actingAs($renter)->get(route('admin.renters.index'));

        $response->assertForbidden();
    }

    public function test_approve_pending_renter_changes_status_to_approved(): void
    {
        $renter = User::factory()->pending()->create();

        $response = $this->actingAs($this->admin)->patch(route('admin.renters.approve', $renter));

        $response->assertSessionHas('success');
        $renter->refresh();
        $this->assertSame(UserStatus::Approved, $renter->status);
    }

    public function test_reject_pending_renter_changes_status_to_rejected(): void
    {
        $renter = User::factory()->pending()->create();

        $response = $this->actingAs($this->admin)->patch(route('admin.renters.reject', $renter));

        $response->assertSessionHas('success');
        $this->assertSame(UserStatus::Rejected, $renter->fresh()->status);
    }

    public function test_reject_already_rejected_renter_is_rejected(): void
    {
        $renter = User::factory()->rejected()->create();

        $response = $this->actingAs($this->admin)->patch(route('admin.renters.reject', $renter));

        $response->assertSessionHas('error');
        $this->assertSame(UserStatus::Rejected, $renter->fresh()->status);
    }

    public function test_approve_already_approved_renter_is_rejected(): void
    {
        $renter = User::factory()->create();

        $response = $this->actingAs($this->admin)
            ->from(route('admin.renters.index'))
            ->patch(route('admin.renters.approve', $renter));

        $response->assertRedirect(route('admin.renters.index'));
        $response->assertSessionHas('error');
    }

    public function test_approve_admin_account_is_rejected(): void
    {
        $otherAdmin = User::factory()->admin()->create();

        $response = $this->actingAs($this->admin)->patch(route('admin.renters.approve', $otherAdmin));

        $response->assertSessionHas('error', 'Only renter accounts can be verified.');
    }

    public function test_show_ktp_admin_receives_private_file(): void
    {
        Storage::fake('local');
        $path = UploadedFile::fake()->image('ktp.jpg')->store('ktp', 'local');
        $renter = User::factory()->pending()->create(['ktp_path' => $path]);

        $response = $this->actingAs($this->admin)->get(route('admin.renters.ktp', $renter));

        $response->assertOk();
    }

    public function test_show_ktp_renter_returns403(): void
    {
        $renter = User::factory()->create();

        $response = $this->actingAs($renter)->get(route('admin.renters.ktp', $renter));

        $response->assertForbidden();
    }

    public function test_show_ktp_guest_redirects_to_login(): void
    {
        $renter = User::factory()->pending()->create();

        $response = $this->get(route('admin.renters.ktp', $renter));

        $response->assertRedirect(route('login'));
    }

    public function test_approved_middleware_pending_renter_returns403(): void
    {
        Route::middleware(['web', 'auth', 'approved'])->get('test-approved', fn () => 'ok');
        $renter = User::factory()->pending()->create();

        $response = $this->actingAs($renter)->get('test-approved');

        $response->assertForbidden();
        $response->assertSee('Your account is still awaiting admin verification.');
    }

    public function test_approved_middleware_rejected_renter_is_logged_out(): void
    {
        Route::middleware(['web', 'auth', 'approved'])->get('test-approved', fn () => 'ok');
        $renter = User::factory()->rejected()->create();

        $response = $this->actingAs($renter)->get('test-approved');

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['email' => 'Your registration was rejected.']);
        $this->assertGuest();
    }

    public function test_approved_middleware_approved_renter_passes(): void
    {
        Route::middleware(['web', 'auth', 'approved'])->get('test-approved', fn () => 'ok');
        $renter = User::factory()->create();

        $response = $this->actingAs($renter)->get('test-approved');

        $response->assertOk()->assertSee('ok');
    }
}
