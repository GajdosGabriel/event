<?php

namespace App\Http\Requests;

use App\Enums\TicketTypeKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TicketTypeStoreRequest extends FormRequest
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
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:190'],
            'kind' => ['sometimes', Rule::enum(TicketTypeKind::class)],
            'description' => ['nullable', 'string', 'max:500'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'price_amount' => ['nullable', 'integer', 'min:0'],
            'price_currency' => ['sometimes', 'string', 'size:3'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'max_per_order' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'min_per_order' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'requires_attendee_name' => ['sometimes', 'boolean'],
            'sale_starts_at' => ['nullable', 'date'],
            'sale_ends_at' => ['nullable', 'date', 'after_or_equal:sale_starts_at'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
