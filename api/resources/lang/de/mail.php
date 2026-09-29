<?php

// Texty e-mailových notifikácií (App\Notifications\*) a ich šablón
// (resources/views/mail/*). Predmet aj telo držíme tu, nie v kóde, aby sa dali
// preložiť do ďalších jazykov. Markdown (**tučné**, [odkaz](:url)) je súčasťou
// textu — šablóny ho prechádzajú Markdown parserom.
return [

    // Texty zdieľané viacerými e-mailami.
    'common' => [
        'greeting' => 'Guten Tag!',
        'greeting_named' => 'Guten Tag, :name!',
        'event_fallback' => 'Veranstaltung',
        'workshop_fallback' => 'Workshop',
        // Náhradný popis vstupenky, keď účastník nemá vyplnené meno.
        'seat_label' => 'Ticket :number',
        // Riadok jednej vstupenky v zozname (s typom lístka a bez neho).
        'seat' => '**:label**',
        'seat_typed' => '**:label** · :type',
        'qr_alt' => 'QR-Code',
        'qr_open' => 'QR-Code öffnen',
        // Sekcia „Pridať do kalendára" (resources/views/mail/partials/calendar).
        'calendar_title' => 'Zum Kalender hinzufügen',
        'calendar_intro' => 'Damit Sie den Termin nicht vergessen, tragen Sie die Veranstaltung in Ihren Kalender ein:',
        'calendar_ics' => 'Apple Kalender und andere',
        'calendar_google' => 'Google Kalender',
        'calendar_outlook' => 'Outlook',
        // Pätička e-mailov z odberu (resources/views/mail/partials/unsubscribe).
        'unsubscribe_intro' => 'Sie erhalten diese E-Mail, weil Sie für diese Veranstaltung eine Benachrichtigung angefordert haben.',
        'unsubscribe_action' => 'Benachrichtigungen abbestellen',
    ],

    // App\Notifications\PendingRegistrationVerification
    'verification' => [
        'subject' => 'Bestätigen Sie Ihre E-Mail-Adresse',
        'intro' => 'Danke für Ihre Registrierung. Schließen Sie sie ab, indem Sie Ihre E-Mail-Adresse bestätigen.',
        'action' => 'E-Mail bestätigen',
        'expires' => '{1} Der Link ist :count Stunde gültig.|[2,*] Der Link ist :count Stunden gültig.',
        'ignore' => 'Wenn Sie kein Konto angelegt haben, brauchen Sie nichts zu tun.',
        'subject_event' => 'Bestätigen Sie Ihre E-Mail und schließen Sie die Anmeldung zu :event ab',
        'intro_event' => 'Danke für Ihre Anmeldung zu **„:event"**. Bitte bestätigen Sie zuerst Ihre E-Mail-Adresse.',
        'event_note' => 'Ihren Platz bei **„:event"** reservieren Sie nach der Bestätigung Ihrer E-Mail — gleich danach senden wir Ihnen den Reservierungslink.',
        'action_event' => 'E-Mail bestätigen',
    ],

    // App\Notifications\PasswordResetLink
    'password_reset' => [
        'subject' => 'Passwort zurücksetzen',
        'intro' => 'Wir haben eine Anfrage zum Zurücksetzen des Passworts für Ihr Konto erhalten. Über die Schaltfläche unten setzen Sie ein neues.',
        'action' => 'Neues Passwort setzen',
        'expires' => '{1} Der Link ist :count Minute gültig und lässt sich einmal verwenden.|[2,*] Der Link ist :count Minuten gültig und lässt sich einmal verwenden.',
        'ignore' => 'Wenn Sie das nicht angefordert haben, brauchen Sie nichts zu tun — Ihr Passwort bleibt unverändert.',
    ],

    // App\Notifications\ContentReviewNotice
    'content_review' => [
        'types' => [
            'canal' => 'Ihres Kanals',
            'venue' => 'Ihres Veranstaltungsorts',
            'event' => 'Ihrer Veranstaltung',
        ],
        'subject' => 'Ein paar Anmerkungen zu Ihrem Text: :name',
        'intro' => 'Nach der Veröffentlichung haben wir den Text :type gelesen und ein paar Dinge bemerkt, die sich verbessern lassen.',
        'intro_named' => 'Nach der Veröffentlichung haben wir die Beschreibung :type **„:name"** gelesen und ein paar Dinge bemerkt, die sich verbessern lassen.',
        'action' => 'Formular mit dem Assistenten öffnen',
        'assistant' => 'Im Formular über der Beschreibung finden Sie die Schaltfläche **„Mit KI ausfüllen"** — bereits auf das eingestellt, worum es in den Anmerkungen geht. Der Vorschlag erscheint neben Ihrem Originaltext, und es ändert sich nichts, bis Sie ihn selbst bestätigen.',
        'no_change' => 'Wir haben nichts geändert und Sie müssen nichts tun — Ihr Text bleibt veröffentlicht und funktioniert weiter.',
    ],

    // App\Notifications\AttributeIssueNotice — spoločné upozornenie na údaj,
    // ktorý prestal fungovať (dnes webová adresa, neskôr čokoľvek ďalšie).
    'attribute_issue' => [
        'types' => [
            'canal' => 'Ihres Kanals',
            'venue' => 'Ihres Veranstaltungsorts',
            'event' => 'Ihrer Veranstaltung',
            'organization' => 'Ihres Veranstalterprofils',
        ],
        'attributes' => [
            'website' => 'Webadresse',
        ],
        'subject' => 'Nicht funktionierende :attribute in Ihrem Eintrag',
        'intro' => 'Bei der Prüfung haben wir festgestellt, dass die :attribute :type im Veranstaltungsportal nicht antwortet.',
        'intro_named' => 'Bei der Prüfung haben wir festgestellt, dass die :attribute :type **„:name"** im Veranstaltungsportal nicht antwortet.',
        'reasons' => [
            'dns' => 'Die Domain wurde nicht gefunden — meist ein Tippfehler in der Adresse oder eine abgelaufene Domain.',
            'not_found' => 'Der Server hat geantwortet, aber die Seite unter dieser Adresse existiert nicht mehr (Fehler :status). Meist wurde die Unterseite verschoben.',
            'server_error' => 'Der Server meldet einen Fehler (:status). Es kann auch ein vorübergehender Ausfall des Hostings sein.',
            'http_error' => 'Der Server hat mit Fehler :status geantwortet.',
            'timeout' => 'Der Server hat nicht in angemessener Zeit geantwortet.',
            'ssl' => 'Eine sichere Verbindung kam nicht zustande — meist wegen eines ungültigen Zertifikats.',
            'unreachable' => 'Unter dieser Adresse war kein Server erreichbar.',
            'redirect' => 'Die Adresse leitet an eine Stelle weiter, die sich nicht öffnen lässt.',
            'redirect_loop' => 'Die Adresse leitet im Kreis weiter.',
            'blocked' => 'Die Adresse zeigt nicht ins öffentliche Internet, wir können sie daher nicht prüfen.',
            'invalid' => 'Die Adresse hat keine gültige Form.',
        ],
        'seen_on' => 'Zuletzt hat jemand hier darauf geklickt: :url',
        'action' => 'Adresse korrigieren',
        'recheck' => 'Wir prüfen die Adresse regelmäßig — nach der Korrektur hören diese Hinweise von selbst auf.',
        'false_alarm' => 'Ist die Adresse in Ordnung und war es nur ein kurzer Ausfall, müssen Sie nichts tun.',
    ],

    // App\Notifications\CanalInvitationSent — pozvánka do tímu kanála.
    'canal_invitation' => [
        'subject' => 'Einladung in das Team :canal',
        'canal_fallback' => 'Kanal',
        'intro' => 'Sie wurden in das Team des Kanals **„:canal"** eingeladen.',
        'intro_named' => '**:inviter** lädt Sie in das Team des Kanals **„:canal"** ein.',
        'role' => 'Ihre Rolle: **:role**.',
        'role_note' => [
            'owner' => 'Als Eigentümer können Sie den Kanal, seine Veranstaltungen und das Team verwalten.',
            'editor' => 'Als Redakteur können Sie Veranstaltungen, Orte und Tickets anlegen und bearbeiten.',
            'checkin' => 'Am Einlass können Sie QR-Codes scannen und Ankommende abfertigen.',
        ],
        'action' => 'Einladung annehmen',
        'expires' => 'Die Einladung gilt bis :date.',
        'email_note' => 'Nehmen Sie die Einladung an, während Sie mit der Adresse **:email** angemeldet sind. Falls Sie noch kein Konto haben, registrieren Sie zuerst diese Adresse.',
        'ignore' => 'Falls Sie diese Einladung nicht erwartet haben, ignorieren Sie diese E-Mail einfach.',
    ],

    // App\Notifications\CanalOwnershipChanged — zmena vlastníctva kanála.
    'canal_ownership' => [
        'claimed' => [
            'subject' => 'Der Kanal :canal hat einen neuen Verwalter',
            'member' => 'Sie haben den Kanal **„:canal"** übernommen. Ab jetzt erhalten Sie Anmeldungen und Nachrichten von Besuchern.',
            'owners' => 'Der Kanal **„:canal"** wurde vom Konto **:member** übernommen.',
            'contact' => 'Der Kanal **„:canal"**, bei dem diese Adresse angegeben ist, wurde vom Konto **:member** übernommen.',
        ],
        'owner_added' => [
            'subject' => 'Neuer Eigentümer des Kanals :canal',
            'member' => 'Sie sind jetzt Eigentümer des Kanals **„:canal"**.',
            'owners' => 'Das Konto **:member** ist jetzt Eigentümer des Kanals **„:canal"**.',
        ],
        'owner_removed' => [
            'subject' => 'Eigentümerwechsel im Kanal :canal',
            'member' => 'Sie sind nicht mehr Eigentümer des Kanals **„:canal"**.',
            'owners' => 'Das Konto **:member** ist nicht mehr Eigentümer des Kanals **„:canal"**.',
        ],
        'action' => 'Kanal öffnen',
        'not_you' => 'Wenn Sie davon nichts wissen, antworten Sie auf diese E-Mail und wir prüfen die Änderung.',
        'contest' => 'Wenn Sie mit der Übernahme nicht einverstanden sind, können Sie bis :date widersprechen.',
        'contest_action' => 'Ich widerspreche',
    ],

    // App\Notifications\CanalOutreachNotice — oslovenie organizátora po akcii.
    'canal_outreach' => [
        'subject' => 'Menschen haben Ihre Veranstaltung :event auf :site angesehen',
        'intro' => 'Wir haben Ihre Veranstaltung **„:event"** (:date) auf :site veröffentlicht.',
        'views' => '{0} Bisher hat sie niemand angesehen.|{1} **1** Person hat sie angesehen.|[2,*] **:visitors** Personen haben sie angesehen (:views Aufrufe).',
        'signups' => '{1} **1** Teilnehmer hat sich angemeldet.|[2,*] **:signups** Teilnehmer haben sich angemeldet.',
        'offer' => 'Das Profil **„:canal"** entstand automatisch aus öffentlichen Quellen. Wenn Sie es übernehmen, können Sie weitere Veranstaltungen kostenlos veröffentlichen, die Angaben bearbeiten und Anmeldungen sowie Nachrichten erhalten.',
        'action' => 'Profil übernehmen',
        'why' => 'Wir schreiben Ihnen, weil Ihre Adresse bei der Veranstaltung oder dem Veranstalterprofil angegeben ist. Wir melden uns nicht erneut, solange Sie kein Interesse zeigen.',
        'unsubscribe' => 'Keine solchen E-Mails mehr? [Abmelden](:url).',
    ],

    // App\Notifications\CanalContactNotice — zmena kontaktného e-mailu kanála.
    'canal_contact' => [
        'verify' => [
            'subject' => 'Kontakt des Kanals :canal bestätigen',
            'intro' => 'Die Adresse **:email** wurde als Kontakt-E-Mail des Kanals **„:canal"** eingetragen. Bitte bestätigen Sie, dass sie zum Kanal gehört.',
            'action' => 'Adresse bestätigen',
            'ignore' => 'Wenn Sie diese Änderung nicht angefordert haben, ignorieren Sie diese E-Mail.',
        ],
        'changed' => [
            'subject' => 'Kontakt des Kanals :canal wurde geändert',
            'intro' => 'Die Kontakt-E-Mail des Kanals **„:canal"** wurde von dieser Adresse auf **:email** geändert.',
        ],
    ],

    // App\Notifications\CanalClaimNotice — žiadosť o prevzatie kanála.
    'canal_claim' => [
        'message' => 'Nachricht: „:message"',
        'verify' => [
            'subject' => 'Anfrage zur Verwaltung des Kanals :canal',
            'intro' => 'Das Konto **:member** möchte den Kanal **„:canal"** verwalten, bei dem diese Adresse angegeben ist. Wenn Sie es kennen und einverstanden sind, bestätigen Sie bitte.',
            'action' => 'Anfrage prüfen',
            'ignore' => 'Wenn Sie den Antragsteller nicht kennen, ignorieren Sie diese E-Mail — die Anfrage läuft am :date ab.',
        ],
        'requested' => [
            'subject' => 'Antrag auf Übernahme des Kanals :canal',
            'intro' => 'Das Konto **:name** (:member) beantragt die Übernahme des Kanals **„:canal"**. Der Antrag wartet auf Prüfung.',
            'action' => 'Anträge öffnen',
        ],
        'rejected' => [
            'subject' => 'Ihr Antrag für den Kanal :canal wurde nicht genehmigt',
            'intro' => 'Ihren Antrag auf Verwaltung des Kanals **„:canal"** haben wir nicht genehmigt.',
        ],
        'contested' => [
            'subject' => 'Übernahme des Kanals :canal wurde angefochten',
            'intro' => 'Die Kontaktadresse des Kanals **„:canal"** ist mit der Übernahme durch **:name** (:member) nicht einverstanden.',
            'action' => 'Anträge öffnen',
        ],
        'reverted' => [
            'subject' => 'Verwaltung des Kanals :canal aufgehoben',
            'intro' => 'Nach Prüfung haben wir Ihre Übernahme des Kanals **„:canal"** aufgehoben.',
        ],
    ],

    // App\Notifications\TicketIssued — objednávateľovi po vytvorení lístka.
    'ticket_issued' => [
        'subject' => 'Ihr Ticket für :event',
        'intro' => 'Ihr Ticket für **„:event"** wurde erfolgreich erstellt.',
        'quantity' => 'Reservierte Plätze: **:count**.',
        'qr_note' => 'Jedes Ticket hat einen eigenen QR-Code. Sie können einzelne Codes an weitere Teilnehmer weiterleiten — am Einlass wird jeder Code separat gescannt.',
        'pending' => '{1} Noch **:count** Ticket wartet auf die Bestätigung des Teilnehmers.|[2,*] Noch **:count** Tickets warten auf die Bestätigung der Teilnehmer.',
        'pending_note' => 'Der zugehörige QR-Code entsteht erst nach der Bestätigung der Teilnahme — über jede Bestätigung informieren wir Sie per E-Mail.',
        'action' => 'Ticket und QR-Code anzeigen',
        'outro' => 'Bringen Sie das Ticket auf dem Handy mit oder drucken Sie es aus und legen Sie es am Einlass vor.',
    ],

    // App\Notifications\TicketIssued s príznakom `restored` — obnovená objednávka.
    'ticket_restored' => [
        'subject' => 'Ihre Anmeldung für :event ist wieder gültig',
        'intro' => 'Wir haben Ihre stornierte Anmeldung für **„:event"** wiederhergestellt — Ihre Plätze sind wieder gültig.',
    ],

    // App\Notifications\AttendeeTicketIssued — ďalšiemu účastníkovi objednávky.
    'attendee_ticket_issued' => [
        'subject' => 'Ihr Ticket für :event',
        'intro_paid' => '**:holder** hat Ihnen ein Ticket für **„:event"** bestellt.',
        'intro_free' => '**:holder** hat Ihnen einen Platz bei **„:event"** reserviert.',
        'outro' => 'Bringen Sie das Ticket auf dem Handy mit oder drucken Sie es aus und legen Sie den QR-Code am Einlass vor.',
        'cancel' => 'Sie können nicht kommen? [Ticket stornieren](:url) — wir geben den Platz für andere frei.',
        'activation' => 'Wir haben für diese E-Mail-Adresse ein Konto angelegt, damit Sie Ihre Tickets immer griffbereit haben. Mit der Anmeldung aktivieren Sie es vollständig — damit bestätigen Sie Ihre E-Mail-Adresse und stimmen den Bedingungen zu.',
        'activation_action' => 'Konto aktivieren',
    ],

    // App\Notifications\AttendeeConfirmationRequest — žiadosť o potvrdenie účasti.
    'attendee_confirmation_request' => [
        'subject' => 'Bestätigen Sie Ihre Teilnahme an :event',
        'intro_paid' => '**:holder** hat Ihnen ein Ticket für **„:event"** bestellt.',
        'intro_free' => '**:holder** hat Ihnen einen Platz bei **„:event"** reserviert.',
        'ask' => 'Damit wir den Platz für Sie freihalten können, bestätigen Sie bitte Ihre Teilnahme.',
        'deadline' => 'Bitte bestätigen Sie **bis :deadline**. Andernfalls wird die Reservierung automatisch storniert und der Platz für andere freigegeben.',
        'confirm' => 'Teilnahme bestätigen',
        'decline' => 'Ticket stornieren',
        'ignore' => 'Falls Sie diese Reservierung nicht angefragt haben, stornieren Sie das Ticket einfach oder ignorieren Sie diese E-Mail — der Platz wird nach Ablauf der Frist von selbst frei.',
        'activation' => 'Wir haben für diese E-Mail-Adresse ein Konto angelegt, damit Sie Ihre Tickets immer griffbereit haben. Mit der Anmeldung aktivieren Sie es vollständig.',
    ],

    // App\Notifications\AttendeeConfirmed — objednávateľovi, keď účastník potvrdil.
    'attendee_confirmed' => [
        'subject' => ':attendee hat die Teilnahme an :event bestätigt',
        'heading' => 'Gute Nachrichten!',
        'heading_named' => 'Gute Nachrichten, :name!',
        'intro' => '{1} **:attendee** hat die Teilnahme an **„:event"** bestätigt.|[2,*] **:attendee** hat die Teilnahme an **„:event"** bestätigt (:count Plätze).',
        'ticket_sent' => 'Das Ticket mit QR-Code haben wir soeben an **:email** geschickt.',
        'action' => 'Bestellung anzeigen',
    ],

    // App\Notifications\AttendeeDeclined — účastník lístok zrušil alebo nepotvrdil.
    'attendee_declined' => [
        'subject' => 'Freigewordener Platz bei :event',
        'expired' => '{1} **:attendee** hat die Teilnahme an **„:event"** nicht fristgerecht bestätigt, daher haben wir den reservierten Platz freigegeben.|[2,*] **:attendee** hat die Teilnahme an **„:event"** nicht fristgerecht bestätigt, daher haben wir :count reservierte Plätze freigegeben.',
        'declined' => '{1} **:attendee** (:email) hat das Ticket für **„:event"** storniert, der Platz ist also wieder frei.|[2,*] **:attendee** (:email) hat das Ticket für **„:event"** storniert (:count Plätze), die Plätze sind also wieder frei.',
        'waitlist_note' => 'Wenn bei einer ausgebuchten Veranstaltung oder einem Workshop ein Platz frei wurde, ist die erste Person auf der Warteliste automatisch nachgerückt.',
    ],

    // App\Notifications\MessageReceived — správa cez tlačidlo „Poslať správu".
    'message_received' => [
        'subject' => 'Neue Nachricht – :label „:name"',
        'heading' => 'Neue Nachricht',
        'intro' => 'Sie haben eine Nachricht zu :label **„:name"** erhalten.',
        'from' => '**Von:** :name (:email)',
        'reply_hint' => 'Sie können direkt auf diese E-Mail antworten — die Antwort erreicht den Absender.',
        'action' => ':label anzeigen',
        // Názov typu cieľa správy (App\Models\Message::targetType()).
        'targets' => [
            'event' => 'der Veranstaltung',
            'venue' => 'dem Ort',
            'canal' => 'dem Kanal',
            'default' => 'dem Profil',
        ],
        'target_fallback' => 'Ihrem Profil',
    ],

    // App\Notifications\MessageReplied — odpoveď organizátora z inboxu.
    'message_replied' => [
        'subject' => 'Antwort – :label „:name"',
        'heading' => 'Sie haben eine Antwort erhalten',
        'intro' => '**:name** hat auf Ihre Nachricht zu :label **„:target"** geantwortet.',
        'reply_hint' => 'Sie können direkt auf diese E-Mail antworten.',
        'action' => 'Unterhaltung anzeigen',
    ],

    // App\Notifications\EventAnnouncement — hromadný e-mail organizátora.
    // Predmet aj telo píše organizátor, tu sú len rámcové texty.
    'event_announcement' => [
        'action' => 'Veranstaltung anzeigen',
        'outro' => 'Sie erhalten diese E-Mail, weil Sie ein Ticket für diese Veranstaltung haben.',
    ],

    // App\Notifications\EventReminder — pripomienka pred akciou.
    'event_reminder' => [
        'subject' => 'Erinnerung: :event',
        'intro' => 'Wir erinnern Sie daran, dass **„:event"**, wofür Sie ein Ticket haben, bald stattfindet.',
        'starts_at' => 'Beginn: **:date**.',
        'venue' => 'Ort: **:venue**.',
        'action' => 'Veranstaltung anzeigen',
        'outro' => 'Ihr Ticket mit dem QR-Code finden Sie in der E-Mail, die Sie bei der Bestellung erhalten haben.',
        // Ten istý e-mail pre toho, kto si vypýtal upozornenie a lístok nemá.
        'outro_subscriber' => 'Der Eintritt ist ohne Anmeldung — kommen Sie einfach vorbei.',
    ],

    // App\Notifications\QuestionAnswered — jediný e-mail, ktorý pisateľ otázky
    // dostane. Adresa sa hneď po odoslaní maže, preto tu nie je odhlásenie.
    'question_answered' => [
        'subject' => 'Antwort auf Ihre Frage: :event',
        'intro' => 'Der Veranstalter hat Ihre Frage zur Veranstaltung **„:event"** beantwortet.',
        'answer_label' => 'Antwort des Veranstalters',
        'action' => 'Veranstaltung ansehen',
        'outro' => 'Wir haben Ihre Adresse nur für diese eine Antwort verwendet und nicht mehr gespeichert — weitere E-Mails erhalten Sie von uns nicht.',
    ],

    'question_received' => [
        'feedback' => [
            'subject' => 'Hinweis aus dem Publikum: :event',
            'intro' => 'Jemand aus dem Publikum hat Ihnen während **„:event"** einen Hinweis geschickt:',
            'hint' => 'Das sehen nur Sie. Wenn sich etwas tun lässt, dann jetzt — markieren Sie es danach auf der Pinnwand als erledigt.',
        ],
        'private' => [
            'subject' => 'Private Frage: :event',
            'intro' => 'Jemand fragt Sie privat zu **„:event"**:',
            'hint' => 'Diese Frage sieht sonst niemand. Die Antwort schreiben Sie auf der Pinnwand, wir schicken sie per E-Mail.',
        ],
        'public' => [
            'subject' => 'Neue Frage: :event',
            'intro' => 'Jemand fragt zu **„:event"**:',
            'hint' => 'Die Frage steht öffentlich auf der Veranstaltungsseite. Die Antwort auf der Pinnwand bleibt dort als FAQ.',
        ],
        'from' => 'Von: :name',
        'action' => 'Fragen-Pinnwand öffnen',
    ],

    // App\Notifications\QuestionRelayed — Frage eines Besuchers zu einer Veranstaltung eines Kanals ohne Betreuer.
    'question_relayed' => [
        'organizer' => [
            'subject' => 'Frage zu :event',
            'intro' => 'Ein Besucher von :site fragt zur Veranstaltung **„:event"** von **:canal**:',
            'reply' => 'Antworten Sie direkt auf diese E-Mail — :name (:email) erhält Ihre Antwort.',
            'offer' => 'Das Profil **„:canal"** wurde automatisch aus öffentlichen Quellen erstellt. Wenn Sie es übernehmen, erhalten Sie Fragen von Menschen, die sich für Ihre Veranstaltung interessieren, können diese beantworten und weitere Veranstaltungen kostenlos veröffentlichen.',
            'action' => 'Profil übernehmen',
            'why' => 'Wir schreiben Ihnen, weil Ihre Adresse im Veranstalterprofil angegeben ist. Andere E-Mails senden wir Ihnen ohne Ihr Interesse nicht.',
            'unsubscribe' => 'Sie möchten solche E-Mails nicht oder diese Adresse gehört Ihnen nicht? [Abmelden](:url).',
        ],
        'admin' => [
            'subject' => 'Frage an den Veranstalter: :event',
            'intro' => ':name (:email) fragt zur Veranstaltung **„:event"** des Kanals **:canal**:',
            'sent_to' => 'Die Frage wurde an die verifizierte Adresse des Kanals weitergeleitet: :email.',
            'action' => 'Veranstaltung öffnen',
        ],
    ],

    // App\Notifications\SubscriptionConfirmed — prvý e-mail po „Pripomeň mi".
    'subscription_confirmed' => [
        'subject' => 'Wir halten Sie auf dem Laufenden: :event',
        'intro' => 'Erledigt. Wenn sich bei **„:event"** etwas ändert oder der Veranstalter absagt, sagen wir Ihnen Bescheid — und vor dem Beginn schicken wir eine Erinnerung.',
        'starts_at' => 'Beginn: **:date**.',
        'venue' => 'Ort: **:venue**.',
        'action' => 'Veranstaltung anzeigen',
        'outro' => 'Falls Sie diese Benachrichtigung nicht angefordert haben, beenden Sie sie über den Link unten — Ihre Adresse wird sofort gelöscht.',
    ],

    // App\Notifications\EventChanged — sľub z tlačidla „Pripomeň mi".
    'event_changed' => [
        'subject' => 'Änderung: :event',
        'subject_cancelled' => 'Abgesagt: :event',
        'intro' => 'Bei **„:event"** hat sich Folgendes geändert:',
        'intro_cancelled' => 'Die Veranstaltung **„:event"** findet nicht statt — der Veranstalter hat sie zurückgezogen. Falls Sie den Termin im Kalender haben, können Sie ihn löschen.',
        'starts_at' => 'Neuer Termin: **:date**.',
        'venue' => 'Ort: **:venue**.',
        'action' => 'Veranstaltung anzeigen',
        'change_start' => 'Termin: :from → :to',
        'change_venue' => 'Ort: :from → :to',
    ],

    // App\Notifications\WorkshopSeatGranted — náhradníkovi sa uvoľnilo miesto.
    'workshop_seat_granted' => [
        'subject' => 'Beim Workshop :workshop ist ein Platz frei geworden',
        'intro' => 'Beim Workshop „:workshop" (:event) ist ein Platz frei geworden und wir bieten ihn Ihnen als erster Person auf der Warteliste an.',
        'starts_at' => 'Termin: :date.',
        'deadline' => 'Wir halten den Platz bis **:deadline** für Sie frei. Bestätigen Sie bis dahin nicht, bieten wir ihn der nächsten Person auf der Warteliste an.',
        'action' => 'Platz bestätigen',
        'after_confirm' => 'Das Ticket mit dem QR-Code schicken wir Ihnen direkt nach der Bestätigung.',
        'decline' => 'Falls Sie am Workshop nicht teilnehmen können, [lehnen Sie den Platz ab](:url) — dann rückt die nächste Person nach.',
    ],

    // App\Notifications\WorkshopWaitlisted — zaradenie medzi náhradníkov.
    'workshop_waitlisted' => [
        'subject' => 'Sie stehen auf der Warteliste für den Workshop :workshop',
        'intro' => 'Der Workshop „:workshop" bei „:event" ist derzeit ausgebucht, daher haben wir Sie auf die Warteliste gesetzt.',
        'position' => 'Ihre Position: :position.',
        'note' => 'Wird ein Platz frei, weisen wir ihn Ihnen automatisch zu und schicken Ihnen ein Ticket mit QR-Code.',
        'action' => 'Veranstaltung anzeigen',
    ],

    // App\Notifications\PosterDraftSaved — odkaz späť na nahratý plagát.
    'poster_draft' => [
        'subject' => 'Ihr Plakat wartet — stellen Sie die Veranstaltung fertig',
        'intro' => 'Wir haben das Plakat verarbeitet und die Veranstaltung ist vorbereitet.',
        'intro_named' => 'Wir haben das Plakat verarbeitet und die Veranstaltung **„:name"** ist vorbereitet.',
        'next' => 'Sie müssen sie nur noch prüfen und speichern. Falls Sie hier noch kein Konto haben, legen Sie es beim Speichern an.',
        'action' => 'Veranstaltung fertigstellen',
        'expires' => 'Den Entwurf halten wir bis **:date** für Sie bereit.',
        'ignore' => 'Falls Sie das Plakat nicht hochgeladen haben, ignorieren Sie diese E-Mail ruhig — ohne Bestätigung wird nirgends etwas veröffentlicht.',
    ],

    // App\Notifications\OpenAiBillingIssue — super-adminom, keď v OpenAI došiel kredit.
    'openai_billing' => [
        'subject' => 'OpenAI: Guthaben aufgebraucht, KI-Funktionen ausgefallen',
        'intro' => 'OpenAI hat eine Anfrage abgelehnt, weil die Organisation kein Guthaben mehr hat oder das Abrechnungslimit erreicht ist.',
        'response' => 'Antwort von OpenAI (HTTP :status, Code `:code`):',
        'impact' => 'Bis Guthaben aufgeladen wird, funktionieren die Erkennung importierter Veranstaltungen, der KI-Textassistent, die Verschlagwortung und die Inhaltsprüfung nicht.',
        'action' => 'Guthaben bei OpenAI aufladen',
        'cooldown' => 'Die nächste Warnung senden wir frühestens in :hours h, auch wenn der Fehler bestehen bleibt.',
    ],

    'event_signup_organizer' => [
        'subject' => 'Neue Anmeldung für :event',
        'intro' => '**:name** hat sich über Hlas Cirkvi zu Ihrer Veranstaltung **„:event"** angemeldet.',
        'reserved' => 'Der Platz ist reserviert — die Person hat ein Ticket mit QR-Code erhalten und steht auf der Teilnehmerliste.',
        'interest' => 'Reservierungen sind aus, erfordern ein kostenpflichtiges Ticket oder es ist voll, daher wurde nur das Interesse erfasst. Bitte melden Sie sich bei der Person.',
        'count' => '{1} Bisher ist **:count** Person angemeldet.|[2,*] Bisher sind **:count** Personen angemeldet.',
        'contact' => 'Kontakt: :email',
        'action' => 'Teilnehmerliste anzeigen',
        'claim_intro' => 'Die Veranstaltung ist auf event.hlascirkvi.sk unter dem Kanal **„:canal"** veröffentlicht, den noch niemand verwaltet. Diese Nachricht ging an den bei der Veranstaltung angegebenen Kontakt.',
        'claim_benefits' => 'Die Übernahme des Kanals ist kostenlos. Sie sehen die Teilnehmerliste mit Kontakten, können die Veranstaltung bearbeiten, weitere hinzufügen, die Kapazität festlegen und am Eingang QR-Codes scannen.',
        'claim_action' => 'Kanal übernehmen und Teilnehmer ansehen',
        'claim_ignore' => 'Wenn Sie nicht der Veranstalter sind, können Sie diese E-Mail ignorieren.',
    ],
    'event_signup_admin' => [
        'subject' => 'Anmeldung: :event',
        'intro' => '**:name** (:email) hat sich zu **„:event"** angemeldet.',
        'status' => [
            'reserved' => 'Ergebnis: Platz reserviert, Ticket ausgestellt.',
            'interest' => 'Ergebnis: nur Interesse (Reservierung aus, nur kostenpflichtige Tickets oder voll).',
            'already_registered' => 'Ergebnis: war bereits angemeldet.',
            'closed' => 'Ergebnis: Anmeldung geschlossen.',
        ],
        'organizer' => [
            'owner' => 'Veranstalter benachrichtigt: :email',
            'invitation' => 'Niemand verwaltet den Kanal — Übernahme-Einladung an :email gesendet.',
            'none' => 'Veranstalter nicht erreichbar — keine E-Mail bei Veranstaltung oder Kanal. Bitte manuell kontaktieren.',
        ],
        'event_link' => 'Veranstaltung: :url',
        'action' => 'Teilnehmerliste',
    ],
    'event_reservation_invite' => [
        'subject' => 'Konto bestätigt — reservieren Sie Ihren Platz bei :event',
        'intro' => 'Danke, Ihre E-Mail ist bestätigt und Ihr Konto ist bereit.',
        'next' => 'Sie können jetzt Ihren Platz bei **„:event"** reservieren — einfach anmelden und mit einem Klick bestätigen. Das Ticket mit QR-Code senden wir per E-Mail.',
        'action' => 'Platz reservieren',
        'outro' => 'Die Reservierung ist kostenlos. Der Veranstalter wird benachrichtigt.',
    ],
    'event_interest' => [
        'subject' => 'Ihr Interesse an :event',
        'intro' => 'Danke für Ihre Anmeldung zu **„:event"**. Der Veranstalter nimmt noch keine Reservierungen an oder es ist voll, daher stellen wir kein Ticket aus.',
        'next' => 'Ihr Interesse haben wir an den Veranstalter weitergegeben. Details zum Einlass finden Sie bei der Veranstaltung.',
        'action' => 'Veranstaltung anzeigen',
    ],

    // App\Notifications\RegistrationCancelled — účastníkovi po zrušení vlastnej rezervácie.
    'registration_cancelled' => [
        'subject' => 'Reservierung für :event storniert',
        'intro' => 'Ihre Reservierung für **„:event"** wurde storniert. Das Ticket und sein QR-Code sind nicht mehr gültig.',
        'next' => 'Wenn Sie es sich anders überlegen, können Sie erneut reservieren, solange Plätze frei sind.',
        'action' => 'Veranstaltung anzeigen',
    ],

    // App\Notifications\EventCancellationOrganizerNotice — organizátorovi, keď sa miesto uvoľní.
    'event_cancellation_organizer' => [
        'subject' => 'Stornierte Reservierung für :event',
        'intro' => '**:name** hat die Reservierung für Ihre Veranstaltung **„:event"** storniert. Ein Platz ist frei geworden.',
    ],

];
