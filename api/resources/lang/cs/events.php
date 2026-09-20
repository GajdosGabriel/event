<?php

return [
    'errors' => [
        'datetime_invalid' => 'Pole :attribute musí být platné datum.',
        'datetime_future' => 'Pole :attribute musí být datum v budoucnosti.',
        'end_after_start' => 'Pole :attribute musí být pozdější než začátek události.',
        'dependencies_not_published' => 'Událost nelze publikovat, dokud není publikováno také :names.',
        'dependency_forbidden' => 'K publikování :name nemáte právo.',
    ],
    'dependencies' => [
        'venue' => 'místo :name',
        'canal' => 'kanál :name',
    ],
    // Tlačidlo lístkov na karte podujatia (Event::ticketCta()).
    'ticket_cta' => [
        'buy' => 'Koupit vstupenku',
        'reserve' => 'Rezervovat',
    ],
];
