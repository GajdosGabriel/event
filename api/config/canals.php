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

    // Jediná brzda e-mailov na kontakt nespravovaného kanála (CanalTakeover):
    // prihláška na akciu, zverejnená akcia (CanalEventOffer), otázka aj správa
    // návštevníka. Ide o nevyžiadaný
    // e-mail na verejnú adresu — súlad s § 116 zákona č. 452/2021 Z. z. overiť.
    // Pri true sa e-mail len zapíše do denníka; kópia adminom odchádza vždy.
    'simulate_takeover_mail' => (bool) env('CANAL_TAKEOVER_MAIL_SIMULATE', false),

    // Domény, na ktoré technický e-mail o zverejnenej akcii (CanalEventOffer)
    // nechodí — zdroje importu s množstvom akcií. Platí aj pre subdomény.
    // ecav.sk tu nie je: na nej majú adresy aj jednotlivé cirkevné zbory;
    // samotnej ECAV vypína e-mail prepínač v kanáli (event_offer_disabled).
    'event_offer_excluded_domains' => array_values(array_filter(array_map('trim',
        explode(',', (string) env('CANAL_EVENT_OFFER_EXCLUDED_DOMAINS', 'vyveska.sk,tkkbs.sk'))))),

    // Adresy kanála (CanalEmails) a hľadanie náhradnej (app:canal-emails-find).
    'emails' => [
        // Dočasné vrátenie (plná schránka) vyradí adresu až po toľkých
        // opakovaniach v okne `soft_bounce_days` dní.
        'soft_bounce_limit' => (int) env('CANAL_EMAIL_SOFT_BOUNCE_LIMIT', 3),
        'soft_bounce_days' => (int) env('CANAL_EMAIL_SOFT_BOUNCE_DAYS', 30),
        'discovery' => [
            'enabled' => (bool) env('CANAL_EMAIL_DISCOVERY', true),
            // Kanálov v jednom behu a časový strop behu — beží vo webcrone.
            'batch' => (int) env('CANAL_EMAIL_DISCOVERY_BATCH', 3),
            'max_seconds' => 40,
            // Neúspešné hľadanie sa zopakuje po toľkých dňoch, najviac `max_attempts`-krát.
            'retry_days' => 30,
            'max_attempts' => 3,
            // Najviac toľko nájdených adries naraz (ďalšie sú záloha pri vrátení).
            'keep' => 3,
            // Stránky webu kanála: titulná + najviac toľko kontaktných podstránok.
            'pages' => 2,
            'timeout' => 8,
            // Keď podujatia ani web nič nedajú — vyhľadávanie cez OpenAI
            // (ProfileResearch, rozpočet `profile_enrichment.monthly_limit_usd`).
            // Predvolená hodnota — vypína sa na stránke Spotreba AI (AppSettings).
            'ai' => true,
            // Kanál bez webu čaká na doplnenie profilu (môže mu nájsť web) najviac
            // toľko hodín od zverejnenia aktuálnej akcie — okno oslovenia je krátke.
            'profile_wait_hours' => (int) env('CANAL_EMAIL_DISCOVERY_PROFILE_WAIT_HOURS', 48),
        ],
    ],

    // Oslovenie organizátorov (app:canal-outreach) — triggery po vzniku akcie,
    // pred akciou, prah zobrazení, po akcii. Hlásia sa len do Account Emailingu
    // (text a oneskorenie sú v kampani); bez ACCOUNT_MAILING sa neoslovuje. Pred
    // zapnutím kampane overiť súlad s § 116 zákona č. 452/2021 Z. z.
    'outreach' => [
        // Ktoré triggery sa vyberajú — len tie so zapnutou kampaňou v Accounte.
        // Trigger bez kampane by kanálu zaberal miesto v behu a vyššiu prioritu
        // by držal pred ostatnými. canal_event_created nezapínať: o novej akcii
        // píše technický e-mail CanalEventOffer, kampaň by poslala druhý.
        'triggers' => array_values(array_filter(array_map('trim',
            explode(',', (string) env('CANAL_OUTREACH_TRIGGERS', 'canal_event_upcoming,canal_views_milestone,canal_event_finished'))))),
        // Akcia sa zverejnila pred najviac toľkými dňami (canal_event_created).
        // Staršie sa neoslovujú, takže zapnutie nepošle e-mail k starým akciám.
        'created_days' => (int) env('CANAL_OUTREACH_CREATED_DAYS', 3),
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
