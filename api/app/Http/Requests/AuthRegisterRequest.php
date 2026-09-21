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
            // `confirmed` je tu z rovnakého dôvodu ako v PasswordResetRequest:
            // registračný formulár pýta heslo dvakrát, a bez tohto pravidla by
            // sa druhé pole nikde neporovnávalo — preklep by ticho založil účet
            // s heslom, ktoré si človek nepamätá.
            'password' => 'required_if:registered_via,local|string|min:8|confirmed',
            'registered_via' => 'sometimes|string|in:local,google,facebook',
            'display_name' => 'required_if:registered_via,local|string|max:255',
            // Súhlas s obchodnými podmienkami je podmienkou vzniku účtu, preto
            // `accepted` (musí prísť true/1/"on"), nie obyčajný boolean —
            // hodnota false alebo chýbajúce pole registráciu zastaví.
            'terms_accepted' => 'accepted',
            // Registrácia z tlačidla „Prihlásiť sa" pri podujatí — miesto sa
            // rezervuje po overení e-mailu. Neznáme či uzavreté id sa ignoruje.
            'event_id' => 'sometimes|nullable|integer',
        ];
    }

    public function messages(): array
    {
        return [
            'terms_accepted.accepted' => 'You must agree to the terms and conditions.',
        ];
    }
}
