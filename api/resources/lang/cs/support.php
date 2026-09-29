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
            'subject' => 'Podpora :reference: :subject',
            'intro' => ':name napsal(a) podpoře (:category, kontext: :context):',
        ],
        'replied' => [
            'subject_answered' => 'Podpora odpověděla — :reference :subject',
            'subject_user' => 'Doplnění k :reference: :subject',
            'intro' => ':name napsal(a):',
            'outro' => 'Na tento e-mail neodpovídejte — odepište přímo v konverzaci.',
        ],
    ],
];
