<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * „Daj mi vedieť" — odber bez účtu.
 *
 * Vzniká preto, že väčšina podujatí v portáli je bezplatná a bez lístkov, takže
 * na ich verejnom detaile sa nedá spraviť vôbec nič: registračná sekcia aj
 * mobilná lišta sú skryté a návštevníkovi zostane „Kopírovať odkaz". Odber je
 * najmenší možný záväzok medzi „pozrel som si to" a „objednal som lístok".
 *
 * Sľubujeme dve veci a v tomto poradí: **ozveme sa, keď sa niečo zmení alebo
 * zruší**, a pripomenieme termín. To prvé je služba, ktorú ľudia prijmú bez
 * váhania; keby sme na tlačidlo napísali „odoberať novinky", väčšina ho minie.
 */
class Subscription extends Model
{
    /**
     * Whitelist cieľov odberu: alias => model. Jediné miesto, kde sa povolené
     * typy definujú — presne ako Message::TARGETS a QuestionBoard::TARGETS.
     * `subscribable_type` sa ukladá ako plný názov triedy (default morph), aby
     * sa neprepísala morph mapa zdieľaná s files.fileable_type.
     */
    public const TARGETS = [
        'event' => Event::class,
        'canal' => Canal::class,
    ];

    protected $guarded = [];

    /**
     * Token je autorizácia odhlásenia a nemá čo opustiť server inak než
     * v odkaze, ktorý sami pošleme do e-mailu.
     */
    protected $hidden = ['token'];

    protected $casts = [
        'confirmed_at' => 'datetime',
        'notified_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
    ];

    /** Cieľ odberu — Event / Canal (morph). */
    public function subscribable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Token, ktorý sa v tabuľke ešte nevyskytuje. Na rozdiel od nástenky otázok
     * ho nikto neprepisuje z plátna — chodí výhradne v odkaze z e-mailu, takže
     * môže byť dlhý a z plnej abecedy.
     */
    public static function freshToken(): string
    {
        do {
            $token = Str::random(48);
        } while (static::query()->where('token', $token)->exists());

        return $token;
    }

    /** Živé odbery — teda tie, ktoré sa majú komu doručiť. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('unsubscribed_at')->whereNotNull('email');
    }

    public function isActive(): bool
    {
        return $this->unsubscribed_at === null && (string) $this->email !== '';
    }

    /**
     * Odhlásenie zahodí adresu, ale riadok nechá. Odkaz z pätičky tak funguje
     * aj na druhý klik (človek ho preposlal, klient si ho prednačítal) a my
     * nedržíme e-mail niekoho, kto o nás už nestojí.
     */
    public function unsubscribe(): void
    {
        if ($this->unsubscribed_at !== null) {
            return;
        }

        $this->forceFill([
            'email' => null,
            'unsubscribed_at' => now(),
        ])->save();
    }
}
