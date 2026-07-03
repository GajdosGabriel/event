<?php

namespace App\Http\Requests;

use App\Enums\FileType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FileStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fileable_type' => ['required', 'string', Rule::in(['canal', 'event', 'venue'])],
            'fileable_id'   => ['required', 'integer', 'min:1'],
            'files'         => ['required', 'array', 'min:1'],
            'files.*'       => ['file', 'max:10240', 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx'],
            'type'          => ['sometimes', 'string', Rule::enum(FileType::class)],
            'disk'          => ['sometimes', 'string', 'max:50'],
            'make_primary'  => ['sometimes', 'boolean'],
        ];
    }
}
