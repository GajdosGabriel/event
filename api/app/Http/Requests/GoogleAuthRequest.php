<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GoogleAuthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_token' => 'required|string',
            // Ten istý endpoint slúži na prihlásenie aj na prvú registráciu,
            // takže súhlas tu nemôže byť povinný pre všetkých. Že ho nový účet
            // musí mať, si ustráži AuthController::authenticateSocialUser().
            'terms_accepted' => 'sometimes|boolean',
            // Registrácia za organizáciu: meno človeka dá Google, názov
            // organizácie prinesie formulár. Použije sa len pri novom účte.
            'organization_name' => 'sometimes|nullable|string|max:255',
        ];
    }
}
