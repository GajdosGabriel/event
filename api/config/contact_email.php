<?php

return [
    /*
     * Ako dlho platí odkaz z overovacieho e-mailu. Dlhšie než pri registrácii:
     * kontaktnú adresu kanála často spravuje niekto iný než ten, kto formulár
     * uložil, a e-mail sa k nemu nemusí dostať hneď.
     */
    'verification_ttl_hours' => (int) env('CONTACT_EMAIL_VERIFICATION_TTL_HOURS', 72),

    /*
     * Najkratší odstup medzi dvoma odoslaniami na tú istú adresu. Chráni adresu
     * pred zahltením opakovaným ukladaním formulára aj tlačidlom „poslať znova".
     */
    'resend_cooldown_minutes' => (int) env('CONTACT_EMAIL_RESEND_COOLDOWN_MINUTES', 5),
];
