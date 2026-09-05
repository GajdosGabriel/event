<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TicketCheckinRequest extends FormRequest
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
            'qr_token' => ['required', 'string'],
            // Čas skenu z offline fronty skenera. Bez neho by sa všetkým, čo
            // prišli počas výpadku signálu, zapísal ten istý okamih — ten,
            // v ktorom sa spojenie vrátilo.
            'scanned_at' => ['nullable', 'date', 'before_or_equal:now', 'after:-1 day'],
        ];
    }
}
