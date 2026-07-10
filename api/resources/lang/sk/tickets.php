<?php

return [
    'status' => [
        'reserved'  => 'Rezervovaný',
        'confirmed' => 'Potvrdený',
        'cancelled' => 'Zrušený',
    ],
    'payment_status' => [
        'none'     => 'Zdarma',
        'pending'  => 'Čaká na platbu',
        'paid'     => 'Uhradený',
        'failed'   => 'Platba zlyhala',
        'refunded' => 'Vrátený',
    ],
    'admission_status' => [
        'valid'      => 'Platný',
        'waitlisted' => 'Náhradník',
        'cancelled'  => 'Zrušený',
    ],
    'type_kind' => [
        'ticket'   => 'Vstupenka',
        'workshop' => 'Workshop',
    ],
    // App\Enums\TicketTypeKindOption (voľba „Druh" v UI = kind + open_to_public)
    'type_kind_option' => [
        'ticket'        => 'Vstupenka',
        'workshop'      => 'Workshop (len pre registrovaných účastníkov)',
        'workshop_open' => 'Workshop (aj pre neregistrovaných na evente)',
    ],
    // Popisky polí vo formulári typu lístka (front ich číta z resource).
    'type_form' => [
        'workshop_starts_at' => 'Začiatok workshopu',
        'workshop_ends_at'   => 'Koniec workshopu',
        'sale_starts_at'     => 'Predaj od',
        'sale_ends_at'       => 'Predaj do',
    ],
    'errors' => [
        'kind_not_allowed' => 'Zvolený druh lístka nie je pre teba dostupný.',
    ],
];
