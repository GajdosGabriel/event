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
];
