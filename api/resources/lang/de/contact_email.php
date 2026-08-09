<?php

// API-Antworten bei der Bestätigung von Kontakt-E-Mails
// (App\Http\Controllers\Public\ContactEmailVerificationController).
// Die Texte der E-Mail selbst stehen in `mail.contact_email_verification`.
return [

    'verify' => [
        'done'    => 'Die E-Mail-Adresse ist bestätigt. Vielen Dank!',
        'invalid' => 'Der Link ist ungültig oder abgelaufen. Fordern Sie im Formular einen neuen an.',
    ],

    'resend' => [
        'sent'             => 'Wir haben die Bestätigungs-E-Mail an :email gesendet.',
        'already_verified' => 'Diese Adresse ist bereits bestätigt.',
        'no_email'         => 'Tragen Sie zuerst eine E-Mail-Adresse ein und speichern Sie sie.',
        'too_soon'         => 'Wir haben die E-Mail gerade gesendet. Fordern Sie die nächste in :minutes Min. an.',
    ],

];
