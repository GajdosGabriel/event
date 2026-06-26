<?php

namespace App\Http\Requests;

use App\Enums\FileType;
use App\Enums\ModelStatus;
use App\Rules\EventDatetimeRule;
use App\Rules\ValidUrlRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EventStoreRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:250'],
            'body' => ['nullable', 'string'],
            'body_ai' => ['nullable', 'string'],
            'start_at' => ['nullable', new EventDatetimeRule],
            'end_at' => ['nullable', new EventDatetimeRule],
            'registration_deadline_at' => ['nullable', 'date', 'before_or_equal:start_at'],
            'status' => ['sometimes', 'string', Rule::in($allowedStatuses)],
            'canal_id' => ['nullable', 'integer', 'exists:canals,id'],
            'venue_id' => ['nullable', 'integer', 'exists:venues,id'],
            'published_at' => ['nullable', 'date'],
            'website' => [
                'nullable',
                'url',
                'max:150',
                new ValidUrlRule()
            ],
            'email' => 'nullable|email|max:100',
            'phone' => 'nullable|string|max:20',
            'files' => ['sometimes', 'array'],
            'files.*' => ['file', 'max:10240'],
            'file_type' => ['sometimes', 'string', Rule::enum(FileType::class)],
            'file_disk' => ['sometimes', 'string', 'max:50'],
            'make_primary_file' => ['sometimes', 'boolean'],
        ];
    }
}
