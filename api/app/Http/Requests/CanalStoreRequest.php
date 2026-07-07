<?php

namespace App\Http\Requests;

use App\Enums\CanalIdentityMode;
use App\Enums\FileType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CanalStoreRequest extends FormRequest
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
        $canalId = $this->route('id') ?? $this->route('canal');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                'min:3',
                Rule::unique('canals', 'name')->ignore($canalId),
            ],
            'title_prefix' => 'nullable|string|max:50',
            'title_suffix' => 'nullable|string|max:50',
            'body' => 'nullable|string',
            'email' => 'nullable|email|max:150',
            'website' => 'nullable|string|max:150',
            'phone' => 'nullable|string|max:20',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'municipality_id' => 'required|integer|exists:municipalities,id',
            'identity_mode' => ['sometimes', 'string', Rule::enum(CanalIdentityMode::class)],
            'files' => ['sometimes', 'array'],
            'files.*' => ['file', 'max:10240'],
            'file_type' => ['sometimes', 'string', Rule::enum(FileType::class)],
            'file_disk' => ['sometimes', 'string', 'max:50'],
            'make_primary_file' => ['sometimes', 'boolean'],
        ];
    }
}
