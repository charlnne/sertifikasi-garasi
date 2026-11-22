<?php

namespace App\Http\Requests;

use App\Enums\VehicleType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVehicleRequest extends FormRequest
{
    /**
     * Normalize the plate number (uppercase, single spaces) so the unique check is not fooled by formatting.
     */
    protected function prepareForValidation(): void
    {
        $plate = preg_replace('/\s+/', ' ', strtoupper(trim((string) $this->input('plate_number'))));

        $this->merge(['plate_number' => $plate === '' ? null : $plate]);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(VehicleType::class)],
            'plate_number' => [
                'nullable',
                'required_unless:type,'.VehicleType::Bicycle->value,
                'string',
                'max:15',
                'regex:/^[A-Z]{1,2} [0-9]{1,4}( [A-Z]{1,3})?$/',
                'unique:vehicles,plate_number',
            ],
            'brand_model' => ['required', 'string', 'max:100'],
            'battery_capacity' => [
                'nullable',
                'required_if:type,'.VehicleType::ElectricCar->value,
                'numeric',
                'gt:0',
                'max:999999.99',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'type' => 'Vehicle type',
            'plate_number' => 'Plate number',
            'brand_model' => 'Brand / model',
            'battery_capacity' => 'Battery capacity',
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Please select a vehicle type.',
            'type.enum' => 'The selected vehicle type is invalid.',
            'plate_number.required_unless' => 'Plate number is required except for bicycles.',
            'plate_number.string' => 'Plate number must be text.',
            'plate_number.max' => 'Plate number may not exceed 15 characters.',
            'plate_number.regex' => 'Invalid plate number format, e.g. B 1234 KLM.',
            'plate_number.unique' => 'Plate number is already registered.',
            'brand_model.required' => 'Brand / model is required.',
            'brand_model.string' => 'Brand / model must be text.',
            'brand_model.max' => 'Brand / model may not exceed 100 characters.',
            'battery_capacity.required_if' => 'Battery capacity is required for an electric car.',
            'battery_capacity.numeric' => 'Battery capacity must be a number.',
            'battery_capacity.gt' => 'Battery capacity must be greater than 0 kWh.',
            'battery_capacity.max' => 'Battery capacity is too large.',
        ];
    }
}
