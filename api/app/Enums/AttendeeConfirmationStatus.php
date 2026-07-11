<?php

namespace App\Enums;

/**
 * Stav potvrdenia účasti pri vstupenke objednanej pre iného účastníka.
 * NULL na vstupenke znamená, že potvrdenie nevyžaduje (patrí objednávateľovi).
 */
enum AttendeeConfirmationStatus: string
{
    case Pending   = 'pending';   // čaká na potvrdenie účastníkom
    case Confirmed = 'confirmed'; // účastník potvrdil účasť
    case Declined  = 'declined';  // účastník odmietol lístok
    case Expired   = 'expired';   // uplynula lehota bez potvrdenia

    public function label(): string
    {
        return match ($this) {
            self::Pending   => 'Čaká na potvrdenie',
            self::Confirmed => 'Potvrdené',
            self::Declined  => 'Odmietnuté',
            self::Expired   => 'Nepotvrdené včas',
        };
    }

    /** Stavy, pri ktorých je miesto uvoľnené (rezervácia zrušená). */
    public function isReleased(): bool
    {
        return $this === self::Declined || $this === self::Expired;
    }
}
