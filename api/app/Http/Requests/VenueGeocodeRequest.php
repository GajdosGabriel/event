<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VenueGeocodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'village_id' => 'required|integer|min:1',
            'street' => 'nullable|string|max:250',
            'postcode' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
        ];
    }
}
