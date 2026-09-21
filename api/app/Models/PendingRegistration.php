<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PendingRegistration extends Model
{
    use HasFactory;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'email',
        'password',
        'display_name',
        'event_id',
        'registered_via',
        'verification_token',
        'expires_at',
        'terms_accepted_at',
        'terms_version',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'terms_accepted_at' => 'datetime',
    ];

    /** Podujatie, na ktoré sa človek registráciou zároveň prihlasuje. */
    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    protected static function booted()
    {
        static::creating(function (self $pending) {
            if (empty($pending->id)) {
                $pending->id = (string) Str::uuid();
            }
        });
    }
}
