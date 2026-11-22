<?php

namespace App\Models;

use App\Enums\VehicleType;
use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'plate_number',
        'brand_model',
        'battery_capacity',
    ];

    protected function casts(): array
    {
        return [
            'type' => VehicleType::class,
            'battery_capacity' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }

    public function getDisplayName(): string
    {
        if (! $this->plate_number) {
            return $this->brand_model;
        }

        return "{$this->brand_model} ({$this->plate_number})";
    }
}
