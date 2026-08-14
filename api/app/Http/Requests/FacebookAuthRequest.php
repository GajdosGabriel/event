<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FacebookAuthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'access_token' => 'required|string',
            // Prihlásenie aj prvá registrácia idú cez jeden endpoint —
            // pozri poznámku v GoogleAuthRequest.
            'terms_accepted' => 'sometimes|boolean',
        ];
    }
}
