<?php

namespace App\Http\Requests;

use App\Enums\RentalType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExtensionRequest extends FormRequest
{
    public const MAX_PROOF_SIZE_KB = 2048;

    public function rules(): array
    {
        return [
            'extension_type' => ['required', Rule::enum(RentalType::class)],
            'days' => ['nullable', 'required_if:extension_type,'.RentalType::Daily->value, 'integer', 'min:1'],
            'payment_proof' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:'.self::MAX_PROOF_SIZE_KB],
        ];
    }

    public function attributes(): array
    {
        return [
            'extension_type' => 'Extension type',
            'days' => 'Number of days',
            'payment_proof' => 'Payment proof',
        ];
    }

    public function messages(): array
    {
        return [
            'extension_type.required' => 'Please select an extension type.',
            'extension_type.enum' => 'The selected extension type is invalid.',
            'days.required_if' => 'Number of days is required for a daily extension.',
            'days.integer' => 'Number of days must be a whole number.',
            'days.min' => 'Number of days must be at least 1.',
            'payment_proof.required' => 'Payment proof is required.',
            'payment_proof.image' => 'Payment proof must be an image.',
            'payment_proof.mimes' => 'Payment proof must be a jpg, jpeg, or png file.',
            'payment_proof.max' => 'Payment proof may not exceed '.self::MAX_PROOF_SIZE_KB.' KB.',
            'payment_proof.uploaded' => 'Payment proof failed to upload.',
        ];
    }

    public function extensionType(): RentalType
    {
        return RentalType::from($this->validated('extension_type'));
    }

    public function days(): ?int
    {
        return $this->extensionType() === RentalType::Daily ? (int) $this->validated('days') : null;
    }
}
