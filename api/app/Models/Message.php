<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Message extends Model
{
    /**
     * Whitelist cieľov správ: alias (target_type z frontendu) => model.
     * Jediné miesto, kde sa povolené typy definujú — pridanie ďalšieho
     * Messageable typu je len o zápise sem. `messageable_type` ukladáme ako
     * plný názov triedy (default morph), aby sme neprepísali morph mapu, ktorú
     * zdieľajú iné polymorfné vzťahy (napr. files.fileable_type).
     */
    public const TARGETS = [
        'event' => Event::class,
        'venue' => Venue::class,
        'canal' => Canal::class,
    ];

    protected $guarded = [];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    /** Alias typu cieľa (event/venue/canal) pre uložený morph class. */
    public function targetType(): ?string
    {
        return array_search($this->messageable_type, self::TARGETS, true) ?: null;
    }

    /** Cieľ správy — Event / Venue / Canal … (morph map). */
    public function messageable(): MorphTo
    {
        return $this->morphTo();
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }
}
