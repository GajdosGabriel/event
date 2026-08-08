<?php

return [
    'periods' => [
        'day'   => 'Dnes',
        'week'  => 'Posledních 7 dní',
        'month' => 'Posledních 30 dní',
        'all'   => 'Celkově',
    ],
    'metrics' => [
        'views'            => 'Zobrazení',
        'events'           => 'Nové akce',
        'events_published' => 'Zveřejněné akce',
        'tickets'          => 'Objednávky / rezervace',
        'admissions'       => 'Vydané vstupenky',
        'checkins'         => 'Příchody (check-in)',
        'revenue'          => 'Zaplacené tržby',
        'venues'           => 'Nová místa',
        'canals'           => 'Nové kanály',
        'messages'         => 'Přijaté zprávy',
        'users'            => 'Noví uživatelé',
    ],
    'attention' => [
        'stale_drafts' => [
            'label' => 'Koncepty starší než týden',
            'hint'  => 'Rozpracované akce, které nikdo nezveřejnil.',
        ],
        'past_drafts' => [
            'label' => 'Koncepty po termínu',
            'hint'  => 'Akce se měla konat, ale nikdy nebyla zveřejněna.',
        ],
        'missing_image' => [
            'label' => 'Zveřejněné bez obrázku',
            'hint'  => 'Ve výpisu dostanou jen zástupnou grafiku.',
        ],
        'empty_upcoming' => [
            'label' => 'Blíží se termín bez přihlášených',
            'hint'  => 'Akce s registrací do 7 dní, zatím bez vstupenek.',
        ],
        'overdue_confirmations' => [
            'label' => 'Nepotvrzené účasti po lhůtě',
            'hint'  => 'Místa jsou blokovaná, ačkoli lhůta na potvrzení uplynula.',
        ],
        'unpaid_orders' => [
            'label' => 'Neuhrazené objednávky nad 3 dny',
            'hint'  => 'Drží místo, ale platba nepřišla.',
        ],
        'almost_sold_out' => [
            'label' => 'Téměř vyprodané typy lístků',
            'hint'  => 'Obsazenost nad 90 % u nadcházejících akcí.',
        ],
        'unread_messages' => [
            'label' => 'Nepřečtené zprávy',
            'hint'  => 'Otázky od návštěvníků, na které nikdo neodpověděl.',
        ],
    ],
];
