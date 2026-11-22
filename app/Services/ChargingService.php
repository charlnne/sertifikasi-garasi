<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\ElectricCharging;
use App\Models\Rental;
use App\Traits\HandlesFileUpload;
use DomainException;
use Illuminate\Http\UploadedFile;

/**
 * Additional charging by the renter while a rental is running. Each charging is paid with its own payment proof.
 *
 * @author Charlene
 *
 * @version 1.0
 */
class ChargingService
{
    use HandlesFileUpload;

    public const PROOF_DIRECTORY = 'chargings';

    public const PENDING_CHARGING_MESSAGE = 'There is still a pending charging for this rental. Please wait for admin verification.';

    public function __construct(
        private readonly RentalCostCalculator $costCalculator,
        private readonly GarageRuleService $garageRules,
    ) {}

    /**
     * Reason the rental cannot have additional charging, or null if it can.
     *
     * Requirements: electric car, electric garage, verified rental payment, the rental covers today,
     * and no additional charging of this rental is still waiting for admin verification.
     */
    public function ineligibilityReason(Rental $rental): ?string
    {
        return match (true) {
            ! $rental->vehicle->type->hasBattery() => 'Charging is only available for electric cars.',
            ! $this->garageRules->hasCharger($rental->garage->type) => 'Charging is only available in electric garages.',
            $rental->payment_status !== PaymentStatus::Verified => 'Charging is only available after the rental payment is verified.',
            $rental->start_date->gt(today()) || $rental->end_date->lt(today()) => 'Charging is only available while the rental is running today.',
            // The initial charging (without its own payment proof) is not counted here.
            $rental->chargings()->additional()->pending()->exists() => self::PENDING_CHARGING_MESSAGE,
            default => null,
        };
    }

    public function isEligible(Rental $rental): bool
    {
        return $this->ineligibilityReason($rental) === null;
    }

    /**
     * The rental meets every charging requirement except that an additional charging is still pending.
     */
    public function isBlockedByPendingCharging(Rental $rental): bool
    {
        return $this->ineligibilityReason($rental) === self::PENDING_CHARGING_MESSAGE;
    }

    /**
     * Calculate the charged kWh and charging cost without saving.
     *
     * @return array{charged_kwh: float, cost: float}
     */
    public function quote(Rental $rental, int $batteryPercent): array
    {
        $chargedKwh = $this->costCalculator->calculateChargedKwh((float) $rental->vehicle->battery_capacity, $batteryPercent);

        return [
            'charged_kwh' => $chargedKwh,
            'cost' => $this->costCalculator->calculateChargingCost($chargedKwh, (float) $rental->garage->kwh_price),
        ];
    }

    /**
     * Store an additional charging with a pending payment status.
     *
     * @param  Rental  $rental  Renter's rental that is eligible for charging.
     * @param  int  $batteryPercent  Current battery percentage (0-99).
     * @param  UploadedFile  $paymentProof  Charging payment proof.
     *
     * @throws DomainException If the rental is not eligible or the battery is already full.
     */
    public function create(Rental $rental, int $batteryPercent, UploadedFile $paymentProof): ElectricCharging
    {
        if ($reason = $this->ineligibilityReason($rental)) {
            throw new DomainException($reason);
        }

        // A full battery needs no charging, so the charging cost is never 0.
        if ($batteryPercent >= 100) {
            throw new DomainException('The battery is already full, no charging needed.');
        }

        return $rental->chargings()->create([
            'current_battery_percent' => $batteryPercent,
            'payment_proof' => $this->uploadFile($paymentProof, self::PROOF_DIRECTORY),
            'payment_status' => PaymentStatus::Pending,
        ]);
    }
}
