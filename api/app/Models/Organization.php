<?php

namespace App\Models;

use App\Models\Traits\HasCommonFilters;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Organization extends Model
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasCommonFilters, HasFactory, SoftDeletes;

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
        'account_synced_at' => 'datetime',
        'status' => \App\Enums\ModelStatus::class,
    ];

    /** Firma má protipól v Accounte, takže sa dajú čítať fakturačné údaje. */
    public function isLinkedToAccount(): bool
    {
        return filled($this->account_uuid);
    }

    public function setTitleAttribute($value)
    {
        $this->attributes['title'] = $value;
        $this->attributes['slug'] = Str::slug($value);
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
