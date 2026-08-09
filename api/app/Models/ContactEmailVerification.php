<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Rozpracované overenie kontaktného e-mailu (viď App\Services\Contacts\ContactEmailVerifier).
 *
 * Záznam existuje len medzi odoslaním odkazu a jeho potvrdením. Výsledok
 * overenia drží samotný model v `email_verified_at`, nie táto tabuľka.
 */
class ContactEmailVerification extends Model
{
    protected $guarded = [];

    protected $hidden = ['token'];

    protected $casts = [
        'sent_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Modely, ktorých kontaktný e-mail sa dá overiť. Rovnaký princíp ako
     * Message::TARGETS — navonok (API, odpoveď po potvrdení) chodí stabilný
     * alias, nie meno triedy.
     *
     * Model v zozname musí používať trait HasVerifiableEmail.
     *
     * @var array<string, class-string<Model>>
     */
    public const TARGETS = [
        'canal' => Canal::class,
        'venue' => Venue::class,
        'event' => Event::class,
        'organization' => Organization::class,
    ];

    /** Alias typu pre daný model, alebo null ak sa jeho e-mail overovať nedá. */
    public static function aliasFor(Model $model): ?string
    {
        $alias = array_search($model::class, self::TARGETS, true);

        return $alias === false ? null : $alias;
    }

    public function verifiable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
