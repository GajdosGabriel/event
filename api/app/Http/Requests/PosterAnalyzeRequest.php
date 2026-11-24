<?php

namespace App\Http\Requests;

use App\Services\Posters\PosterTextExtractor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

/**
 * Nahratie plagátu z verejnej stránky. Bez prihlásenia — účet pýtame až
 * pri ukladaní, keď už človek vidí, čo z toho vzniklo.
 */
class PosterAnalyzeRequest extends FormRequest
{
    /** Pod tento počet znakov samotný text AI nemá z čoho čítať. */
    private const MIN_TEXT = 30;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // 12 MB: bežný plagát v PDF má 1–4 MB, sken z mobilu do 8 MB.
        $file = ['file', 'max:12288', 'mimes:pdf,docx,txt,md,jpg,jpeg,png,webp'];

        return [
            // Súbory a text sa dajú poslať spolu (plagát + sprievodný text),
            // rovnako ako pozvánka e-mailom. `file` je pôvodné jedno pole.
            'file' => ['sometimes', ...$file],
            'files' => ['sometimes', 'array', 'max:'.PosterTextExtractor::MAX_FILES],
            'files.*' => $file,
            // Minimum dĺžky platí len pre samotný text — pri súbore stačí aj
            // krátka poznámka („vstup voľný").
            'text' => ['sometimes', 'nullable', 'string', 'max:20000'],
        ];
    }

    /** @return array<int, UploadedFile> */
    public function uploads(): array
    {
        $files = array_values(array_filter(
            [$this->file('file'), ...(array) $this->file('files', [])],
            static fn ($file) => $file instanceof UploadedFile,
        ));

        return array_slice($files, 0, PosterTextExtractor::MAX_FILES);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $text = trim((string) $this->input('text'));

            if ($this->uploads() === [] && $text === '') {
                $validator->errors()->add('file', __('poster.errors.input_missing'));
            } elseif ($this->uploads() === [] && mb_strlen($text) < self::MIN_TEXT) {
                $validator->errors()->add('text', __('poster.errors.text_min'));
            }
        });
    }

    public function messages(): array
    {
        return [
            'file.mimes' => __('poster.errors.file_mimes'),
            'file.max' => __('poster.errors.file_max'),
            'files.*.mimes' => __('poster.errors.file_mimes'),
            'files.*.max' => __('poster.errors.file_max'),
            'files.max' => __('poster.errors.files_max', ['max' => PosterTextExtractor::MAX_FILES]),
        ];
    }
}
