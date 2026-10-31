<?php

return [
    'attributes' => [
        'body' => 'zpráva',
    ],

    'errors' => [
        'login_required' => 'Pro odeslání zprávy se musíte přihlásit.',
        'verified_required' => 'Zprávy mohou posílat jen účty s ověřeným e-mailem.',
        'not_contactable' => 'Tomuto cíli nelze poslat zprávu.',
        'relay_unavailable' => 'Organizátorovi se zpráva teď nedá poslat.',
        'duplicate' => 'Tuto zprávu jste právě poslali.',
        'relay_limit' => 'Příliš mnoho zpráv. Zkuste to později.',
        'self' => 'Nemůžete poslat zprávu sami sobě.',
        'unknown_target' => 'Neznámý typ cíle zprávy.',
        'sender_gone' => 'Odesílatel zprávy už neexistuje, odpovědět nelze.',
    ],
];
