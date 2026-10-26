<?php

namespace App\Http\Requests;

use App\Enums\QuestionScope;
use App\Enums\QuestionType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EventQuestionStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Autorizácia proti podujatiu prebieha v controlleri (event.update).
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'label' => [$required, 'string', 'max:250'],
            'help' => ['nullable', 'string', 'max:500'],
            'scope' => [$required, Rule::enum(QuestionScope::class)],
            'type' => [$required, Rule::enum(QuestionType::class)],
            'is_required' => ['sometimes', 'boolean'],
            'ticket_type_ids' => ['nullable', 'array'],
            'ticket_type_ids.*' => ['integer'],
            'editable_by_attendee' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],

            // Možnosti výberových otázok. Riadok s `id` sa upraví, bez `id`
            // vznikne; možnosti, ktoré v zozname chýbajú, sa vypnú (odpovede ostanú).
            'options' => ['sometimes', 'array', 'max:50'],
            'options.*.id' => ['nullable', 'integer'],
            'options.*.label' => ['required', 'string', 'max:250'],
            'options.*.price_amount' => ['nullable', 'integer', 'min:0'],
            'options.*.capacity' => ['nullable', 'integer', 'min:1'],
            'options.*.is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** Výberová otázka bez možností by sa nedala zodpovedať. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = QuestionType::tryFrom((string) $this->input('type'));

            if ($type?->hasOptions() && $this->has('options') && count((array) $this->input('options')) === 0) {
                $validator->errors()->add('options', __('tickets.errors.question_needs_options'));
            }

            if ($type?->hasOptions() && $this->isMethod('POST') && ! $this->has('options')) {
                $validator->errors()->add('options', __('tickets.errors.question_needs_options'));
            }
        });
    }
}
