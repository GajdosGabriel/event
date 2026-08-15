<?php

// Hlášky správy súborov (App\Http\Controllers\Admin\FileController).
// Text v úvodzovkách odkazuje na tlačidlá v admine, preto musí sedieť
// s popiskami v ui/src/i18n (admin.files).
return [
    'errors' => [
        'already_trashed' => 'Súbor je už v koši. Na trvalé zmazanie použite „Zmazať natrvalo“.',
        'primary_locked' => 'Primárny súbor nie je možné zmazať. Najprv nastavte ako primárny iný súbor.',
        'trash_first' => 'Najprv presuňte súbor do koša („Zmazať“), potom ho možno zmazať natrvalo.',
    ],
];
