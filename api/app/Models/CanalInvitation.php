<?php

namespace App\Models;

use App\Enums\CanalRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Pozvánka do tímu kanála (viď App\Services\Canals\CanalInviter).
 */
class CanalInvitation extends Model
{
    protected $guarded = [];

    protected $casts = [
        'role' => CanalRole::class,
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    protected $hidden = ['token'];

    protected static function booted(): void
    {
        static::creating(function (self $invitation) {
            if (empty($invitation->token)) {
                $invitation->token = (string) Str::random(64);
            }
        });
    }

    public function canal(): BelongsTo
    {
        return $this->belongsTo(Canal::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by_user_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /** Čaká na prijatie — ani prijatá, ani zrušená, ani po platnosti. */
    public function isPending(): bool
    {
        return $this->accepted_at === null
            && $this->revoked_at === null
            && ! $this->isExpired();
    }

    public function scopePending($query)
    {
        return $query->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
