<?php

// Podpora — App\Http\Controllers\Dashboard\DashboardSupportController a jej notifikácie.
return [
    'staff_name' => 'Podpora',
    'no_context' => 'bez kanála',

    'status' => [
        'open' => 'Čaká na podporu',
        'answered' => 'Odpovedané',
        'closed' => 'Uzavreté',
    ],

    'category' => [
        'question' => 'Otázka',
        'problem' => 'Niečo nefunguje',
        'canal' => 'Kanál a prístupy',
        'idea' => 'Návrh na zlepšenie',
        'other' => 'Iné',
    ],

    'mail' => [
        'action' => 'Otvoriť konverzáciu',
        'created' => [
            'subject' => 'Nová žiadosť :reference – :subject',
            'intro' => 'Nová žiadosť od :name (:category, kontext: :context):',
        ],
        'replied' => [
            'subject_answered' => 'Odpoveď podpory :reference – :subject',
            'subject_user' => 'Doplnenie :reference – :subject',
            'intro' => 'Nová správa od :name:',
            'intro_staff' => 'Odpoveď podpory:',
            'outro' => 'Na tento e-mail neodpovedajte – odpíšte priamo v konverzácii.',
        ],
    ],
];
