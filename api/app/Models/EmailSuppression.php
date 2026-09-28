<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Adresa, ktorá si odhlásila nevyžiadané e-maily (oslovenie po akcii, ponuka
 * prevzatia kanála). Transakčné e-maily (lístky, heslo…) sa jej netýkajú.
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

    public static function add(string $email, string $reason, ?string $source = null): self
    {
        return static::query()->firstOrCreate(
            ['email' => mb_strtolower(trim($email))],
            ['reason' => $reason, 'source' => $source],
        );
    }
}
