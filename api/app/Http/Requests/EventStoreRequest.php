<?php

namespace App\Http\Requests;

use App\Enums\FileType;
use App\Enums\ModelStatus;
use App\Rules\EventDatetimeRule;
use App\Rules\WebsiteUrl;
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
            ModelStatus::allowedForEvent($this->user()),
            'value'
        );

        return [
            'name' => ['required', 'string', 'max:250'],
            'body' => ['nullable', 'string'],
            'start_at' => ['nullable', new EventDatetimeRule],
            'end_at' => ['nullable', new EventDatetimeRule],
            'registration_deadline_at' => ['nullable', 'date', 'before_or_equal:start_at'],
            'status' => ['sometimes', 'string', Rule::in($allowedStatuses)],
            // Súhlas z dialógu „miesto a kanál nie sú publikované — publikovať
            // aj ich?". Nie je to stĺpec, repozitár ho z payloadu vyberie.
            'publish_dependencies' => ['sometimes', 'boolean'],
            'canal_id' => ['nullable', 'integer', 'exists:canals,id'],
            'venue_id' => ['nullable', 'integer', 'exists:venues,id'],
            'published_at' => ['nullable', 'date'],
            // Naplánované publikovanie. `published_at` je čas prvého zverejnenia
            // (história), `publish_at` je čas, kedy sa má podujatie zverejniť —
            // preklopí ho príkaz app:events-publish-scheduled. Termín v minulosti
            // nemá zmysel: podujatie by vyšlo hneď pri najbližšom behu, čo je
            // „publikovať", nie „naplánovať".
            'publish_at' => $this->input('status') === ModelStatus::Scheduled->value
                ? ['required', 'date', 'after:now']
                : ['nullable', 'date'],
            'website' => ['nullable', 'string', 'max:150', new WebsiteUrl()],
            'email' => 'nullable|email|max:100',
            'phone' => 'nullable|string|max:20',
            'price_amount' => ['nullable', 'integer', 'min:0'],
            'price_currency' => ['sometimes', 'string', 'size:3'],
            // Chýbajúci kľúč znamená „štítkov sa nedotýkaj", prázdne pole
            // „odpoj všetky" — viď EloquentEventRepository::extractTagIds().
            'tag_ids' => ['sometimes', 'array', 'max:12'],
            'tag_ids.*' => ['integer', 'distinct', 'exists:tags,id'],
            'files' => ['sometimes', 'array'],
            'files.*' => ['file', 'max:10240'],
            'file_type' => ['sometimes', 'string', Rule::enum(FileType::class)],
            'file_disk' => ['sometimes', 'string', 'max:50'],
            'make_primary_file' => ['sometimes', 'boolean'],
        ];
    }
}
