<?php

namespace App\Models;

use App\Casts\StringLength250;
use App\Casts\Website;
use App\Contracts\HasQuestionBoard;
use App\Contracts\Messageable;
use App\Enums\ModelStatus;
use App\Models\Traits\HasCheckedAttributes;
use App\Models\Traits\HasCommonFilters;
use App\Models\Traits\HasContentReview;
use App\Models\Traits\HasFile;
use App\Models\Traits\HasViews;
use App\Models\Traits\InteractsAsMessageable;
use App\Models\Traits\InteractsAsQuestionBoard;
use App\Models\Traits\SanitizesHtmlBody;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class Event extends Model implements HasQuestionBoard, Messageable
{
    use HasCheckedAttributes, HasCommonFilters, HasContentReview, HasFactory, HasFile, HasViews, InteractsAsMessageable, InteractsAsQuestionBoard, SanitizesHtmlBody, SoftDeletes;

    /** Indexy dodáva migrácia `add_fulltext_search_indexes`. */
    protected function usesFulltextSearch(): bool
    {
        return true;
    }

    protected $guarded = [];

    /**
     * Vnútorná réžia, ktorá nepatrí do odpovede.
     *
     * Verejné endpointy serializujú model priamo cez toArray()
     * (Public\EventController::show) a EventResource stavia na
     * parent::toArray(), takže bez $hidden by tieto stĺpce videl každý
     * návštevník — rovnaký dôvod ako pri `views_count` v HasViews.
     *
     * `meta` je z nich najcitlivejšia: drží surový text importu
     * (`raw_text`, `imported_raw_body`) a celú odpoveď detektora vrátane
     * `ai_detector.event_payload.persons[]`, kde bývajú mená, telefóny
     * a e-maily kontaktných osôb. V UI sa nezobrazuje nikde; organizátorovi
     * a adminovi ju vracia späť EventResource po kontrole práva `view`.
     */
    protected $hidden = [
        'meta',
        'ai_tagged_at',
        'ai_tags_hash',
        'ai_tags_attempts',
        'body_rewritten_at',
    ];

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
        'body_rewritten_at' => 'datetime',
        'workshop_lock_on_start' => 'boolean',
        'price_amount' => 'integer',
        'meta' => 'array',
    ];

    public function setNameAttribute($value)
    {
        $this->attributes['name'] = $value;
        $this->attributes['slug'] = Str::slug($value);
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

    /**
     * Komu sa ozveme, keď na nástenke pribudne súkromná otázka alebo podnet
     * z publika.
     *
     * Zámerne to **nie je** `messageRecipient()`, hoci otázka znie rovnako
     * („kto sa o toto podujatie stará?"). Ten pri importovanom zázname vracia
     * null a má na to dobrý dôvod: na verejnú správu určenú organizátorovi
     * nemá kto odpovedať, keď sme podujatie len prevzali z cudzieho zdroja.
     *
     * Nástenka otázok je iný prípad. Neexistuje sama od seba — vzniká až tým,
     * že ju niekto ručne zapol v dashboarde, a ten niekto sa o podujatie stará
     * bez ohľadu na to, odkiaľ sa doviezlo. S pravidlom o importe by podnet
     * „v sále je zima" na importovanom podujatí nedostal nikdy nikto, hoci
     * nástenku naň niekto vedome zapol. Presne to sa aj stalo pri prvom
     * ostrom podnete.
     *
     * Prvá voľba je vlastník podujatia; keď jeho účet e-mail prijať nemôže
     * (neoverený, zablokovaný), skúsi sa vlastník kanála — ten má na nástenku
     * práva tak či tak (QuestionBoardPolicy).
     */
    public function questionBoardRecipient(): ?User
    {
        // Explicitný dotaz namiesto `$this->user`: mimo produkcie je lenivé
        // načítanie tvrdá chyba a sem sa chodí z verejnej cesty, kde model
        // prišiel z repozitára bez relácií.
        $owner = $this->relationLoaded('user') ? $this->getRelation('user') : $this->user()->first();

        if ($owner?->canReceiveMessages()) {
            return $owner;
        }

        return User::query()
            ->whereHas('canals', fn ($query) => $query
                ->where('canals.id', (int) $this->canal_id)
                ->where('canal_user.is_owner', true))
            ->get()
            ->first(fn (User $user) => $user->canReceiveMessages());
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

    /**
     * Séria opakovaných termínov, ak do nejakej patrí. `null` je bežný stav —
     * väčšina podujatí sa koná raz.
     */
    public function series(): BelongsTo
    {
        return $this->belongsTo(EventSeries::class, 'series_id');
    }

    /**
     * Všetky termíny série vrátane tohto — pre `withCount` vo výpise.
     *
     * Samostatná relácia popri `siblingOccurrences()` preto, že tá vylučuje
     * seba cez `$this->getKey()`, a to pri počítaní nad dotazom (bez inštancie)
     * nefunguje. Volajúci si od počtu odpočíta jednotku.
     */
    public function seriesEvents(): HasMany
    {
        return $this->hasMany(Event::class, 'series_id', 'series_id')
            ->whereNotNull('series_id');
    }

    /**
     * Ostatné termíny tej istej série (bez tohto). Prázdna kolekcia aj vtedy,
     * keď podujatie sériu nemá — volajúci sa tak nemusí pýtať dvakrát.
     */
    public function siblingOccurrences(): HasMany
    {
        return $this->hasMany(Event::class, 'series_id', 'series_id')
            ->whereKeyNot($this->getKey())
            // `series_id` NULL sa v SQL nerovná ničomu vrátane seba, takže
            // podujatie bez série tu prirodzene nedostane ani jeden riadok.
            ->whereNotNull('series_id')
            ->orderByRaw('start_at IS NULL')
            ->orderBy('start_at');
    }

    public function ticketTypes()
    {
        return $this->hasMany(TicketType::class);
    }

    /**
     * Odbery „Pripomeň mi" na tomto podujatí. Polymorfné, lebo tá istá tabuľka
     * nesie aj sledovanie organizátora — pozri App\Models\Subscription.
     */
    public function subscriptions()
    {
        return $this->morphMany(Subscription::class, 'subscribable');
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
