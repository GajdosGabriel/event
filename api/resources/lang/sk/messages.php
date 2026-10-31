<?php

// Hlášky správ (App\Http\Requests\MessageStoreRequest). Pole `body` sa tu volá
// inak než vo validation.attributes — tam je `popis` podujatia, tu `správa`.
return [
    'attributes' => [
        'body' => 'správa',
    ],

    // App\Http\Controllers\Public\MessageController a jeho dashboardový náprotivok.
    'errors' => [
        'login_required' => 'Na poslanie správy sa musíte prihlásiť.',
        'verified_required' => 'Správy môžu posielať len účty s overeným e-mailom.',
        'not_contactable' => 'Tomuto cieľu nie je možné poslať správu.',
        'relay_unavailable' => 'Organizátorovi sa teraz správa nedá poslať.',
        'duplicate' => 'Túto správu ste práve poslali.',
        'relay_limit' => 'Príliš veľa správ. Skúste to neskôr.',
        'self' => 'Nemôžete poslať správu sami sebe.',
        'unknown_target' => 'Neznámy typ cieľa správy.',
        'sender_gone' => 'Odosielateľ správy už neexistuje, odpovedať sa nedá.',
    ],
];
