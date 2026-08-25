<?php

return [
    'errors' => [
        'last_canal' => 'Toto je poslední kanál firmy. Nejprve přiřaď jiný, jinak by ses k firmě už nedostal.',
        'canal_required' => 'Vyberte kanál, pod který má organizace patřit.',
    ],

    'account' => [
        'disabled' => 'Napojení na Account není nastaveno.',
        'lookup_timeout' => 'Rejstřík neodpověděl včas. Zkus to znovu nebo údaje vyplň ručně.',
        'lookup_failed' => 'Rejstřík je momentálně nedostupný.',
        'unavailable' => 'Fakturační údaje se nepodařilo uložit — Account neodpovídá. Zkus to za chvíli znovu.',
        'upstream_failed' => 'Fakturační údaje se nepodařilo uložit — Account odpověděl chybou (HTTP :status). Podrobnosti jsou v logu Eventu.',
        'remote_write_blocked' => 'Zápis do vzdáleného Accountu je z tohoto prostředí zakázaný, aby v ostré evidenci firem nevznikaly testovací záznamy. Pokud to opravdu chceš, nastav v .env ACCOUNT_ALLOW_REMOTE_WRITES=true.',
    ],

    'webhook' => [
        'disabled' => 'Webhooky z Accountu nejsou nastavené.',
        'invalid_signature' => 'Neplatný podpis.',
        'stale' => 'Zastaralý požadavek.',
    ],
];
