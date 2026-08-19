<?php

// Serverom vykreslené stránky pre crawlerov (App\Http\Controllers\Public\PrerenderController
// a resources/views/prerender). Znenie musí sedieť s `public.seo` v ui/src/i18n —
// crawler aj návštevník majú vidieť tú istú stránku.
return [
    'list' => [
        'heading' => 'Podujatia',
        'title' => 'Podujatia na Slovensku',
        'description' => 'Prehľad nadchádzajúcich koncertov, divadiel, workshopov a ďalších podujatí.',
        'weekend_heading' => 'Podujatia tento víkend',
        // Rozsah víkendu je v texte zámerne — landing stránka sa tým odlíši
        // od bežného výpisu aj vo výsledkoch vyhľadávania.
        'weekend_description' => 'Čo sa deje :from – :to: koncerty, divadlo, workshopy a podujatia pre rodiny.',
        'municipality_heading' => 'Podujatia — :name',
        'municipality_title' => 'Podujatia v obci :name',
        'municipality_description' => 'Nadchádzajúce podujatia v obci :name a okolí — koncerty, divadlo, workshopy.',
        'tag_heading' => 'Podujatia — :name',
        'tag_title' => ':name — podujatia',
        'tag_description' => 'Nadchádzajúce podujatia so štítkom :name.',
        'of_name' => 'Podujatia — :name',
    ],
    'venue_description' => 'Podujatia na mieste :name.',
    'canal_description' => 'Podujatia organizátora :name.',
    'not_found_title' => 'Stránka sa nenašla',
    'not_found_description' => 'Podujatie už nie je zverejnené alebo bola adresa zadaná nesprávne.',

    // Popisky v samotnej HTML odpovedi.
    'page' => [
        'upcoming' => 'Nadchádzajúce podujatia',
        'empty' => 'Momentálne tu nie sú žiadne nadchádzajúce podujatia.',
        'when' => 'Kedy',
        'where' => 'Kde',
        'organizer' => 'Organizátor',
        // Nadpis sekcie zodpovedaných otázok publika (JsonLd::faqPage).
        'faq' => 'Otázky a odpovede',
        'related' => 'Ďalšie podujatia — :name',
        'all_events' => 'Všetky podujatia',
        'weekend' => 'Podujatia tento víkend',
    ],
];
