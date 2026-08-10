<?php

// Texty e-mailových notifikácií (App\Notifications\*) a ich šablón
// (resources/views/mail/*). Predmet aj telo držíme tu, nie v kóde, aby sa dali
// preložiť do ďalších jazykov. Markdown (**tučné**, [odkaz](:url)) je súčasťou
// textu — šablóny ho prechádzajú Markdown parserom.
return [

    // Texty zdieľané viacerými e-mailami.
    'common' => [
        'greeting'          => 'Guten Tag!',
        'greeting_named'    => 'Guten Tag, :name!',
        'event_fallback'    => 'Veranstaltung',
        'workshop_fallback' => 'Workshop',
        // Náhradný popis vstupenky, keď účastník nemá vyplnené meno.
        'seat_label'        => 'Ticket :number',
        // Riadok jednej vstupenky v zozname (s typom lístka a bez neho).
        'seat'              => '**:label**',
        'seat_typed'        => '**:label** · :type',
        'qr_alt'            => 'QR-Code',
        'qr_open'           => 'QR-Code öffnen',
        // Sekcia „Pridať do kalendára" (resources/views/mail/partials/calendar).
        'calendar_intro'    => 'Damit Sie den Termin nicht vergessen, tragen Sie die Veranstaltung in Ihren Kalender ein:',
        'calendar_ics'      => 'Zum Kalender hinzufügen',
        'calendar_google'   => 'Google Kalender',
    ],

    // App\Notifications\PendingRegistrationVerification
    'verification' => [
        'subject' => 'Bestätigen Sie Ihre E-Mail-Adresse',
        'intro'   => 'Danke für Ihre Registrierung. Schließen Sie sie ab, indem Sie Ihre E-Mail-Adresse bestätigen.',
        'action'  => 'E-Mail bestätigen',
        'expires' => '{1} Der Link ist :count Stunde gültig.|[2,*] Der Link ist :count Stunden gültig.',
        'ignore'  => 'Wenn Sie kein Konto angelegt haben, brauchen Sie nichts zu tun.',
    ],

    // App\Notifications\AttributeIssueNotice — spoločné upozornenie na údaj,
    // ktorý prestal fungovať (dnes webová adresa, neskôr čokoľvek ďalšie).
    'attribute_issue' => [
        'types' => [
            'canal'        => 'Ihres Kanals',
            'venue'        => 'Ihres Veranstaltungsorts',
            'event'        => 'Ihrer Veranstaltung',
            'organization' => 'Ihres Veranstalterprofils',
        ],
        'attributes' => [
            'website' => 'Webadresse',
        ],
        'subject'     => 'Nicht funktionierende :attribute in Ihrem Eintrag',
        'intro'       => 'Bei der Prüfung haben wir festgestellt, dass die :attribute :type im Veranstaltungsportal nicht antwortet.',
        'intro_named' => 'Bei der Prüfung haben wir festgestellt, dass die :attribute :type **„:name"** im Veranstaltungsportal nicht antwortet.',
        'reasons'     => [
            'dns'           => 'Die Domain wurde nicht gefunden — meist ein Tippfehler in der Adresse oder eine abgelaufene Domain.',
            'not_found'     => 'Der Server hat geantwortet, aber die Seite unter dieser Adresse existiert nicht mehr (Fehler :status). Meist wurde die Unterseite verschoben.',
            'server_error'  => 'Der Server meldet einen Fehler (:status). Es kann auch ein vorübergehender Ausfall des Hostings sein.',
            'http_error'    => 'Der Server hat mit Fehler :status geantwortet.',
            'timeout'       => 'Der Server hat nicht in angemessener Zeit geantwortet.',
            'ssl'           => 'Eine sichere Verbindung kam nicht zustande — meist wegen eines ungültigen Zertifikats.',
            'unreachable'   => 'Unter dieser Adresse war kein Server erreichbar.',
            'redirect'      => 'Die Adresse leitet an eine Stelle weiter, die sich nicht öffnen lässt.',
            'redirect_loop' => 'Die Adresse leitet im Kreis weiter.',
            'blocked'       => 'Die Adresse zeigt nicht ins öffentliche Internet, wir können sie daher nicht prüfen.',
            'invalid'       => 'Die Adresse hat keine gültige Form.',
        ],
        'seen_on'     => 'Zuletzt hat jemand hier darauf geklickt: :url',
        'action'      => 'Adresse korrigieren',
        'recheck'     => 'Wir prüfen die Adresse regelmäßig — nach der Korrektur hören diese Hinweise von selbst auf.',
        'false_alarm' => 'Ist die Adresse in Ordnung und war es nur ein kurzer Ausfall, müssen Sie nichts tun.',
    ],

    // App\Notifications\CanalInvitationSent — pozvánka do tímu kanála.
    'canal_invitation' => [
        'subject'        => 'Einladung in das Team :canal',
        'canal_fallback' => 'Kanal',
        'intro'          => 'Sie wurden in das Team des Kanals **„:canal"** eingeladen.',
        'intro_named'    => '**:inviter** lädt Sie in das Team des Kanals **„:canal"** ein.',
        'role'           => 'Ihre Rolle: **:role**.',
        'role_note'      => [
            'owner'   => 'Als Eigentümer können Sie den Kanal, seine Veranstaltungen und das Team verwalten.',
            'editor'  => 'Als Redakteur können Sie Veranstaltungen, Orte und Tickets anlegen und bearbeiten.',
            'checkin' => 'Am Einlass können Sie QR-Codes scannen und Ankommende abfertigen.',
        ],
        'action'         => 'Einladung annehmen',
        'expires'        => 'Die Einladung gilt bis :date.',
        'email_note'     => 'Nehmen Sie die Einladung an, während Sie mit der Adresse **:email** angemeldet sind. Falls Sie noch kein Konto haben, registrieren Sie zuerst diese Adresse.',
        'ignore'         => 'Falls Sie diese Einladung nicht erwartet haben, ignorieren Sie diese E-Mail einfach.',
    ],

    // App\Notifications\TicketIssued — objednávateľovi po vytvorení lístka.
    'ticket_issued' => [
        'subject'      => 'Ihr Ticket für :event',
        'intro'        => 'Ihr Ticket für **„:event"** wurde erfolgreich erstellt.',
        'quantity'     => 'Reservierte Plätze: **:count**.',
        'qr_note'      => 'Jedes Ticket hat einen eigenen QR-Code. Sie können einzelne Codes an weitere Teilnehmer weiterleiten — am Einlass wird jeder Code separat gescannt.',
        'pending'      => '{1} Noch **:count** Ticket wartet auf die Bestätigung des Teilnehmers.|[2,*] Noch **:count** Tickets warten auf die Bestätigung der Teilnehmer.',
        'pending_note' => 'Der zugehörige QR-Code entsteht erst nach der Bestätigung der Teilnahme — über jede Bestätigung informieren wir Sie per E-Mail.',
        'action'       => 'Ticket und QR-Code anzeigen',
        'outro'        => 'Bringen Sie das Ticket auf dem Handy mit oder drucken Sie es aus und legen Sie es am Einlass vor.',
    ],

    // App\Notifications\AttendeeTicketIssued — ďalšiemu účastníkovi objednávky.
    'attendee_ticket_issued' => [
        'subject'           => 'Ihr Ticket für :event',
        'intro_paid'        => '**:holder** hat Ihnen ein Ticket für **„:event"** bestellt.',
        'intro_free'        => '**:holder** hat Ihnen einen Platz bei **„:event"** reserviert.',
        'outro'             => 'Bringen Sie das Ticket auf dem Handy mit oder drucken Sie es aus und legen Sie den QR-Code am Einlass vor.',
        'cancel'            => 'Sie können nicht kommen? [Ticket stornieren](:url) — wir geben den Platz für andere frei.',
        'activation'        => 'Wir haben für diese E-Mail-Adresse ein Konto angelegt, damit Sie Ihre Tickets immer griffbereit haben. Mit der Anmeldung aktivieren Sie es vollständig — damit bestätigen Sie Ihre E-Mail-Adresse und stimmen den Bedingungen zu.',
        'activation_action' => 'Konto aktivieren',
    ],

    // App\Notifications\AttendeeConfirmationRequest — žiadosť o potvrdenie účasti.
    'attendee_confirmation_request' => [
        'subject'    => 'Bestätigen Sie Ihre Teilnahme an :event',
        'intro_paid' => '**:holder** hat Ihnen ein Ticket für **„:event"** bestellt.',
        'intro_free' => '**:holder** hat Ihnen einen Platz bei **„:event"** reserviert.',
        'ask'        => 'Damit wir den Platz für Sie freihalten können, bestätigen Sie bitte Ihre Teilnahme.',
        'deadline'   => 'Bitte bestätigen Sie **bis :deadline**. Andernfalls wird die Reservierung automatisch storniert und der Platz für andere freigegeben.',
        'confirm'    => 'Teilnahme bestätigen',
        'decline'    => 'Ticket stornieren',
        'ignore'     => 'Falls Sie diese Reservierung nicht angefragt haben, stornieren Sie das Ticket einfach oder ignorieren Sie diese E-Mail — der Platz wird nach Ablauf der Frist von selbst frei.',
        'activation' => 'Wir haben für diese E-Mail-Adresse ein Konto angelegt, damit Sie Ihre Tickets immer griffbereit haben. Mit der Anmeldung aktivieren Sie es vollständig.',
    ],

    // App\Notifications\AttendeeConfirmed — objednávateľovi, keď účastník potvrdil.
    'attendee_confirmed' => [
        'subject'        => ':attendee hat die Teilnahme an :event bestätigt',
        'heading'        => 'Gute Nachrichten!',
        'heading_named'  => 'Gute Nachrichten, :name!',
        'intro'          => '{1} **:attendee** hat die Teilnahme an **„:event"** bestätigt.|[2,*] **:attendee** hat die Teilnahme an **„:event"** bestätigt (:count Plätze).',
        'ticket_sent'    => 'Das Ticket mit QR-Code haben wir soeben an **:email** geschickt.',
        'action'         => 'Bestellung anzeigen',
    ],

    // App\Notifications\AttendeeDeclined — účastník lístok zrušil alebo nepotvrdil.
    'attendee_declined' => [
        'subject'       => 'Freigewordener Platz bei :event',
        'expired'       => '{1} **:attendee** hat die Teilnahme an **„:event"** nicht fristgerecht bestätigt, daher haben wir den reservierten Platz freigegeben.|[2,*] **:attendee** hat die Teilnahme an **„:event"** nicht fristgerecht bestätigt, daher haben wir :count reservierte Plätze freigegeben.',
        'declined'      => '{1} **:attendee** (:email) hat das Ticket für **„:event"** storniert, der Platz ist also wieder frei.|[2,*] **:attendee** (:email) hat das Ticket für **„:event"** storniert (:count Plätze), die Plätze sind also wieder frei.',
        'waitlist_note' => 'Wenn bei einer ausgebuchten Veranstaltung oder einem Workshop ein Platz frei wurde, ist die erste Person auf der Warteliste automatisch nachgerückt.',
    ],

    // App\Notifications\MessageReceived — správa cez tlačidlo „Poslať správu".
    'message_received' => [
        'subject'    => 'Neue Nachricht – :label „:name"',
        'heading'    => 'Neue Nachricht',
        'intro'      => 'Sie haben eine Nachricht zu :label **„:name"** erhalten.',
        'from'       => '**Von:** :name (:email)',
        'reply_hint' => 'Sie können direkt auf diese E-Mail antworten — die Antwort erreicht den Absender.',
        'action'     => ':label anzeigen',
        // Názov typu cieľa správy (App\Models\Message::targetType()).
        'targets'    => [
            'event'   => 'der Veranstaltung',
            'venue'   => 'dem Ort',
            'canal'   => 'dem Kanal',
            'default' => 'dem Profil',
        ],
        'target_fallback' => 'Ihrem Profil',
    ],

    // App\Notifications\MessageReplied — odpoveď organizátora z inboxu.
    'message_replied' => [
        'subject'    => 'Antwort – :label „:name"',
        'heading'    => 'Sie haben eine Antwort erhalten',
        'intro'      => '**:name** hat auf Ihre Nachricht zu :label **„:target"** geantwortet.',
        'reply_hint' => 'Sie können direkt auf diese E-Mail antworten.',
        'action'     => 'Unterhaltung anzeigen',
    ],

    // App\Notifications\EventAnnouncement — hromadný e-mail organizátora.
    // Predmet aj telo píše organizátor, tu sú len rámcové texty.
    'event_announcement' => [
        'action' => 'Veranstaltung anzeigen',
        'outro'  => 'Sie erhalten diese E-Mail, weil Sie ein Ticket für diese Veranstaltung haben.',
    ],

    // App\Notifications\EventReminder — pripomienka pred akciou.
    'event_reminder' => [
        'subject'   => 'Erinnerung: :event',
        'intro'     => 'Wir erinnern Sie daran, dass **„:event"**, wofür Sie ein Ticket haben, bald stattfindet.',
        'starts_at' => 'Beginn: **:date**.',
        'venue'     => 'Ort: **:venue**.',
        'action'    => 'Veranstaltung anzeigen',
        'outro'     => 'Ihr Ticket mit dem QR-Code finden Sie in der E-Mail, die Sie bei der Bestellung erhalten haben.',
    ],

    // App\Notifications\WorkshopSeatGranted — náhradníkovi sa uvoľnilo miesto.
    'workshop_seat_granted' => [
        'subject'       => 'Beim Workshop :workshop ist ein Platz frei geworden',
        'intro'         => 'Beim Workshop „:workshop" (:event) ist ein Platz frei geworden und wir bieten ihn Ihnen als erster Person auf der Warteliste an.',
        'starts_at'     => 'Termin: :date.',
        'deadline'      => 'Wir halten den Platz bis **:deadline** für Sie frei. Bestätigen Sie bis dahin nicht, bieten wir ihn der nächsten Person auf der Warteliste an.',
        'action'        => 'Platz bestätigen',
        'after_confirm' => 'Das Ticket mit dem QR-Code schicken wir Ihnen direkt nach der Bestätigung.',
        'decline'       => 'Falls Sie am Workshop nicht teilnehmen können, [lehnen Sie den Platz ab](:url) — dann rückt die nächste Person nach.',
    ],

    // App\Notifications\WorkshopWaitlisted — zaradenie medzi náhradníkov.
    'workshop_waitlisted' => [
        'subject'  => 'Sie stehen auf der Warteliste für den Workshop :workshop',
        'intro'    => 'Der Workshop „:workshop" bei „:event" ist derzeit ausgebucht, daher haben wir Sie auf die Warteliste gesetzt.',
        'position' => 'Ihre Position: :position.',
        'note'     => 'Wird ein Platz frei, weisen wir ihn Ihnen automatisch zu und schicken Ihnen ein Ticket mit QR-Code.',
        'action'   => 'Veranstaltung anzeigen',
    ],

    // App\Notifications\PosterDraftSaved — odkaz späť na nahratý plagát.
    'poster_draft' => [
        'subject'     => 'Ihr Plakat wartet — stellen Sie die Veranstaltung fertig',
        'intro'       => 'Wir haben das Plakat verarbeitet und die Veranstaltung ist vorbereitet.',
        'intro_named' => 'Wir haben das Plakat verarbeitet und die Veranstaltung **„:name"** ist vorbereitet.',
        'next'        => 'Sie müssen sie nur noch prüfen und speichern. Falls Sie hier noch kein Konto haben, legen Sie es beim Speichern an.',
        'action'      => 'Veranstaltung fertigstellen',
        'expires'     => 'Den Entwurf halten wir bis **:date** für Sie bereit.',
        'ignore'      => 'Falls Sie das Plakat nicht hochgeladen haben, ignorieren Sie diese E-Mail ruhig — ohne Bestätigung wird nirgends etwas veröffentlicht.',
    ],

];
