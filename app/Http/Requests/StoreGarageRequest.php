<?php

namespace App\Http\Requests;

use App\Enums\GarageType;
use App\Services\GarageCodeGenerator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreGarageRequest extends FormRequest
{
    public const MAX_PRICE = 999999999;

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(GarageType::class)],
            'count' => ['required', 'integer', 'min:1', 'max:'.GarageCodeGenerator::MAX_PER_TYPE],
            'daily_price' => ['required', 'numeric', 'gt:0', 'max:'.self::MAX_PRICE],
            'kwh_price' => ['nullable', 'required_if:type,'.GarageType::Electric->value, 'numeric', 'gt:0', 'max:'.self::MAX_PRICE],
        ];
    }

    /**
     * The garage count may not exceed the remaining quota of the selected type.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['type', 'count'])) {
                    return;
                }

                $type = GarageType::from($this->input('type'));
                $remaining = app(GarageCodeGenerator::class)->remainingQuota($type);

                if ((int) $this->input('count') > $remaining) {
                    $label = strtolower($type->label());
                    $validator->errors()->add('count', "Only {$remaining} more {$label} ".Str::plural('garage', $remaining).' can be added (maximum '.GarageCodeGenerator::MAX_PER_TYPE.').');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'type' => 'Garage type',
            'count' => 'Garage count',
            'daily_price' => 'Daily price',
            'kwh_price' => 'Price per kWh',
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Please select a garage type.',
            'type.enum' => 'The selected garage type is invalid.',
            'count.required' => 'Garage count is required.',
            'count.integer' => 'Garage count must be a whole number.',
            'count.min' => 'Garage count must be at least 1.',
            'count.max' => 'Garage count may not exceed '.GarageCodeGenerator::MAX_PER_TYPE.'.',
            'daily_price.required' => 'Daily price is required.',
            'daily_price.numeric' => 'Daily price must be a number.',
            'daily_price.gt' => 'Daily price must be greater than 0.',
            'daily_price.max' => 'Daily price is too large.',
            'kwh_price.required_if' => 'Price per kWh is required for an electric garage.',
            'kwh_price.numeric' => 'Price per kWh must be a number.',
            'kwh_price.gt' => 'Price per kWh must be greater than 0.',
            'kwh_price.max' => 'Price per kWh is too large.',
        ];
    }
}
