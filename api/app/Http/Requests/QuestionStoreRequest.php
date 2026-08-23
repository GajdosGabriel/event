<?php

namespace App\Http\Requests;

use App\Enums\QuestionVisibility;
use App\Support\SubmissionTicket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class QuestionStoreRequest extends FormRequest
{
    /** Kým človek prečíta otázku a napíše ju, uplynie viac než toľkoto sekúnd. */
    private const MIN_FILL_SECONDS = 3;

    /** Nástenka otvorená celý deň v jednej záložke je bežná, preto veľkoryso. */
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
            // 500 znakov je asi šesť riadkov. Kto potrebuje viac, nepíše otázku
            // pre prednášajúceho, ale príspevok do diskusie — na to sú správy.
            'body' => ['required', 'string', 'min:3', 'max:500'],
            'author_name' => ['nullable', 'string', 'max:80'],
            // Komu je otázka určená. Chýbajúca hodnota je „verejná" — to bolo
            // jediné možné správanie predtým a musí ním zostať aj pre klienta,
            // ktorý o prepínači nevie.
            'visibility' => ['sometimes', 'string', Rule::enum(QuestionVisibility::class)],
            // „Dajte mi vedieť, keď organizátor odpovie." Zaškrtávacie pole,
            // nie predvolený stav — adresu pýtame len od toho, kto o odpoveď
            // naozaj stojí. Pri súkromnej otázke sa nepýtame vôbec: inde než
            // v e-maile sa odpoveď zobraziť nemá kde.
            'notify' => ['sometimes', 'boolean'],
            // Povinná až so zaškrtnutím (alebo pri súkromnej otázke, kde je
            // zaškrtnutie automatické), a to len keď adresu nevieme odinakiaľ.
            // Prihlásenému ju doplní server z účtu, presne ako pri lístkoch
            // (TicketStoreRequest).
            'author_email' => ['nullable', 'string', 'email:filter', 'max:190', Rule::requiredIf(
                fn () => $this->wantsAnswerNotification() && ! auth('sanctum')->check(),
            )],
            // Jazyk, v ktorom si stránku čítal. Odpoveď má prísť v ňom.
            'locale' => ['nullable', 'string', 'max:5'],
            'ticket' => ['nullable', 'string', 'max:2000'],
            // Honeypot. Pole má v UI `aria-hidden`, `tabindex="-1"` a je mimo
            // obrazovky — človek doň nemá ako napísať, automat ho vyplní.
            'website' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'body' => __('questions.attributes.body'),
            'author_name' => __('questions.attributes.author_name'),
            'author_email' => __('questions.attributes.author_email'),
        ];
    }

    /**
     * Obe pasce vracajú tú istú hlášku ako „príliš rýchlo" — chyba nesmie
     * botovi povedať, ktorá z nich ho chytila.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (filled($this->input('website'))) {
                $validator->errors()->add('body', __('questions.errors.too_fast'));

                return;
            }

            $valid = SubmissionTicket::isValid(
                $this->input('ticket'),
                $this->submissionScope(),
                self::MIN_FILL_SECONDS,
                self::MAX_TICKET_AGE_SECONDS,
            );

            if (! $valid) {
                $validator->errors()->add('body', __('questions.errors.too_fast'));
            }
        });
    }

    /**
     * Účel známky. Na tú istú nástenku vedú dve cesty a každá má vlastný
     * rozsah, aby sa známka vydaná pre jednu nedala použiť na druhej:
     *
     * - `/q/{token}` — QR premietnutý v sále,
     * - `/events/{event}/questions` — verejný detail podujatia, kde token
     *   zámerne nie je (dá sa rotovať a nemá sa šíriť mimo QR).
     */
    private function submissionScope(): string
    {
        $token = $this->route('token');

        return $token !== null
            ? 'question:' . $token
            : 'question:event:' . $this->route('event');
    }

    /** Otázka je čistý text — riadkovanie sa zachová, biele okraje nie. */
    public function questionBody(): string
    {
        return trim((string) $this->input('body'));
    }

    public function questionAuthorName(): ?string
    {
        $name = trim((string) $this->input('author_name'));

        return $name !== '' ? $name : null;
    }

    /** Komu je otázka určená. Bez hodnoty verejná — to je pôvodné správanie. */
    public function questionVisibility(): QuestionVisibility
    {
        return QuestionVisibility::tryFrom((string) $this->input('visibility'))
            ?? QuestionVisibility::Public;
    }

    /**
     * Chce sa pisateľ dozvedieť odpoveď e-mailom?
     *
     * Pri súkromnej otázke to nie je voľba, ale dôsledok: odpoveď nebude vo
     * verejnom zozname ani na stene, takže bez e-mailu by sa k pisateľovi
     * nedostala nijako. Preto sa zaškrtnutie neposiela z formulára — vyplýva
     * z voľby „súkromná" a odvodzuje sa tu, na serveri, aby ho nešlo obísť
     * podstrčeným formulárom.
     */
    public function wantsAnswerNotification(): bool
    {
        return $this->boolean('notify') || $this->questionVisibility()->requiresContact();
    }

    /** Adresa sa ukladá malými písmenami — rovnako ako pri odberoch. */
    public function questionAuthorEmail(): ?string
    {
        $email = Str::lower(trim((string) $this->input('author_email')));

        return $email !== '' ? $email : null;
    }

    /**
     * Jazyk beriem len ako dvojpísmenový kód a len zo zoznamu, ktorý naozaj
     * prekladáme — čokoľvek iné by v e-maile skončilo fallbackom aj tak.
     * Rovnaké pravidlo ako v SubscriptionStoreRequest.
     */
    public function questionLocale(): ?string
    {
        $locale = Str::lower(substr(trim((string) $this->input('locale')), 0, 2));

        return in_array($locale, config('app.supported_locales', ['sk', 'cs', 'de', 'en']), true)
            ? $locale
            : null;
    }
}
