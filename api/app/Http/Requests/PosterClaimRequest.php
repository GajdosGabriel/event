<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Uloženie rozpracovaného plagátu ako podujatia. Okrem tokenu z nahratia nesie
 * aj opravy, ktoré človek spravil v sprievodcovi — tie majú prednosť pred AI.
 */
class PosterClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:128'],

            'overrides' => ['sometimes', 'array'],
            'overrides.title' => ['sometimes', 'nullable', 'string', 'max:250'],
            'overrides.start_at' => ['sometimes', 'nullable', 'date'],
            'overrides.end_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:overrides.start_at'],
            // Dĺžky sedia na stĺpce `events.email` (100) a `events.phone` (20) —
            // dlhšia hodnota by pri zápise spadla na chybe databázy namiesto
            // zrozumiteľnej validačnej hlášky.
            'overrides.email' => ['sometimes', 'nullable', 'email', 'max:100'],
            'overrides.phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'overrides.description' => ['sometimes', 'nullable', 'string', 'max:50000'],
            'overrides.canal_id' => ['sometimes', 'nullable', 'integer'],

            'overrides.venue' => ['sometimes', 'array'],
            'overrides.venue.name' => ['sometimes', 'nullable', 'string', 'max:250'],
            'overrides.venue.street_and_number' => ['sometimes', 'nullable', 'string', 'max:250'],
            'overrides.venue.city' => ['sometimes', 'nullable', 'string', 'max:250'],

            'overrides.organizer' => ['sometimes', 'array'],
            'overrides.organizer.name' => ['sometimes', 'nullable', 'string', 'max:250'],
            'overrides.organizer.city' => ['sometimes', 'nullable', 'string', 'max:250'],
        ];
    }

    public function messages(): array
    {
        return [
            'overrides.end_at.after_or_equal' => __('poster.errors.end_before_start'),
        ];
    }
}
