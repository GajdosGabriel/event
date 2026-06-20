<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActiveCanalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'canal_id' => [
                'required',
                'integer',
                Rule::exists('canal_user', 'canal_id')->where('user_id', $userId),
            ],
        ];
    }
}
