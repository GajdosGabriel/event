<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QuestionBoardSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Autorizácia proti nástenke prebieha v controlleri (QuestionBoardPolicy).
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'is_open' => ['sometimes', 'boolean'],
            'moderation' => ['sometimes', 'boolean'],
            'show_questions' => ['sometimes', 'boolean'],
            'allow_upvotes' => ['sometimes', 'boolean'],
            'ask_for_name' => ['sometimes', 'boolean'],
            'allow_private' => ['sometimes', 'boolean'],
            'intro' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
