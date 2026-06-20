<?php

namespace App\Http\Requests;

use App\Enums\FileType;
use App\Enums\ModelStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VenueStoreRequest extends FormRequest
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
        $allowedStatuses = array_column(
            ModelStatus::allowedForUser($this->user()),
            'value'
        );

        return [
            'canal_id' => ['required_without:canal_ids', 'integer', 'exists:canals,id'],
            'canal_ids' => ['sometimes', 'array', 'min:1'],
            'canal_ids.*' => ['integer', 'exists:canals,id'],
            'village_id' => 'required|integer|exists:municipalities,id',
            'name' => 'required|string|max:250',
            'street' => 'nullable|string|max:250',
            'postcode' => 'nullable|string|max:250',
            'body' => 'nullable|string',
            'website' => 'nullable|string|max:150',
            'email' => 'nullable|email|max:100',
            'phone' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'capacity' => 'nullable|integer',
            'opening_hours' => 'nullable|array',
            'category' => 'nullable|string|max:100',
            'status' => ['nullable', 'string', Rule::in($allowedStatuses)],
            'files' => ['sometimes', 'array'],
            'files.*' => ['file', 'max:10240'],
            'file_type' => ['sometimes', 'string', Rule::enum(FileType::class)],
            'file_disk' => ['sometimes', 'string', 'max:50'],
            'make_primary_file' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $user = $this->user();

            if ($user === null || $user->hasRole('super-admin')) {
                return;
            }

            $canalIds = collect($this->input('canal_ids', []));

            if ($canalIds->isEmpty() && $this->filled('canal_id')) {
                $canalIds = collect([$this->input('canal_id')]);
            }

            $unauthorizedCanalIds = $canalIds
                ->map(fn ($id) => (int) $id)
                ->diff($user->dashboardCanalIds())
                ->values();

            if ($unauthorizedCanalIds->isNotEmpty()) {
                $validator->errors()->add('canal_ids', 'Selected canal is not accessible.');
            }
        });
    }
}
