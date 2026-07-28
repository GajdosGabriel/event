<?php

// Texty e-mailových notifikácií (App\Notifications\*) a ich šablón
// (resources/views/mail/*). Predmet aj telo držíme tu, nie v kóde, aby sa dali
// preložiť do ďalších jazykov. Markdown (**tučné**, [odkaz](:url)) je súčasťou
// textu — šablóny ho prechádzajú Markdown parserom.
return [

    // Texty zdieľané viacerými e-mailami.
    'common' => [
        'greeting'          => 'Dobrý deň!',
        'greeting_named'    => 'Dobrý deň, :name!',
        'event_fallback'    => 'podujatie',
        'workshop_fallback' => 'workshop',
        // Náhradný popis vstupenky, keď účastník nemá vyplnené meno.
        'seat_label'        => 'Vstupenka :number',
        // Riadok jednej vstupenky v zozname (s typom lístka a bez neho).
        'seat'              => '**:label**',
        'seat_typed'        => '**:label** · :type',
        'qr_alt'            => 'QR kód',
        'qr_open'           => 'Otvoriť QR kód',
    ],

    // App\Notifications\PendingRegistrationVerification
    'verification' => [
        'subject' => 'Overte si e-mailovú adresu',
        'intro'   => 'Ďakujeme za registráciu. Dokončite ju overením svojej e-mailovej adresy.',
        'action'  => 'Overiť e-mail',
        'expires' => '{1} Odkaz je platný :count hodinu.|[2,4] Odkaz je platný :count hodiny.|[5,*] Odkaz je platný :count hodín.',
        'ignore'  => 'Ak ste si účet nevytvárali, nemusíte robiť nič.',
    ],

    // App\Notifications\TicketIssued — objednávateľovi po vytvorení lístka.
    'ticket_issued' => [
        'subject'      => 'Váš lístok na :event',
        'intro'        => 'Váš lístok na akciu **„:event"** bol úspešne vytvorený.',
        'quantity'     => 'Počet rezervovaných miest: **:count**.',
        'qr_note'      => 'Každá vstupenka má vlastný QR kód. Jednotlivé kódy môžete preposlať aj ďalším účastníkom — pri vstupe sa každý načíta samostatne.',
        'pending'      => '{1} Ešte **:count** vstupenka čaká na potvrdenie účastníkom.|[2,4] Ešte **:count** vstupenky čakajú na potvrdenie účastníkmi.|[5,*] Ešte **:count** vstupeniek čaká na potvrdenie účastníkmi.',
        'pending_note' => 'Ich QR kód sa vytvorí až po tom, čo potvrdia účasť — o každom potvrdení vás upozorníme e-mailom.',
        'action'       => 'Zobraziť lístok a QR kód',
        'outro'        => 'Lístok si prineste v telefóne alebo vytlačte a predložte ho pri vstupe na akciu.',
    ],

    // App\Notifications\AttendeeTicketIssued — ďalšiemu účastníkovi objednávky.
    'attendee_ticket_issued' => [
        'subject'           => 'Vaša vstupenka na :event',
        'intro_paid'        => '**:holder** vám objednal(a) vstupenku na akciu **„:event"**.',
        'intro_free'        => '**:holder** vám rezervoval(a) miesto na akciu **„:event"**.',
        'outro'             => 'Vstupenku si prineste v telefóne alebo vytlačte a QR kód predložte pri vstupe na akciu.',
        'cancel'            => 'Nemôžete prísť? [Zrušiť vstupenku](:url) — miesto uvoľníme ďalším záujemcom.',
        'activation'        => 'Na túto e-mailovú adresu sme vám založili účet, aby ste mali svoje lístky vždy poruke. Účet si plne aktivujete prihlásením — potvrdíte tým svoju e-mailovú adresu a odsúhlasíte podmienky.',
        'activation_action' => 'Aktivovať účet',
    ],

    // App\Notifications\AttendeeConfirmationRequest — žiadosť o potvrdenie účasti.
    'attendee_confirmation_request' => [
        'subject'    => 'Potvrďte účasť na :event',
        'intro_paid' => '**:holder** vám objednal(a) vstupenku na akciu **„:event"**.',
        'intro_free' => '**:holder** vám rezervoval(a) miesto na akciu **„:event"**.',
        'ask'        => 'Aby sme vám miesto podržali, potvrďte prosím svoju účasť.',
        'deadline'   => 'Potvrďte prosím **do :deadline**. Ak sa tak nestane, rezervácia sa automaticky zruší a miesto uvoľníme ďalším záujemcom.',
        'confirm'    => 'Potvrdiť účasť',
        'decline'    => 'Zrušiť lístok',
        'ignore'     => 'Ak ste o túto rezerváciu nežiadali, jednoducho lístok zrušte alebo tento e-mail ignorujte — miesto sa po lehote uvoľní samo.',
        'activation' => 'Na túto e-mailovú adresu sme vám založili účet, aby ste mali svoje lístky vždy poruke. Plne ho aktivujete prihlásením.',
    ],

    // App\Notifications\AttendeeConfirmed — objednávateľovi, keď účastník potvrdil.
    'attendee_confirmed' => [
        'subject'        => ':attendee potvrdil(a) účasť na :event',
        'heading'        => 'Dobrá správa!',
        'heading_named'  => 'Dobrá správa, :name!',
        'intro'          => '{1} **:attendee** potvrdil(a) účasť na akcii **„:event"**.|[2,4] **:attendee** potvrdil(a) účasť na akcii **„:event"** (:count miesta).|[5,*] **:attendee** potvrdil(a) účasť na akcii **„:event"** (:count miest).',
        'ticket_sent'    => 'Jeho/jej vstupenku s QR kódom sme práve poslali na **:email**.',
        'action'         => 'Zobraziť objednávku',
    ],

    // App\Notifications\AttendeeDeclined — účastník lístok zrušil alebo nepotvrdil.
    'attendee_declined' => [
        'subject'       => 'Uvoľnené miesto na :event',
        'expired'       => '{1} **:attendee** nepotvrdil(a) účasť na akcii **„:event"** v stanovenej lehote, preto sme jeho/jej rezervované miesto uvoľnili.|[2,4] **:attendee** nepotvrdil(a) účasť na akcii **„:event"** v stanovenej lehote, preto sme :count rezervované miesta uvoľnili.|[5,*] **:attendee** nepotvrdil(a) účasť na akcii **„:event"** v stanovenej lehote, preto sme :count rezervovaných miest uvoľnili.',
        'declined'      => '{1} **:attendee** (:email) zrušil(a) lístok na akciu **„:event"**, takže miesto je opäť voľné.|[2,4] **:attendee** (:email) zrušil(a) lístok na akciu **„:event"** (:count miesta), takže miesta sú opäť voľné.|[5,*] **:attendee** (:email) zrušil(a) lístok na akciu **„:event"** (:count miest), takže miesta sú opäť voľné.',
        'waitlist_note' => 'Ak sa uvoľnilo miesto na obsadenom podujatí alebo workshope, automaticky sme oň posunuli prvého náhradníka.',
    ],

    // App\Notifications\MessageReceived — správa cez tlačidlo „Poslať správu".
    'message_received' => [
        'subject'    => 'Nová správa – :label „:name"',
        'heading'    => 'Nová správa',
        'intro'      => 'Dostali ste správu k :label **„:name"**.',
        'from'       => '**Od:** :name (:email)',
        'reply_hint' => 'Odpovedať môžete priamo na tento e-mail — odpoveď dorazí odosielateľovi.',
        'action'     => 'Zobraziť :label',
        // Názov typu cieľa správy (App\Models\Message::targetType()).
        'targets'    => [
            'event'   => 'podujatie',
            'venue'   => 'miesto',
            'canal'   => 'kanál',
            'default' => 'profil',
        ],
        'target_fallback' => 'váš profil',
    ],

    // App\Notifications\WorkshopSeatGranted — náhradníkovi sa uvoľnilo miesto.
    'workshop_seat_granted' => [
        'subject'       => 'Uvoľnilo sa miesto na workshope :workshop',
        'intro'         => 'Na workshope „:workshop" (:event) sa uvoľnilo miesto a ponúkame ho vám ako prvému náhradníkovi.',
        'starts_at'     => 'Termín: :date.',
        'deadline'      => 'Miesto vám držíme do **:deadline**. Ak ho dovtedy nepotvrdíte, ponúkneme ho ďalšiemu náhradníkovi.',
        'action'        => 'Potvrdiť miesto',
        'after_confirm' => 'Vstupenku s QR kódom vám pošleme hneď po potvrdení.',
        'decline'       => 'Ak sa workshopu zúčastniť nemôžete, [odmietnite miesto](:url) — pustíme naň ďalšieho v poradí.',
    ],

    // App\Notifications\WorkshopWaitlisted — zaradenie medzi náhradníkov.
    'workshop_waitlisted' => [
        'subject'  => 'Ste náhradník na workshop :workshop',
        'intro'    => 'Workshop „:workshop" na akcii „:event" je momentálne plný, zaradili sme vás medzi náhradníkov.',
        'position' => 'Vaše poradie: :position.',
        'note'     => 'Ak sa miesto uvoľní, automaticky vám ho pridelíme a pošleme vám lístok s QR kódom.',
        'action'   => 'Zobraziť podujatie',
    ],

];
