<?php

namespace App\Models;

use App\Enums\SupportCategory;
use App\Enums\SupportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Vlákno používateľa s podporou platformy (sekcia Správy → Podpora). */
class SupportTicket extends Model
{
    protected $fillable = [
        'user_id',
        'canal_id',
        'category',
        'subject',
        'status',
        'page_url',
        'user_agent',
        'last_activity_at',
        'user_seen_at',
        'staff_seen_at',
    ];

    protected $casts = [
        'category' => SupportCategory::class,
        'status' => SupportStatus::class,
        'last_activity_at' => 'datetime',
        'user_seen_at' => 'datetime',
        'staff_seen_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function canal(): BelongsTo
    {
        return $this->belongsTo(Canal::class)->withTrashed();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class)->orderBy('id');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(SupportMessage::class)->latestOfMany();
    }

    /** Čitateľné číslo do predmetu mailu a do zoznamu. */
    public function reference(): string
    {
        return '#' . str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }
}
