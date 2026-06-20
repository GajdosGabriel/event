<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VenueDetectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:250',
            'city' => 'required|string|max:250',
            'country' => 'nullable|string|max:100',
            'attach_image_to_model' => 'sometimes|boolean',
            'make_primary_image' => 'sometimes|boolean',
            'fileable_type' => ['required_if:attach_image_to_model,true', 'string', Rule::in(['canal', 'event', 'venue'])],
            'fileable_id' => ['required_if:attach_image_to_model,true', 'integer', 'min:1'],
        ];
    }
}
