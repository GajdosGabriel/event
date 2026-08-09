<?php

namespace App\Models;

use App\Enums\AttributeCheckStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Stav overenia jednej hodnoty jedného modelu — „odpovedá tá stránka?".
 *
 * Zapisuje ho výhradne App\Services\Attributes\AttributeCheckService; model
 * sám drží len whitelisty, prevody a dopyty.
 *
 * Rozdiel oproti ContactEmailVerification: tam ide o dôkaz vlastníctva adresy
 * (človek klikne v e-maile) a záznam po potvrdení zaniká. Tu ide o funkčnosť
 * hodnoty, overuje ju stroj a záznam žije, kým žije hodnota.
 */
class AttributeCheck extends Model
{
    protected $guarded = [];

    protected $casts = [
        'status' => AttributeCheckStatus::class,
        'checked_at' => 'datetime',
        'next_check_at' => 'datetime',
        'notified_at' => 'datetime',
        'reported_at' => 'datetime',
    ];

    /**
     * Modely, ktorých hodnoty sa dajú overovať. Rovnaký princíp ako
     * Message::TARGETS a ContactEmailVerification::TARGETS — alias v databáze
     * je stabilný, presun alebo premenovanie triedy tak nezneplatní záznamy.
     *
     * Model v zozname musí používať trait HasCheckedAttributes.
     *
     * @var array<string, class-string<Model>>
     */
    public const TARGETS = [
        'canal' => Canal::class,
        'venue' => Venue::class,
        'event' => Event::class,
        'organization' => Organization::class,
    ];

    /** Atribút webovej adresy — jediný, ktorý sa dnes overuje. */
    public const WEBSITE = 'website';

    /** Alias typu pre daný model, alebo null ak sa jeho hodnoty neoverujú. */
    public static function aliasFor(Model $model): ?string
    {
        $alias = array_search($model::class, self::TARGETS, true);

        return $alias === false ? null : $alias;
    }

    public function checkable(): MorphTo
    {
        return $this->morphTo();
    }

    /** Hodnoty, ktoré čakajú na overenie a už im dobehol odstup. */
    public function scopeDue(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereNull('next_check_at')
            ->orWhere('next_check_at', '<=', now()));
    }

    /** Týka sa výsledok ešte tej hodnoty, ktorá je teraz na modeli? */
    public function matches(?string $value): bool
    {
        return $value !== null && $this->value === $value;
    }
}
