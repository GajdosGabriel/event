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
            // Pripomienka účastníkom: koľko hodín pred začiatkom. null = neposielať.
            // Strop 336 h (14 dní) drží aj predvýber v app:events-send-reminders.
            'reminder_hours_before' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:336'],
        ];
    }
}
