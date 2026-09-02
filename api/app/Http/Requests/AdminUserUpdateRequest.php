<?php

namespace App\Http\Requests;

use App\Enums\ModelStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Úprava účtu z admin rozhrania.
 *
 * Všetko je `sometimes` — ten istý endpoint obsluhuje celý formulár aj
 * jednotlivé akcie z detailu (zablokovať / odblokovať), takže sa nikdy
 * neposiela naraz. Čo v požiadavke nie je, ostáva nezmenené.
 */
class AdminUserUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                // Zmazané účty držia e-mail ďalej (soft delete), preto sa
                // unikátnosť kontroluje aj nad nimi — bez `withoutTrashed()`.
                Rule::unique('users', 'email')->ignore($this->route('user')),
            ],
            'status'          => ['sometimes', 'required', Rule::enum(ModelStatus::class)],
            // Overenie e-mailu vie admin potvrdiť aj zrušiť — napr. keď sa
            // adresa zmenila a má sa overiť znova.
            'email_verified'  => 'sometimes|boolean',
            // Prázdne pole = heslo sa nemení, preto `nullable`.
            'password'        => 'sometimes|nullable|string|min:8',
            // Osobný kanál. Kto ho má nastavený, je v ňom vlastník
            // (User::canalRole), takže sa smie ukázať len ako výber
            // z kanálov, ktorých je členom.
            'canal_id'        => 'sometimes|nullable|integer|exists:canals,id',
            'blocked'         => 'sometimes|boolean',
            'blocked_until'   => 'nullable|date|after:now',
            'blocked_reason'  => 'nullable|string|max:255',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'email'          => __('users.fields.email'),
            'status'         => __('users.fields.status'),
            'password'       => __('users.fields.password'),
            'canal_id'       => __('users.fields.canal_id'),
            'blocked_until'  => __('users.fields.blocked_until'),
            'blocked_reason' => __('users.fields.blocked_reason'),
        ];
    }
}
