<?php

namespace Tests\Feature\Web;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RegistrationWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'nik' => '3171010101900001',
            'name' => 'New Renter',
            'phone' => '081234567890',
            'email' => 'new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'ktp_photo' => UploadedFile::fake()->image('ktp.jpg', 640, 400),
        ], $overrides);
    }

    public function test_register_valid_data_creates_pending_renter_with_private_ktp(): void
    {
        $response = $this->post(route('register.store'), $this->validPayload());

        $response->assertRedirect(route('dashboard'));
        $user = User::where('email', 'new@example.com')->firstOrFail();
        $this->assertSame(UserRole::Renter, $user->role);
        $this->assertSame(UserStatus::Pending, $user->status);
        $this->assertSame('3171010101900001', $user->nik);
        Storage::disk('local')->assertExists($user->ktp_path);
        Storage::disk('public')->assertMissing($user->ktp_path);
        $this->assertAuthenticatedAs($user);
    }

    public function test_register_nik_exactly16_digits_is_accepted(): void
    {
        $response = $this->post(route('register.store'), $this->validPayload(['nik' => '3171010101900001']));

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['nik' => '3171010101900001']);
    }

    public static function invalidNikProvider(): array
    {
        return [
            'boundary: 15 digits' => ['317101010190000', 'NIK must be exactly 16 digits.'],
            'boundary: 17 digits' => ['31710101019000011', 'NIK must be exactly 16 digits.'],
            'negative: contains letters' => ['3171010101900A01', 'NIK must be exactly 16 digits.'],
            'negative: contains spaces' => ['3171 10101900001', 'NIK must be exactly 16 digits.'],
            'negative: empty' => ['', 'NIK is required.'],
        ];
    }

    #[DataProvider('invalidNikProvider')]
    public function test_register_invalid_nik_returns_validation_error(string $nik, string $expectedMessage): void
    {
        $response = $this->post(route('register.store'), $this->validPayload(['nik' => $nik]));

        $response->assertSessionHasErrors(['nik' => $expectedMessage]);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_register_duplicate_nik_returns_validation_error(): void
    {
        User::factory()->create(['nik' => '3171010101900001']);

        $response = $this->post(route('register.store'), $this->validPayload());

        $response->assertSessionHasErrors(['nik' => 'NIK is already registered.']);
    }

    public function test_register_duplicate_email_returns_validation_error(): void
    {
        User::factory()->create(['email' => 'new@example.com']);

        $response = $this->post(route('register.store'), $this->validPayload());

        $response->assertSessionHasErrors(['email' => 'Email is already registered.']);
    }

    public function test_register_form_nik_input_is_numeric_with_max16(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk();
        $response->assertSee('inputmode="numeric"', false);
        $response->assertSee('maxlength="16"', false);
    }

    public function test_register_ktp_exactly2_mb_is_accepted(): void
    {
        $ktp = UploadedFile::fake()->image('ktp.jpg')->size(2048);

        $response = $this->post(route('register.store'), $this->validPayload(['ktp_photo' => $ktp]));

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'new@example.com']);
    }

    public function test_register_ktp_larger_than2_mb_returns_validation_error(): void
    {
        $ktp = UploadedFile::fake()->image('ktp.jpg')->size(2049);

        $response = $this->post(route('register.store'), $this->validPayload(['ktp_photo' => $ktp]));

        $response->assertSessionHasErrors(['ktp_photo' => 'KTP photo may not exceed 2048 KB.']);
    }

    public function test_register_ktp_not_image_returns_validation_error(): void
    {
        $file = UploadedFile::fake()->create('ktp.pdf', 100, 'application/pdf');

        $response = $this->post(route('register.store'), $this->validPayload(['ktp_photo' => $file]));

        $response->assertSessionHasErrors('ktp_photo');
    }

    public function test_register_without_ktp_returns_validation_error(): void
    {
        $response = $this->post(route('register.store'), $this->validPayload(['ktp_photo' => null]));

        $response->assertSessionHasErrors(['ktp_photo' => 'KTP photo is required.']);
    }

    public function test_register_invalid_phone_returns_validation_error(): void
    {
        $response = $this->post(route('register.store'), $this->validPayload(['phone' => '12345']));

        $response->assertSessionHasErrors('phone');
    }

    public function test_register_password_too_short_returns_validation_error(): void
    {
        $response = $this->post(route('register.store'), $this->validPayload([
            'password' => '1234567',
            'password_confirmation' => '1234567',
        ]));

        $response->assertSessionHasErrors(['password' => 'Password must be at least 8 characters.']);
    }
}
