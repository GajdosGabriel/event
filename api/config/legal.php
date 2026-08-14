<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Verzia právnych dokumentov
    |--------------------------------------------------------------------------
    |
    | Verzia, na ktorú sa viaže súhlas udelený pri registrácii. Ukladá sa ku
    | každému účtu spolu s dátumom, aby bol súhlas preukázateľný aj po tom, čo
    | sa znenie dokumentov zmení (čl. 7 ods. 1 GDPR).
    |
    | Pri KAŽDEJ zmene textu obchodných podmienok alebo zásad ochrany osobných
    | údajov zvýšte túto hodnotu a rovnakú zmeňte aj v
    | ui/src/content/legal/index.ts (LEGAL_VERSION) — inak by staré a nové
    | súhlasy nebolo možné odlíšiť.
    |
    */

    'version' => env('LEGAL_VERSION', '2026-08-14'),
];
