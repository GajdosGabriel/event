<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Posledné hľadanie polohy kanála alebo miesta (App\Services\Geocoding\PlaceCoordinateBackfill).
 */
class CoordinateLookup extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'looked_up_at' => 'datetime',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public static function for(Model $subject): ?self
    {
        return self::query()
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->first();
    }
}
