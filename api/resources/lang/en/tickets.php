<?php

return [
    'status' => [
        'reserved'  => 'Reserved',
        'confirmed' => 'Confirmed',
        'cancelled' => 'Cancelled',
    ],
    'payment_status' => [
        'none'     => 'Free',
        'pending'  => 'Awaiting payment',
        'paid'     => 'Paid',
        'failed'   => 'Payment failed',
        'refunded' => 'Refunded',
    ],
    'admission_status' => [
        'valid'      => 'Valid',
        'waitlisted' => 'Waitlisted',
        'cancelled'  => 'Cancelled',
    ],
    'type_kind' => [
        'ticket'   => 'Ticket',
        'workshop' => 'Workshop',
    ],
    // App\Enums\TicketTypeKindOption (voľba „Druh" v UI = kind + open_to_public)
    'type_kind_option' => [
        'ticket'        => 'Ticket',
        'workshop'      => 'Workshop (registered attendees only)',
        'workshop_open' => 'Workshop (open to non-attendees too)',
    ],
    // Popisky polí vo formulári typu lístka (front ich číta z resource).
    'type_form' => [
        'workshop_starts_at' => 'Workshop start',
        'workshop_ends_at'   => 'Workshop end',
        'sale_starts_at'     => 'On sale from',
        'sale_ends_at'       => 'On sale until',
    ],
    'errors' => [
        'kind_not_allowed' => 'The selected ticket kind is not available to you.',
    ],
];
