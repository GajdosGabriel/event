<?php

return [
    'errors' => [
        'dependencies_not_published' => 'The event cannot be published until :names is published as well.',
        'dependency_forbidden' => 'You are not allowed to publish :name.',
    ],
    'dependencies' => [
        'venue' => 'venue :name',
        'canal' => 'canal :name',
    ],
    // Tlačidlo lístkov na karte podujatia (Event::ticketCta()).
    'ticket_cta' => [
        'buy'     => 'Buy tickets',
        'reserve' => 'Reserve',
    ],
];
