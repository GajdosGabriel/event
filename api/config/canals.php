<?php

return [
    // E-maily o zmenách vlastníctva kanála (CanalOwnershipChanged). Kým je
    // true, neodchádzajú — len sa zapíšu do denníka ako `mail.simulated`.
    'simulate_ownership_mail' => (bool) env('CANAL_OWNERSHIP_MAIL_SIMULATE', true),

    // Notifikácie ďalším členom tímu podľa ich nastavení (CanalRecipients).
    // Doterajší adresáti dostávajú e-maily normálne; noví sa kým je true
    // len zapíšu do denníka, a hlavným adresátom správ ostáva vlastník.
    'simulate_team_notifications' => (bool) env('CANAL_TEAM_NOTIFICATIONS_SIMULATE', true),

    // E-maily procesu prevzatia (CanalClaimNotice): potvrdenie z kontaktnej
    // schránky, žiadosť/námietka adminom, zamietnutie/vrátenie žiadateľovi.
    // Kým je true, idú len do denníka — odkaz na potvrdenie je v jeho kontexte.
    'simulate_claim_mail' => (bool) env('CANAL_CLAIM_MAIL_SIMULATE', true),

    // E-maily po zmene kontaktného e-mailu kanála (CanalContactNotice):
    // overenie na novú adresu, upozornenie na pôvodnú.
    'simulate_contact_mail' => (bool) env('CANAL_CONTACT_MAIL_SIMULATE', true),

    // Oslovenie organizátorov po akcii (app:canal-outreach). Pred vypnutím
    // simulácie overiť súlad s § 116 zákona č. 452/2021 Z. z.
    'simulate_outreach_mail' => (bool) env('CANAL_OUTREACH_MAIL_SIMULATE', true),

    'outreach' => [
        // Akcia skončila aspoň pred `after_days` a najviac pred `window_days` dňami.
        'after_days' => (int) env('CANAL_OUTREACH_AFTER_DAYS', 1),
        'window_days' => (int) env('CANAL_OUTREACH_WINDOW_DAYS', 14),
        // Ten istý kanál najviac raz za toľko dní.
        'cooldown_days' => (int) env('CANAL_OUTREACH_COOLDOWN_DAYS', 90),
        // Bez zobrazení nemáme čo ukázať — takú akciu nepíšeme.
        'min_views' => (int) env('CANAL_OUTREACH_MIN_VIEWS', 1),
        // Najviac oslovení za jeden beh.
        'limit' => (int) env('CANAL_OUTREACH_LIMIT', 20),
    ],
];
