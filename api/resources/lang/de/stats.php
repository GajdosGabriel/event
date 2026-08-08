<?php

return [
    'periods' => [
        'day'   => 'Heute',
        'week'  => 'Letzte 7 Tage',
        'month' => 'Letzte 30 Tage',
        'all'   => 'Insgesamt',
    ],
    'metrics' => [
        'views'            => 'Aufrufe',
        'events'           => 'Neue Veranstaltungen',
        'events_published' => 'Veröffentlichte Veranstaltungen',
        'tickets'          => 'Bestellungen / Reservierungen',
        'admissions'       => 'Ausgegebene Tickets',
        'checkins'         => 'Einlass (Check-in)',
        'revenue'          => 'Bezahlte Umsätze',
        'venues'           => 'Neue Orte',
        'canals'           => 'Neue Kanäle',
        'messages'         => 'Erhaltene Nachrichten',
        'users'            => 'Neue Benutzer',
    ],
    'attention' => [
        'stale_drafts' => [
            'label' => 'Entwürfe älter als eine Woche',
            'hint'  => 'Angefangene Veranstaltungen, die niemand veröffentlicht hat.',
        ],
        'past_drafts' => [
            'label' => 'Entwürfe nach dem Termin',
            'hint'  => 'Die Veranstaltung hätte stattfinden sollen, wurde aber nie veröffentlicht.',
        ],
        'missing_image' => [
            'label' => 'Veröffentlicht ohne Bild',
            'hint'  => 'In der Übersicht bekommen sie nur eine Platzhaltergrafik.',
        ],
        'empty_upcoming' => [
            'label' => 'Termin rückt näher, keine Anmeldungen',
            'hint'  => 'Veranstaltungen mit Anmeldung in den nächsten 7 Tagen, bisher ohne Tickets.',
        ],
        'overdue_confirmations' => [
            'label' => 'Unbestätigte Teilnahmen nach Frist',
            'hint'  => 'Plätze sind blockiert, obwohl die Bestätigungsfrist abgelaufen ist.',
        ],
        'unpaid_orders' => [
            'label' => 'Unbezahlte Bestellungen über 3 Tage',
            'hint'  => 'Sie halten einen Platz, aber die Zahlung ist nicht eingegangen.',
        ],
        'almost_sold_out' => [
            'label' => 'Fast ausverkaufte Ticketarten',
            'hint'  => 'Auslastung über 90 % bei kommenden Veranstaltungen.',
        ],
        'unread_messages' => [
            'label' => 'Ungelesene Nachrichten',
            'hint'  => 'Fragen von Besuchern, die niemand beantwortet hat.',
        ],
    ],
];
