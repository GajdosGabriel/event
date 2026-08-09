<?php

// Odpovede API pri overovaní kontaktných e-mailov
// (App\Http\Controllers\Public\ContactEmailVerificationController).
// Texty samotného e-mailu sú v `mail.contact_email_verification`.
return [

    'verify' => [
        'done'    => 'E-mailová adresa je potvrdená. Ďakujeme!',
        // Neplatný, prepadnutý aj medzičasom prepísaný odkaz zámerne hlásia to
        // isté — z odpovede sa nemá dať vyčítať, ktorý token existuje.
        'invalid' => 'Odkaz je neplatný alebo mu vypršala platnosť. Nechajte si vo formulári poslať nový.',
    ],

    'resend' => [
        'sent'             => 'Overovací e-mail sme poslali na :email.',
        'already_verified' => 'Táto adresa je už overená.',
        'no_email'         => 'Najprv vyplňte a uložte e-mailovú adresu.',
        'too_soon'         => 'E-mail sme práve poslali. Ďalší si vyžiadajte o :minutes min.',
    ],

];
