<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\ElectricCharging;
use App\Models\Rental;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Payment verification by admins for new rentals and additional chargings. Decisions are final:
 * only pending payments can be changed.
 *
 * The initial charging is not verified on its own because it is paid with the rental, so its status always follows the rental.
 *
 * @author Charlene
 *
 * @version 1.0
 */
class PaymentVerificationService
{
    /**
     * @return Collection<int, Rental>
     */
    public function pendingRentals(): Collection
    {
        return Rental::pending()
            ->with(['garage', 'vehicle.user', 'initialCharging'])
            ->oldest()
            ->get();
    }

    /**
     * @return Collection<int, ElectricCharging>
     */
    public function pendingChargings(): Collection
    {
        return ElectricCharging::additional()
            ->pending()
            ->with(['rental.garage', 'rental.vehicle.user'])
            ->oldest()
            ->get();
    }

    public function countPending(): int
    {
        return Rental::pending()->count() + ElectricCharging::additional()->pending()->count();
    }

    /**
     * Approve the rental payment. The initial charging is verified along with it.
     *
     * @throws DomainException If the rental payment has already been decided.
     */
    public function verifyRental(Rental $rental): Rental
    {
        return $this->decideRental($rental, PaymentStatus::Verified);
    }

    /**
     * Reject the rental payment. The rental becomes cancelled so the garage is available again for that range,
     * and the initial charging is rejected along with it.
     *
     * @throws DomainException If the rental payment has already been decided.
     */
    public function rejectRental(Rental $rental): Rental
    {
        return $this->decideRental($rental, PaymentStatus::Rejected);
    }

    /**
     * @throws DomainException If the charging is not an additional charging or has already been decided.
     */
    public function verifyCharging(ElectricCharging $charging): ElectricCharging
    {
        return $this->decideCharging($charging, PaymentStatus::Verified);
    }

    /**
     * @throws DomainException If the charging is not an additional charging or has already been decided.
     */
    public function rejectCharging(ElectricCharging $charging): ElectricCharging
    {
        return $this->decideCharging($charging, PaymentStatus::Rejected);
    }

    private function decideRental(Rental $rental, PaymentStatus $status): Rental
    {
        return DB::transaction(function () use ($rental, $status) {
            // Locked so two admins cannot decide the same payment at the same time.
            $rental = Rental::whereKey($rental->id)->lockForUpdate()->firstOrFail();

            if ($rental->payment_status !== PaymentStatus::Pending) {
                throw new DomainException('This rental payment has already been verified.');
            }

            $rental->update(['payment_status' => $status]);
            $rental->chargings()->whereNull('payment_proof')->update(['payment_status' => $status]);

            return $rental;
        });
    }

    private function decideCharging(ElectricCharging $charging, PaymentStatus $status): ElectricCharging
    {
        return DB::transaction(function () use ($charging, $status) {
            $charging = ElectricCharging::whereKey($charging->id)->lockForUpdate()->firstOrFail();

            if ($charging->isInitial()) {
                throw new DomainException('The initial charging is verified together with the rental payment.');
            }

            if ($charging->payment_status !== PaymentStatus::Pending) {
                throw new DomainException('This charging payment has already been verified.');
            }

            $charging->update(['payment_status' => $status]);

            return $charging;
        });
    }
}
