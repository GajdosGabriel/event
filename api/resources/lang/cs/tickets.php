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
        'registration_disabled' => 'Registrace na tuto akci není povolena.',
        'event_finished' => 'Akce už proběhla, registrace není možná.',
        'deadline_passed' => 'Termín registrace už uplynul.',
        'type_unavailable' => 'Vybraný typ lístku není k dispozici.',
        'sale_not_started' => 'Prodej lístku „:name“ ještě nezačal.',
        'sale_ended' => 'Prodej lístku „:name“ už skončil.',
        'min_per_order' => 'Minimální počet lístků „:name“ na objednávku je :count.',
        'max_per_order' => 'Maximální počet lístků „:name“ na objednávku je :count.',
        'nothing_selected' => 'Nevybrali jste žádný lístek.',
        'workshop_requires_ticket' => 'Na workshopy se mohou přihlásit jen účastníci registrovaní na akci.',
        'workshop_already_joined' => 'Na tento workshop jste už přihlášen.',
        'workshop_not_joined' => 'Na tento workshop nejste přihlášen.',
        'event_not_joined' => 'Na tuto akci nejste přihlášen.',
        'workshop_locked_join' => 'Akce už začala — přihlášení na workshopy už nelze měnit.',
        'workshop_locked_leave' => 'Akce už začala — odhlášení z workshopu už není možné.',
        'no_attendees' => 'Na tuto akci zatím není nikdo přihlášen.',
    ],

    'counts' => [
        'remaining' => '{1} Pro „:name“ zbývá už jen :count.|[2,4] Pro „:name“ zbývají už jen :count.|[5,*] Pro „:name“ zbývá už jen :count.',
        'workshop_entitlement' => '{1} Na workshop „:name“ můžete objednat nejvýše :count místo — podle počtu vstupenek na akci.|[2,4] Na workshop „:name“ můžete objednat nejvýše :count místa — podle počtu vstupenek na akci.|[5,*] Na workshop „:name“ můžete objednat nejvýše :count míst — podle počtu vstupenek na akci.',
    ],
];
