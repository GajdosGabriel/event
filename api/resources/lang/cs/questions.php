<?php

return [
    'attributes' => [
        'body' => 'otázka',
        'author_name' => 'jméno',
        'author_email' => 'e-mailová adresa',
        'answer_body' => 'odpověď',
    ],

    'status' => [
        'pending' => 'Čeká na schválení',
        'published' => 'Zveřejněná',
        'hidden' => 'Skrytá',
    ],

    'visibility' => [
        'public' => 'Veřejná',
        'private' => 'Soukromá',
    ],

    'target' => [
        'event' => 'Akce',
        'workshop' => 'Workshop',
    ],

    'errors' => [
        'closed' => 'Otázky k této akci teď nelze přidávat.',
        'duplicate' => 'Tuto otázku jste právě poslali.',
        'too_fast' => 'Formulář se odeslal příliš rychle. Zkuste to prosím znovu.',
        'votes_disabled' => 'Hlasování o otázkách je na této nástěnce vypnuté.',
        'not_votable' => 'Pro tuto otázku nelze hlasovat.',
        'unknown_target' => 'Neznámý typ cíle nástěnky.',
        'workshop_only' => 'Nástěnku otázek má smysl zapnout jen u workshopu, ne u běžného typu vstupenky.',
        'unknown_variant' => 'Neznámý formát nebo motiv snímku.',
        'rendering_unavailable' => 'Server nemá nainstalovanou podporu pro vykreslování textu (GD FreeType), snímek nelze vytvořit.',
        'private_unavailable' => 'Soukromé otázky u této akce posílat nelze.',
        'private_needs_account' => 'Podnět během akce může poslat jen přihlášený účastník.',
        'private_needs_email' => 'U soukromé otázky potřebujeme e-mailovou adresu — odpověď jinde neuvidíte.',
        'private_not_highlightable' => 'Soukromou otázku není kam zvýraznit — na promítací stěně není.',
    ],

    'slide' => [
        'eyebrow' => 'Otázky z publika',
        'cta' => 'Naskenujte a zeptejte se',
    ],
];
