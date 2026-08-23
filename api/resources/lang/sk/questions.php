<?php

// Otázky z publika — verejná nástenka `/q/{token}`, moderovanie v dashboarde
// a texty vypálené do generovanej snímky.
return [
    'attributes' => [
        'body' => 'otázka',
        'author_name' => 'meno',
        'author_email' => 'e-mailová adresa',
        'answer_body' => 'odpoveď',
    ],

    'status' => [
        'pending' => 'Čaká na schválenie',
        'published' => 'Zverejnená',
        'hidden' => 'Skrytá',
    ],

    // Komu je otázka určená. Súkromná sa nikde nezverejní a odpoveď na ňu
    // chodí len e-mailom — viď App\Enums\QuestionVisibility.
    'visibility' => [
        'public' => 'Verejná',
        'private' => 'Súkromná',
    ],

    'target' => [
        'event' => 'Podujatie',
        'workshop' => 'Workshop',
    ],

    'errors' => [
        'closed' => 'Otázky na toto podujatie sa práve nedajú pridávať.',
        'duplicate' => 'Túto otázku ste práve poslali.',
        'too_fast' => 'Formulár sa odoslal príliš rýchlo. Skúste to prosím znova.',
        'votes_disabled' => 'Hlasovanie za otázky je na tejto nástenke vypnuté.',
        'not_votable' => 'Za túto otázku sa hlasovať nedá.',
        'unknown_target' => 'Neznámy typ cieľa nástenky.',
        'workshop_only' => 'Nástenku otázok má zmysel zapnúť len na workshope, nie na bežnom type lístka.',
        'unknown_variant' => 'Neznámy formát alebo motív snímky.',
        'rendering_unavailable' => 'Server nemá nainštalovanú podporu pre vykresľovanie textu (GD FreeType), snímka sa nedá vytvoriť.',
        'private_unavailable' => 'Súkromné otázky sa na tomto podujatí posielať nedajú.',
        'private_needs_account' => 'Podnet počas akcie môže poslať len prihlásený účastník.',
        'private_needs_email' => 'Na súkromnú otázku potrebujeme e-mailovú adresu — odpoveď inde neuvidíte.',
        'private_not_highlightable' => 'Súkromnú otázku nie je kam zvýrazniť — na premietacej stene nie je.',
    ],

    // Texty vypálené do obrázka. Držať ich krátke — na snímke sú vo veľkom
    // a dlhší preklad by zmenšil nadpis podujatia.
    'slide' => [
        'eyebrow' => 'Otázky z publika',
        'cta' => 'Naskenujte a opýtajte sa',
    ],
];
