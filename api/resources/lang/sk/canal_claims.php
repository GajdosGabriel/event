<?php

// Chyby žiadosti o prevzatie kanála (App\Services\Canals\CanalClaims).
return [
    'not_found' => 'Kanál neexistuje.',
    'already_managed' => 'Tento kanál už niekto spravuje. Požiadajte jeho vlastníka o pozvánku do tímu.',
    'invalid_method' => 'Neznámy spôsob overenia.',
    'already_requested' => 'O tento kanál ste už požiadali. Počkajte na potvrdenie.',
    'no_contact' => 'Kanál nemá kontaktný e-mail. Pošlite žiadosť na posúdenie administrátorovi.',
    'not_contestable' => 'Lehota na námietku uplynula alebo odkaz už nie je platný.',
    'not_contested' => 'Prevzatie nie je napadnuté.',
    'not_pending' => 'Žiadosť už nie je platná.',
    'too_many' => 'Máte už :max nevybavené žiadosti o kanály. Počkajte, kým ich niekto posúdi.',
];
