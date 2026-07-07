<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TicketStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
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

            // Nový tvar: výber lístkov po typoch.
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.ticket_type_id' => ['required_with:items', 'integer'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1', 'max:20'],
            'items.*.attendees' => ['sometimes', 'array'],
            'items.*.attendees.*.name' => ['nullable', 'string', 'max:250'],

            // Spätná kompatibilita: len počet miest (default typ lístka).
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ];
    }
}
