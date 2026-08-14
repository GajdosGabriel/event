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
        // Sekcia „Pridať do kalendára" (resources/views/mail/partials/calendar).
        'calendar_intro'    => 'Aby ste na termín nezabudli, zapíšte si podujatie do kalendára:',
        'calendar_ics'      => 'Pridať do kalendára',
        'calendar_google'   => 'Google Kalendár',
        'calendar_outlook'  => 'Outlook',
    ],

    // App\Notifications\PendingRegistrationVerification
    'verification' => [
        'subject' => 'Overte si e-mailovú adresu',
        'intro'   => 'Ďakujeme za registráciu. Dokončite ju overením svojej e-mailovej adresy.',
        'action'  => 'Overiť e-mail',
        'expires' => '{1} Odkaz je platný :count hodinu.|[2,4] Odkaz je platný :count hodiny.|[5,*] Odkaz je platný :count hodín.',
        'ignore'  => 'Ak ste si účet nevytvárali, nemusíte robiť nič.',
    ],

    // App\Notifications\AttributeIssueNotice — spoločné upozornenie na údaj,
    // ktorý prestal fungovať (dnes webová adresa, neskôr čokoľvek ďalšie).
    // Nový overovaný údaj = nový riadok v `attributes`, nie nová notifikácia.
    'attribute_issue' => [
        'types' => [
            'canal'        => 'kanála',
            'venue'        => 'miesta',
            'event'        => 'podujatia',
            'organization' => 'organizátora',
        ],
        'attributes' => [
            'website' => 'webová adresa',
        ],
        'subject'     => 'Nefunkčná :attribute vo vašom zázname',
        'intro'       => 'Pri kontrole sme zistili, že :attribute :type na portáli podujatí neodpovedá.',
        'intro_named' => 'Pri kontrole sme zistili, že :attribute :type **„:name"** na portáli podujatí neodpovedá.',
        'reasons'     => [
            'dns'           => 'Doménu sa nepodarilo nájsť — býva to preklep v adrese alebo doména po expirácii.',
            'not_found'     => 'Server odpovedal, ale stránka na tejto adrese už neexistuje (chyba :status). Najčastejšie ide o presunutú podstránku.',
            'server_error'  => 'Server na adrese hlási chybu (:status). Môže ísť aj o dočasný výpadok hostingu.',
            'http_error'    => 'Server odpovedal chybou :status.',
            'timeout'       => 'Server na adrese neodpovedal v rozumnom čase.',
            'ssl'           => 'Zabezpečené spojenie sa nepodarilo nadviazať — obvykle kvôli neplatnému certifikátu.',
            'unreachable'   => 'Na adrese sa nepodarilo spojiť so žiadnym serverom.',
            'redirect'      => 'Adresa presmerúva na miesto, ktoré sa nedá otvoriť.',
            'redirect_loop' => 'Adresa sa presmerúva dokola.',
            'blocked'       => 'Adresa nesmeruje na verejný internet, takže ju nevieme overiť.',
            'invalid'       => 'Adresa nemá platný tvar.',
        ],
        'seen_on'     => 'Naposledy na ňu niekto klikol tu: :url',
        'action'      => 'Opraviť adresu',
        'recheck'     => 'Adresu overujeme pravidelne — po oprave sa upozornenie samo prestane posielať.',
        'false_alarm' => 'Ak je adresa v poriadku a išlo len o dočasný výpadok, nemusíte robiť nič.',
    ],

    // App\Notifications\CanalInvitationSent — pozvánka do tímu kanála.
    'canal_invitation' => [
        'subject'        => 'Pozvánka do tímu :canal',
        'canal_fallback' => 'kanál',
        'intro'          => 'Boli ste pozvaný(á) do tímu kanála **„:canal"**.',
        'intro_named'    => '**:inviter** vás pozýva do tímu kanála **„:canal"**.',
        'role'           => 'Vaša rola: **:role**.',
        'role_note'      => [
            'owner'   => 'Ako vlastník budete môcť spravovať kanál, jeho podujatia aj tím.',
            'editor'  => 'Ako editor budete môcť vytvárať a upravovať podujatia, miesta a lístky.',
            'checkin' => 'Ako obsluha vstupu budete môcť načítavať QR kódy a odbavovať príchody.',
        ],
        'action'         => 'Prijať pozvánku',
        'expires'        => 'Pozvánka platí do :date.',
        'email_note'     => 'Pozvánku prijmite po prihlásení účtom s adresou **:email**. Ak účet ešte nemáte, najprv sa na túto adresu zaregistrujte.',
        'ignore'         => 'Ak ste pozvánku nečakali, stačí tento e-mail ignorovať.',
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

    // App\Notifications\MessageReplied — odpoveď organizátora z inboxu.
    'message_replied' => [
        'subject'    => 'Odpoveď – :label „:name"',
        'heading'    => 'Prišla vám odpoveď',
        'intro'      => '**:name** odpovedal(a) na vašu správu k :label **„:target"**.',
        'reply_hint' => 'Odpovedať môžete priamo na tento e-mail.',
        'action'     => 'Zobraziť konverzáciu',
    ],

    // App\Notifications\EventAnnouncement — hromadný e-mail organizátora.
    // Predmet aj telo píše organizátor, tu sú len rámcové texty.
    'event_announcement' => [
        'action' => 'Zobraziť podujatie',
        'outro'  => 'Tento e-mail vám prišiel, lebo máte lístok na uvedené podujatie.',
    ],

    // App\Notifications\EventReminder — pripomienka pred akciou.
    'event_reminder' => [
        'subject'   => 'Pripomienka: :event',
        'intro'     => 'Pripomíname, že sa blíži akcia **„:event"**, na ktorú máte lístok.',
        'starts_at' => 'Začiatok: **:date**.',
        'venue'     => 'Miesto: **:venue**.',
        'action'    => 'Zobraziť podujatie',
        'outro'     => 'Vstupenku s QR kódom nájdete v e-maile, ktorý vám prišiel pri objednávke.',
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

    // App\Notifications\PosterDraftSaved — odkaz späť na nahratý plagát.
    'poster_draft' => [
        'subject'     => 'Váš plagát čaká — dokončite podujatie',
        'intro'       => 'Plagát sme spracovali a podujatie máme pripravené.',
        'intro_named' => 'Plagát sme spracovali a podujatie **„:name"** máme pripravené.',
        'next'        => 'Zostáva ho už len skontrolovať a uložiť. Ak tu ešte účet nemáte, vytvoríte si ho pri ukladaní.',
        'action'      => 'Dokončiť podujatie',
        'expires'     => 'Rozpracované podujatie vám držíme do **:date**.',
        'ignore'      => 'Ak ste plagát nenahrávali vy, tento e-mail pokojne ignorujte — bez potvrdenia sa nikde nič nezverejní.',
    ],

];
