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

    'visibility' => [
        'public' => 'Öffentlich',
        'private' => 'Privat',
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
        'private_unavailable' => 'Private Fragen sind bei dieser Veranstaltung nicht möglich.',
        'private_needs_account' => 'Einen Hinweis während der Veranstaltung kann nur ein angemeldeter Teilnehmer senden.',
        'private_needs_email' => 'Für eine private Frage brauchen wir eine E-Mail-Adresse — die Antwort sehen Sie sonst nirgends.',
        'private_not_highlightable' => 'Eine private Frage lässt sich nicht hervorheben — sie steht nicht an der Wand.',
    ],

    'slide' => [
        'eyebrow' => 'Fragen aus dem Publikum',
        'cta' => 'Scannen und fragen',
    ],
];
