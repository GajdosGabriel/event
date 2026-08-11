<?php

return [
    'errors' => [
        'last_canal'     => 'Das ist der letzte Kanal der Firma. Weisen Sie zuerst einen anderen zu, sonst verlieren Sie den Zugriff auf die Firma.',
        'canal_required' => 'Wählen Sie den Kanal, zu dem die Organisation gehören soll.',
    ],

    'account' => [
        'disabled'        => 'Die Anbindung an Account ist nicht eingerichtet.',
        'lookup_timeout'  => 'Das Register hat nicht rechtzeitig geantwortet. Versuchen Sie es erneut oder tragen Sie die Daten manuell ein.',
        'lookup_failed'   => 'Das Register ist derzeit nicht erreichbar.',
        'unavailable'     => 'Die Rechnungsdaten konnten nicht gespeichert werden — Account antwortet nicht. Versuchen Sie es gleich noch einmal.',
        'upstream_failed' => 'Die Rechnungsdaten konnten nicht gespeichert werden — Account hat mit einem Fehler geantwortet (HTTP :status). Details stehen im Log von Event.',
    ],
];
