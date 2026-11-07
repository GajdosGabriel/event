<?php

return [
    'verification_ttl_hours' => (int) env('REGISTRATION_VERIFICATION_TTL_HOURS', 48),

    // Registrácia overí, že doména e-mailu existuje v DNS (MX alebo A záznam).
    // Na adresu s vymyslenou doménou by overovací mail len skončil ako
    // nedoručiteľný a zhoršil reputáciu odosielateľa.
    'check_email_dns' => (bool) env('REGISTRATION_CHECK_EMAIL_DNS', true),
];
