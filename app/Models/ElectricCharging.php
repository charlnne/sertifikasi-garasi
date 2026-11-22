<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Services\RentalCostCalculator;
use Database\Factories\ElectricChargingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ElectricCharging extends Model
{
    /** @use HasFactory<ElectricChargingFactory> */
    use HasFactory;

    protected $fillable = [
        'rental_id',
        'current_battery_percent',
        'payment_proof',
        'payment_status',
    ];

    protected $attributes = [
        'payment_status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'current_battery_percent' => 'integer',
            'payment_status' => PaymentStatus::class,
        ];
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function isInitial(): bool
    {
        return $this->payment_proof === null;
    }

    /**
     * Charged kWh = vehicle battery capacity x (100 - battery percent) / 100.
     */
    protected function chargedKwh(): Attribute
    {
        return Attribute::get(fn () => (new RentalCostCalculator)->calculateChargedKwh(
            (float) $this->rental->vehicle->battery_capacity,
            $this->current_battery_percent,
        ));
    }

    /**
     * Charging cost = charged kWh x the garage price per kWh.
     */
    protected function cost(): Attribute
    {
        return Attribute::get(fn () => (new RentalCostCalculator)->calculateChargingCost(
            $this->charged_kwh,
            (float) $this->rental->garage->kwh_price,
        ));
    }

    public function scopeAdditional(Builder $query): void
    {
        $query->whereNotNull('payment_proof');
    }

    public function scopeVerified(Builder $query): void
    {
        $query->where('payment_status', PaymentStatus::Verified);
    }

    public function scopePending(Builder $query): void
    {
        $query->where('payment_status', PaymentStatus::Pending);
    }
}
