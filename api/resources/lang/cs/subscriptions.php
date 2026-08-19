<?php

// „Pripomeň mi" — odber podujatia bez účtu (App\Models\Subscription).
return [
    'attributes' => [
        'email' => 'e-mailová adresa',
    ],

    'errors' => [
        'too_fast' => 'Formulář se odeslal příliš rychle. Zkuste to prosím znovu.',
        'event_started' => 'Tato akce už začala, připomínku nemá co poslat.',
    ],
];
