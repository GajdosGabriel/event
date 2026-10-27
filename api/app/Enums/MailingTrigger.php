<?php

namespace App\Enums;

/**
 * Udalosti, ktoré Event hlási do Accountu – operátor na ne v Accounte
 * (menu Emailing) naviaže automatickú kampaň.
 *
 * Nový trigger = nový case + hlásenie cez AccountMailing na mieste, kde
 * udalosť nastáva + `php artisan emailing:register-triggers`.
 *
 * `canal_*` sú ponuky prevzatia nespravovaného kanála (CanalOutreachSender).
 * Adresátom je kontakt kanála, vecou (`ref`) kanál – prevzatím sa čakajúce
 * maily k nemu zrušia.
 */
enum MailingTrigger: string
{
    /** Účastník sa zaregistroval na podujatie (vznikla vstupenka/registrácia) */
    case TicketRegistered = 'ticket_registered';

    /** Registrácia sa zrušila (účastník alebo organizátor) */
    case TicketCancelled = 'ticket_cancelled';

    /** Podujatie skončilo – ide každému s platnou registráciou */
    case EventFinished = 'event_finished';

    /** Akcia nespravovaného kanála začína o `canals.outreach.upcoming_days` dní */
    case CanalEventUpcoming = 'canal_event_upcoming';

    /** Akcia nespravovaného kanála dosiahla prah zobrazení (`canals.outreach.view_milestones`) */
    case CanalViewsMilestone = 'canal_views_milestone';

    /** Akcia nespravovaného kanála skončila – so štatistikou zobrazení */
    case CanalEventFinished = 'canal_event_finished';

    public function label(): string
    {
        return match ($this) {
            self::TicketRegistered => 'Po registrácii na podujatie',
            self::TicketCancelled => 'Po zrušení registrácie',
            self::EventFinished => 'Po skončení podujatia',
            self::CanalEventUpcoming => 'Pred začiatkom akcie nespravovaného kanála',
            self::CanalViewsMilestone => 'Akcia nespravovaného kanála dosiahla prah zobrazení',
            self::CanalEventFinished => 'Po skončení akcie nespravovaného kanála',
        };
    }

    /** Krátky tvar do zoznamu kampaní: „Po registrácii · 24 h“ */
    public function shortLabel(): string
    {
        return match ($this) {
            self::TicketRegistered => 'Po registrácii',
            self::TicketCancelled => 'Po zrušení',
            self::EventFinished => 'Po podujatí',
            self::CanalEventUpcoming => 'Pred akciou kanála',
            self::CanalViewsMilestone => 'Zobrazenia akcie kanála',
            self::CanalEventFinished => 'Po akcii kanála',
        };
    }

    public function isCanal(): bool
    {
        return str_starts_with($this->value, 'canal_');
    }

    /** @return array<int, string> premenné, ktoré pri udalosti idú do Accountu */
    public function variables(): array
    {
        return $this->isCanal() ? self::CANAL_VARIABLES : self::VARIABLES;
    }

    /** Premenné, ktoré sa pri udalosti posielajú do Accountu (v texte kampane ako {kluc}). */
    public const VARIABLES = ['meno', 'podujatie', 'termin', 'miesto', 'pocet_miest', 'odkaz_podujatia', 'odkaz_vstupenky'];

    /** Premenné ponúk prevzatia kanála. */
    public const CANAL_VARIABLES = [
        'kanal', 'podujatie', 'termin', 'miesto', 'odkaz_podujatia', 'odkaz_kanala', 'odkaz_prevzatia',
        'zobrazenia', 'ludia', 'prihlaseni', 'dni_do_zaciatku', 'prah_zobrazeni',
    ];
}
