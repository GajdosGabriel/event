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
    // Hlášky objednávky a workshopov (App\Repositories\Eloquent\EloquentTicketRepository).
    // Vidí ich návštevník na verejnom detaile podujatia, preto sú v jeho jazyku.
    'errors' => [
        'kind_not_allowed' => 'Zvolený druh lístka nie je pre teba dostupný.',
        'registration_disabled' => 'Registrácia na tento event nie je povolená.',
        'event_finished' => 'Podujatie už prebehlo, registrácia nie je možná.',
        'deadline_passed' => 'Termín registrácie už uplynul.',
        'type_unavailable' => 'Vybraný typ lístka nie je k dispozícii.',
        'sale_not_started' => 'Predaj lístka „:name“ ešte nezačal.',
        'sale_ended' => 'Predaj lístka „:name“ už skončil.',
        'min_per_order' => 'Minimálny počet lístkov „:name“ na objednávku je :count.',
        'max_per_order' => 'Maximálny počet lístkov „:name“ na objednávku je :count.',
        'nothing_selected' => 'Nevybrali ste žiadny lístok.',
        'workshop_requires_ticket' => 'Na workshopy sa môžu prihlásiť len účastníci registrovaní na podujatie.',
        'workshop_already_joined' => 'Na tento workshop ste už prihlásený.',
        'workshop_not_joined' => 'Na tento workshop nie ste prihlásený.',
        'event_not_joined' => 'Na toto podujatie nie ste prihlásený.',
        'workshop_locked_join' => 'Podujatie už začalo — prihlásenie na workshopy sa už nedá meniť.',
        'workshop_locked_leave' => 'Podujatie už začalo — odhlásenie z workshopu už nie je možné.',
        'no_attendees' => 'Na toto podujatie zatiaľ nie je nikto prihlásený.',
    ],

    // Tvary podľa počtu (trans_choice). Rozsahy sú zapísané explicitne, takže
    // platia rovnako vo všetkých štyroch jazykoch.
    'counts' => [
        'remaining' => '{1} Pre „:name“ ostáva už len :count.|[2,4] Pre „:name“ ostávajú už len :count.|[5,*] Pre „:name“ ostáva už len :count.',
        'workshop_entitlement' => '{1} Na workshop „:name“ môžete objednať najviac :count miesto — podľa počtu vstupeniek na podujatie.|[2,4] Na workshop „:name“ môžete objednať najviac :count miesta — podľa počtu vstupeniek na podujatie.|[5,*] Na workshop „:name“ môžete objednať najviac :count miest — podľa počtu vstupeniek na podujatie.',
    ],
];
