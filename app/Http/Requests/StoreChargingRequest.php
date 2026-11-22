<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreChargingRequest extends FormRequest
{
    public const MAX_PROOF_SIZE_KB = 2048;

    public function rules(): array
    {
        return [
            'current_battery_percent' => ['required', 'integer', 'min:0', 'max:99'],
            'payment_proof' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:'.self::MAX_PROOF_SIZE_KB],
        ];
    }

    public function attributes(): array
    {
        return [
            'current_battery_percent' => 'Battery percentage',
            'payment_proof' => 'Payment proof',
        ];
    }

    public function messages(): array
    {
        return [
            'current_battery_percent.required' => 'Battery percentage is required.',
            'current_battery_percent.integer' => 'Battery percentage must be a whole number.',
            'current_battery_percent.min' => 'Battery percentage must be at least 0.',
            'current_battery_percent.max' => 'A 100% battery is already full, battery percentage may not exceed 99.',
            'payment_proof.required' => 'Payment proof is required.',
            'payment_proof.image' => 'Payment proof must be an image.',
            'payment_proof.mimes' => 'Payment proof must be a jpg, jpeg, or png file.',
            'payment_proof.max' => 'Payment proof may not exceed '.self::MAX_PROOF_SIZE_KB.' KB.',
            'payment_proof.uploaded' => 'Payment proof failed to upload.',
        ];
    }
}
