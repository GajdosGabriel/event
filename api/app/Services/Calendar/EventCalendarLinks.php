<?php

namespace App\Services\Calendar;

use App\Models\Event;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * „Pridať do kalendára" pre e-mail — jedno miesto pre všetky tri cesty, ktorými
 * sa podujatie dostane do kalendára príjemcu:
 *
 *  1. príloha `.ics` — Gmail aj Apple Mail nad ňou samy vykreslia tlačidlo,
 *  2. odkaz na stiahnutie toho istého súboru — pre klientov bez tlačidla,
 *  3. odkaz do Google Kalendára — pre webový Gmail, kde príloha nepomôže.
 *
 * Bez termínu (alebo bez podujatia) je všetko null a šablóna sekciu vynechá.
 */
final class EventCalendarLinks
{
    public readonly ?string $downloadUrl;

    public readonly ?string $googleUrl;

    private readonly ?string $ics;

    private readonly ?string $filename;

    public function __construct(?Event $event)
    {
        $generator = app(IcsGenerator::class);

        $ics = $event !== null ? $generator->forEvent($event) : null;

        $this->ics = $ics;
        $this->filename = $ics !== null ? $generator->filename($event) : null;
        $this->downloadUrl = $ics !== null ? $generator->downloadUrl($event) : null;
        $this->googleUrl = $ics !== null ? $generator->googleUrl($event) : null;
    }

    public function attachTo(MailMessage $mail): MailMessage
    {
        if ($this->ics === null) {
            return $mail;
        }

        return $mail->attachData($this->ics, $this->filename, [
            'mime' => 'text/calendar; charset=utf-8; method=PUBLISH',
        ]);
    }
}
