<?php

return [
    'periods' => [
        'day'   => 'Today',
        'week'  => 'Last 7 days',
        'month' => 'Last 30 days',
        'all'   => 'All time',
    ],
    'metrics' => [
        'views'            => 'Views',
        'events'           => 'New events',
        'events_published' => 'Published events',
        'tickets'          => 'Orders / reservations',
        'admissions'       => 'Issued tickets',
        'checkins'         => 'Check-ins',
        'revenue'          => 'Paid revenue',
        'venues'           => 'New venues',
        'canals'           => 'New canals',
        'messages'         => 'Messages received',
        'users'            => 'New users',
    ],
    'attention' => [
        'stale_drafts' => [
            'label' => 'Drafts older than a week',
            'hint'  => 'Unfinished events nobody has published.',
        ],
        'past_drafts' => [
            'label' => 'Drafts past their date',
            'hint'  => 'The event was supposed to happen but was never published.',
        ],
        'missing_image' => [
            'label' => 'Published without an image',
            'hint'  => 'They only get placeholder artwork in listings.',
        ],
        'empty_upcoming' => [
            'label' => 'Date approaching with no sign-ups',
            'hint'  => 'Events with registration within 7 days, still without tickets.',
        ],
        'overdue_confirmations' => [
            'label' => 'Unconfirmed attendance past the deadline',
            'hint'  => 'Seats are held even though the confirmation deadline has passed.',
        ],
        'unpaid_orders' => [
            'label' => 'Unpaid orders over 3 days old',
            'hint'  => 'They hold a seat, but the payment never arrived.',
        ],
        'almost_sold_out' => [
            'label' => 'Almost sold-out ticket types',
            'hint'  => 'Over 90 % taken for upcoming events.',
        ],
        'unread_messages' => [
            'label' => 'Unread messages',
            'hint'  => 'Questions from visitors nobody has answered.',
        ],
    ],
];
