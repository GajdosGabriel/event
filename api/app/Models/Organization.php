<?php

namespace App\Models;

use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Traits\HasCommonFilters;

class Organization extends Model
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, SoftDeletes, HasCommonFilters;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [];

    protected $casts = [
        'title' => \App\Casts\StringLength250::class,
        'website' => \App\Casts\Website::class,
        'published_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'status' => \App\Enums\ModelStatus::class,
    ];

    public function setTitleAttribute($value)
    {
        $this->attributes['title'] = $value;
        $this->attributes['slug']  = Str::slug($value);
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
}
