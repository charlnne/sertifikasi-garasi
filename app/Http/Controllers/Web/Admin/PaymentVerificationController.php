<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\ElectricCharging;
use App\Models\Rental;
use App\Services\PaymentVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PaymentVerificationController extends Controller
{
    public function __construct(private readonly PaymentVerificationService $verificationService) {}

    public function index(): View
    {
        $rentals = $this->verificationService->pendingRentals();
        $chargings = $this->verificationService->pendingChargings();

        return view('admin.payments.index', compact('rentals', 'chargings'));
    }

    public function verifyRental(Rental $rental): RedirectResponse
    {
        $this->verificationService->verifyRental($rental);

        return back()->with('success', "Rental payment for garage {$rental->garage->code} verified.");
    }

    public function rejectRental(Rental $rental): RedirectResponse
    {
        $this->verificationService->rejectRental($rental);

        return back()->with('success', "Rental payment for garage {$rental->garage->code} rejected. The rental is cancelled.");
    }

    public function verifyCharging(ElectricCharging $charging): RedirectResponse
    {
        $this->verificationService->verifyCharging($charging);

        return back()->with('success', 'Charging payment verified.');
    }

    public function rejectCharging(ElectricCharging $charging): RedirectResponse
    {
        $this->verificationService->rejectCharging($charging);

        return back()->with('success', 'Charging payment rejected.');
    }
}
