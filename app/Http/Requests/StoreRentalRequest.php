<?php

namespace App\Http\Requests;

use App\Enums\RentalType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRentalRequest extends FormRequest
{
    public const MAX_PROOF_SIZE_KB = 2048;

    public function rules(): array
    {
        return [
            'vehicle_id' => ['required', Rule::exists('vehicles', 'id')->where('user_id', $this->user()->id)],
            'rental_type' => ['required', Rule::enum(RentalType::class)],
            'start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'days' => ['nullable', 'required_if:rental_type,'.RentalType::Daily->value, 'integer', 'min:1'],
            'garage_id' => ['required', 'integer', Rule::exists('garages', 'id')],
            'current_battery_percent' => ['nullable', 'integer', 'between:0,100'],
            'payment_proof' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:'.self::MAX_PROOF_SIZE_KB],
        ];
    }

    public function attributes(): array
    {
        return [
            'vehicle_id' => 'Vehicle',
            'rental_type' => 'Rental type',
            'start_date' => 'Start date',
            'days' => 'Number of days',
            'garage_id' => 'Garage',
            'current_battery_percent' => 'Battery percentage',
            'payment_proof' => 'Payment proof',
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
            'garage_id.required' => 'Please select one of the available garages.',
            'garage_id.integer' => 'The selected garage is invalid.',
            'garage_id.exists' => 'The selected garage was not found.',
            'current_battery_percent.integer' => 'Battery percentage must be a whole number.',
            'current_battery_percent.between' => 'Battery percentage must be between 0 and 100.',
            'payment_proof.required' => 'Payment proof is required.',
            'payment_proof.image' => 'Payment proof must be an image.',
            'payment_proof.mimes' => 'Payment proof must be a jpg, jpeg, or png file.',
            'payment_proof.max' => 'Payment proof may not exceed '.self::MAX_PROOF_SIZE_KB.' KB.',
            'payment_proof.uploaded' => 'Payment proof failed to upload.',
        ];
    }
}
