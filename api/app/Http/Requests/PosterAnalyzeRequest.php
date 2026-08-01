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
                $validator->errors()->add('file', 'Nahrajte plagát alebo vložte text pozvánky.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'file.mimes' => 'Podporujeme PDF, Word (.docx), obrázok plagátu (JPG, PNG, WEBP) alebo textový súbor.',
            'file.max' => 'Súbor je príliš veľký — maximum je 12 MB.',
            'text.min' => 'Text je príliš krátky na to, aby sa z neho dalo podujatie zostaviť.',
        ];
    }
}
