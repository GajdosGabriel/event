<?php

namespace App\Support;

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Prihlásenie super-admina do cudzieho účtu („vidieť to, čo vidí on").
 *
 * Admin dostane obyčajný Sanctum token toho používateľa — appka sa tak
 * správa presne ako u neho, bez výnimiek v policies. Kto sa prihlásil, nesie
 * názov tokenu; vlastný token admina si drží front a po návrate ho obnoví.
 */
final class Impersonation
{
    public const TOKEN_PREFIX = 'impersonation:';

    /** Token platí obmedzene — zabudnutá karta nemá ostať v cudzom účte. */
    public const TTL_HOURS = 2;

    public static function tokenName(User $impersonator): string
    {
        return self::TOKEN_PREFIX.$impersonator->id;
    }

    /** ID admina, ak je aktuálny požiadavok poslaný impersonačným tokenom. */
    public static function impersonatorId(?User $user): ?int
    {
        $token = $user?->currentAccessToken();

        if (! $token instanceof PersonalAccessToken || ! str_starts_with((string) $token->name, self::TOKEN_PREFIX)) {
            return null;
        }

        return (int) substr($token->name, strlen(self::TOKEN_PREFIX)) ?: null;
    }

    public static function active(?User $user): bool
    {
        return self::impersonatorId($user) !== null;
    }
}
