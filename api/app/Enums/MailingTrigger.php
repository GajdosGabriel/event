<?php

namespace App\Enums;

/**
 * Udalosti, ktoré Event hlási do Accountu – operátor na ne v Accounte
 * (menu Emailing) naviaže automatickú kampaň.
 *
 * Nový trigger = nový case + hlásenie cez AccountMailing na mieste, kde
 * udalosť nastáva + `php artisan emailing:register-triggers`.
 */
enum MailingTrigger: string
{
    /** Účastník sa zaregistroval na podujatie (vznikla vstupenka/registrácia) */
    case TicketRegistered = 'ticket_registered';

    /** Registrácia sa zrušila (účastník alebo organizátor) */
    case TicketCancelled = 'ticket_cancelled';

    /** Podujatie skončilo – ide každému s platnou registráciou */
    case EventFinished = 'event_finished';

    public function label(): string
    {
        return match ($this) {
            self::TicketRegistered => 'Po registrácii na podujatie',
            self::TicketCancelled => 'Po zrušení registrácie',
            self::EventFinished => 'Po skončení podujatia',
        };
    }

    /** Krátky tvar do zoznamu kampaní: „Po registrácii · 24 h“ */
    public function shortLabel(): string
    {
        return match ($this) {
            self::TicketRegistered => 'Po registrácii',
            self::TicketCancelled => 'Po zrušení',
            self::EventFinished => 'Po podujatí',
        };
    }

    /** Premenné, ktoré sa pri udalosti posielajú do Accountu (v texte kampane ako {kluc}). */
    public const VARIABLES = ['meno', 'podujatie', 'termin', 'miesto', 'pocet_miest', 'odkaz_podujatia', 'odkaz_vstupenky'];
}
