<?php

// Popisky prehľadovej štatistiky (App\Services\Stats\OverviewStats).
// Front ich len vykresľuje — počítajú sa na serveri, tak sa tu aj prekladajú.
return [
    'periods' => [
        'day'   => 'Dnes',
        'week'  => 'Posledných 7 dní',
        'month' => 'Posledných 30 dní',
        'all'   => 'Celkovo',
    ],
    'metrics' => [
        'views'            => 'Zobrazenia',
        'events'           => 'Nové podujatia',
        'events_published' => 'Zverejnené podujatia',
        'tickets'          => 'Objednávky / rezervácie',
        'admissions'       => 'Vydané vstupenky',
        'checkins'         => 'Príchody (check-in)',
        'revenue'          => 'Zaplatené tržby',
        'venues'           => 'Nové miesta',
        'canals'           => 'Nové kanály',
        'messages'         => 'Prijaté správy',
        'users'            => 'Noví používatelia',
    ],
    'attention' => [
        'stale_drafts' => [
            'label' => 'Koncepty staršie ako týždeň',
            'hint'  => 'Rozpracované podujatia, ktoré nikto nezverejnil.',
        ],
        'past_drafts' => [
            'label' => 'Koncepty po termíne',
            'hint'  => 'Podujatie sa malo konať, no nikdy nebolo zverejnené.',
        ],
        'missing_image' => [
            'label' => 'Zverejnené bez obrázka',
            'hint'  => 'Vo výpise dostanú len zástupnú grafiku.',
        ],
        'empty_upcoming' => [
            'label' => 'Blíži sa termín bez prihlásených',
            'hint'  => 'Podujatia s registráciou do 7 dní, zatiaľ bez vstupeniek.',
        ],
        'overdue_confirmations' => [
            'label' => 'Nepotvrdené účasti po lehote',
            'hint'  => 'Miesta sú blokované, hoci lehota na potvrdenie uplynula.',
        ],
        'unpaid_orders' => [
            'label' => 'Neuhradené objednávky nad 3 dni',
            'hint'  => 'Držia miesto, ale platba neprišla.',
        ],
        'almost_sold_out' => [
            'label' => 'Takmer vypredané typy lístkov',
            'hint'  => 'Obsadenosť nad 90 % pri nadchádzajúcich podujatiach.',
        ],
        'unread_messages' => [
            'label' => 'Neprečítané správy',
            'hint'  => 'Otázky od návštevníkov, na ktoré nikto neodpovedal.',
        ],
    ],
];
