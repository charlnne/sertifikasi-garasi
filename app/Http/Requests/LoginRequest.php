<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'email' => 'Email',
            'password' => 'Password',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Email is required.',
            'email.string' => 'Email must be text.',
            'email.email' => 'Email must be a valid email address.',
            'password.required' => 'Password is required.',
            'password.string' => 'Password must be text.',
        ];
    }
}
