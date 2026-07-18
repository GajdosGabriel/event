<?php

namespace App\Models;

use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;
use App\Casts\{StringLength250, Website};
use App\Contracts\Messageable;
use App\Enums\CanalIdentityMode;
use App\Enums\ModelStatus;
use App\Enums\RegistrationSource;
use App\Models\User;
use App\Models\Traits\{HasCommonFilters, HasFile, InteractsAsMessageable};

class Canal extends Model implements Messageable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, SoftDeletes, HasFile, HasCommonFilters, InteractsAsMessageable;

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
    ];

    public function setNameAttribute($value)
    {
        $this->attributes['name'] = $value;
        $this->attributes['slug']  = Str::slug($value);
    }

    public function municipality()
    {
        return $this->belongsTo(\App\Models\Municipality::class, 'municipality_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class)->withPivot(['is_owner', 'status'])->withTimestamps();
    }

    public function owners()
    {
        return $this->belongsToMany(User::class)
            ->wherePivot('is_owner', true);
    }

    /**
     * Správu kanálu dostane jeho vlastník — ale len pri kanáloch, za ktorými
     * reálne niekto stojí (registrácia self/admin). Importované a systémové
     * kanály nemajú koho osloviť → nekontaktovateľné, aj keby mali priradeného
     * technického vlastníka (importéra), ktorý za cudzí obsah nevie odpovedať.
     */
    public function messageRecipient(): ?User
    {
        $managed = in_array($this->registration_source, [
            RegistrationSource::SELF,
            RegistrationSource::ADMIN,
        ], true);

        if (! $managed) {
            return null;
        }

        return $this->owners->first(fn (User $owner) => $owner->canReceiveMessages());
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

    protected function defaultThumbImageUrl(): string
    {
        return $this->publicImageUrl('images/canal-default.svg', 'images/canal.png', 'images/default.png');
    }
}
