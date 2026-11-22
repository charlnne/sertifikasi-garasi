<?php

namespace App\Http\Requests;

use App\Enums\GarageType;
use App\Enums\RentalType;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * First step of the new rental form: vehicle, rental type, start date, number of days, and garage type.
 * Validation only runs after the renter presses the search garage button.
 */
class SearchRentalGarageRequest extends FormRequest
{
    protected $redirectRoute = 'rentals.create';

    public function isSearching(): bool
    {
        return $this->filled('vehicle_id');
    }

    public function rules(): array
    {
        if (! $this->isSearching()) {
            return [];
        }

        return [
            'vehicle_id' => ['required', Rule::exists('vehicles', 'id')->where('user_id', $this->user()->id)],
            'rental_type' => ['required', Rule::enum(RentalType::class)],
            'start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'days' => ['nullable', 'required_if:rental_type,'.RentalType::Daily->value, 'integer', 'min:1'],
            'garage_type' => ['required', Rule::enum(GarageType::class)],
        ];
    }

    public function attributes(): array
    {
        return [
            'vehicle_id' => 'Vehicle',
            'rental_type' => 'Rental type',
            'start_date' => 'Start date',
            'days' => 'Number of days',
            'garage_type' => 'Garage type',
        ];
    }

    public function messages(): array
    {
        return [
            'vehicle_id.required' => 'Please select a vehicle.',
            'vehicle_id.exists' => 'The selected vehicle was not found.',
            'rental_type.required' => 'Please select a rental type.',
            'rental_type.enum' => 'The selected rental type is invalid.',
            'start_date.required' => 'Start date is required.',
            'start_date.date_format' => 'Start date must use the year-month-day format.',
            'start_date.after_or_equal' => 'Start date cannot be before today.',
            'days.required_if' => 'Number of days is required for a daily rental.',
            'days.integer' => 'Number of days must be a whole number.',
            'days.min' => 'Number of days must be at least 1.',
            'garage_type.required' => 'Please select a garage type.',
            'garage_type.enum' => 'The selected garage type is invalid.',
        ];
    }

    public function rentalType(): RentalType
    {
        return RentalType::from($this->validated('rental_type'));
    }

    public function startDate(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->validated('start_date'))->startOfDay();
    }

    public function days(): ?int
    {
        return $this->rentalType() === RentalType::Daily ? (int) $this->validated('days') : null;
    }

    public function garageType(): GarageType
    {
        return GarageType::from($this->validated('garage_type'));
    }
}
