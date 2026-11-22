<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuthService;
use App\Services\RenterVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RenterVerificationController extends Controller
{
    public function __construct(private readonly RenterVerificationService $verificationService) {}

    public function index(Request $request): View
    {
        $status = UserStatus::tryFrom((string) $request->query('status')) ?? UserStatus::Pending;
        $renters = $this->verificationService->paginate($status);
        $counts = $this->verificationService->countByStatus();

        return view('admin.renters.index', compact('renters', 'status', 'counts'));
    }

    public function approve(User $user): RedirectResponse
    {
        $this->verificationService->approve($user);

        return back()->with('success', "Applicant {$user->name} approved.");
    }

    public function reject(User $user): RedirectResponse
    {
        $this->verificationService->reject($user);

        return back()->with('success', "Applicant {$user->name} rejected.");
    }

    public function showKtp(User $user): StreamedResponse
    {
        $disk = Storage::disk(AuthService::KTP_DISK);

        abort_unless($user->ktp_path && $disk->exists($user->ktp_path), 404);

        return $disk->response($user->ktp_path);
    }
}
