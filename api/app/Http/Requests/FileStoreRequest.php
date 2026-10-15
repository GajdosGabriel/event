<?php

namespace App\Http\Requests;

use App\Enums\FileType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FileStoreRequest extends FormRequest
{
    /**
     * Povolené prílohy — zhodné s UPLOAD_ACCEPT na fronte. Platí pre každý
     * formulár, ktorý prijíma súbory (podujatie, kanál, miesto): `store()`
     * dáva príponu podľa obsahu, takže bez whitelistu by sa HTML, SVG či PHP
     * uložilo s vlastnou príponou a verejný disk by ho servíroval z našej domény.
     */
    public const FILE_RULES = ['file', 'max:10240', 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx'];

    /**
     * Len verejné disky. Voľný reťazec pustil súkromný `local` (súbor bez
     * funkčnej URL) a neexistujúci disk skončil 500-kou.
     */
    public const DISKS = ['public', 's3'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fileable_type' => ['required', 'string', Rule::in(['canal', 'event', 'venue'])],
            'fileable_id' => ['required', 'integer', 'min:1'],
            'files' => ['required', 'array', 'min:1'],
            'files.*' => self::FILE_RULES,
            'type' => ['sometimes', 'string', Rule::enum(FileType::class)],
            'disk' => ['sometimes', 'string', Rule::in(self::DISKS)],
            'make_primary' => ['sometimes', 'boolean'],
        ];
    }
}
