<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TicketStoreRequest extends FormRequest
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
        // Prihlásený užívateľ môže rezervovať na jedno kliknutie – meno a e-mail
        // doplní backend z jeho účtu, takže sú povinné len pre hosťa.
        $requiredForGuest = Rule::requiredIf(fn () => ! auth('sanctum')->check());

        return [
            'holder_name' => [$requiredForGuest, 'nullable', 'string', 'max:250'],
            'holder_email' => [$requiredForGuest, 'nullable', 'email', 'max:190'],
            'holder_phone' => ['nullable', 'string', 'max:30'],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:10'],
        ];
    }
}
