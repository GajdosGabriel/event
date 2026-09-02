<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Vstup panela „Vyplniť pomocou AI".
 *
 * Autorizácia sa tu zámerne nerieši — závisí od `kind` a robí ju controller
 * cez policy (viď AiAssistController::assist).
 */
class AiAssistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kind' => ['required', 'string', 'in:event,venue,canal'],
            'action' => ['required', 'string', 'in:improve,draft'],

            // Horný strop je ochrana peňaženky, nie formátu: 20 000 znakov je
            // rádovo celý program púte a nad tým už nejde o popis podujatia.
            'text' => ['required_if:action,improve', 'nullable', 'string', 'max:20000'],
            'modes' => ['required_if:action,improve', 'array'],
            'modes.*' => ['string', 'in:grammar,style,expand'],

            'name' => ['required_if:action,draft', 'nullable', 'string', 'max:250'],
            'context' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->input('action') !== 'improve') {
                    return;
                }

                // Dolná hranica sa počíta z textu bez značiek. `min:50` na
                // surovom HTML prepustilo prázdny odsek s odkazom — model
                // potom dostal tri slová a vrátil vymyslený odstavec.
                $length = app(\App\Services\Publishing\PublishReadiness::class)
                    ->textLength((string) $this->input('text'));

                if ($length < 50) {
                    $validator->errors()->add('text', __('validation.ai_text_too_short'));
                }
            },
        ];
    }
}
