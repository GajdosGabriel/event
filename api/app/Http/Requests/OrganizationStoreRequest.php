<?php

namespace App\Http\Requests;

use App\Enums\ModelStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrganizationStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $allowedStatuses = array_column(
            ModelStatus::allowedForUser($this->user()),
            'value'
        );

        return [
            /*
             * Vlastný profil organizátora. Adresa tu zámerne nie je –
             * tú drží Account a číta sa cez `account.address`.
             */
            'village_id' => ['nullable', 'integer', 'exists:municipalities,id'],
            'person' => ['sometimes', 'boolean'],
            'title' => ['required', 'string', 'max:250'],
            'email' => ['nullable', 'email', 'max:100'],
            'description' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:20'],
            'website' => ['nullable', 'url', 'max:150'],
            'published' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::in($allowedStatuses)],

            /*
             * Kanál, ktorý pod touto firmou fakturuje. Do tabuľky `organizations`
             * sa neukladá — väzbu nesie `canals.organization_id`. Právo na kanál
             * sa overuje v controlleri (policy `update` nad kanálom), tu sa
             * kontroluje len existencia.
             */
            'canal_id' => ['sometimes', 'nullable', 'integer', 'exists:canals,id'],

            /*
             * Fakturačné údaje pre Account. Tvar sa tu kontroluje len zhruba,
             * aby sa do Accountu neposielal nezmysel — platnosť IČO a IČ DPH
             * rozhoduje Account, aby sa všetky projekty správali rovnako
             * a pravidlá neboli na troch miestach.
             */
            'account' => ['sometimes', 'array'],
            'account.name' => ['nullable', 'string', 'max:255'],
            'account.legal_name' => ['nullable', 'string', 'max:255'],
            'account.legal_form' => ['nullable', 'string', 'max:40'],
            'account.ico' => ['nullable', 'string', 'max:12'],
            'account.dic' => ['nullable', 'string', 'max:15'],
            'account.ic_dph' => ['nullable', 'string', 'max:15'],
            'account.vat_mode' => ['nullable', 'string', 'max:40'],
            'account.oss_registered' => ['sometimes', 'boolean'],
            'account.register_court' => ['nullable', 'string', 'max:255'],
            'account.register_section' => ['nullable', 'string', 'max:20'],
            'account.register_insert' => ['nullable', 'string', 'max:30'],
            'account.established_at' => ['nullable', 'date'],
            'account.street' => ['nullable', 'string', 'max:255'],
            'account.street_no' => ['nullable', 'string', 'max:30'],
            'account.city' => ['nullable', 'string', 'max:120'],
            'account.postal_code' => ['nullable', 'string', 'max:12'],
            'account.region' => ['nullable', 'string', 'max:80'],
            'account.country' => ['nullable', 'string', 'size:2'],
            'account.email' => ['nullable', 'email', 'max:255'],
            'account.billing_email' => ['nullable', 'email', 'max:255'],
            'account.phone' => ['nullable', 'string', 'max:40'],
            'account.website' => ['nullable', 'url', 'max:255'],
            'account.bank_name' => ['nullable', 'string', 'max:120'],
            'account.iban' => ['nullable', 'string', 'max:34'],
            'account.swift' => ['nullable', 'string', 'max:11'],
            'account.currency' => ['nullable', 'string', 'size:3'],
            'account.payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:180'],
            'account.payment_method' => ['nullable', 'in:transfer,card,cash,cod'],
            'account.invoice_language' => ['nullable', 'string', 'size:2'],
            'account.invoice_delivery' => ['nullable', 'in:email,post,both'],
            'account.supplier_number' => ['nullable', 'string', 'max:40'],
        ];
    }

    /** Fakturačné údaje pre Account — do lokálnej tabuľky sa neukladajú. */
    /** @return array<string, mixed> */
    public function accountData(): array
    {
        return $this->validated()['account'] ?? [];
    }

    /**
     * Lokálne polia organizácie, bez fakturačného bloku a bez `canal_id` —
     * ani jedno nie je stĺpec tabuľky `organizations`.
     */
    /** @return array<string, mixed> */
    public function organizationData(): array
    {
        return collect($this->validated())->except(['account', 'canal_id'])->all();
    }

    /** Kanál, ktorý sa má naviazať na firmu, ak ho klient poslal. */
    public function canalId(): ?int
    {
        $canalId = $this->validated()['canal_id'] ?? null;

        return $canalId !== null ? (int) $canalId : null;
    }
}
