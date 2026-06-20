<?php

namespace App\Http\Requests;

use App\Enums\ModelStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrganizationStoreRequest extends FormRequest
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
        $allowedStatuses = array_column(
            ModelStatus::allowedForUser($this->user()),
            'value'
        );

        return [
            'village_id' => ['nullable', 'integer', 'exists:municipalities,id'],
            'person' => ['sometimes', 'boolean'],
            'avatar' => ['nullable', 'string', 'max:200'],
            'title' => ['required', 'string', 'max:250'],
            'street' => ['nullable', 'string', 'max:191'],
            'psc' => ['nullable', 'integer'],
            'email' => ['nullable', 'email', 'max:100'],
            'description' => ['nullable', 'string'],
            'mod_title' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:20'],
            'phone_numeric' => ['nullable', 'string', 'max:20'],
            'youtube_channel' => ['nullable', 'string', 'max:40'],
            'youtube_playlist' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'url', 'max:150'],
            'published' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::in($allowedStatuses)],
        ];
    }
}
