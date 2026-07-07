<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TicketingSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Autorizácia proti podujatiu prebieha v controlleri (event.update).
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tickets_enabled' => ['required', 'boolean'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'registration_deadline_at' => ['nullable', 'date'],
        ];
    }
}
