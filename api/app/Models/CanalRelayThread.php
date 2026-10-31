<?php

namespace App\Models;

use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Komu patrí odpoveď organizátora na preposlanú otázku alebo správu (CanalRelay).
 *
 * Každé preposlanie má v Accounte vlastnú konverzáciu (ref `canal_relay`), takže
 * odpoveď sa spáruje s jedným pisateľom, aj keď kanálu píše viac ľudí. Text sa
 * neukladá a záznam sa po RETENTION_DAYS maže.
 */
class CanalRelayThread extends Model
{
    use MassPrunable;

    /** Dokedy sa ešte dá odpoveď doručiť pisateľovi. */
    public const RETENTION_DAYS = 180;

    protected $guarded = [];

    protected $casts = [
        'last_reply_at' => 'datetime',
    ];

    public function canal(): BelongsTo
    {
        return $this->belongsTo(Canal::class);
    }

    public function prunable()
    {
        return static::query()->where('created_at', '<', now()->subDays(self::RETENTION_DAYS));
    }
}
