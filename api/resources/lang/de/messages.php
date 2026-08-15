<?php

return [
    'attributes' => [
        'body' => 'Nachricht',
    ],

    'errors' => [
        'login_required' => 'Zum Senden einer Nachricht müssen Sie sich anmelden.',
        'verified_required' => 'Nachrichten können nur Konten mit bestätigter E-Mail senden.',
        'not_contactable' => 'An dieses Ziel lässt sich keine Nachricht senden.',
        'self' => 'Sie können sich selbst keine Nachricht senden.',
        'unknown_target' => 'Unbekannter Zieltyp der Nachricht.',
        'sender_gone' => 'Der Absender der Nachricht existiert nicht mehr, eine Antwort ist nicht möglich.',
    ],
];
