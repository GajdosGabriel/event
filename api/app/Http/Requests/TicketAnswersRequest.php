<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Úprava odpovedí dotazníka: `answers` za objednávku a `admissions`
 * ako {admission_id: {question_id: hodnota}}. Obsah overuje
 * App\Services\Tickets\Questionnaire.
 */
class TicketAnswersRequest extends FormRequest
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
        return [
            'answers' => ['sometimes', 'array'],
            'admissions' => ['sometimes', 'array'],
            'admissions.*' => ['array'],
        ];
    }
}
