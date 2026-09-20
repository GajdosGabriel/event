<?php

return [
    'errors' => [
        'datetime_invalid' => 'The :attribute field must be a valid date.',
        'datetime_future' => 'The :attribute field must be a date in the future.',
        'end_after_start' => 'The :attribute field must be later than the start of the event.',
        'dependencies_not_published' => 'The event cannot be published until :names is published as well.',
        'dependency_forbidden' => 'You are not allowed to publish :name.',
    ],
    'dependencies' => [
        'venue' => 'venue :name',
        'canal' => 'canal :name',
    ],
    // Tlačidlo lístkov na karte podujatia (Event::ticketCta()).
    'ticket_cta' => [
        'buy' => 'Buy tickets',
        'reserve' => 'Reserve',
    ],
];
