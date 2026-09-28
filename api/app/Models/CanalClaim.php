<?php

namespace App\Models;

use App\Enums\CanalClaimMethod;
use App\Enums\CanalClaimStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Žiadosť o prevzatie kanála a zároveň doklad o prevzatí (viď CanalClaims).
 */
class CanalClaim extends Model
{
    protected $guarded = [];

    protected $casts = [
        'method' => CanalClaimMethod::class,
        'status' => CanalClaimStatus::class,
        'expires_at' => 'datetime',
        'completed_at' => 'datetime',
        'contest_until' => 'datetime',
        'contested_at' => 'datetime',
        'decided_at' => 'datetime',
    ];

    protected $hidden = ['token', 'contest_token'];

    public function canal(): BelongsTo
    {
        return $this->belongsTo(Canal::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === CanalClaimStatus::Pending
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /** Dá sa ešte napadnúť? Len dokončené prevzatie v lehote. */
    public function isContestable(): bool
    {
        return $this->status === CanalClaimStatus::Completed
            && $this->contest_until !== null
            && $this->contest_until->isFuture();
    }
}
