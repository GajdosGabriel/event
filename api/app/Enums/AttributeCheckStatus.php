<?php

namespace App\Enums;

/**
 * Stav overenia hodnoty (viď App\Models\AttributeCheck).
 *
 * `Pending` nie je „chyba", ale „zatiaľ nevieme" — tak sa musí aj zobrazovať.
 * Hodnotu zadanú pred minútou nikto neoveril a tváriť sa, že je pokazená, by
 * bolo horšie než nepovedať nič.
 */
enum AttributeCheckStatus: string
{
    case Pending = 'pending';
    case Ok = 'ok';
    case Failed = 'failed';

    public function isFailed(): bool
    {
        return $this === self::Failed;
    }
}
