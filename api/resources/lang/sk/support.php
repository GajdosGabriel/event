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
            'subject' => 'Podpora :reference: :subject',
            'intro' => ':name napísal(a) podpore (:category, kontext: :context):',
        ],
        'replied' => [
            'subject_answered' => 'Podpora odpovedala — :reference :subject',
            'subject_user' => 'Doplnenie k :reference: :subject',
            'intro' => ':name napísal(a):',
            'outro' => 'Na tento e-mail neodpovedajte — odpíšte priamo v konverzácii.',
        ],
    ],
];
