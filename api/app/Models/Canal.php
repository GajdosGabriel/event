<?php

namespace App\Models;

use App\Casts\StringLength250;
use App\Casts\Website;
use App\Contracts\Messageable;
use App\Enums\CanalIdentityMode;
use App\Enums\CanalNotificationTopic;
use App\Enums\ModelStatus;
use App\Enums\RegistrationSource;
use App\Models\Traits\AlwaysHasCoordinates;
use App\Models\Traits\HasCheckedAttributes;
use App\Models\Traits\HasCommonFilters;
use App\Models\Traits\HasContentReview;
use App\Models\Traits\HasFile;
use App\Models\Traits\HasViews;
use App\Models\Traits\InteractsAsMessageable;
use App\Models\Traits\ProtectsReferencedRecords;
use App\Models\Traits\SanitizesHtmlBody;
use App\Services\Canals\CanalRecipients;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Canal extends Model implements Messageable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use AlwaysHasCoordinates, HasCheckedAttributes, HasCommonFilters, HasContentReview, HasFactory, HasFile, HasViews, InteractsAsMessageable, ProtectsReferencedRecords, SanitizesHtmlBody, SoftDeletes;

    /** Indexy dodáva migrácia `add_fulltext_search_indexes`. */
    protected function usesFulltextSearch(): bool
    {
        return true;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $guarded = [];

    protected $appends = ['has_primary_image', 'primary_image', 'thumb_image', 'files'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [];

    protected $casts = [
        'name' => StringLength250::class,
        'website' => Website::class,
        'published_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'status' => ModelStatus::class,
        'registration_source' => \App\Enums\RegistrationSource::class,
        'identity_mode' => CanalIdentityMode::class,
        'claimed_at' => 'datetime',
    ];

    public function setNameAttribute($value)
    {
        $this->attributes['name'] = $value;
        $this->attributes['slug'] = Str::slug($value);
    }

    public function municipality()
    {
        return $this->belongsTo(\App\Models\Municipality::class, 'municipality_id');
    }

    /**
     * Fakturačná identita kanála. `null` je bežný stav — osobný kanál
     * z registrácie žiadnu firmu nemá a beží v neplatenom režime.
     */
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Má tento kanál nárok na platené funkcie?
     *
     * Jediné miesto, kde sa reťazec vyhodnocuje. Pretrhne sa kdekoľvek —
     * kanál bez organizácie, organizácia bez Accountu, archivovaná alebo
     * zmazaná firma — a kanál spadne na neplatený režim.
     *
     * Kontroluj cez User::hasPaidAccessTo(), nie priamo: samotný nárok kanála
     * ešte nehovorí, že sa naň pýta jeho člen.
     */
    public function hasPaidAccess(): bool
    {
        return $this->organization?->canBill() ?? false;
    }

    public function users()
    {
        return $this->belongsToMany(User::class)->withPivot(['is_owner', 'status', 'role'])->withTimestamps();
    }

    public function owners()
    {
        return $this->belongsToMany(User::class)
            ->wherePivot('is_owner', true);
    }

    /**
     * Všetky e-mailové adresy kanála so stavom doručiteľnosti. Primárna je
     * zrkadlená v `email` — zapisuje ich len CanalEmails.
     */
    public function emails()
    {
        return $this->hasMany(CanalEmail::class);
    }

    /** Nevybavené pozvánky do tímu kanála. */
    public function invitations()
    {
        return $this->hasMany(CanalInvitation::class);
    }

    /** Kto kanál prevzal (viď CanalStewardship). */
    public function claimedBy()
    {
        return $this->belongsTo(User::class, 'claimed_by_user_id');
    }

    /**
     * Stojí za kanálom reálny človek? Registrovaný kanál (self/admin) vždy,
     * importovaný alebo systémový až potom, čo ho niekto prevzal. Pôvod
     * (`registration_source`) sa prevzatím nemení — rozhoduje táto metóda.
     */
    public function isManaged(): bool
    {
        return $this->isClaimed() || in_array($this->registration_source, [
            RegistrationSource::SELF,
            RegistrationSource::ADMIN,
        ], true);
    }

    /** Importovaný/systémový kanál, ktorý prevzal organizátor. */
    public function isClaimed(): bool
    {
        return $this->claimed_at !== null;
    }

    /**
     * Správu kanálu dostane hlavný adresát témy „správy" (CanalRecipients) —
     * predvolene vlastník. Len pri kanáloch, za ktorými reálne niekto stojí
     * (isManaged): nespravované kanály nemajú koho osloviť → nekontaktovateľné,
     * aj keby mali technického vlastníka (importéra), ktorý za cudzí obsah
     * nevie odpovedať.
     */
    public function messageRecipient(): ?User
    {
        if (! $this->isManaged()) {
            return null;
        }

        return app(CanalRecipients::class)->primary($this, CanalNotificationTopic::Messages);
    }

    /**
     * Komu ísť s kontrolou obsahu, atribútov a doplneným profilom — hlavný
     * adresát témy „kontroly", nie správ (prepisuje HasCheckedAttributes).
     */
    public function attributeIssueRecipient(): ?User
    {
        if (! $this->isManaged()) {
            return null;
        }

        return app(CanalRecipients::class)->primary($this, CanalNotificationTopic::Reviews);
    }

    public function events()
    {
        return $this->hasMany(Event::class);
    }

    public function venue()
    {
        return $this->venues();
    }

    public function venues()
    {
        return $this->belongsToMany(Venue::class)
            ->withPivot(['is_owner', 'status'])
            ->withTimestamps();
    }

    public function activeVenues()
    {
        return $this->venues()->wherePivot('status', ModelStatus::Published->value);
    }

    public function ownedVenues()
    {
        return $this->venues()->wherePivot('is_owner', true);
    }

    /**
     * Podujatia sú v zozname prvé zámerne — je to najčastejšia prekážka a
     * doteraz jediná, ktorú DashboardCanalController::destroy() nekontroloval.
     * Pripojené cudzie miesta (`is_owner = false`) prekážkou nie sú, tie sa
     * pri mazaní len odpoja.
     */
    protected function deletionBlockerCounts(): array
    {
        return [
            'canals.errors.blocked_by_events' => $this->events()->withTrashed()->count(),
            'canals.errors.blocked_by_venues' => $this->ownedVenues()->count(),
            'canals.errors.blocked_by_users' => User::query()->where('canal_id', $this->id)->count(),
        ];
    }

    /**
     * Odpublikovanie drží späť len podujatie. Miesta a používatelia stiahnutie
     * z výpisu prežijú — podujatie nie, to na kanál verejne odkazuje.
     */
    protected function unpublishBlockerCounts(): array
    {
        return [
            'canals.errors.unpublish_blocked_by_events' => $this->events()->withTrashed()->count(),
        ];
    }

    protected function defaultThumbImageUrl(): string
    {
        return $this->publicImageUrl('images/canal.svg', 'images/default.svg');
    }
}
