<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PasswordForgotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Zámerne bez `exists:users`: validačná chyba pri neznámej adrese by
     * z formulára spravila zoznam registrovaných používateľov. Rozhodnutie,
     * komu sa e-mail naozaj pošle, patrí controlleru — odpoveď je rovnaká tak
     * či tak.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => 'required|string|email|max:255',
        ];
    }
}
