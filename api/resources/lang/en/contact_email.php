<?php

// API responses for contact e-mail verification
// (App\Http\Controllers\Public\ContactEmailVerificationController).
// The e-mail copy itself lives in `mail.contact_email_verification`.
return [

    'verify' => [
        'done'    => 'The e-mail address is confirmed. Thank you!',
        'invalid' => 'The link is invalid or has expired. Request a new one from the form.',
    ],

    'resend' => [
        'sent'             => 'We sent the verification e-mail to :email.',
        'already_verified' => 'This address is already verified.',
        'no_email'         => 'Fill in and save an e-mail address first.',
        'too_soon'         => 'We have just sent the e-mail. Request another one in :minutes min.',
    ],

];
