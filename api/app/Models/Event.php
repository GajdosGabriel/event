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
    protected $appends = ['has_primary_image', 'primary_image', 'thumb_image', 'owner', 'canal', 'venue', 'municipality', 'files', 'remaining_capacity'];

    protected $casts = [
        'name' => StringLength250::class,
        'status' => ModelStatus::class,
        'published_at' => 'datetime',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'registration_deadline_at' => 'datetime',
        'tickets_enabled' => 'boolean',
        'capacity' => 'integer',
        'price_amount' => 'integer',
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

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function getRemainingCapacityAttribute(): ?int
    {
        if ($this->capacity === null) {
            return null;
        }

        $issued = $this->tickets()
            ->whereIn('status', [\App\Enums\TicketStatus::Reserved->value, \App\Enums\TicketStatus::Confirmed->value])
            ->count();

        return max(0, $this->capacity - $issued);
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

        $modelFallback = 'storage/images/event-default.svg';
        $legacyFallback = 'storage/images/canal-man.png';
        $sharedFallback = 'storage/images/default.png';

        if (file_exists(public_path($modelFallback))) return url($modelFallback);
        if (file_exists(public_path($legacyFallback))) return url($legacyFallback);
        return url($sharedFallback);
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
