<?php

return [
    'status' => [
        'reserved'  => 'Reserviert',
        'confirmed' => 'Bestätigt',
        'cancelled' => 'Storniert',
    ],
    'payment_status' => [
        'none'     => 'Kostenlos',
        'pending'  => 'Zahlung ausstehend',
        'paid'     => 'Bezahlt',
        'failed'   => 'Zahlung fehlgeschlagen',
        'refunded' => 'Erstattet',
    ],
    'admission_status' => [
        'valid'      => 'Gültig',
        'waitlisted' => 'Auf der Warteliste',
        'cancelled'  => 'Storniert',
    ],
    'type_kind' => [
        'ticket'   => 'Ticket',
        'workshop' => 'Workshop',
    ],
    // App\Enums\TicketTypeKindOption (voľba „Druh" v UI = kind + open_to_public)
    'type_kind_option' => [
        'ticket'        => 'Ticket',
        'workshop'      => 'Workshop (nur für angemeldete Teilnehmer)',
        'workshop_open' => 'Workshop (auch für nicht angemeldete Teilnehmer)',
    ],
    // Popisky polí vo formulári typu lístka (front ich číta z resource).
    'type_form' => [
        'workshop_starts_at' => 'Beginn des Workshops',
        'workshop_ends_at'   => 'Ende des Workshops',
        'sale_starts_at'     => 'Verkauf ab',
        'sale_ends_at'       => 'Verkauf bis',
    ],
    'errors' => [
        'kind_not_allowed' => 'Die gewählte Ticketart steht Ihnen nicht zur Verfügung.',
    ],
];
