<?php

return [
    'staff_name' => 'Support',
    'no_context' => 'ohne Kanal',

    'status' => [
        'open' => 'Wartet auf Support',
        'answered' => 'Beantwortet',
        'closed' => 'Geschlossen',
    ],

    'category' => [
        'question' => 'Frage',
        'problem' => 'Etwas funktioniert nicht',
        'canal' => 'Kanal und Zugänge',
        'idea' => 'Verbesserungsvorschlag',
        'other' => 'Sonstiges',
    ],

    'mail' => [
        'action' => 'Unterhaltung öffnen',
        'created' => [
            'subject' => 'Support :reference: :subject',
            'intro' => ':name hat dem Support geschrieben (:category, Kontext: :context):',
        ],
        'replied' => [
            'subject_answered' => 'Der Support hat geantwortet — :reference :subject',
            'subject_user' => 'Ergänzung zu :reference: :subject',
            'intro' => ':name hat geschrieben:',
            'outro' => 'Bitte antworten Sie nicht auf diese E-Mail, sondern direkt in der Unterhaltung.',
        ],
    ],
];
