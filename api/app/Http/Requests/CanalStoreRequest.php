<?php

namespace App\Http\Requests;

use App\Enums\CanalIdentityMode;
use App\Enums\FileType;
use App\Enums\ModelStatus;
use App\Rules\PhoneNumber;
use App\Rules\Postcode;
use App\Rules\WebsiteUrl;
use App\Services\Canals\CanalEmails;
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
            'email' => 'nullable|email:filter|max:150',
            // Ďalšie adresy kanála; primárna je vždy v `email` (CanalEmails).
            'additional_emails' => ['sometimes', 'nullable', 'array', 'max:'.(CanalEmails::MAX - 1)],
            'additional_emails.*' => ['required', 'email:filter', 'max:150', 'distinct:ignore_case'],
            'website' => ['nullable', 'string', 'max:150', new WebsiteUrl],
            'phone' => ['nullable', 'string', 'max:20', new PhoneNumber],
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'municipality_id' => 'required|integer|exists:municipalities,id',
            'street' => 'nullable|string|max:250',
            'postcode' => ['nullable', 'string', 'max:20', new Postcode],
            'country' => 'nullable|string|max:100',
            // Presnost suradnic: budova / adresa / odhad AI / stred obce / rucne.
            'coordinates_source' => ['nullable', 'string', Rule::in(['venue', 'address', 'ai', 'municipality', 'manual'])],
            // Kanál doteraz nemal cestu, ako si stav zmeniť — ostával na DB
            // defaulte `published`. Rovnaký zápis ako VenueStoreRequest.
            'status' => ['nullable', 'string', Rule::in(array_column(
                ModelStatus::allowedForUser($this->user()),
                'value'
            ))],
            'identity_mode' => ['sometimes', 'string', Rule::enum(CanalIdentityMode::class)],
            'files' => ['sometimes', 'array'],
            'files.*' => ['file', 'max:10240'],
            'file_type' => ['sometimes', 'string', Rule::enum(FileType::class)],
            'file_disk' => ['sometimes', 'string', 'max:50'],
            'make_primary_file' => ['sometimes', 'boolean'],
        ];
    }
}
