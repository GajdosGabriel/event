<?php

return [

    'reset'     => 'Ihr Passwort wurde zurückgesetzt.',
    'sent'      => 'Wir haben Ihnen eine E-Mail mit einem Link zum Zurücksetzen des Passworts geschickt.',
    'throttled' => 'Bitte warten Sie, bevor Sie es erneut versuchen.',
    'token'     => 'Dieser Token zum Zurücksetzen des Passworts ist ungültig.',
    'user'      => 'Wir konnten keinen Benutzer mit dieser E-Mail-Adresse finden.',

    // Nicht von Laravel: die Antwort auf „Passwort vergessen“ muss für eine
    // registrierte und eine unbekannte Adresse gleich sein, sonst verrät das Formular, wer ein Konto hat.
    'sent_blind' => 'Wenn zu dieser Adresse ein Konto gehört, haben wir einen Link zum Zurücksetzen des Passworts geschickt.',

];
