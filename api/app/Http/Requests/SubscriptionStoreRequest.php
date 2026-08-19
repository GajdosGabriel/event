<?php

namespace App\Http\Requests;

use App\Support\SubmissionTicket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class SubscriptionStoreRequest extends FormRequest
{
    /** Kým človek klikne na tlačidlo a naťuká adresu, uplynie viac než toľkoto. */
    private const MIN_FILL_SECONDS = 2;

    /** Detail podujatia otvorený v záložke celý deň je bežný, preto veľkoryso. */
    private const MAX_TICKET_AGE_SECONDS = 12 * 3600;

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
            // `email:filter` je prísnejšie než default a nechytá len tvar —
            // adresa tu je jediný údaj, ktorý zbierame, a preklep v nej znamená
            // e-mail cudziemu človeku.
            'email' => ['required', 'string', 'email:filter', 'max:190'],
            'ticket' => ['nullable', 'string', 'max:2000'],
            // Honeypot — rovnaká pasca ako pri otázkach: pole je mimo obrazovky
            // s `aria-hidden` a `tabindex="-1"`, človek doň nemá ako napísať.
            'website' => ['nullable', 'string', 'max:255'],
            // Jazyk, v ktorom si stránku čítal. Pripomienka má prísť v ňom.
            'locale' => ['nullable', 'string', 'max:5'],
        ];
    }

    public function attributes(): array
    {
        return [
            'email' => __('subscriptions.attributes.email'),
        ];
    }

    /**
     * Obe pasce vracajú tú istú hlášku — chyba nesmie botovi povedať, ktorá
     * z nich ho chytila.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (filled($this->input('website'))) {
                $validator->errors()->add('email', __('subscriptions.errors.too_fast'));

                return;
            }

            $valid = SubmissionTicket::isValid(
                $this->input('ticket'),
                'subscription:' . $this->route('event'),
                self::MIN_FILL_SECONDS,
                self::MAX_TICKET_AGE_SECONDS,
            );

            if (! $valid) {
                $validator->errors()->add('email', __('subscriptions.errors.too_fast'));
            }
        });
    }

    /** Adresa sa ukladá malými písmenami — inak by sa tá istá schránka odobrala dvakrát. */
    public function subscriptionEmail(): string
    {
        return Str::lower(trim((string) $this->input('email')));
    }

    /**
     * Jazyk beriem len ako dvojpísmenový kód a len zo zoznamu, ktorý naozaj
     * prekladáme — čokoľvek iné by v e-maile skončilo fallbackom aj tak.
     */
    public function subscriptionLocale(): ?string
    {
        $locale = Str::lower(substr(trim((string) $this->input('locale')), 0, 2));

        return in_array($locale, config('app.supported_locales', ['sk', 'cs', 'de', 'en']), true)
            ? $locale
            : null;
    }
}
