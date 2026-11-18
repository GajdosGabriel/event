<?php

return [
    'staff_name' => 'Podpora',
    'no_context' => 'bez kanálu',

    'status' => [
        'open' => 'Čeká na podporu',
        'answered' => 'Zodpovězeno',
        'closed' => 'Uzavřeno',
    ],

    'category' => [
        'question' => 'Dotaz',
        'problem' => 'Něco nefunguje',
        'canal' => 'Kanál a přístupy',
        'idea' => 'Návrh na zlepšení',
        'other' => 'Jiné',
    ],

    'mail' => [
        'action' => 'Otevřít konverzaci',
        'created' => [
            'subject' => 'Nový požadavek :reference – :subject',
            'intro' => 'Nový požadavek od :name (:category, kontext: :context):',
        ],
        'replied' => [
            'subject_answered' => 'Odpověď podpory :reference – :subject',
            'subject_user' => 'Doplnění :reference – :subject',
            'intro' => 'Nová zpráva od :name:',
            'intro_staff' => 'Odpověď podpory:',
            'outro' => 'Na tento e-mail neodpovídejte – odepište přímo v konverzaci.',
        ],
    ],
];
