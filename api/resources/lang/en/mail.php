<?php

// Texty e-mailových notifikácií (App\Notifications\*) a ich šablón
// (resources/views/mail/*). Predmet aj telo držíme tu, nie v kóde, aby sa dali
// preložiť do ďalších jazykov. Markdown (**tučné**, [odkaz](:url)) je súčasťou
// textu — šablóny ho prechádzajú Markdown parserom.
return [

    // Texty zdieľané viacerými e-mailami.
    'common' => [
        'greeting'          => 'Hello!',
        'greeting_named'    => 'Hello :name!',
        'event_fallback'    => 'event',
        'workshop_fallback' => 'workshop',
        // Náhradný popis vstupenky, keď účastník nemá vyplnené meno.
        'seat_label'        => 'Ticket :number',
        // Riadok jednej vstupenky v zozname (s typom lístka a bez neho).
        'seat'              => '**:label**',
        'seat_typed'        => '**:label** · :type',
        'qr_alt'            => 'QR code',
        'qr_open'           => 'Open QR code',
        // Sekcia „Pridať do kalendára" (resources/views/mail/partials/calendar).
        'calendar_title'    => 'Add to calendar',
        'calendar_intro'    => 'So the date does not slip your mind, add the event to your calendar:',
        'calendar_ics'      => 'Apple Calendar and others',
        'calendar_google'   => 'Google Calendar',
        'calendar_outlook'  => 'Outlook',
        // Pätička e-mailov z odberu (resources/views/mail/partials/unsubscribe).
        'unsubscribe_intro'  => 'You are getting this e-mail because you asked to be notified about this event.',
        'unsubscribe_action' => 'Stop these notifications',
    ],

    // App\Notifications\PendingRegistrationVerification
    'verification' => [
        'subject' => 'Verify your e-mail address',
        'intro'   => 'Thanks for signing up. Finish it by verifying your e-mail address.',
        'action'  => 'Verify e-mail',
        'expires' => '{1} The link is valid for :count hour.|[2,*] The link is valid for :count hours.',
        'ignore'  => 'If you did not create an account, no action is required.',
    ],

    // App\Notifications\AttributeIssueNotice — spoločné upozornenie na údaj,
    // ktorý prestal fungovať (dnes webová adresa, neskôr čokoľvek ďalšie).
    'attribute_issue' => [
        'types' => [
            'canal'        => 'your channel',
            'venue'        => 'your venue',
            'event'        => 'your event',
            'organization' => 'your organiser profile',
        ],
        'attributes' => [
            'website' => 'website address',
        ],
        'subject'     => 'A broken :attribute in your record',
        'intro'       => 'While checking, we found that the :attribute of :type on the events portal does not respond.',
        'intro_named' => 'While checking, we found that the :attribute of :type **“:name”** on the events portal does not respond.',
        'reasons'     => [
            'dns'           => 'The domain could not be found — usually a typo in the address or an expired domain.',
            'not_found'     => 'The server responded, but the page at this address no longer exists (error :status). Most often the subpage has moved.',
            'server_error'  => 'The server reports an error (:status). It may also be a temporary hosting outage.',
            'http_error'    => 'The server responded with error :status.',
            'timeout'       => 'The server did not respond in reasonable time.',
            'ssl'           => 'A secure connection could not be established — usually an invalid certificate.',
            'unreachable'   => 'No server could be reached at this address.',
            'redirect'      => 'The address redirects somewhere that cannot be opened.',
            'redirect_loop' => 'The address redirects in a loop.',
            'blocked'       => 'The address does not point to the public internet, so we cannot verify it.',
            'invalid'       => 'The address is not in a valid form.',
        ],
        'seen_on'     => 'Someone last clicked it here: :url',
        'action'      => 'Fix the address',
        'recheck'     => 'We check the address regularly — once fixed, these notices stop on their own.',
        'false_alarm' => 'If the address is fine and it was only a temporary outage, you need not do anything.',
    ],

    // App\Notifications\CanalInvitationSent — pozvánka do tímu kanála.
    'canal_invitation' => [
        'subject'        => 'Invitation to the :canal team',
        'canal_fallback' => 'canal',
        'intro'          => 'You have been invited to the team of canal **":canal"**.',
        'intro_named'    => '**:inviter** invites you to the team of canal **":canal"**.',
        'role'           => 'Your role: **:role**.',
        'role_note'      => [
            'owner'   => 'As an owner you will be able to manage the canal, its events and its team.',
            'editor'  => 'As an editor you will be able to create and edit events, venues and tickets.',
            'checkin' => 'On check-in duty you will be able to scan QR codes and admit arrivals.',
        ],
        'action'         => 'Accept invitation',
        'expires'        => 'The invitation is valid until :date.',
        'email_note'     => 'Accept the invitation while signed in with the address **:email**. If you do not have an account yet, register that address first.',
        'ignore'         => 'If you were not expecting this invitation, simply ignore this e-mail.',
    ],

    // App\Notifications\TicketIssued — objednávateľovi po vytvorení lístka.
    'ticket_issued' => [
        'subject'      => 'Your ticket for :event',
        'intro'        => 'Your ticket for **":event"** has been created.',
        'quantity'     => 'Seats reserved: **:count**.',
        'qr_note'      => 'Each ticket has its own QR code. You can forward individual codes to other attendees — every code is scanned separately at the door.',
        'pending'      => '{1} **:count** more ticket is awaiting confirmation by its attendee.|[2,*] **:count** more tickets are awaiting confirmation by their attendees.',
        'pending_note' => 'Their QR code is generated only once they confirm — we will e-mail you about every confirmation.',
        'action'       => 'View ticket and QR code',
        'outro'        => 'Bring the ticket on your phone or print it and present it at the door.',
    ],

    // App\Notifications\TicketIssued s príznakom `restored` — obnovená objednávka.
    'ticket_restored' => [
        'subject' => 'Your registration for :event is valid again',
        'intro'   => 'We have restored your cancelled registration for **":event"** — your seats are valid again.',
    ],

    // App\Notifications\AttendeeTicketIssued — ďalšiemu účastníkovi objednávky.
    'attendee_ticket_issued' => [
        'subject'           => 'Your ticket for :event',
        'intro_paid'        => '**:holder** has bought you a ticket for **":event"**.',
        'intro_free'        => '**:holder** has reserved you a seat at **":event"**.',
        'outro'             => 'Bring the ticket on your phone or print it and present the QR code at the door.',
        'cancel'            => 'Cannot make it? [Cancel the ticket](:url) — we will release the seat to someone else.',
        'activation'        => 'We created an account for this e-mail address so your tickets are always at hand. Signing in fully activates it — that confirms your e-mail address and accepts the terms.',
        'activation_action' => 'Activate account',
    ],

    // App\Notifications\AttendeeConfirmationRequest — žiadosť o potvrdenie účasti.
    'attendee_confirmation_request' => [
        'subject'    => 'Confirm your attendance at :event',
        'intro_paid' => '**:holder** has bought you a ticket for **":event"**.',
        'intro_free' => '**:holder** has reserved you a seat at **":event"**.',
        'ask'        => 'Please confirm your attendance so we can hold the seat for you.',
        'deadline'   => 'Please confirm **by :deadline**. Otherwise the reservation is cancelled automatically and the seat goes to someone else.',
        'confirm'    => 'Confirm attendance',
        'decline'    => 'Cancel ticket',
        'ignore'     => 'If you did not ask for this reservation, just cancel the ticket or ignore this e-mail — the seat is released on its own once the deadline passes.',
        'activation' => 'We created an account for this e-mail address so your tickets are always at hand. Signing in fully activates it.',
    ],

    // App\Notifications\AttendeeConfirmed — objednávateľovi, keď účastník potvrdil.
    'attendee_confirmed' => [
        'subject'        => ':attendee confirmed attendance at :event',
        'heading'        => 'Good news!',
        'heading_named'  => 'Good news, :name!',
        'intro'          => '{1} **:attendee** confirmed attendance at **":event"**.|[2,*] **:attendee** confirmed attendance at **":event"** (:count seats).',
        'ticket_sent'    => 'We have just sent their ticket with the QR code to **:email**.',
        'action'         => 'View order',
    ],

    // App\Notifications\AttendeeDeclined — účastník lístok zrušil alebo nepotvrdil.
    'attendee_declined' => [
        'subject'       => 'Seat released at :event',
        'expired'       => '{1} **:attendee** did not confirm attendance at **":event"** in time, so we released their reserved seat.|[2,*] **:attendee** did not confirm attendance at **":event"** in time, so we released :count reserved seats.',
        'declined'      => '{1} **:attendee** (:email) cancelled their ticket for **":event"**, so the seat is free again.|[2,*] **:attendee** (:email) cancelled their ticket for **":event"** (:count seats), so the seats are free again.',
        'waitlist_note' => 'If a seat opened up at a sold-out event or workshop, we automatically moved the first person on the waitlist into it.',
    ],

    // App\Notifications\MessageReceived — správa cez tlačidlo „Poslať správu".
    'message_received' => [
        'subject'    => 'New message – :label ":name"',
        'heading'    => 'New message',
        'intro'      => 'You have received a message about the :label **":name"**.',
        'from'       => '**From:** :name (:email)',
        'reply_hint' => 'You can reply straight to this e-mail — your reply reaches the sender.',
        'action'     => 'View :label',
        // Názov typu cieľa správy (App\Models\Message::targetType()).
        'targets'    => [
            'event'   => 'event',
            'venue'   => 'venue',
            'canal'   => 'canal',
            'default' => 'profile',
        ],
        'target_fallback' => 'your profile',
    ],

    // App\Notifications\MessageReplied — odpoveď organizátora z inboxu.
    'message_replied' => [
        'subject'    => 'Reply – :label ":name"',
        'heading'    => 'You have a reply',
        'intro'      => '**:name** replied to your message about the :label **":target"**.',
        'reply_hint' => 'You can reply straight to this e-mail.',
        'action'     => 'View conversation',
    ],

    // App\Notifications\EventAnnouncement — hromadný e-mail organizátora.
    // Predmet aj telo píše organizátor, tu sú len rámcové texty.
    'event_announcement' => [
        'action' => 'View event',
        'outro'  => 'You are receiving this e-mail because you hold a ticket for this event.',
    ],

    // App\Notifications\EventReminder — pripomienka pred akciou.
    'event_reminder' => [
        'subject'   => 'Reminder: :event',
        'intro'     => 'A reminder that **":event"**, which you hold a ticket for, is coming up.',
        'starts_at' => 'Starts: **:date**.',
        'venue'     => 'Venue: **:venue**.',
        'action'    => 'View event',
        'outro'     => 'Your ticket with the QR code is in the e-mail you received with your order.',
        // Ten istý e-mail pre toho, kto si vypýtal upozornenie a lístok nemá.
        'outro_subscriber' => 'No registration needed — just turn up.',
    ],

    // App\Notifications\QuestionAnswered — jediný e-mail, ktorý pisateľ otázky
    // dostane. Adresa sa hneď po odoslaní maže, preto tu nie je odhlásenie.
    'question_answered' => [
        'subject'      => 'An answer to your question: :event',
        'intro'        => 'The organiser has answered the question you asked about **":event"**.',
        'answer_label' => "Organiser's answer",
        'action'       => 'View the event',
        'outro'        => 'We used your address for this single answer and no longer have it — you will not hear from us again.',
    ],

    'question_received' => [
        'feedback' => [
            'subject' => 'Feedback from the audience: :event',
            'intro'   => 'Someone in the audience sent you feedback during **":event"**:',
            'hint'    => 'Only you can see this. If anything can be done about it, now is the time — then mark it as handled on the board.',
        ],
        'private' => [
            'subject' => 'Private question: :event',
            'intro'   => 'Someone is asking you privately about **":event"**:',
            'hint'    => 'Nobody else can see this question. Write the answer on the board and we will e-mail it to them.',
        ],
        'public' => [
            'subject' => 'New question: :event',
            'intro'   => 'Someone is asking about **":event"**:',
            'hint'    => 'The question is public on the event page. Write the answer on the board and it stays there as an FAQ.',
        ],
        'from'   => 'From: :name',
        'action' => 'Open the question board',
    ],

    // App\Notifications\SubscriptionConfirmed — prvý e-mail po „Pripomeň mi".
    'subscription_confirmed' => [
        'subject'   => 'We will keep you posted: :event',
        'intro'     => 'Done. If anything about **":event"** changes, or the organiser calls it off, we will let you know — and we will send a reminder before it starts.',
        'starts_at' => 'Starts: **:date**.',
        'venue'     => 'Venue: **:venue**.',
        'action'    => 'View event',
        'outro'     => 'If you did not ask for this, stop it with the link below — your address is deleted right away.',
    ],

    // App\Notifications\EventChanged — sľub z tlačidla „Pripomeň mi".
    'event_changed' => [
        'subject'           => 'Changed: :event',
        'subject_cancelled' => 'Called off: :event',
        'intro'             => 'Here is what changed about **":event"**:',
        'intro_cancelled'   => 'The event **":event"** is not happening — the organiser withdrew it. If you added it to your calendar, you can delete that entry.',
        'starts_at'         => 'New date: **:date**.',
        'venue'             => 'Venue: **:venue**.',
        'action'            => 'View event',
        'change_start'      => 'Date: :from → :to',
        'change_venue'      => 'Venue: :from → :to',
    ],

    // App\Notifications\WorkshopSeatGranted — náhradníkovi sa uvoľnilo miesto.
    'workshop_seat_granted' => [
        'subject'       => 'A seat opened up at the :workshop workshop',
        'intro'         => 'A seat opened up at the workshop ":workshop" (:event) and we are offering it to you as first on the waitlist.',
        'starts_at'     => 'Date: :date.',
        'deadline'      => 'We are holding the seat until **:deadline**. If you do not confirm by then, we offer it to the next person on the waitlist.',
        'action'        => 'Confirm seat',
        'after_confirm' => 'We will send your ticket with the QR code as soon as you confirm.',
        'decline'       => 'If you cannot attend the workshop, [decline the seat](:url) — we will pass it to the next person in line.',
    ],

    // App\Notifications\WorkshopWaitlisted — zaradenie medzi náhradníkov.
    'workshop_waitlisted' => [
        'subject'  => 'You are on the waitlist for the :workshop workshop',
        'intro'    => 'The workshop ":workshop" at ":event" is currently full, so we put you on the waitlist.',
        'position' => 'Your position: :position.',
        'note'     => 'If a seat opens up, we assign it to you automatically and send you a ticket with a QR code.',
        'action'   => 'View event',
    ],

    // App\Notifications\PosterDraftSaved — odkaz späť na nahratý plagát.
    'poster_draft' => [
        'subject'     => 'Your poster is waiting — finish the event',
        'intro'       => 'We processed the poster and your event is ready.',
        'intro_named' => 'We processed the poster and the event **":name"** is ready.',
        'next'        => 'All that is left is to review and save it. If you do not have an account here yet, you create one while saving.',
        'action'      => 'Finish event',
        'expires'     => 'We keep the draft event for you until **:date**.',
        'ignore'      => 'If you did not upload the poster, feel free to ignore this e-mail — nothing is published anywhere without confirmation.',
    ],

];
