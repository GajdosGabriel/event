<?php

namespace App\Http\Requests;

use App\Enums\AnnouncementPlacement;
use App\Enums\AnnouncementVariant;
use App\Enums\ModelStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnnouncementStoreRequest extends FormRequest
{
    /** Prístup rieši `role:super-admin` na route skupine. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $allowedStatuses = array_column(
            ModelStatus::allowedForUser($this->user()),
            'value'
        );

        return [
            'placement' => ['required', Rule::in(AnnouncementPlacement::values())],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'variant' => ['required', Rule::in(AnnouncementVariant::values())],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'published_from' => ['nullable', 'date'],
            'published_until' => ['nullable', 'date', 'after_or_equal:published_from'],
            'status' => ['required', Rule::in($allowedStatuses)],
        ];
    }

    /** @return array<string, mixed> */
    public function announcementData(): array
    {
        $data = $this->validated();
        $data['sort_order'] = $data['sort_order'] ?? 0;

        return $data;
    }
}
