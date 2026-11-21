<?php

// Texty e-mailových notifikácií (App\Notifications\*) a ich šablón
// (resources/views/mail/*). Predmet aj telo držíme tu, nie v kóde, aby sa dali
// preložiť do ďalších jazykov. Markdown (**tučné**, [odkaz](:url)) je súčasťou
// textu — šablóny ho prechádzajú Markdown parserom.
//
// Konvencie: „podujatie" (nie „akcia"), „lístok" = objednávka, „vstupenka" =
// miesto jedného účastníka s QR kódom, „prevzatie" kanála (nie „správa"),
// „pozvánka" len do tímu kanála. Pomlčka „–" s medzerami.
return [

    // Texty zdieľané viacerými e-mailami.
    'common' => [
        // Odkaz na stránku „Pre organizátorov" — len v prvom kontakte s organizátorom.
        'for_organizers' => 'Čo všetko portál organizátorom ponúka, nájdete na stránke [Pre organizátorov](:url).',
        // Oslovenie je vo všetkých e-mailoch rovnaké, bez mena — meno býva
        // „Priezvisko Meno" alebo názov organizácie.
        'greeting' => 'Dobrý deň!',
        // Zápis dátumov (Carbon::isoFormat) — App\Support\MailText.
        'date_moment' => 'dd D. M. YYYY [o] HH:mm',
        'date_day' => 'dd D. M. YYYY',
        // Termín a miesto podujatia (resources/views/mail/partials/when).
        'when' => 'Termín: **:when**',
        'where' => 'Miesto: **:where**',
        'event_fallback' => 'podujatie',
        'workshop_fallback' => 'workshop',
        // Náhradný popis vstupenky, keď účastník nemá vyplnené meno.
        'seat_label' => 'Vstupenka :number',
        // Riadok jednej vstupenky v zozname (s typom lístka a bez neho).
        'seat' => '**:label**',
        'seat_typed' => '**:label** · :type',
        'qr_alt' => 'QR kód',
        'qr_open' => 'Otvoriť QR kód',
        // Sekcia „Pridať do kalendára" (resources/views/mail/partials/calendar).
        'calendar_title' => 'Pridať do kalendára',
        'calendar_intro' => 'Aby ste na termín nezabudli, zapíšte si podujatie do kalendára:',
        'calendar_ics' => 'Apple Kalendár a ostatné',
        'calendar_google' => 'Google Kalendár',
        'calendar_outlook' => 'Outlook',
        // Pätička e-mailov z odberu (resources/views/mail/partials/unsubscribe).
        'unsubscribe_intro' => 'Tento e-mail vám prišiel, lebo ste si k podujatiu vypýtali upozornenie.',
        'unsubscribe_action' => 'Zrušiť upozornenia',
    ],

    // App\Notifications\PendingRegistrationVerification
    'verification' => [
        'subject' => 'Overte si e-mailovú adresu',
        'intro' => 'Ďakujeme za registráciu. Dokončite ju overením svojej e-mailovej adresy.',
        'action' => 'Overiť e-mail',
        'expires' => '{1} Odkaz je platný :count hodinu.|[2,4] Odkaz je platný :count hodiny.|[5,*] Odkaz je platný :count hodín.',
        'ignore' => 'Ak ste si účet nevytvárali, nemusíte robiť nič.',
        // Registrácia z tlačidla „Prihlásiť sa" pri podujatí (EventSignup).
        'subject_event' => 'Overte e-mail a dokončite prihlásenie na :event',
        'intro_event' => 'Ďakujeme, že sa prihlasujete na podujatie **„:event"**. Najprv prosím overte svoju e-mailovú adresu.',
        'event_note' => 'Kliknutím na tlačidlo overíte e-mail a zároveň vás na podujatie **„:event"** rovno prihlásime – ďalší e-mail ani prihlasovanie netreba.',
        'action_event' => 'Overiť e-mail',
    ],

    // App\Notifications\PasswordResetLink
    'password_reset' => [
        'subject' => 'Obnova hesla',
        'intro' => 'Dostali sme žiadosť o obnovu hesla k vášmu účtu. Nové heslo si nastavíte cez tlačidlo nižšie.',
        'action' => 'Nastaviť nové heslo',
        'expires' => '{1} Odkaz je platný :count minútu a použiť sa dá raz.|[2,4] Odkaz je platný :count minúty a použiť sa dá raz.|[5,*] Odkaz je platný :count minút a použiť sa dá raz.',
        'ignore' => 'Ak ste o obnovu nežiadali, nemusíte robiť nič – heslo zostáva pôvodné.',
    ],

    // App\Notifications\ContentReviewNotice — výhrady ku textu, ktorý je už
    // zverejnený. Tón je ponuka, nie výčitka: text funguje, len sa dá zlepšiť
    // a vo formulári na to čaká AI panel.
    'content_review' => [
        'types' => [
            'canal' => 'kanála',
            'venue' => 'miesta',
            'event' => 'podujatia',
        ],
        'subject' => 'Pár poznámok k textu: :name',
        'intro' => 'Po zverejnení sme si text :type prečítali a všimli sme si pár vecí, ktoré by sa dali zlepšiť.',
        'intro_named' => 'Po zverejnení sme si prečítali popis :type **„:name"** a všimli sme si pár vecí, ktoré by sa dali zlepšiť.',
        'action' => 'Otvoriť formulár s pomocníkom',
        'assistant' => 'Vo formulári nad popisom nájdete tlačidlo **„Vyplniť pomocou AI"** – už s prednastaveným tým, čoho sa poznámky týkajú. Návrh vám ukáže vedľa pôvodného textu a nič sa nezmení, kým ho sami nepotvrdíte.',
        'no_change' => 'Nič sme neupravili a nemusíte robiť nič – text je zverejnený a funguje ďalej.',
    ],

    // App\Notifications\AttributeIssueNotice — spoločné upozornenie na údaj,
    // ktorý prestal fungovať (dnes webová adresa, neskôr čokoľvek ďalšie).
    // Nový overovaný údaj = nový riadok v `attributes`, nie nová notifikácia.
    'attribute_issue' => [
        'types' => [
            'canal' => 'kanála',
            'venue' => 'miesta',
            'event' => 'podujatia',
            'organization' => 'organizátora',
        ],
        'attributes' => [
            'website' => 'webová adresa',
        ],
        'subject' => 'Nefunkčná :attribute vo vašom zázname',
        'intro' => 'Pri kontrole sme zistili, že :attribute :type nefunguje.',
        'intro_named' => 'Pri kontrole sme zistili, že :attribute :type **„:name"** nefunguje.',
        'reasons' => [
            'dns' => 'Doménu sa nepodarilo nájsť – býva to preklep v adrese alebo doména po expirácii.',
            'not_found' => 'Stránka na tejto adrese už neexistuje (chyba :status). Najčastejšie ide o presunutú podstránku.',
            'server_error' => 'Server na adrese hlási chybu (:status). Môže ísť aj o dočasný výpadok hostingu.',
            'http_error' => 'Stránka na adrese hlási chybu :status.',
            'timeout' => 'Server na adrese neodpovedal v rozumnom čase.',
            'ssl' => 'Zabezpečené spojenie sa nepodarilo nadviazať – obvykle kvôli neplatnému certifikátu.',
            'unreachable' => 'Na adrese sa nepodarilo spojiť so žiadnym serverom.',
            'redirect' => 'Adresa presmerúva na miesto, ktoré sa nedá otvoriť.',
            'redirect_loop' => 'Adresa sa presmerúva dokola.',
            'blocked' => 'Adresa nesmeruje na verejný internet, takže ju nevieme overiť.',
            'invalid' => 'Adresa nemá platný tvar.',
        ],
        'seen_on' => 'Naposledy na ňu niekto klikol tu: :url',
        'action' => 'Opraviť adresu',
        'recheck' => 'Adresu overujeme pravidelne – po oprave sa upozornenie samo prestane posielať.',
        'false_alarm' => 'Ak je adresa v poriadku a išlo len o dočasný výpadok, nemusíte robiť nič.',
    ],

    // App\Notifications\CanalInvitationSent — pozvánka do tímu kanála.
    'canal_invitation' => [
        'subject' => 'Pozvánka do tímu :canal',
        'canal_fallback' => 'kanál',
        'intro' => 'Dostali ste pozvánku do tímu kanála **„:canal"**.',
        'intro_named' => '**:inviter** vás pozýva do tímu kanála **„:canal"**.',
        'role' => 'Vaša rola: **:role**.',
        'role_note' => [
            'owner' => 'Ako vlastník budete môcť upravovať kanál, jeho podujatia aj tím.',
            'editor' => 'Ako editor budete môcť vytvárať a upravovať podujatia, miesta a lístky.',
            'checkin' => 'Ako obsluha vstupu budete môcť načítavať QR kódy a odbavovať príchody.',
        ],
        'action' => 'Prijať pozvánku',
        'expires' => 'Pozvánka platí do :date.',
        'email_note' => 'Pozvánku prijmite po prihlásení účtom s adresou **:email**. Ak účet ešte nemáte, najprv sa na túto adresu zaregistrujte.',
        'ignore' => 'Ak ste pozvánku nečakali, stačí tento e-mail ignorovať.',
    ],

    // App\Notifications\CanalOwnershipChanged — zmena vlastníctva kanála.
    'canal_ownership' => [
        'claimed' => [
            'subject' => 'Kanál :canal má nového vlastníka',
            'member' => 'Prevzali ste kanál **„:canal"**. Odteraz vám budú chodiť prihlášky a správy od návštevníkov.',
            'owners' => 'Kanál **„:canal"** prevzal účet **:member**.',
            'contact' => 'Kanál **„:canal"**, pri ktorom je uvedená táto adresa, prevzal účet **:member**.',
        ],
        'owner_added' => [
            'subject' => 'Nový vlastník kanála :canal',
            'member' => 'Stali ste sa vlastníkom kanála **„:canal"**.',
            'owners' => 'Vlastníkom kanála **„:canal"** sa stal účet **:member**.',
        ],
        'owner_removed' => [
            'subject' => 'Kanál :canal: odobratý vlastník',
            'member' => 'Už nie ste vlastníkom kanála **„:canal"** a do jeho správy nemáte prístup.',
            'owners' => 'Účet **:member** už nie je vlastníkom kanála **„:canal"**.',
        ],
        'action' => 'Otvoriť kanál',
        'not_you' => 'Ak o zmene neviete, odpovedzte na tento e-mail a preveríme ju.',
        'contest' => 'Ak s tým nesúhlasíte, dajte nám vedieť do :date.',
        'contest_action' => 'Nesúhlasím s prevzatím',
    ],

    // App\Notifications\CanalContactNotice — zmena kontaktného e-mailu kanála.
    'canal_contact' => [
        'verify' => [
            'subject' => 'Overte kontakt kanála :canal',
            'intro' => 'Adresa **:email** bola nastavená ako kontaktný e-mail kanála **„:canal"**. Potvrďte, že patrí kanálu.',
            'action' => 'Potvrdiť adresu',
            'ignore' => 'Ak ste o zmenu nežiadali, e-mail ignorujte.',
        ],
        'changed' => [
            'subject' => 'Kontakt kanála :canal bol zmenený',
            'intro' => 'Kontaktný e-mail kanála **„:canal"** bol zmenený z tejto adresy na **:email**.',
        ],
    ],

    // App\Notifications\CanalClaimNotice — žiadosť o prevzatie kanála.
    'canal_claim' => [
        'message' => 'Správa od žiadateľa: „:message"',
        // Zdôvodnenie zamietnutia či zrušenia prevzatia.
        'reason' => 'Dôvod: :reason',
        // Ďalší krok po zamietnutí alebo zrušení prevzatia.
        'next' => 'Ak si myslíte, že ide o omyl, odpovedzte na tento e-mail. Žiadosť o prevzatie môžete podať aj znova, s doplneným vysvetlením.',
        'verify' => [
            'subject' => 'Žiadosť o prevzatie kanála :canal',
            'intro' => '**:name** (:member) žiada o prevzatie kanála **„:canal"**, pri ktorom je uvedená táto adresa. Ak žiadateľa poznáte a súhlasíte, potvrďte to.',
            'action' => 'Posúdiť žiadosť',
            'ignore' => 'Ak žiadateľa nepoznáte, e-mail ignorujte – žiadosť vyprší :date.',
        ],
        'requested' => [
            'subject' => 'Žiadosť o prevzatie kanála :canal',
            'intro' => 'Účet **:name** (:member) žiada o prevzatie kanála **„:canal"**. Žiadosť čaká na posúdenie.',
            'action' => 'Otvoriť žiadosti',
        ],
        'rejected' => [
            'subject' => 'Žiadosť o prevzatie kanála :canal nebola schválená',
            'intro' => 'Vašu žiadosť o prevzatie kanála **„:canal"** sme neschválili.',
        ],
        'contested' => [
            'subject' => 'Prevzatie kanála :canal bolo napadnuté',
            'intro' => 'Kontaktná adresa kanála **„:canal"** nesúhlasí s jeho prevzatím účtom **:name** (:member).',
            'action' => 'Otvoriť žiadosti',
        ],
        'reverted' => [
            'subject' => 'Prevzatie kanála :canal bolo zrušené',
            'intro' => 'Po preverení sme vaše prevzatie kanála **„:canal"** zrušili.',
        ],
    ],

    // App\Notifications\TicketIssued — objednávateľovi po vytvorení lístka.
    // App\Notifications\TicketIssued pri neuhradenej platenej objednávke — výzva na úhradu bez QR.
    'ticket_payment' => [
        'subject' => 'Rezervácia na :event – čaká na úhradu',
        'intro' => 'Vašu rezerváciu na podujatie **„:event"** sme prijali.',
        'recap' => 'Rekapitulácia objednávky',
        'amount' => 'Suma na úhradu: **:amount**',
        'note' => 'Platbu uhraďte podľa pokynov organizátora. Vstupenky s QR kódmi vám pošleme e-mailom hneď, ako organizátor platbu potvrdí.',
        'bank_title' => 'Platobné údaje',
        'bank_recipient' => 'Príjemca',
        'bank_iban' => 'IBAN',
        'bank_swift' => 'SWIFT',
        'bank_vs' => 'Variabilný symbol',
        'bank_amount' => 'Suma',
        'bank_note' => 'Správa pre príjemcu',
        'bank_after' => 'Uveďte, prosím, variabilný symbol, aby organizátor platbu spároval. Vstupenky s QR kódmi vám pošleme e-mailom hneď, ako platbu potvrdí.',
        'action' => 'Zobraziť rezerváciu',
    ],

    'ticket_issued' => [
        'paid_amount' => 'Suma objednávky: **:amount** (uhradené).',
        'answers_title' => 'Vaše odpovede',
        'answers_edit' => 'Odpovede z registrácie môžete do uzávierky upraviť na stránke lístka.',
        'subject' => 'Váš lístok na :event',
        'intro' => 'Váš lístok na podujatie **„:event"** je vystavený.',
        'quantity' => 'Počet rezervovaných miest: **:count**.',
        'qr_note' => 'Každá vstupenka má vlastný QR kód. Jednotlivé kódy môžete preposlať aj ďalším účastníkom – pri vstupe sa každý načíta samostatne.',
        'pending' => '{1} Ešte **:count** vstupenka čaká na potvrdenie účastníkom.|[2,4] Ešte **:count** vstupenky čakajú na potvrdenie účastníkmi.|[5,*] Ešte **:count** vstupeniek čaká na potvrdenie účastníkmi.',
        'pending_note' => 'Ich QR kód sa vytvorí až po tom, čo potvrdia účasť – o každom potvrdení vás upozorníme e-mailom.',
        'action' => 'Zobraziť lístok a QR kód',
        'outro' => 'Lístok si prineste v telefóne alebo vytlačte a predložte ho pri vstupe na podujatie.',
    ],

    // App\Notifications\TicketIssued s príznakom `restored` — obnovená objednávka.
    'ticket_restored' => [
        'subject' => 'Váš lístok na :event je opäť platný',
        'intro' => 'Váš zrušený lístok na podujatie **„:event"** sme obnovili – vaše miesta opäť platia.',
    ],

    // App\Notifications\AttendeeTicketIssued — ďalšiemu účastníkovi objednávky.
    'attendee_ticket_issued' => [
        'subject' => 'Vaša vstupenka na :event',
        'intro_paid' => 'Objednávateľ **:holder** vám objednal vstupenku na podujatie **„:event"**.',
        'intro_free' => 'Objednávateľ **:holder** vám rezervoval miesto na podujatí **„:event"**.',
        'outro' => 'Vstupenku si prineste v telefóne alebo vytlačte a QR kód predložte pri vstupe na podujatie.',
        'cancel' => 'Nemôžete prísť? [Zrušiť vstupenku](:url) – miesto uvoľníme ďalším záujemcom.',
        'activation' => 'Na túto e-mailovú adresu sme vám založili účet, aby ste mali svoje lístky vždy poruke. Účet si plne aktivujete prihlásením – potvrdíte tým svoju e-mailovú adresu a odsúhlasíte podmienky.',
        'activation_action' => 'Aktivovať účet',
    ],

    // App\Notifications\AttendeeConfirmationRequest — žiadosť o potvrdenie účasti.
    // O účte tu nehovoríme — človek ešte nič nepotvrdil; o aktivácii sa dozvie
    // až v e-maile so vstupenkou (attendee_ticket_issued.activation).
    'attendee_confirmation_request' => [
        'paid_by_holder' => 'Vstupné za vás uhrádza **:holder**. Vstupenku s QR kódom dostanete po potvrdení účasti a úhrade objednávky.',
        'subject' => 'Potvrďte účasť na :event',
        'intro_paid' => 'Objednávateľ **:holder** vám objednal vstupenku na podujatie **„:event"**.',
        'intro_free' => 'Objednávateľ **:holder** vám rezervoval miesto na podujatí **„:event"**.',
        'ask' => 'Aby sme vám miesto podržali, potvrďte prosím svoju účasť.',
        'deadline' => 'Potvrďte prosím **do :deadline**. Ak sa tak nestane, rezervácia sa automaticky zruší a miesto uvoľníme ďalším záujemcom.',
        'confirm' => 'Potvrdiť účasť',
        'decline' => 'Zrušiť lístok',
        'ignore' => 'Ak ste o túto rezerváciu nežiadali, lístok zrušte alebo tento e-mail ignorujte.',
    ],

    // App\Notifications\AttendeeConfirmed — objednávateľovi, keď účastník potvrdil.
    'attendee_confirmed' => [
        'subject' => 'Potvrdená účasť na :event: :attendee',
        'intro' => '{1} Dobrá správa: **:attendee** potvrdzuje účasť na podujatí **„:event"**.|[2,4] Dobrá správa: **:attendee** potvrdzuje účasť na podujatí **„:event"** (:count miesta).|[5,*] Dobrá správa: **:attendee** potvrdzuje účasť na podujatí **„:event"** (:count miest).',
        'ticket_sent' => 'Vstupenka s QR kódom práve odišla na **:email**.',
        'action' => 'Zobraziť objednávku',
    ],

    // App\Notifications\AttendeeDeclined — účastník lístok zrušil alebo nepotvrdil.
    // Predmety sa líšia, aby sa oba prípady dali v schránke rozlíšiť.
    'attendee_declined' => [
        'subject' => 'Účastník zrušil vstupenku na :event',
        'subject_expired' => 'Nepotvrdená účasť na :event',
        'expired' => '{1} Účastník **:attendee** nepotvrdil účasť na podujatí **„:event"** v stanovenej lehote, preto sa rezervované miesto uvoľnilo.|[2,4] Účastník **:attendee** nepotvrdil účasť na podujatí **„:event"** v stanovenej lehote, preto sa :count rezervované miesta uvoľnili.|[5,*] Účastník **:attendee** nepotvrdil účasť na podujatí **„:event"** v stanovenej lehote, preto sa :count rezervovaných miest uvoľnilo.',
        'declined' => '{1} Účastník **:attendee** (:email) zrušil vstupenku na podujatie **„:event"**, takže miesto je opäť voľné.|[2,4] Účastník **:attendee** (:email) zrušil vstupenky na podujatie **„:event"** (:count miesta), takže miesta sú opäť voľné.|[5,*] Účastník **:attendee** (:email) zrušil vstupenky na podujatie **„:event"** (:count miest), takže miesta sú opäť voľné.',
        'others_valid' => 'Ostatné vstupenky vo vašej objednávke platia ďalej.',
        // Len keď sa uvoľnené miesto na workshope naozaj ponúklo náhradníkovi.
        'waitlist_note' => 'Uvoľnené miesto na workshope sme ponúkli prvému náhradníkovi.',
    ],

    // App\Notifications\MessageReceived — správa cez tlačidlo „Poslať správu".
    'message_received' => [
        'subject' => 'Nová správa – :label „:name"',
        'intro' => 'Dostali ste novú správu :label **„:name"**.',
        'from' => '**Od:** :name (:email)',
        'reply_hint' => 'Odpovedať môžete priamo na tento e-mail – odpoveď dorazí odosielateľovi.',
        'action' => 'Zobraziť :label',
        // Názov typu cieľa správy (App\Models\Message::targetType()).
        'targets' => [
            'event' => 'podujatie',
            'venue' => 'miesto',
            'canal' => 'kanál',
            'default' => 'profil',
        ],
        // Predložka s datívom pre vetu „správa k …".
        'targets_to' => [
            'event' => 'k podujatiu',
            'venue' => 'k miestu',
            'canal' => 'ku kanálu',
            'default' => 'k profilu',
        ],
        'target_fallback' => 'váš profil',
    ],

    // App\Notifications\MessageReplied — odpoveď organizátora z inboxu.
    'message_replied' => [
        'subject' => 'Odpoveď – :label „:name"',
        'intro' => 'Prišla odpoveď od **:name** na vašu správu :label **„:target"**.',
        'reply_hint' => 'Odpovedať môžete priamo na tento e-mail.',
        'action' => 'Zobraziť konverzáciu',
    ],

    // App\Notifications\EventAnnouncement — hromadný e-mail organizátora.
    // Predmet aj telo píše organizátor, tu sú len rámcové texty.
    'event_announcement' => [
        // Predmet od organizátora doplnený o názov podujatia.
        'subject' => ':subject – :event',
        'action' => 'Zobraziť podujatie',
        'outro' => 'Tento e-mail vám prišiel, lebo máte lístok na uvedené podujatie.',
    ],

    // App\Notifications\EventReminder — pripomienka pred podujatím.
    'event_reminder' => [
        'subject' => 'Pripomienka: :event',
        'intro' => 'Pripomíname, že sa blíži podujatie **„:event"**, na ktoré máte lístok.',
        // Ten istý e-mail pre toho, kto si vypýtal upozornenie a lístok nemá.
        'intro_subscriber' => 'Pripomíname, že sa blíži podujatie **„:event"**, o ktorom ste chceli dostať upozornenie.',
        'starts_at' => 'Termín: **:date**',
        'venue' => 'Miesto: **:venue**',
        'action' => 'Zobraziť podujatie',
        'outro' => 'Vstupenku s QR kódom nájdete v e-maile, ktorý vám prišiel pri objednávke.',
        'outro_subscriber' => 'Podrobnosti o vstupe nájdete na stránke podujatia.',
    ],

    // App\Notifications\QuestionAnswered — jediný e-mail, ktorý pisateľ otázky
    // dostane. Adresa sa pri otázke hneď po odoslaní maže, preto tu nie je
    // odhlásenie. Pisateľ môže mať aj účet — veta preto hovorí len o otázke.
    'question_answered' => [
        'subject' => 'Odpoveď na vašu otázku: :event',
        'intro' => 'Organizátor odpovedal na otázku, ktorú ste položili k podujatiu **„:event"**.',
        'answer_label' => 'Odpoveď organizátora',
        'action' => 'Zobraziť podujatie',
        'outro' => 'Adresu zadanú pri otázke sme použili len na túto odpoveď a pri otázke sme ju vymazali. K tejto otázke vám už ďalší e-mail nepríde.',
    ],

    // App\Notifications\QuestionReceived — chodí pri každej otázke z nástenky.
    // Líši sa len text: podnet z podujatia sa rieši teraz, verejná otázka visí
    // na stránke, súkromnú nevidno nikde.
    'question_received' => [
        'feedback' => [
            'subject' => 'Podnet z publika: :event',
            'intro' => 'Niekto z publika vám počas podujatia **„:event"** poslal podnet:',
            'hint' => 'Vidíte to len vy. Ak sa to dá vyriešiť, urobte to ešte počas podujatia a v nástenke to potom označte za vybavené.',
        ],
        'private' => [
            'subject' => 'Súkromná otázka: :event',
            'intro' => 'Niekto sa vás súkromne pýta na podujatie **„:event"**:',
            'hint' => 'Túto otázku nikto iný nevidí. Odpoveď napíšete v nástenke a pisateľovi ju pošleme e-mailom.',
        ],
        'public' => [
            'subject' => 'Nová otázka: :event',
            'intro' => 'Niekto sa pýta na podujatie **„:event"**:',
            'hint' => 'Otázka je na stránke podujatia verejne. Odpoveď napíšete v nástenke a zostane tam ako častá otázka.',
        ],
        'from' => 'Píše: :name',
        'action' => 'Otvoriť nástenku otázok',
    ],

    // App\Notifications\QuestionRelayed — otázka návštevníka na podujatie kanála bez správcu.
    'question_relayed' => [
        'organizer' => [
            'subject' => 'Otázka k podujatiu :event',
            'intro' => 'Návštevník portálu :site sa pýta na podujatie **„:event"** kanála **:canal**:',
            'reply' => 'Odpovedzte priamo na tento e-mail – odpoveď doručíme pisateľovi (:name).',
            'offer' => 'Kanál **„:canal"** vznikol automaticky z verejných zdrojov. Ak ho prevezmete, budete dostávať otázky ľudí, ktorí majú záujem o vaše podujatie, môžete im odpovedať a zdarma zverejňovať ďalšie podujatia.',
            'action' => 'Prevziať kanál',
            'why' => 'Píšeme vám, lebo vaša adresa je uvedená pri kanáli. Iné e-maily vám bez vášho záujmu neposielame.',
        ],
        'admin' => [
            'subject' => 'Otázka pre organizátora: :event',
            'intro' => ':name (:email) sa pýta na podujatie **„:event"** kanála **:canal**:',
            'sent_to' => 'Otázka bola preposlaná na adresu kanála: :email.',
            'action' => 'Otvoriť podujatie',
        ],
    ],

    // App\Notifications\CanalRelayReplied — odpoveď organizátora pisateľovi.
    'canal_relay_replied' => [
        'subject' => 'Odpoveď organizátora: :target',
        'intro' => 'Organizátor **:canal** odpovedal na vašu správu k **„:target"**:',
        'attachments' => 'Organizátor poslal aj prílohy, ktoré sme nepreposlali. Ak ich potrebujete, vyžiadajte si ich odpoveďou.',
        'outro' => 'Ak chcete pokračovať, odpovedzte priamo na tento e-mail – príde organizátorovi.',
    ],

    // App\Notifications\MessageRelayed — správa pre kanál bez správcu; spoločné texty sú v question_relayed.
    'message_relayed' => [
        // Doplnok za názov podujatia; pri správe priamo kanálu vypadne.
        'organizer_suffix' => '(kanál **:canal**)',
        'organizer' => [
            'subject' => 'Správa od návštevníka: :target',
            'intro' => 'Návštevník portálu :site vám posiela správu k **„:target"**:organizer:',
            'offer' => 'Kanál **„:canal"** vznikol automaticky z verejných zdrojov. Ak ho prevezmete, budete dostávať správy ľudí, ktorí majú záujem o vaše podujatia, a môžete zdarma zverejňovať ďalšie podujatia.',
        ],
        'admin' => [
            'subject' => 'Správa pre organizátora: :target',
            'intro' => ':name (:email) posiela správu k **„:target"** kanála **:canal**:',
            'sent_to' => 'Správa bola preposlaná na adresu kanála: :email.',
            'action' => 'Otvoriť',
        ],
    ],

    // App\Notifications\SubscriptionConfirmed — prvý e-mail po „Pripomeň mi".
    // App\Notifications\CanalFollowStarted — „Sledovať organizátora".
    'canal_follow' => [
        'subject' => 'Sledujete: :canal',
        'intro' => 'Hotovo – keď organizátor **„:canal"** zverejní novú akciu, dáme vám vedieť e-mailom. Viac akcií naraz príde v jednom súhrne.',
        'action' => 'Zobraziť organizátora',
        'outro' => 'Ak ste o to nežiadali alebo už nechcete, odhláste sa odkazom nižšie.',
    ],

    // App\Notifications\CanalNewEvents — súhrn nových akcií sledovaného kanála.
    'canal_new_events' => [
        'subject_one' => 'Nová akcia: :event (:canal)',
        'subject_many' => ':canal zverejnil :count nových akcií',
        'intro' => 'Organizátor **„:canal"**, ktorého sledujete, zverejnil:',
        'more' => '…a ďalšie (:count) nájdete na stránke organizátora.',
        'action' => 'Všetky akcie organizátora',
        'outro' => 'Tento e-mail dostávate, lebo sledujete organizátora „:canal".',
    ],

    'subscription_confirmed' => [
        'subject' => 'Budeme vás informovať: :event',
        'intro' => 'Hotovo – ak sa na podujatí **„:event"** niečo zmení alebo ho organizátor zruší, dáme vám vedieť. Pred začiatkom vám pošleme pripomienku.',
        'starts_at' => 'Termín: **:date**',
        'venue' => 'Miesto: **:venue**',
        'action' => 'Zobraziť podujatie',
        'outro' => 'Ak ste o upozornenie nežiadali, zrušte ho odkazom nižšie – vaša adresa sa hneď zmaže.',
    ],

    // App\Notifications\EventChanged — sľub z tlačidla „Pripomeň mi".
    // Nový termín a miesto sú vo výpise zmien, druhýkrát sa neopakujú.
    'event_changed' => [
        'subject' => 'Zmena: :event',
        'subject_cancelled' => 'Zrušené: :event',
        'intro' => 'Pri podujatí **„:event"** sa zmenilo toto:',
        'intro_cancelled' => 'Podujatie **„:event"** sa neuskutoční – organizátor ho zrušil. Ak ste si ho zapísali do kalendára, záznam si môžete zmazať.',
        'action' => 'Zobraziť podujatie',
        'change_start' => 'Termín: :from → :to',
        'change_venue' => 'Miesto: :from → :to',
    ],

    // App\Notifications\WorkshopSeatGranted — náhradníkovi sa uvoľnilo miesto.
    'workshop_seat_granted' => [
        'subject' => 'Uvoľnilo sa miesto na workshope :workshop',
        'intro' => 'Na workshope **„:workshop"** (:event) sa uvoľnilo miesto a ponúkame ho vám ako prvému náhradníkovi.',
        'starts_at' => 'Termín: **:date**',
        'deadline' => 'Miesto vám držíme do **:deadline**. Ak ho dovtedy nepotvrdíte, ponúkneme ho ďalšiemu náhradníkovi.',
        'action' => 'Potvrdiť miesto',
        'after_confirm' => 'Vstupenku s QR kódom vám pošleme hneď po potvrdení.',
        'decline' => 'Ak sa workshopu zúčastniť nemôžete, [odmietnite miesto](:url) – ponúkneme ho ďalšiemu v poradí.',
    ],

    // App\Notifications\WorkshopWaitlisted — zaradenie medzi náhradníkov.
    // Uvoľnené miesto treba potvrdiť (WorkshopSeatGranted), nepridelí sa samo.
    'workshop_waitlisted' => [
        'subject' => 'Ste náhradník na workshop :workshop',
        'intro' => 'Workshop **„:workshop"** na podujatí **„:event"** je momentálne plný, zaradili sme vás medzi náhradníkov.',
        'position' => 'Vaše poradie: **:position**',
        'note' => 'Keď sa miesto uvoľní a budete na rade, pošleme vám e-mail. Miesto potom potvrdíte v lehote, ktorú v ňom uvedieme – inak ho ponúkneme ďalšiemu náhradníkovi.',
        'action' => 'Zobraziť podujatie',
    ],

    // App\Notifications\PosterDraftSaved — odkaz späť na nahratý plagát.
    'poster_draft' => [
        'subject' => 'Váš plagát čaká – dokončite podujatie',
        'intro' => 'Plagát sme spracovali a podujatie máme pripravené.',
        'intro_named' => 'Plagát sme spracovali a podujatie **„:name"** máme pripravené.',
        'next' => 'Zostáva ho už len skontrolovať a uložiť. Ak tu ešte účet nemáte, vytvoríte si ho pri ukladaní.',
        'action' => 'Dokončiť podujatie',
        'expires' => 'Rozpracované podujatie vám držíme do **:date**.',
        'ignore' => 'Ak ste plagát nenahrávali vy, tento e-mail pokojne ignorujte – bez potvrdenia sa nikde nič nezverejní.',
    ],

    // App\Notifications\PosterEmailReply — odpoveď na podujatie poslané e-mailom.
    'poster_email' => [
        'subject' => 'Vaše podujatie máme pripravené',
        'intro' => 'Ďakujeme za e-mail. Podujatie sme z neho pochopili takto:',
        'missing' => 'Ešte nám chýba: :fields.',
        'reply_hint' => 'Stačí odpísať na tento e-mail – údaje doplníme a pošleme vám súhrn znova. Alebo ich doplňte rovno v koncepte.',
        'complete' => 'Všetko potrebné máme. Podujatie skontrolujte a uložte – ak tu ešte účet nemáte, vytvoríte si ho pri ukladaní.',
        'action' => 'Skontrolovať podujatie',
        'expires' => 'Koncept podujatia vám držíme do **:date**.',
        'ignore' => 'Bez vášho potvrdenia sa nikde nič nezverejní. Ak ste chceli niečo iné, odpíšte – správu si prečíta človek.',
    ],

    // App\Notifications\OpenAiBillingIssue — super-adminom, keď v OpenAI došiel kredit.
    'openai_billing' => [
        'subject' => 'OpenAI: došiel kredit, AI funkcie nefungujú',
        'intro' => 'OpenAI odmietlo požiadavku, lebo na účte organizácie nie je kredit alebo bol dosiahnutý limit fakturácie.',
        'response' => 'Odpoveď OpenAI (HTTP :status, kód `:code`):',
        'impact' => 'Kým kredit nedoplníte, nefunguje rozpoznávanie importovaných podujatí, AI asistent textov, štítkovanie ani kontrola obsahu.',
        'action' => 'Doplniť kredit v OpenAI',
        'cooldown' => 'Ďalšie upozornenie pošleme najskôr o :hours h, aj keď chyba pretrváva.',
    ],

    // App\Notifications\EventSignupOrganizerNotice — organizátorovi pri prihlásení na podujatie.
    'event_signup_organizer' => [
        'subject' => 'Nový účastník: :event',
        'subject_interest' => 'Nový záujemca: :event',
        'intro' => 'Nový účastník na vašom podujatí **„:event"**: **:name** (prihláška cez portál :site).',
        'intro_interest' => 'Nový záujemca o vaše podujatie **„:event"**: **:name** (prihláška cez portál :site).',
        'reserved' => 'Miesto je rezervované – účastník dostal vstupenku s QR kódom a je v zozname prihlásených.',
        // Prečo sa zaznamenal len záujem (EventSignup::INTEREST_*).
        'interest_unavailable' => 'Podujatie nemá zapnutú rezerváciu miest zdarma (alebo ponúka len platené lístky), preto sme vstupenku nevystavili a zaznamenali len záujem. Ozvite sa, prosím, záujemcovi.',
        'interest_full' => 'Voľné miesta sa minuli alebo sa skončila rezervácia, preto sme vstupenku nevystavili a zaznamenali len záujem. Ozvite sa, prosím, záujemcovi.',
        // Počet platných vstupeniek — záujemcovia sa doň nerátajú.
        'count' => '{1} Prihlásený je teraz **:count** účastník.|[2,4] Prihlásení sú teraz **:count** účastníci.|[5,*] Prihlásených je teraz **:count** účastníkov.',
        'contact' => 'Kontakt na účastníka: :email',
        'action' => 'Zobraziť zoznam prihlásených',
        'claim_subject' => 'Na vaše podujatie „:event" sa prihlasujú ľudia',
        'claim_signup' => 'Na podujatie **„:event"** sa cez portál :site prihlásil nový účastník.',
        'claim_interest' => 'O podujatie **„:event"** prejavil cez portál :site záujem nový účastník.',
        'claim_intro' => 'Podujatie sme zverejnili na portáli :site pod kanálom **„:canal"**, ktorý zatiaľ nikto nespravuje. Správa vám prišla na kontakt uvedený pri kanáli alebo podujatí.',
        'claim_benefits' => 'Prevzatie kanála je zadarmo. Uvidíte zoznam prihlásených a ich kontakty, budete môcť upravovať toto podujatie, pridávať ďalšie, nastaviť kapacitu a na vstupe načítavať QR kódy.',
        'claim_action' => 'Prevziať kanál a zobraziť prihlásených',
        // Odkaz na prevzatie prijme len účet s adresou, na ktorú ponuka išla.
        'claim_email_note' => 'Kanál prevezmete po prihlásení účtom s adresou **:email**. Ak účet ešte nemáte, vytvoríte si ho po kliknutí na tlačidlo vyššie.',
        'claim_ignore' => 'Ak nie ste organizátorom tohto podujatia, tento e-mail pokojne ignorujte.',
    ],

    // App\Notifications\CanalEventPublishedOffer — kontaktu nespravovaného kanála pri zverejnení podujatia.
    'canal_event_offer' => [
        'new_event' => [
            'subject' => 'Zverejnili sme vaše podujatie „:event"',
            'intro' => 'Na portáli :site sme zverejnili podujatie **„:event"** pod kanálom **„:canal"**, ktorý zatiaľ nikto nespravuje.',
        ],
        'new_canal' => [
            'subject' => 'Zverejnili sme vaše podujatie „:event" v novom kanáli „:canal"',
            'intro' => 'Na portáli :site sme zverejnili podujatie **„:event"**. Založili sme preň kanál **„:canal"**, ktorý zatiaľ nikto nespravuje.',
        ],
        'when' => 'Termín: :when',
        'where' => 'Miesto: :where',
        'event_link' => 'Podujatie: :url',
        'contact_note' => 'Správa vám prišla na kontakt uvedený pri kanáli alebo podujatí.',
        'action' => 'Prevziať kanál',
    ],

    // App\Notifications\EventSignupAdminNotice — super-adminom pri každom prihlásení.
    'event_signup_admin' => [
        'subject' => 'Prihlásenie na podujatie: :event',
        'intro' => 'Prihláška od **:name** (:email) na podujatie **„:event"**.',
        'status' => [
            'reserved' => 'Výsledok: miesto rezervované, vstupenka vystavená.',
            'interest' => 'Výsledok: len záujem (rezervácia vypnutá, len platené lístky alebo plno).',
            'already_registered' => 'Výsledok: prihlášku už mal(a).',
            'closed' => 'Výsledok: prihlasovanie je uzavreté.',
        ],
        'organizer' => [
            'owner' => 'Organizátor dostal správu: :email',
            'invitation' => 'Kanál nikto nespravuje – ponuka prevzatia odišla na :email.',
            'none' => 'Organizátora sa nepodarilo osloviť – pri podujatí ani kanáli chýba e-mail. Kontaktujte organizátora ručne.',
            'muted' => 'Organizátorovi správa neodišla – vlastníci kanála si upozornenia na prihlášky vypli.',
            'collection' => 'Organizátorovi správa neodišla – podujatie je v zbernom kanáli, ktorý sa prevziať nedá. Kontaktujte organizátora ručne.',
            'suppressed' => 'Organizátorovi správa neodišla – kontakt :email je nedoručiteľný (vrátené e-maily).',
            'throttled' => 'Organizátorovi správa teraz neodišla – ponuka prevzatia už nedávno išla na :email, posielame ju najviac raz za 7 dní.',
        ],
        'event_link' => 'Podujatie: :url',
        'action' => 'Zoznam prihlásených',
    ],

    // App\Notifications\EventReservationInvite — po overení e-mailu registrácie s podujatím.
    'event_reservation_invite' => [
        'subject' => 'Účet je overený – rezervujte si miesto na :event',
        'intro' => 'Ďakujeme, váš e-mail je overený a účet je pripravený.',
        'next' => 'Teraz si môžete rezervovať miesto na podujatí **„:event"** – stačí sa prihlásiť a potvrdiť jedným klikom. Vstupenku s QR kódom vám pošleme e-mailom.',
        'action' => 'Rezervovať miesto',
        'outro' => 'Rezervácia je zadarmo. Organizátor dostane správu, že prídete.',
    ],

    // App\Notifications\EventInterestRecorded — účastníkovi, keď vstupenku vydať nešlo.
    'event_interest' => [
        'subject' => 'Váš záujem o :event',
        'intro_unavailable' => 'Ďakujeme za prihlásenie na podujatie **„:event"**. Organizátor rezerváciu miest cez portál neponúka, preto vám vstupenku nevystavujeme.',
        'intro_full' => 'Ďakujeme za prihlásenie na podujatie **„:event"**. Voľné miesta sa už minuli alebo sa skončila rezervácia, preto vám vstupenku nevystavujeme.',
        'next' => 'Váš záujem sme odovzdali organizátorovi. Podrobnosti o vstupe nájdete na stránke podujatia.',
        'action' => 'Zobraziť podujatie',
    ],

    // App\Notifications\RegistrationCancelled — účastníkovi po zrušení vlastnej rezervácie.
    'registration_cancelled' => [
        'subject' => 'Rezervácia na :event je zrušená',
        'intro' => 'Vaša rezervácia na podujatie **„:event"** je zrušená. Vstupenka a jej QR kód už neplatia.',
        'next' => 'Ak si to rozmyslíte, miesto si môžete znova rezervovať, kým je voľné.',
        'action' => 'Zobraziť podujatie',
    ],

    // App\Notifications\EventCancellationOrganizerNotice — organizátorovi, keď sa miesto uvoľní.
    'event_cancellation_organizer' => [
        'subject' => 'Zrušená rezervácia: :event',
        'intro' => 'Účastník **:name** zrušil rezerváciu na vaše podujatie **„:event"**. Miesto sa uvoľnilo.',
    ],

    // App\Notifications\TicketCancelledByOrganizer — zrušenie objednávky organizátorom.
    'ticket_cancelled_by_organizer' => [
        'subject' => 'Váš lístok na :event je zrušený',
        'intro' => 'Organizátor zrušil váš lístok na podujatie **„:event"**.',
        'reason' => 'Odkaz od organizátora: :reason',
        'outro' => 'Vstupenka už neplatí. Ak máte otázky, odpovedzte na tento e-mail.',
    ],
];
