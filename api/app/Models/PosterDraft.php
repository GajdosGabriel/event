<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Nahratý plagát čakajúci na to, kým si ho niekto privlastní.
 *
 * Prístup dáva iba `token` z odkazu (v DB je uložený jeho hash), nie ID —
 * inak by sa dali cudzie rozpracované plagáty čítať uhádnutím adresy.
 */
class PosterDraft extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'email',
        'source_kind',
        'original_filename',
        'file_disk',
        'file_path',
        'extracted_text',
        'detection',
        'analysis',
        'overrides',
        'claimed_by_user_id',
        'event_id',
        'claimed_at',
        'expires_at',
    ];

    protected $casts = [
        'detection' => 'array',
        'analysis' => 'array',
        'overrides' => 'array',
        'claimed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected $hidden = ['token', 'detection', 'extracted_text'];

    protected static function booted(): void
    {
        static::creating(function (self $draft) {
            if (empty($draft->id)) {
                $draft->id = (string) Str::uuid();
            }
        });
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && now()->greaterThan($this->expires_at);
    }

    public function isClaimed(): bool
    {
        return $this->claimed_at !== null;
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
