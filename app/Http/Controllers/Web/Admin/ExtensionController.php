<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExtensionRequest;
use App\Models\Rental;
use App\Services\RentalExtensionService;
use App\Services\RentalPeriodCalculator;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ExtensionController extends Controller
{
    public function __construct(
        private readonly RentalExtensionService $extensionService,
        private readonly RentalPeriodCalculator $periodCalculator,
    ) {}

    public function index(): View
    {
        $rentals = $this->extensionService->activeRentalsToday();

        return view('admin.extensions.index', compact('rentals'));
    }

    public function create(Rental $rental): View|RedirectResponse
    {
        try {
            $this->extensionService->ensureExtendable($rental);
        } catch (DomainException $exception) {
            return redirect()->route('admin.extensions.index')->with('error', $exception->getMessage());
        }

        $rental->load(['garage', 'vehicle.user']);
        $extensionStart = $this->periodCalculator->extensionStartDate($rental->end_date);

        return view('admin.extensions.create', [
            'rental' => $rental,
            'extensionStart' => $extensionStart,
            'maxDailyDays' => $this->periodCalculator->maxDailyExtensionDays($rental->end_date),
            'monthlyDays' => $extensionStart->daysInMonth,
            'maxExtendableDays' => $this->extensionService->maxExtendableDays($rental),
            'nextBooking' => $this->extensionService->nextBooking($rental),
        ]);
    }

    public function store(StoreExtensionRequest $request, Rental $rental): RedirectResponse
    {
        $oldEndDate = $rental->end_date->translatedFormat('d F Y');

        $rental = $this->extensionService->extend($rental, $request->extensionType(), $request->days(), $request->file('payment_proof'));

        return redirect()->route('admin.extensions.index')->with(
            'success',
            "Rental for garage {$rental->garage->code} extended from {$oldEndDate} to {$rental->end_date->translatedFormat('d F Y')}.",
        );
    }
}
