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
        'registration_disabled' => 'Die Anmeldung zu dieser Veranstaltung ist nicht erlaubt.',
        'event_finished' => 'Die Veranstaltung ist vorbei, eine Anmeldung ist nicht mehr möglich.',
        'deadline_passed' => 'Die Anmeldefrist ist abgelaufen.',
        'type_unavailable' => 'Die gewählte Ticketart ist nicht verfügbar.',
        'sale_not_started' => 'Der Verkauf des Tickets „:name“ hat noch nicht begonnen.',
        'sale_ended' => 'Der Verkauf des Tickets „:name“ ist bereits beendet.',
        'min_per_order' => 'Die Mindestanzahl der Tickets „:name“ pro Bestellung ist :count.',
        'max_per_order' => 'Die Höchstanzahl der Tickets „:name“ pro Bestellung ist :count.',
        'nothing_selected' => 'Sie haben kein Ticket ausgewählt.',
        'workshop_requires_ticket' => 'Für Workshops können sich nur Teilnehmende anmelden, die für die Veranstaltung registriert sind.',
        'workshop_already_joined' => 'Für diesen Workshop sind Sie bereits angemeldet.',
        'workshop_not_joined' => 'Für diesen Workshop sind Sie nicht angemeldet.',
        'event_not_joined' => 'Für diese Veranstaltung sind Sie nicht angemeldet.',
        'workshop_locked_join' => 'Die Veranstaltung hat begonnen — die Workshop-Anmeldung lässt sich nicht mehr ändern.',
        'workshop_locked_leave' => 'Die Veranstaltung hat begonnen — die Abmeldung vom Workshop ist nicht mehr möglich.',
        'no_attendees' => 'Für diese Veranstaltung ist noch niemand angemeldet.',
    ],

    'counts' => [
        'remaining' => '{1} Für „:name“ ist nur noch :count Platz frei.|[2,*] Für „:name“ sind nur noch :count Plätze frei.',
        'workshop_entitlement' => '{1} Für den Workshop „:name“ können Sie höchstens :count Platz bestellen — je nach Anzahl der Tickets für die Veranstaltung.|[2,*] Für den Workshop „:name“ können Sie höchstens :count Plätze bestellen — je nach Anzahl der Tickets für die Veranstaltung.',
    ],
];
