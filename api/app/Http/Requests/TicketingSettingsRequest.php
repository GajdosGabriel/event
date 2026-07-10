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
            'workshop_lock_on_start' => ['sometimes', 'boolean'],
        ];
    }
}
