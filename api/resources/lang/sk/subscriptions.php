<?php

// „Pripomeň mi" — odber podujatia bez účtu (App\Models\Subscription).
return [
    'attributes' => [
        'email' => 'e-mailová adresa',
    ],

    'errors' => [
        'too_fast' => 'Formulár sa odoslal príliš rýchlo. Skúste to prosím znova.',
        'event_started' => 'Toto podujatie sa už začalo, pripomienku nemá čo poslať.',
    ],
];
