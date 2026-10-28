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

    // Otázky návštevníkov preposielané organizátorom importovaných kanálov
    // (QuestionRelay). Ide o nevyžiadaný e-mail na verejnú adresu — pred
    // vypnutím simulácie overiť súlad s § 116 zákona č. 452/2021 Z. z.
    // Kópia adminom odchádza vždy.
    'simulate_question_relay_mail' => (bool) env('CANAL_QUESTION_RELAY_MAIL_SIMULATE', true),

    // Oslovenie organizátorov (app:canal-outreach) — triggery pred akciou,
    // prah zobrazení, po akcii. Hlásia sa len do Account Emailingu (text
    // a oneskorenie sú v kampani); bez ACCOUNT_MAILING sa neoslovuje. Pred
    // zapnutím kampane overiť súlad s § 116 zákona č. 452/2021 Z. z.
    'outreach' => [
        // Akcia začína najneskôr o toľko dní (trigger canal_event_upcoming).
        'upcoming_days' => (int) env('CANAL_OUTREACH_UPCOMING_DAYS', 10),
        // Prahy zobrazení akcie pred jej koncom (canal_views_milestone).
        'view_milestones' => array_values(array_filter(array_map('intval',
            explode(',', (string) env('CANAL_OUTREACH_VIEW_MILESTONES', '50,200'))))),
        // Akcia skončila aspoň pred `after_days` a najviac pred `window_days` dňami.
        'after_days' => (int) env('CANAL_OUTREACH_AFTER_DAYS', 1),
        'window_days' => (int) env('CANAL_OUTREACH_WINDOW_DAYS', 14),
        // Ten istý kanál najviac `max_per_cooldown` oslovení za `cooldown_days`
        // dní a medzi dvoma aspoň `min_gap_days` dní.
        'cooldown_days' => (int) env('CANAL_OUTREACH_COOLDOWN_DAYS', 90),
        'max_per_cooldown' => (int) env('CANAL_OUTREACH_MAX_PER_COOLDOWN', 3),
        'min_gap_days' => (int) env('CANAL_OUTREACH_MIN_GAP_DAYS', 5),
        // Platnosť odkazu na prevzatie — musí prežiť oneskorenie kampane aj celú postupnosť.
        'invitation_days' => (int) env('CANAL_OUTREACH_INVITATION_DAYS', 30),
        // Bez zobrazení nemáme čo ukázať — takú akciu nepíšeme.
        'min_views' => (int) env('CANAL_OUTREACH_MIN_VIEWS', 1),
        // Najviac oslovení za jeden beh.
        'limit' => (int) env('CANAL_OUTREACH_LIMIT', 20),
    ],
];
