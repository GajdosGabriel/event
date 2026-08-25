<?php

// Hlášky organizácií (App\Http\Controllers\Dashboard\DashboardOrganizationController).
return [
    'errors' => [
        'last_canal' => 'Toto je posledný kanál firmy. Najprv priraď iný, inak by si sa k firme už nedostal.',
        'canal_required' => 'Vyberte kanál, pod ktorý má organizácia patriť.',
    ],

    // Napojenie na Account (App\Services\Account). Vidí ich používateľ vo
    // formulári firmy, preto sú v jeho jazyku, nie v jazyku servera.
    'account' => [
        'disabled' => 'Napojenie na Account nie je nastavené.',
        'lookup_timeout' => 'Register neodpovedal načas. Skús to znova alebo údaje vyplň ručne.',
        'lookup_failed' => 'Register je momentálne nedostupný.',
        'unavailable' => 'Fakturačné údaje sa nepodarilo uložiť — Account neodpovedá. Skús to o chvíľu znova.',
        'upstream_failed' => 'Fakturačné údaje sa nepodarilo uložiť — Account odpovedal chybou (HTTP :status). Podrobnosti sú v logu Eventu.',
        'remote_write_blocked' => 'Zápis do vzdialeného Accountu je z tohto prostredia zakázaný, aby v ostrej evidencii firiem nevznikali testovacie záznamy. Ak to naozaj chceš, nastav v .env ACCOUNT_ALLOW_REMOTE_WRITES=true.',
    ],

    // Odpovede na webhooky z Accountu (App\Http\Controllers\Webhooks).
    // Číta ich druhý server, nie človek — preklad je tu kvôli jednotnosti.
    'webhook' => [
        'disabled' => 'Webhooky z Accountu nie sú nastavené.',
        'invalid_signature' => 'Neplatný podpis.',
        'stale' => 'Zastaraná požiadavka.',
    ],
];
