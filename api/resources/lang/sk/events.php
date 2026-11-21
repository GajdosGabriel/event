<?php

// Hlášky podujatí (App\Exceptions\DependenciesNotPublishedException a spol.).
return [
    'errors' => [
        'datetime_invalid' => 'Pole :attribute musí byť platný dátum.',
        'datetime_future' => 'Pole :attribute musí byť dátum v budúcnosti.',
        'end_after_start' => 'Pole :attribute musí byť neskoršie ako začiatok podujatia.',
        'dependencies_not_published' => 'Podujatie sa nedá publikovať, kým nie je publikované aj :names.',
        'dependency_forbidden' => 'Na publikovanie :name nemáte právo.',
    ],
    'dependencies' => [
        'venue' => 'miesto :name',
        'canal' => 'kanál :name',
    ],
    // Tlačidlo lístkov na karte podujatia (Event::ticketCta()).
    'ticket_cta' => [
        'buy' => 'Kúpiť lístok',
        'reserve' => 'Rezervovať',
    ],
    // Pozvánka s QR kódom (panel Propagácia v správe akcie).
    'invite' => [
        'eyebrow' => 'Pozývame vás',
        'cta_signup' => 'Naskenujte a prihláste sa',
        'cta_info' => 'Naskenujte pre viac informácií',
        'unavailable' => 'Pozvánku sa teraz nepodarilo vykresliť. Skúste to neskôr.',
    ],
];
