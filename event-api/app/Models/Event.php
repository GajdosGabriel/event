<?php

namespace App\Models;

use App\Casts\StringLength250;
use App\Enums\ModelStatus;
use App\Models\Traits\{HasCommonFilters, HasFile};
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class Event extends Model
{
    use HasFactory, SoftDeletes, HasFile, HasCommonFilters;

    protected $guarded = [];
    protected $hidden = [];
    protected $appends = ['primary_image', 'thumb_image', 'owner', 'canal', 'venue', 'municipality', 'files'];

    protected $casts = [
        'name' => StringLength250::class,
        'status' => ModelStatus::class,
        'published_at' => 'datetime',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'registration_deadline_at' => 'datetime',
        'meta' => 'array',
    ];


    public function setNameAttribute($value)
    {
        $this->attributes['name'] = $value;
        $this->attributes['slug']  = Str::slug($value);
    }

    public function canal()
    {
        return $this->belongsTo(Canal::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }

    public function getOwnerAttribute()
    {
        return Auth::check() && Auth::id() === $this->user_id;
    }

    public function getCanalAttribute()
    {
        return $this->canal()->first();
    }

    public function getVenueAttribute()
    {
        return $this->venue()->first();
    }

    public function getMunicipalityAttribute()
    {
        $venue = $this->relationLoaded('venue')
            ? $this->getRelation('venue')
            : $this->venue()->first();

        if ($venue === null || $venue->village_id === null) {
            return null;
        }

        return Municipality::query()->find($venue->village_id);
    }

    public function getFilesAttribute()
    {
        return $this->files()->get();
    }

    protected function defaultThumbImageUrl(): string
    {
        $canal = $this->relationLoaded('canal')
            ? $this->getRelation('canal')
            : $this->canal()->first();

        if ($canal?->thumb_image) {
            return $canal->thumb_image;
        }

        $modelFallback = 'storage/images/canal-man.png';
        $sharedFallback = 'storage/images/default.png';

        return file_exists(public_path($modelFallback))
            ? url($modelFallback)
            : url($sharedFallback);
    }

    protected function defaultPrimaryImage(): array
    {
        $canal = $this->relationLoaded('canal')
            ? $this->getRelation('canal')
            : $this->canal()->first();

        if (is_array($canal?->primary_image ?? null)) {
            return $canal->primary_image;
        }

        $fallback = $this->defaultThumbImageUrl();

        return [
            'thumb' => $fallback,
            'large' => $fallback,
            // 'original' => $fallback,
        ];
    }
}
