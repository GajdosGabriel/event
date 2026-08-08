<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Nahratie plagátu z verejnej stránky. Bez prihlásenia — účet pýtame až
 * pri ukladaní, keď už človek vidí, čo z toho vzniklo.
 */
class PosterAnalyzeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // 12 MB: bežný plagát v PDF má 1–4 MB, sken z mobilu do 8 MB.
            'file' => [
                'sometimes',
                'file',
                'max:12288',
                'mimes:pdf,docx,txt,md,jpg,jpeg,png,webp',
            ],
            'text' => ['sometimes', 'string', 'min:30', 'max:20000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->hasFile('file') && blank($this->input('text'))) {
                $validator->errors()->add('file', __('poster.errors.input_missing'));
            }
        });
    }

    public function messages(): array
    {
        return [
            'file.mimes' => __('poster.errors.file_mimes'),
            'file.max' => __('poster.errors.file_max'),
            'text.min' => __('poster.errors.text_min'),
        ];
    }
}
