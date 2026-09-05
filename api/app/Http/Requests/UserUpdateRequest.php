<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // `ignore` na vlastný riadok je tu nutnosť, nie kozmetika: bez neho
            // padne na „e-mail je obsadený“ každé uloženie, ktoré adresu nemení
            // — teda každá zmena čohokoľvek iného v profile. Kľúč berieme
            // z routy (`users/{user}`), nie z tela požiadavky.
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->route('user')),
            ],
            'registered_via' => 'sometimes|string|in:local,google,facebook',
            'blocked_reason' => 'nullable|string|max:255',
            'blocked_until' => 'nullable|date',
        ];
    }
}
