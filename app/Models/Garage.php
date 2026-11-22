<?php

namespace App\Models;

use App\Enums\GarageStatus;
use App\Enums\GarageType;
use App\Services\GarageRuleService;
use App\Services\RentalCostCalculator;
use Carbon\CarbonInterface;
use Database\Factories\GarageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Garage extends Model
{
    /** @use HasFactory<GarageFactory> */
    use HasFactory;

    protected $fillable = [
        'type',
        'number',
        'daily_price',
        'kwh_price',
    ];

    protected function casts(): array
    {
        return [
            'type' => GarageType::class,
            'number' => 'integer',
            'daily_price' => 'decimal:2',
            'kwh_price' => 'decimal:2',
        ];
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }

    protected function code(): Attribute
    {
        return Attribute::get(fn () => (new GarageRuleService)->formatCode($this->type, $this->number));
    }

    protected function monthlyPrice(): Attribute
    {
        return Attribute::get(fn () => (new RentalCostCalculator)->monthlyPrice((float) $this->daily_price));
    }

    /**
     * Garage status on a given date (default today). Uses the withStatusOn result when already loaded.
     */
    public function statusOn(?CarbonInterface $date = null): GarageStatus
    {
        if ($date === null && isset($this->attributes['is_used'], $this->attributes['is_booked'])) {
            return self::resolveStatus((bool) $this->attributes['is_used'], (bool) $this->attributes['is_booked']);
        }

        $date ??= today();

        return self::resolveStatus(
            $this->rentals()->notCancelled()->covering($date)->exists(),
            $this->rentals()->notCancelled()->whereDate('start_date', '>', $date->toDateString())->exists(),
        );
    }

    protected function status(): Attribute
    {
        return Attribute::get(fn () => $this->statusOn());
    }

    public function scopeOfType(Builder $query, GarageType $type): void
    {
        $query->where('type', $type);
    }

    /**
     * Add is_used and is_booked columns so the status of many garages can be resolved without a query per row.
     */
    public function scopeWithStatusOn(Builder $query, ?CarbonInterface $date = null): void
    {
        $date = ($date ?? today())->toDateString();

        $query->withExists([
            'rentals as is_used' => fn (Builder $rentals) => $rentals->notCancelled()->covering($date),
            'rentals as is_booked' => fn (Builder $rentals) => $rentals->notCancelled()->whereDate('start_date', '>', $date),
        ]);
    }

    /**
     * Filter garages by their status on a given date (default today).
     */
    public function scopeWhereStatus(Builder $query, GarageStatus $status, ?CarbonInterface $date = null): void
    {
        $date = ($date ?? today())->toDateString();
        $used = fn (Builder $rentals) => $rentals->notCancelled()->covering($date);
        $booked = fn (Builder $rentals) => $rentals->notCancelled()->whereDate('start_date', '>', $date);

        match ($status) {
            GarageStatus::Used => $query->whereHas('rentals', $used),
            GarageStatus::Booked => $query->whereDoesntHave('rentals', $used)->whereHas('rentals', $booked),
            GarageStatus::Available => $query->whereDoesntHave('rentals', $used)->whereDoesntHave('rentals', $booked),
        };
    }

    /**
     * In use if an active rental covers that date, booked if an active rental starts
     * after it, otherwise available.
     */
    public static function resolveStatus(bool $isUsed, bool $isBooked): GarageStatus
    {
        return match (true) {
            $isUsed => GarageStatus::Used,
            $isBooked => GarageStatus::Booked,
            default => GarageStatus::Available,
        };
    }
}
