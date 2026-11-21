<?php

return [
    'errors' => [
        'datetime_invalid' => 'Das Feld :attribute muss ein gültiges Datum sein.',
        'datetime_future' => 'Das Feld :attribute muss ein Datum in der Zukunft sein.',
        'end_after_start' => 'Das Feld :attribute muss nach dem Beginn der Veranstaltung liegen.',
        'dependencies_not_published' => 'Die Veranstaltung kann erst veröffentlicht werden, wenn auch :names veröffentlicht ist.',
        'dependency_forbidden' => 'Sie dürfen :name nicht veröffentlichen.',
    ],
    'dependencies' => [
        'venue' => 'Ort :name',
        'canal' => 'Kanal :name',
    ],
    // Tlačidlo lístkov na karte podujatia (Event::ticketCta()).
    'ticket_cta' => [
        'buy' => 'Tickets kaufen',
        'reserve' => 'Reservieren',
    ],
    // Einladung mit QR-Code (Bereich „Werbung“ in der Veranstaltungsverwaltung).
    'invite' => [
        'eyebrow' => 'Herzliche Einladung',
        'cta_signup' => 'Scannen und anmelden',
        'cta_info' => 'Scannen für weitere Informationen',
        'unavailable' => 'Die Einladung konnte gerade nicht erstellt werden. Bitte versuchen Sie es später erneut.',
    ],
];
