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

];
