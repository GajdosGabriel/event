<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Vstup pre geokódovanie rozpísanej adresy.
 *
 * Editor miesta posiela obec ako `village_id` (tak sa volá stĺpec vo `venues`),
 * editor kanála ako `municipality_id`. Endpoint je jeden, preto berie oboje —
 * ide o to isté id z toho istého číselníka.
 */
class AddressGeocodeRequest extends FormRequest
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
        return [
            'village_id' => 'required_without:municipality_id|integer|min:1',
            'municipality_id' => 'required_without:village_id|integer|min:1',
            'street' => 'nullable|string|max:250',
            'postcode' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
        ];
    }

    public function municipalityId(): int
    {
        return (int) ($this->input('municipality_id') ?? $this->input('village_id'));
    }
}
