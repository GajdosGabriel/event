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
        'registration_disabled' => 'Registration for this event is not allowed.',
        'event_finished' => 'The event is over, registration is no longer possible.',
        'deadline_passed' => 'The registration deadline has passed.',
        'type_unavailable' => 'The selected ticket type is not available.',
        'sale_not_started' => 'Sales of the ticket “:name” have not started yet.',
        'sale_ended' => 'Sales of the ticket “:name” have already ended.',
        'min_per_order' => 'The minimum number of “:name” tickets per order is :count.',
        'max_per_order' => 'The maximum number of “:name” tickets per order is :count.',
        'nothing_selected' => 'You have not selected any ticket.',
        'workshop_requires_ticket' => 'Only attendees registered for the event can sign up for workshops.',
        'workshop_already_joined' => 'You are already signed up for this workshop.',
        'workshop_not_joined' => 'You are not signed up for this workshop.',
        'event_not_joined' => 'You are not registered for this event.',
        'workshop_locked_join' => 'The event has started — workshop sign-ups can no longer be changed.',
        'workshop_locked_leave' => 'The event has started — leaving a workshop is no longer possible.',
        'no_attendees' => 'Nobody has registered for this event yet.',
    ],

    'counts' => [
        'remaining' => '{1} Only :count spot is left for “:name”.|[2,*] Only :count spots are left for “:name”.',
        'workshop_entitlement' => '{1} You can order at most :count spot for the workshop “:name” — based on the number of event tickets.|[2,*] You can order at most :count spots for the workshop “:name” — based on the number of event tickets.',
    ],
];
