<?php

namespace App\Http\Requests;

use App\Models\Message;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MessageStoreRequest extends FormRequest
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
        // Prihlásený odosielateľ má meno aj e-mail z účtu — vyžadujeme ich len od hosťa.
        $requiredForGuest = Rule::requiredIf(fn () => ! auth('sanctum')->check());

        return [
            // Cieľ správy — typ z whitelistu (Message::TARGETS) + id. Existenciu
            // a kontaktovateľnosť rieši controller.
            'target_type' => ['required', 'string', Rule::in(array_keys(Message::TARGETS))],
            'target_id' => ['required', 'integer', 'min:1'],
            'body' => ['required', 'string', 'min:2', 'max:5000'],
            'sender_name' => [$requiredForGuest, 'nullable', 'string', 'max:250'],
            'sender_email' => [$requiredForGuest, 'nullable', 'email', 'max:190'],
        ];
    }

    public function attributes(): array
    {
        return [
            'body' => 'správa',
            'sender_name' => 'meno',
            'sender_email' => 'e-mail',
        ];
    }
}
