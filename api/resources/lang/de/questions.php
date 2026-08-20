<?php

return [
    'attributes' => [
        'body' => 'Frage',
        'author_name' => 'Name',
        'author_email' => 'E-Mail-Adresse',
        'answer_body' => 'Antwort',
    ],

    'status' => [
        'pending' => 'Wartet auf Freigabe',
        'published' => 'Veröffentlicht',
        'hidden' => 'Ausgeblendet',
    ],

    'target' => [
        'event' => 'Veranstaltung',
        'workshop' => 'Workshop',
    ],

    'errors' => [
        'closed' => 'Für diese Veranstaltung können derzeit keine Fragen gestellt werden.',
        'duplicate' => 'Diese Frage haben Sie gerade schon gesendet.',
        'too_fast' => 'Das Formular wurde zu schnell abgeschickt. Bitte versuchen Sie es erneut.',
        'votes_disabled' => 'Das Abstimmen über Fragen ist auf dieser Pinnwand deaktiviert.',
        'not_votable' => 'Über diese Frage kann nicht abgestimmt werden.',
        'unknown_target' => 'Unbekannter Zieltyp der Pinnwand.',
        'workshop_only' => 'Eine Fragen-Pinnwand ist nur bei einem Workshop sinnvoll, nicht bei einer gewöhnlichen Ticketart.',
        'unknown_variant' => 'Unbekanntes Format oder Design der Folie.',
        'rendering_unavailable' => 'Auf dem Server fehlt die Textdarstellung (GD FreeType), die Folie kann nicht erstellt werden.',
    ],

    'slide' => [
        'eyebrow' => 'Fragen aus dem Publikum',
        'cta' => 'Scannen und fragen',
    ],
];
