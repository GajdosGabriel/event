<?php

namespace App\Models;

use App\Enums\CanalEmailStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jedna e-mailová adresa kanála so stavom doručiteľnosti.
 * Zapisuje ju len App\Services\Canals\CanalEmails.
 */
class CanalEmail extends Model
{
    /** Adresa neexistuje (neznámy adresát, doména) — končí aj v EmailSuppression. */
    public const BOUNCE_HARD = 'hard';

    /** Dočasná prekážka (plná schránka, server neodpovedá). */
    public const BOUNCE_SOFT = 'soft';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_IMPORT = 'import';

    /** Adresa sa pridala sama tým, že niečo potvrdila (pozvánka, prevzatie). */
    public const SOURCE_CONFIRMED = 'confirmed';

    protected $guarded = [];

    protected $casts = [
        'is_primary' => 'boolean',
        'status' => CanalEmailStatus::class,
        'verified_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'bounced_at' => 'datetime',
    ];

    public function canal(): BelongsTo
    {
        return $this->belongsTo(Canal::class)->withTrashed();
    }

    /** Dá sa na ňu písať — čiže môže byť primárna. */
    public function isUsable(): bool
    {
        return $this->status !== CanalEmailStatus::Undeliverable;
    }

    public function isVerified(): bool
    {
        return $this->status === CanalEmailStatus::Verified;
    }
}
