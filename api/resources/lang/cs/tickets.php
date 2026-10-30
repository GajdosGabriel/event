<?php

return [
    'status' => [
        'reserved' => 'Rezervovaný',
        'confirmed' => 'Potvrzený',
        'cancelled' => 'Zrušený',
    ],
    'payment_status' => [
        'none' => 'Zdarma',
        'pending' => 'Čeká na platbu',
        'paid' => 'Uhrazený',
        'failed' => 'Platba selhala',
        'refunded' => 'Vrácený',
    ],
    'admission_status' => [
        'valid' => 'Platný',
        'waitlisted' => 'Náhradník',
        'cancelled' => 'Zrušený',
    ],
    'type_kind' => [
        'ticket' => 'Vstupenka',
        'workshop' => 'Workshop',
    ],
    // App\Enums\TicketTypeKindOption (volba „Druh" v UI = kind + open_to_public)
    'type_kind_option' => [
        'ticket' => 'Vstupenka',
        'workshop' => 'Workshop (jen pro registrované účastníky)',
        'workshop_open' => 'Workshop (i pro neregistrované na akci)',
    ],
    // Popisky polí ve formuláři typu lístku (front je čte z resource).
    'type_form' => [
        'workshop_starts_at' => 'Začátek workshopu',
        'workshop_ends_at' => 'Konec workshopu',
        'sale_starts_at' => 'Prodej od',
        'sale_ends_at' => 'Prodej do',
    ],
    // Dotazník k registrácii (App\Enums\QuestionScope, QuestionType).
    'question_scope' => [
        'order' => 'Jednou za objednávku',
        'attendee' => 'Za každého účastníka',
    ],
    // Zobrazenie zaškrtnutého poľa v odpovediach (zoznam, export, e-mail).
    'answer_yes' => 'Ano',
    'question_type' => [
        'text' => 'Krátký text',
        'textarea' => 'Delší text',
        'single_choice' => 'Výběr jedné možnosti',
        'multi_choice' => 'Výběr více možností',
        'checkbox' => 'Zaškrtávací pole',
        'number' => 'Číslo',
        'date' => 'Datum',
    ],
    'errors' => [
        'question_needs_options' => 'Otázka s výběrem potřebuje alespoň jednu možnost.',
        'question_type_locked' => 'Na otázku už někdo odpověděl — její typ nelze změnit. Vytvořte novou otázku.',
        'answer_required' => 'Vyplňte prosím „:label“.',
        'answer_invalid' => 'Neplatná odpověď.',
        'answer_paid_locked' => 'Uhrazenou možnost už nemůžete změnit — obraťte se na pořadatele.',
        'answers_locked' => 'Odpovědi už nelze měnit.',
        'signup_needs_form' => 'Před registrací je třeba vyplnit dotazník.',
        'kind_not_allowed' => 'Zvolený druh lístku pro tebe není dostupný.',
        'registration_disabled' => 'Registrace na tuto akci není povolena.',
        'event_finished' => 'Akce už proběhla, registrace není možná.',
        'deadline_passed' => 'Termín registrace už uplynul.',
        'type_unavailable' => 'Vybraný typ lístku není k dispozici.',
        'sale_not_started' => 'Prodej lístku „:name“ ještě nezačal.',
        'sale_ended' => 'Prodej lístku „:name“ už skončil.',
        'min_per_order' => 'Minimální počet lístků „:name“ na objednávku je :count.',
        'max_below_min' => 'Nejvíce na objednávku nesmí být méně než nejméně na objednávku.',
        'max_per_order' => 'Maximální počet lístků „:name“ na objednávku je :count.',
        'nothing_selected' => 'Nevybrali jste žádný lístek.',
        'workshop_requires_ticket' => 'Na workshopy se mohou přihlásit jen účastníci registrovaní na akci.',
        'workshop_already_joined' => 'Na tento workshop jste už přihlášen.',
        'workshop_not_joined' => 'Na tento workshop nejste přihlášen.',
        'event_not_joined' => 'Na tuto akci nejste přihlášen.',
        'workshop_locked_join' => 'Akce už začala — přihlášení na workshopy už nelze měnit.',
        'workshop_locked_leave' => 'Akce už začala — odhlášení z workshopu už není možné.',
        'no_attendees' => 'Na tuto akci zatím není nikdo přihlášen.',
        // Akcie v zozname prihlásených (dashboard).
        'restore_not_cancelled' => 'Objednávka není zrušená — obnovit lze jen zrušenou objednávku.',
        'restore_not_cancelled_seat' => 'Vstupenka není zrušená — obnovit lze jen zrušenou vstupenku.',
        'restore_capacity' => 'Kapacita „:name“ je už naplněná — vstupenku není kam obnovit.',
        'delete_not_cancelled' => 'Smazat lze jen zrušenou objednávku.',
        'delete_not_cancelled_seat' => 'Smazat lze jen zrušenou vstupenku.',
        'confirm_not_reserved' => 'Potvrdit lze jen rezervaci čekající na potvrzení.',
        'payment_not_pending' => 'Platba už byla vyřízena.',
        'refund_not_paid' => 'Vrátit lze pouze uhrazenou objednávku.',
    ],

    'counts' => [
        'option_remaining' => '{0} Možnost „:name“ je už obsazená.|{1} Pro možnost „:name“ zbývá už jen :count místo.|[2,4] Pro možnost „:name“ zbývají už jen :count místa.|[5,*] Pro možnost „:name“ zbývá už jen :count míst.',
        'remaining' => '{1} Pro „:name“ zbývá už jen :count.|[2,4] Pro „:name“ zbývají už jen :count.|[5,*] Pro „:name“ zbývá už jen :count.',
        'workshop_entitlement' => '{1} Na workshop „:name“ můžete objednat nejvýše :count místo — podle počtu vstupenek na akci.|[2,4] Na workshop „:name“ můžete objednat nejvýše :count místa — podle počtu vstupenek na akci.|[5,*] Na workshop „:name“ můžete objednat nejvýše :count míst — podle počtu vstupenek na akci.',
    ],
];
