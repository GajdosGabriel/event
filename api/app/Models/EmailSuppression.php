<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Adresa vyradená z nevyžiadaných e-mailov: odhlásená z kampaní (oslovenie
 * kanála cez Account Emailing) alebo nedoručiteľná (`reason` = bounced).
 * Technické e-maily (lístky, heslo, technické e-maily kontaktu kanála) sa
 * odhlásenia netýkajú — tie zastaví len nedoručiteľnosť (bounced()).
 */
class EmailSuppression extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    public static function has(?string $email): bool
    {
        $email = mb_strtolower(trim((string) $email));

        return $email !== '' && static::query()->where('email', $email)->exists();
    }

    /**
     * Nedoručiteľná adresa (vrátený e-mail). Len tá zastaví aj technické
     * e-maily kontaktu kanála — odhlásenie platí iba pre kampane.
     */
    public static function bounced(?string $email): bool
    {
        $email = mb_strtolower(trim((string) $email));

        return $email !== '' && static::query()->where('email', $email)->where('reason', 'bounced')->exists();
    }

    public static function add(string $email, string $reason, ?string $source = null): self
    {
        return static::query()->firstOrCreate(
            ['email' => mb_strtolower(trim($email))],
            ['reason' => $reason, 'source' => $source],
        );
    }
}
