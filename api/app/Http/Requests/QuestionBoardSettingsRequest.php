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
            'intro' => ['sometimes', 'nullable', 'string', 'max:255'],
            // Okno sa dá aj zrušiť (null) — nástenka potom visí na `is_open`.
            // Poradie sa nekontroluje krížovo: organizátor si ho môže prepnúť
            // v ľubovoľnom poradí a medzistav by mu len hádzal chybu.
            'opens_at' => ['sometimes', 'nullable', 'date'],
            'closes_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
