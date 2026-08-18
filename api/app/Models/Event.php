<?php

namespace App\Models;

use App\Casts\StringLength250;
use App\Casts\Website;
use App\Contracts\HasQuestionBoard;
use App\Contracts\Messageable;
use App\Enums\ModelStatus;
use App\Models\Traits\HasCheckedAttributes;
use App\Models\Traits\HasCommonFilters;
use App\Models\Traits\HasFile;
use App\Models\Traits\HasViews;
use App\Models\Traits\InteractsAsMessageable;
use App\Models\Traits\InteractsAsQuestionBoard;
use App\Models\Traits\SanitizesHtmlBody;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class Event extends Model implements HasQuestionBoard, Messageable
{
    use HasCheckedAttributes, HasCommonFilters, HasFactory, HasFile, HasViews, InteractsAsMessageable, InteractsAsQuestionBoard, SanitizesHtmlBody, SoftDeletes;

    /** Indexy dodáva migrácia `add_fulltext_search_indexes`. */
    protected function usesFulltextSearch(): bool
    {
        return true;
    }

    protected $guarded = [];

    protected $hidden = [];

    protected $appends = ['has_primary_image', 'primary_image', 'thumb_image', 'owner', 'canal', 'venue', 'municipality', 'files', 'tickets_enabled'];

    protected $casts = [
        'name' => StringLength250::class,
        'website' => Website::class,
        'status' => ModelStatus::class,
        'published_at' => 'datetime',
        'publish_at' => 'datetime',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'registration_deadline_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
        'workshop_lock_on_start' => 'boolean',
        'price_amount' => 'integer',
        'meta' => 'array',
    ];

    public function setNameAttribute($value)
    {
        $this->attributes['name'] = $value;
        $this->attributes['slug'] = Str::slug($value);
    }

    /** Popis od AI sa vykresľuje rovnako cez v-html — viď SanitizesHtmlBody. */
    public function setBodyAiAttribute(mixed $value): void
    {
        $this->attributes['body_ai'] = static::sanitizeHtmlBody($value);
    }

    public function canal()
    {
        return $this->belongsTo(Canal::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Správu k podujatiu dostane jeho vlastník — ale len pri „vlastnom" obsahu.
     * Importované podujatia (orginal_source) vlastní ten, kto ich importoval,
     * a ten nevie odpovedať za cudzieho organizátora → nekontaktovateľné.
     */
    public function messageRecipient(): ?User
    {
        if (! empty($this->orginal_source)) {
            return null;
        }

        $owner = $this->user;

        return $owner && $owner->canReceiveMessages() ? $owner : null;
    }

    /** Nástenka otázok na podujatí patrí samotnému podujatiu. */
    public function questionBoardEvent(): ?Event
    {
        return $this;
    }

    public function questionBoardTitle(): string
    {
        return (string) $this->name;
    }

    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function ticketTypes()
    {
        return $this->hasMany(TicketType::class);
    }

    public function admissions()
    {
        return $this->hasMany(Admission::class);
    }

    /**
     * Obsahové štítky naprieč facetmi (druh, téma, publikum, charakter).
     *
     * Pivot drží, kto štítok priradil — preštítkovanie cez AI sa dotýka len
     * riadkov so source='ai', takže ručný výber organizátora prežije.
     *
     * Zámerne NIE JE v $appends: accessor by pri výpise strieľal dotaz na každý
     * riadok. Štítky sa načítavajú eager loadom (indexEagerLoads, publicIndexQuery,
     * publicShow) a do odpovede ich dáva EventResource.
     */
    public function tags()
    {
        return $this->belongsToMany(Tag::class)
            ->withPivot(['confidence', 'source'])
            ->withTimestamps();
    }

    /**
     * Registrácia / predaj lístkov je dostupná, keď má podujatie aspoň jeden
     * aktívny typ lístka. Nie je to samostatný prepínač — odvádza sa z typov.
     */
    public function getTicketsEnabledAttribute(): bool
    {
        if ($this->relationLoaded('ticketTypes')) {
            return $this->ticketTypes->contains(fn ($type) => (bool) $type->is_active);
        }

        if ($this->id === null) {
            return false;
        }

        return $this->ticketTypes()->where('is_active', true)->exists();
    }

    /**
     * Po začiatku podujatia sa prihlásenie na workshopy už nedá meniť
     * (ak to organizátor nevypol). Po skončení podujatia vždy.
     */
    public function workshopChangesLocked(): bool
    {
        if ($this->end_at !== null && $this->end_at->isPast()) {
            return true;
        }

        return (bool) $this->workshop_lock_on_start
            && $this->start_at !== null
            && $this->start_at->isPast();
    }

    public function getOwnerAttribute()
    {
        return Auth::check() && Auth::id() === $this->user_id;
    }

    public function getCanalAttribute()
    {
        return $this->relationLoaded('canal')
            ? $this->getRelation('canal')
            : $this->canal()->first();
    }

    public function getVenueAttribute()
    {
        return $this->relationLoaded('venue')
            ? $this->getRelation('venue')
            : $this->venue()->first();
    }

    public function getMunicipalityAttribute()
    {
        $venue = $this->relationLoaded('venue')
            ? $this->getRelation('venue')
            : $this->venue()->first();

        if ($venue === null) {
            return null;
        }

        if ($venue->relationLoaded('municipality')) {
            return $venue->getRelation('municipality');
        }

        if ($venue->village_id === null) {
            return null;
        }

        return Municipality::query()->find($venue->village_id);
    }

    protected function defaultThumbImageUrl(): string
    {
        $canal = $this->relationLoaded('canal')
            ? $this->getRelation('canal')
            : $this->canal()->first();

        // Use canal image only when canal has a real primary image
        if ($canal?->has_primary_image) {
            return $canal->thumb_image;
        }

        return $this->publicImageUrl('images/event.svg', 'images/default.svg');
    }

    protected function defaultPrimaryImage(): array
    {
        $canal = $this->relationLoaded('canal')
            ? $this->getRelation('canal')
            : $this->canal()->first();

        // Use canal image only when canal has a real primary image
        if ($canal?->has_primary_image) {
            return $canal->primary_image;
        }

        $fallback = $this->defaultThumbImageUrl();

        return [
            'thumb' => $fallback,
            'large' => $fallback,
        ];
    }
}
