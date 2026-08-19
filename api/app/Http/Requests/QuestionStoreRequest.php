<?php

namespace App\Http\Requests;

use App\Support\SubmissionTicket;
use Illuminate\Foundation\Http\FormRequest;
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
}
