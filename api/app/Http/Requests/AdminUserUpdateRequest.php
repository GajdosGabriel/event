<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminUserUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'blocked'        => 'required|boolean',
            'blocked_until'  => 'nullable|date|after:now',
            'blocked_reason' => 'nullable|string|max:255',
        ];
    }
}
