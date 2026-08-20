<?php

// Texty e-mailových notifikácií (App\Notifications\*) a ich šablón
// (resources/views/mail/*). Predmet aj telo držíme tu, nie v kóde, aby sa dali
// preložiť do ďalších jazykov. Markdown (**tučné**, [odkaz](:url)) je súčasťou
// textu — šablóny ho prechádzajú Markdown parserom.
return [

    // Texty zdieľané viacerými e-mailami.
    'common' => [
        'greeting'          => 'Dobrý den!',
        'greeting_named'    => 'Dobrý den, :name!',
        'event_fallback'    => 'akce',
        'workshop_fallback' => 'workshop',
        // Náhradný popis vstupenky, keď účastník nemá vyplnené meno.
        'seat_label'        => 'Vstupenka :number',
        // Riadok jednej vstupenky v zozname (s typom lístka a bez neho).
        'seat'              => '**:label**',
        'seat_typed'        => '**:label** · :type',
        'qr_alt'            => 'QR kód',
        'qr_open'           => 'Otevřít QR kód',
        // Sekcia „Pridať do kalendára" (resources/views/mail/partials/calendar).
        'calendar_title'    => 'Přidat do kalendáře',
        'calendar_intro'    => 'Abyste na termín nezapomněli, zapište si akci do kalendáře:',
        'calendar_ics'      => 'Apple Kalendář a ostatní',
        'calendar_google'   => 'Google Kalendář',
        'calendar_outlook'  => 'Outlook',
        // Pätička e-mailov z odberu (resources/views/mail/partials/unsubscribe).
        'unsubscribe_intro'  => 'Tento e-mail vám přišel, protože jste si k akci vyžádali upozornění.',
        'unsubscribe_action' => 'Zrušit upozornění',
    ],

    // App\Notifications\PendingRegistrationVerification
    'verification' => [
        'subject' => 'Ověřte si e-mailovou adresu',
        'intro'   => 'Děkujeme za registraci. Dokončete ji ověřením své e-mailové adresy.',
        'action'  => 'Ověřit e-mail',
        'expires' => '{1} Odkaz je platný :count hodinu.|[2,4] Odkaz je platný :count hodiny.|[5,*] Odkaz je platný :count hodin.',
        'ignore'  => 'Pokud jste si účet nevytvářeli, nemusíte dělat nic.',
    ],

    // App\Notifications\AttributeIssueNotice — spoločné upozornenie na údaj,
    // ktorý prestal fungovať (dnes webová adresa, neskôr čokoľvek ďalšie).
    'attribute_issue' => [
        'types' => [
            'canal'        => 'kanálu',
            'venue'        => 'místa',
            'event'        => 'akce',
            'organization' => 'pořadatele',
        ],
        'attributes' => [
            'website' => 'webová adresa',
        ],
        'subject'     => 'Nefunkční :attribute ve vašem záznamu',
        'intro'       => 'Při kontrole jsme zjistili, že :attribute :type na portálu akcí neodpovídá.',
        'intro_named' => 'Při kontrole jsme zjistili, že :attribute :type **„:name"** na portálu akcí neodpovídá.',
        'reasons'     => [
            'dns'           => 'Doménu se nepodařilo najít — bývá to překlep v adrese nebo doména po expiraci.',
            'not_found'     => 'Server odpověděl, ale stránka na této adrese už neexistuje (chyba :status). Nejčastěji jde o přesunutou podstránku.',
            'server_error'  => 'Server na adrese hlásí chybu (:status). Může jít i o dočasný výpadek hostingu.',
            'http_error'    => 'Server odpověděl chybou :status.',
            'timeout'       => 'Server na adrese neodpověděl v rozumném čase.',
            'ssl'           => 'Zabezpečené spojení se nepodařilo navázat — obvykle kvůli neplatnému certifikátu.',
            'unreachable'   => 'Na adrese se nepodařilo spojit s žádným serverem.',
            'redirect'      => 'Adresa přesměrovává na místo, které nelze otevřít.',
            'redirect_loop' => 'Adresa se přesměrovává dokola.',
            'blocked'       => 'Adresa nesměřuje na veřejný internet, takže ji neumíme ověřit.',
            'invalid'       => 'Adresa nemá platný tvar.',
        ],
        'seen_on'     => 'Naposledy na ni někdo klikl zde: :url',
        'action'      => 'Opravit adresu',
        'recheck'     => 'Adresu ověřujeme pravidelně — po opravě se upozornění samo přestane posílat.',
        'false_alarm' => 'Pokud je adresa v pořádku a šlo jen o dočasný výpadek, nemusíte dělat nic.',
    ],

    // App\Notifications\CanalInvitationSent — pozvánka do tímu kanála.
    'canal_invitation' => [
        'subject'        => 'Pozvánka do týmu :canal',
        'canal_fallback' => 'kanál',
        'intro'          => 'Byli jste pozváni do týmu kanálu **„:canal"**.',
        'intro_named'    => '**:inviter** vás zve do týmu kanálu **„:canal"**.',
        'role'           => 'Vaše role: **:role**.',
        'role_note'      => [
            'owner'   => 'Jako vlastník budete moci spravovat kanál, jeho akce i tým.',
            'editor'  => 'Jako editor budete moci vytvářet a upravovat akce, místa a lístky.',
            'checkin' => 'Jako obsluha vstupu budete moci načítat QR kódy a odbavovat příchody.',
        ],
        'action'         => 'Přijmout pozvánku',
        'expires'        => 'Pozvánka platí do :date.',
        'email_note'     => 'Pozvánku přijměte po přihlášení účtem s adresou **:email**. Pokud účet ještě nemáte, nejprve se na tuto adresu zaregistrujte.',
        'ignore'         => 'Pokud jste pozvánku nečekali, stačí tento e-mail ignorovat.',
    ],

    // App\Notifications\TicketIssued — objednávateľovi po vytvorení lístka.
    'ticket_issued' => [
        'subject'      => 'Váš lístek na :event',
        'intro'        => 'Váš lístek na akci **„:event"** byl úspěšně vytvořen.',
        'quantity'     => 'Počet rezervovaných míst: **:count**.',
        'qr_note'      => 'Každá vstupenka má vlastní QR kód. Jednotlivé kódy můžete přeposlat i dalším účastníkům — u vstupu se každý načte samostatně.',
        'pending'      => '{1} Ještě **:count** vstupenka čeká na potvrzení účastníkem.|[2,4] Ještě **:count** vstupenky čekají na potvrzení účastníky.|[5,*] Ještě **:count** vstupenek čeká na potvrzení účastníky.',
        'pending_note' => 'Jejich QR kód se vytvoří až poté, co potvrdí účast — o každém potvrzení vás upozorníme e-mailem.',
        'action'       => 'Zobrazit lístek a QR kód',
        'outro'        => 'Lístek si přineste v telefonu nebo vytiskněte a předložte ho při vstupu na akci.',
    ],

    // App\Notifications\AttendeeTicketIssued — ďalšiemu účastníkovi objednávky.
    'attendee_ticket_issued' => [
        'subject'           => 'Vaše vstupenka na :event',
        'intro_paid'        => '**:holder** vám objednal(a) vstupenku na akci **„:event"**.',
        'intro_free'        => '**:holder** vám rezervoval(a) místo na akci **„:event"**.',
        'outro'             => 'Vstupenku si přineste v telefonu nebo vytiskněte a QR kód předložte při vstupu na akci.',
        'cancel'            => 'Nemůžete přijít? [Zrušit vstupenku](:url) — místo uvolníme dalším zájemcům.',
        'activation'        => 'Na tuto e-mailovou adresu jsme vám založili účet, abyste měli své lístky vždy po ruce. Účet si plně aktivujete přihlášením — potvrdíte tím svou e-mailovou adresu a odsouhlasíte podmínky.',
        'activation_action' => 'Aktivovat účet',
    ],

    // App\Notifications\AttendeeConfirmationRequest — žiadosť o potvrdenie účasti.
    'attendee_confirmation_request' => [
        'subject'    => 'Potvrďte účast na :event',
        'intro_paid' => '**:holder** vám objednal(a) vstupenku na akci **„:event"**.',
        'intro_free' => '**:holder** vám rezervoval(a) místo na akci **„:event"**.',
        'ask'        => 'Abychom vám místo podrželi, potvrďte prosím svou účast.',
        'deadline'   => 'Potvrďte prosím **do :deadline**. Pokud se tak nestane, rezervace se automaticky zruší a místo uvolníme dalším zájemcům.',
        'confirm'    => 'Potvrdit účast',
        'decline'    => 'Zrušit lístek',
        'ignore'     => 'Pokud jste o tuto rezervaci nežádali, jednoduše lístek zrušte nebo tento e-mail ignorujte — místo se po lhůtě uvolní samo.',
        'activation' => 'Na tuto e-mailovou adresu jsme vám založili účet, abyste měli své lístky vždy po ruce. Plně ho aktivujete přihlášením.',
    ],

    // App\Notifications\AttendeeConfirmed — objednávateľovi, keď účastník potvrdil.
    'attendee_confirmed' => [
        'subject'        => ':attendee potvrdil(a) účast na :event',
        'heading'        => 'Dobrá zpráva!',
        'heading_named'  => 'Dobrá zpráva, :name!',
        'intro'          => '{1} **:attendee** potvrdil(a) účast na akci **„:event"**.|[2,4] **:attendee** potvrdil(a) účast na akci **„:event"** (:count místa).|[5,*] **:attendee** potvrdil(a) účast na akci **„:event"** (:count míst).',
        'ticket_sent'    => 'Jeho/její vstupenku s QR kódem jsme právě poslali na **:email**.',
        'action'         => 'Zobrazit objednávku',
    ],

    // App\Notifications\AttendeeDeclined — účastník lístok zrušil alebo nepotvrdil.
    'attendee_declined' => [
        'subject'       => 'Uvolněné místo na :event',
        'expired'       => '{1} **:attendee** nepotvrdil(a) účast na akci **„:event"** ve stanovené lhůtě, proto jsme jeho/její rezervované místo uvolnili.|[2,4] **:attendee** nepotvrdil(a) účast na akci **„:event"** ve stanovené lhůtě, proto jsme :count rezervovaná místa uvolnili.|[5,*] **:attendee** nepotvrdil(a) účast na akci **„:event"** ve stanovené lhůtě, proto jsme :count rezervovaných míst uvolnili.',
        'declined'      => '{1} **:attendee** (:email) zrušil(a) lístek na akci **„:event"**, takže místo je opět volné.|[2,4] **:attendee** (:email) zrušil(a) lístek na akci **„:event"** (:count místa), takže místa jsou opět volná.|[5,*] **:attendee** (:email) zrušil(a) lístek na akci **„:event"** (:count míst), takže místa jsou opět volná.',
        'waitlist_note' => 'Pokud se uvolnilo místo na obsazené akci nebo workshopu, automaticky jsme na něj posunuli prvního náhradníka.',
    ],

    // App\Notifications\MessageReceived — správa cez tlačidlo „Poslať správu".
    'message_received' => [
        'subject'    => 'Nová zpráva – :label „:name"',
        'heading'    => 'Nová zpráva',
        'intro'      => 'Dostali jste zprávu k :label **„:name"**.',
        'from'       => '**Od:** :name (:email)',
        'reply_hint' => 'Odpovědět můžete přímo na tento e-mail — odpověď dorazí odesílateli.',
        'action'     => 'Zobrazit :label',
        // Názov typu cieľa správy (App\Models\Message::targetType()).
        'targets'    => [
            'event'   => 'akci',
            'venue'   => 'místu',
            'canal'   => 'kanálu',
            'default' => 'profilu',
        ],
        'target_fallback' => 'váš profil',
    ],

    // App\Notifications\MessageReplied — odpoveď organizátora z inboxu.
    'message_replied' => [
        'subject'    => 'Odpověď – :label „:name"',
        'heading'    => 'Přišla vám odpověď',
        'intro'      => '**:name** odpověděl(a) na vaši zprávu k :label **„:target"**.',
        'reply_hint' => 'Odpovědět můžete přímo na tento e-mail.',
        'action'     => 'Zobrazit konverzaci',
    ],

    // App\Notifications\EventAnnouncement — hromadný e-mail organizátora.
    // Predmet aj telo píše organizátor, tu sú len rámcové texty.
    'event_announcement' => [
        'action' => 'Zobrazit akci',
        'outro'  => 'Tento e-mail vám přišel, protože máte lístek na uvedenou akci.',
    ],

    // App\Notifications\EventReminder — pripomienka pred akciou.
    'event_reminder' => [
        'subject'   => 'Připomínka: :event',
        'intro'     => 'Připomínáme, že se blíží akce **„:event"**, na kterou máte lístek.',
        'starts_at' => 'Začátek: **:date**.',
        'venue'     => 'Místo: **:venue**.',
        'action'    => 'Zobrazit akci',
        'outro'     => 'Vstupenku s QR kódem najdete v e-mailu, který vám přišel při objednávce.',
        // Ten istý e-mail pre toho, kto si vypýtal upozornenie a lístok nemá.
        'outro_subscriber' => 'Vstup je bez registrace — stačí přijít.',
    ],

    // App\Notifications\QuestionAnswered — jediný e-mail, ktorý pisateľ otázky
    // dostane. Adresa sa hneď po odoslaní maže, preto tu nie je odhlásenie.
    'question_answered' => [
        'subject'      => 'Odpověď na váš dotaz: :event',
        'intro'        => 'Organizátor odpověděl na dotaz, který jste položili k akci **„:event"**.',
        'answer_label' => 'Odpověď organizátora',
        'action'       => 'Zobrazit akci',
        'outro'        => 'Vaši adresu jsme použili jen na tuto jednu odpověď a už ji nemáme — další e-mail od nás nepřijde.',
    ],

    // App\Notifications\SubscriptionConfirmed — prvý e-mail po „Pripomeň mi".
    'subscription_confirmed' => [
        'subject'   => 'Budeme vás informovat: :event',
        'intro'     => 'Máte to. Pokud se u akce **„:event"** něco změní nebo ji pořadatel zruší, dáme vám vědět — a před začátkem pošleme připomínku.',
        'starts_at' => 'Začátek: **:date**.',
        'venue'     => 'Místo: **:venue**.',
        'action'    => 'Zobrazit akci',
        'outro'     => 'Pokud jste o upozornění nežádali, zrušte ho odkazem níže — vaše adresa se hned smaže.',
    ],

    // App\Notifications\EventChanged — sľub z tlačidla „Pripomeň mi".
    'event_changed' => [
        'subject'           => 'Změna: :event',
        'subject_cancelled' => 'Zrušeno: :event',
        'intro'             => 'U akce **„:event"** se změnilo toto:',
        'intro_cancelled'   => 'Akce **„:event"** se neuskuteční — pořadatel ji stáhl. Pokud jste si ji zapsali do kalendáře, záznam můžete smazat.',
        'starts_at'         => 'Nový termín: **:date**.',
        'venue'             => 'Místo: **:venue**.',
        'action'            => 'Zobrazit akci',
        'change_start'      => 'Termín: :from → :to',
        'change_venue'      => 'Místo: :from → :to',
    ],

    // App\Notifications\WorkshopSeatGranted — náhradníkovi sa uvoľnilo miesto.
    'workshop_seat_granted' => [
        'subject'       => 'Uvolnilo se místo na workshopu :workshop',
        'intro'         => 'Na workshopu „:workshop" (:event) se uvolnilo místo a nabízíme ho vám jako prvnímu náhradníkovi.',
        'starts_at'     => 'Termín: :date.',
        'deadline'      => 'Místo vám držíme do **:deadline**. Pokud ho do té doby nepotvrdíte, nabídneme ho dalšímu náhradníkovi.',
        'action'        => 'Potvrdit místo',
        'after_confirm' => 'Vstupenku s QR kódem vám pošleme hned po potvrzení.',
        'decline'       => 'Pokud se workshopu zúčastnit nemůžete, [odmítněte místo](:url) — pustíme na něj dalšího v pořadí.',
    ],

    // App\Notifications\WorkshopWaitlisted — zaradenie medzi náhradníkov.
    'workshop_waitlisted' => [
        'subject'  => 'Jste náhradník na workshop :workshop',
        'intro'    => 'Workshop „:workshop" na akci „:event" je momentálně plný, zařadili jsme vás mezi náhradníky.',
        'position' => 'Vaše pořadí: :position.',
        'note'     => 'Pokud se místo uvolní, automaticky vám ho přidělíme a pošleme vám lístek s QR kódem.',
        'action'   => 'Zobrazit akci',
    ],

    // App\Notifications\PosterDraftSaved — odkaz späť na nahratý plagát.
    'poster_draft' => [
        'subject'     => 'Váš plakát čeká — dokončete akci',
        'intro'       => 'Plakát jsme zpracovali a akci máme připravenou.',
        'intro_named' => 'Plakát jsme zpracovali a akci **„:name"** máme připravenou.',
        'next'        => 'Zbývá ji už jen zkontrolovat a uložit. Pokud tu ještě účet nemáte, vytvoříte si ho při ukládání.',
        'action'      => 'Dokončit akci',
        'expires'     => 'Rozpracovanou akci vám držíme do **:date**.',
        'ignore'      => 'Pokud jste plakát nenahrávali vy, tento e-mail klidně ignorujte — bez potvrzení se nikde nic nezveřejní.',
    ],

];
