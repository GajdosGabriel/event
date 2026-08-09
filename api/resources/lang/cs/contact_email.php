<?php

// Odpovědi API při ověřování kontaktních e-mailů
// (App\Http\Controllers\Public\ContactEmailVerificationController).
// Texty samotného e-mailu jsou v `mail.contact_email_verification`.
return [

    'verify' => [
        'done'    => 'E-mailová adresa je potvrzená. Děkujeme!',
        'invalid' => 'Odkaz je neplatný nebo mu vypršela platnost. Nechte si ve formuláři poslat nový.',
    ],

    'resend' => [
        'sent'             => 'Ověřovací e-mail jsme poslali na :email.',
        'already_verified' => 'Tato adresa je už ověřená.',
        'no_email'         => 'Nejprve vyplňte a uložte e-mailovou adresu.',
        'too_soon'         => 'E-mail jsme právě poslali. Další si vyžádejte za :minutes min.',
    ],

];
