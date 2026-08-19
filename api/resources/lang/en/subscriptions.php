<?php

// „Pripomeň mi" — odber podujatia bez účtu (App\Models\Subscription).
return [
    'attributes' => [
        'email' => 'e-mail address',
    ],

    'errors' => [
        'too_fast' => 'The form was submitted too quickly. Please try again.',
        'event_started' => 'This event has already started, there is nothing left to remind you about.',
    ],
];
