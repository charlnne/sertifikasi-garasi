<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public const MAX_KTP_SIZE_KB = 2048;

    public function rules(): array
    {
        return [
            'nik' => ['required', 'regex:/^[0-9]{16}$/', 'unique:users,nik'],
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'regex:/^(\+62|0)8[0-9]{8,13}$/'],
            'email' => ['required', 'string', 'email', 'max:100', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'ktp_photo' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:'.self::MAX_KTP_SIZE_KB],
        ];
    }

    public function attributes(): array
    {
        return [
            'nik' => 'NIK',
            'name' => 'Full name',
            'phone' => 'Phone number',
            'email' => 'Email',
            'password' => 'Password',
            'ktp_photo' => 'KTP photo',
        ];
    }

    public function messages(): array
    {
        return [
            'nik.required' => 'NIK is required.',
            'nik.regex' => 'NIK must be exactly 16 digits.',
            'nik.unique' => 'NIK is already registered.',
            'name.required' => 'Full name is required.',
            'name.string' => 'Full name must be text.',
            'name.max' => 'Full name may not exceed 100 characters.',
            'phone.required' => 'Phone number is required.',
            'phone.string' => 'Phone number must be text.',
            'phone.regex' => 'Phone number must start with 08 or +628 and contain 10-15 digits.',
            'email.required' => 'Email is required.',
            'email.string' => 'Email must be text.',
            'email.email' => 'Email must be a valid email address.',
            'email.max' => 'Email may not exceed 100 characters.',
            'email.unique' => 'Email is already registered.',
            'password.required' => 'Password is required.',
            'password.string' => 'Password must be text.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.confirmed' => 'Password confirmation does not match.',
            'ktp_photo.required' => 'KTP photo is required.',
            'ktp_photo.image' => 'KTP photo must be an image.',
            'ktp_photo.mimes' => 'KTP photo must be a jpg, jpeg, or png file.',
            'ktp_photo.max' => 'KTP photo may not exceed '.self::MAX_KTP_SIZE_KB.' KB.',
            'ktp_photo.uploaded' => 'KTP photo failed to upload.',
        ];
    }
}
