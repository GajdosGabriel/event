<?php

// „Pripomeň mi" — odber podujatia bez účtu (App\Models\Subscription).
return [
    'attributes' => [
        'email' => 'E-Mail-Adresse',
    ],

    'errors' => [
        'too_fast' => 'Das Formular wurde zu schnell abgeschickt. Bitte versuchen Sie es erneut.',
        'event_started' => 'Diese Veranstaltung hat bereits begonnen, eine Erinnerung ergibt keinen Sinn mehr.',
    ],
];
