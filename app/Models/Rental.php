<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Enums\RentalStatus;
use App\Enums\RentalType;
use App\Services\RentalCostCalculator;
use App\Services\RentalPeriodCalculator;
use Carbon\CarbonInterface;
use Database\Factories\RentalFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Rental extends Model
{
    /** @use HasFactory<RentalFactory> */
    use HasFactory;

    protected $fillable = [
        'vehicle_id',
        'garage_id',
        'rental_type',
        'start_date',
        'end_date',
        'extension_days',
        'extension_cost',
        'payment_proof',
        'payment_status',
        'extension_payment_proof',
    ];

    protected $attributes = [
        'extension_days' => 0,
        'extension_cost' => 0,
        'payment_status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'rental_type' => RentalType::class,
            'payment_status' => PaymentStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'extension_days' => 'integer',
            'extension_cost' => 'decimal:2',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function garage(): BelongsTo
    {
        return $this->belongsTo(Garage::class);
    }

    public function chargings(): HasMany
    {
        return $this->hasMany(ElectricCharging::class);
    }

    /**
     * The initial charging is paid together with the rental, so it has no payment proof of its own.
     */
    public function initialCharging(): HasOne
    {
        return $this->hasOne(ElectricCharging::class)->whereNull('payment_proof');
    }

    /**
     * Rentals that still count: the payment has not been rejected.
     */
    public function scopeNotCancelled(Builder $query): void
    {
        $query->where('payment_status', '!=', PaymentStatus::Rejected);
    }

    /**
     * Rentals whose period overlaps another period. The end date is inclusive,
     * so they overlap when existing.start_date <= end AND existing.end_date >= start.
     */
    public function scopeOverlapping(Builder $query, CarbonInterface|string $startDate, CarbonInterface|string $endDate): void
    {
        $query->whereDate('start_date', '<=', self::toDateString($endDate))
            ->whereDate('end_date', '>=', self::toDateString($startDate));
    }

    public function scopeCovering(Builder $query, CarbonInterface|string $date): void
    {
        $query->overlapping($date, $date);
    }

    public function scopeOwnedBy(Builder $query, User $user): void
    {
        $query->whereHas('vehicle', fn (Builder $vehicles) => $vehicles->where('user_id', $user->id));
    }

    public function scopeVerified(Builder $query): void
    {
        $query->where('payment_status', PaymentStatus::Verified);
    }

    public function scopePending(Builder $query): void
    {
        $query->where('payment_status', PaymentStatus::Pending);
    }

    /**
     * Rental status is derived, not stored: a rejected payment means cancelled,
     * a past end date means finished, otherwise active.
     */
    protected function status(): Attribute
    {
        return Attribute::get(fn () => match (true) {
            $this->payment_status === PaymentStatus::Rejected => RentalStatus::Cancelled,
            $this->end_date->lt(today()) => RentalStatus::Finished,
            default => RentalStatus::Active,
        });
    }

    protected function baseDays(): Attribute
    {
        return Attribute::get(fn () => (new RentalPeriodCalculator)->baseDays($this->start_date, $this->end_date, $this->extension_days));
    }

    protected function totalDays(): Attribute
    {
        return Attribute::get(fn () => (new RentalPeriodCalculator)->countDaysInclusive($this->start_date, $this->end_date));
    }

    protected function remainingDays(): Attribute
    {
        return Attribute::get(fn () => (new RentalPeriodCalculator)->remainingDays($this->end_date));
    }

    protected function rentCost(): Attribute
    {
        return Attribute::get(fn () => (new RentalCostCalculator)->calculateRentCost(
            $this->rental_type,
            $this->base_days,
            (float) $this->garage->daily_price,
        ));
    }

    protected function initialChargingCost(): Attribute
    {
        return Attribute::get(fn () => $this->initialCharging?->cost ?? 0.0);
    }

    /**
     * Initial payment total = rent cost + initial charging cost.
     */
    protected function initialTotal(): Attribute
    {
        return Attribute::get(fn () => (new RentalCostCalculator)->calculateTotal($this->rent_cost, $this->initial_charging_cost));
    }

    public function isActive(): bool
    {
        return $this->status === RentalStatus::Active;
    }

    public function isVerified(): bool
    {
        return $this->payment_status === PaymentStatus::Verified;
    }

    private static function toDateString(CarbonInterface|string $date): string
    {
        return $date instanceof CarbonInterface ? $date->toDateString() : $date;
    }
}
