<?php

return [
    'status' => [
        'reserved'  => 'Rezervovaný',
        'confirmed' => 'Potvrzený',
        'cancelled' => 'Zrušený',
    ],
    'payment_status' => [
        'none'     => 'Zdarma',
        'pending'  => 'Čeká na platbu',
        'paid'     => 'Uhrazený',
        'failed'   => 'Platba selhala',
        'refunded' => 'Vrácený',
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
    // App\Enums\TicketTypeKindOption (volba „Druh" v UI = kind + open_to_public)
    'type_kind_option' => [
        'ticket'        => 'Vstupenka',
        'workshop'      => 'Workshop (jen pro registrované účastníky)',
        'workshop_open' => 'Workshop (i pro neregistrované na akci)',
    ],
    // Popisky polí ve formuláři typu lístku (front je čte z resource).
    'type_form' => [
        'workshop_starts_at' => 'Začátek workshopu',
        'workshop_ends_at'   => 'Konec workshopu',
        'sale_starts_at'     => 'Prodej od',
        'sale_ends_at'       => 'Prodej do',
    ],
    'errors' => [
        'kind_not_allowed' => 'Zvolený druh lístku pro tebe není dostupný.',
    ],
];
