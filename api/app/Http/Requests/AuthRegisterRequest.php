<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AuthRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
                Rule::unique('pending_registrations', 'email'),
            ],
            'password' => 'required_if:registered_via,local|string|min:8',
            'registered_via' => 'sometimes|string|in:local,google,facebook',
            'display_name' => 'required_if:registered_via,local|string|max:255',
        ];
    }

}

