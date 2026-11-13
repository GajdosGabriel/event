<?php

namespace App\Models;

use App\Support\PublicUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Odkaz na skener pre dobrovoľníkov pri vchode — `/vstup/{token}`.
 *
 * Dobrovoľník nepotrebuje účet ani miesto v tíme kanála: token v adrese je
 * autorizácia, rovnako ako pri nástenke otázok. Preto je odkaz zviazaný
 * s jedným podujatím, má koniec platnosti a dá sa zrušiť — a jeho držiteľ
 * vie len overiť vstupenku tohto podujatia, nie čítať zoznam prihlásených.
 */
class CheckinLink extends Model
{
    protected $table = 'event_checkin_links';

    protected $guarded = [];

    /** Token opúšťa server len ako súčasť `url` pre organizátora. */
    protected $hidden = ['token'];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    /** Odkaz platí ešte deň po skončení podujatia — neskorí príchodzí, prehratie offline fronty. */
    public const GRACE_HOURS = 24;

    /** Podujatie bez termínu: odkaz platí týždeň od vytvorenia. */
    public const FALLBACK_DAYS = 7;

    public static function newToken(): string
    {
        do {
            $token = Str::random(32);
        } while (static::query()->where('token', $token)->exists());

        return $token;
    }

    /** Koniec platnosti pre nový odkaz k podujatiu. */
    public static function expiryFor(Event $event): Carbon
    {
        $end = $event->end_at ?? $event->start_at;

        if ($end === null) {
            return now()->addDays(self::FALLBACK_DAYS);
        }

        $expiry = Carbon::parse($end)->addHours(self::GRACE_HOURS);

        // Odkaz na už skončené podujatie (dodatočné skeny) — aspoň deň odteraz.
        return $expiry->isPast() ? now()->addHours(self::GRACE_HOURS) : $expiry;
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }

    /** @param  Builder<CheckinLink>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    public function url(): string
    {
        return PublicUrl::checkinLink($this->token);
    }
}
