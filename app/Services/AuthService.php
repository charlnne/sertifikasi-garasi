<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Traits\HandlesFileUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Renter registration and session-based login.
 *
 * @author Charlene
 *
 * @version 1.0
 */
class AuthService
{
    use HandlesFileUpload;

    public const KTP_DIRECTORY = 'ktp';

    public const KTP_DISK = 'local';

    public const REJECTED_MESSAGE = 'Your registration was rejected.';

    /**
     * Register a new renter. The role is always renter with a status awaiting admin verification.
     * The KTP photo is stored on the private disk so only admins can open it through a dedicated route.
     *
     * @param  array{nik: string, name: string, phone: string, email: string, password: string}  $data
     * @param  UploadedFile  $ktpPhoto  Applicant's KTP photo.
     * @return User New renter with pending status.
     */
    public function register(array $data, UploadedFile $ktpPhoto): User
    {
        $ktpPath = $this->uploadFile($ktpPhoto, self::KTP_DIRECTORY, disk: self::KTP_DISK);

        try {
            return User::create([
                'nik' => $data['nik'],
                'name' => $data['name'],
                'phone' => $data['phone'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => UserRole::Renter,
                'status' => UserStatus::Pending,
                'ktp_path' => $ktpPath,
            ]);
        } catch (Throwable $exception) {
            $this->deleteFile($ktpPath, self::KTP_DISK);

            throw $exception;
        }
    }

    /**
     * Session-based login. Pending renters may still log in, rejected renters may not.
     *
     * @param  array{email: string, password: string}  $credentials
     *
     * @throws ValidationException If the email/password is wrong or the registration was rejected.
     */
    public function attemptSessionLogin(array $credentials, bool $remember = false): void
    {
        if (! Auth::validate($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['Incorrect email or password.'],
            ]);
        }

        $user = User::where('email', $credentials['email'])->first();

        if ($user->isRejected()) {
            throw ValidationException::withMessages([
                'email' => [self::REJECTED_MESSAGE],
            ]);
        }

        Auth::attempt($credentials, $remember);
    }
}
