<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Potvrdenie účasti účastníkov
    |--------------------------------------------------------------------------
    |
    | Keď objednávateľ objedná vstupenky pre iných ľudí (meno + e-mail), každý
    | z nich dostane e-mail so žiadosťou o potvrdenie rezervácie. Ak ju do tejto
    | lehoty nepotvrdí, jeho miesto sa automaticky uvoľní.
    |
    */

    'confirmation_hours' => (int) env('TICKET_CONFIRMATION_HOURS', 48),

    /*
    |--------------------------------------------------------------------------
    | Prijatie uvoľneného miesta na workshope
    |--------------------------------------------------------------------------
    |
    | Keď sa na plnom workshope uvoľní miesto, prvý náhradník dostane ponuku —
    | miesto mu držíme, ale platnú vstupenku (QR) dostane až po potvrdení. Ak
    | do tejto lehoty nepotvrdí, ponuka prepadne a miesto ide ďalšiemu v poradí.
    | Lehota býva kratšia než pri bežnom potvrdení — miesto blokuje ostatných.
    |
    */

    'waitlist_confirmation_hours' => (int) env('TICKET_WAITLIST_CONFIRMATION_HOURS', 24),

];
