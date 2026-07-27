<?php

namespace App\Models;

use App\Enums\TagGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Obsahový štítok podujatia. Číselník je verzovaný v seedri
 * (database/seeders/TagSeeder.php), nezakladá sa cez UI.
 */
class Tag extends Model
{
    protected $guarded = [];

    /**
     * Štítok sa serializuje ako čip — stačí id, slug, názov, facet a emoji.
     * Pivot (confidence, source) je interná informácia o tom, kto štítok
     * priradil; navonok by bola len šum.
     */
    protected $hidden = ['pivot', 'is_active', 'sort_order', 'created_at', 'updated_at'];

    protected $casts = [
        'group' => TagGroup::class,
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function events()
    {
        return $this->belongsToMany(Event::class)
            ->withPivot(['confidence', 'source'])
            ->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInGroup(Builder $query, TagGroup|string $group): Builder
    {
        return $query->where('group', $group instanceof TagGroup ? $group->value : $group);
    }

    /**
     * Poradie, v akom sa štítky ukazujú vo výbere aj vo filtri: najprv facet
     * (v poradí deklarácie enumu), v ňom podľa sort_order.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderByRaw('FIELD(`group`, ' . implode(', ', array_fill(0, count(TagGroup::values()), '?')) . ')', TagGroup::values())
            ->orderBy('sort_order')
            ->orderBy('name');
    }
}
