<?php

namespace App\Models;

use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;
use App\Casts\{StringLength250, Website};
use App\Enums\CanalIdentityMode;
use App\Enums\ModelStatus;
use App\Models\User;
use App\Models\Traits\{HasCommonFilters, HasFile};

class Canal extends Model
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, SoftDeletes, HasFile, HasCommonFilters;

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
        $modelFallback = 'storage/images/canal-default.svg';
        $legacyFallback = 'storage/images/canal.png';
        $sharedFallback = 'storage/images/default.png';

        if (file_exists(public_path($modelFallback))) return url($modelFallback);
        if (file_exists(public_path($legacyFallback))) return url($legacyFallback);
        return url($sharedFallback);
    }
}
