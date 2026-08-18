<?php

// Otázky z publika — verejná nástenka `/q/{token}`, moderovanie v dashboarde
// a texty vypálené do generovanej snímky.
return [
    'attributes' => [
        'body' => 'otázka',
        'author_name' => 'meno',
        'answer_body' => 'odpoveď',
    ],

    'status' => [
        'pending' => 'Čaká na schválenie',
        'published' => 'Zverejnená',
        'hidden' => 'Skrytá',
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
    ],

    // Texty vypálené do obrázka. Držať ich krátke — na snímke sú vo veľkom
    // a dlhší preklad by zmenšil nadpis podujatia.
    'slide' => [
        'eyebrow' => 'Otázky z publika',
        'cta' => 'Naskenujte a opýtajte sa',
    ],
];
