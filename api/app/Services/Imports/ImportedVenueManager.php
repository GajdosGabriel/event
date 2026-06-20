<?php

namespace App\Services\Imports;

use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\Venue;

class ImportedVenueManager
{
    public function resolveFallbackVenue(Canal $canal): Venue
    {
        $venue = Venue::query()
            ->whereHas('canals', fn ($query) => $query->where('canals.id', $canal->id))
            ->where('slug', 'cele-slovensko')
            ->first();

        if ($venue instanceof Venue) {
            return $venue;
        }

        $venue = Venue::query()->create([
            'village_id' => (int) ($canal->municipality_id ?: 4209),
            'name' => 'Cele Slovensko',
            'street' => null,
            'postcode' => null,
            'body' => 'Fallback venue pre importovane eventy bez spolahlivo rozpoznaneho miesta konania.',
            'website' => $canal->website,
            'country' => 'Slovensko',
            'latitude' => null,
            'longitude' => null,
            'capacity' => null,
            'opening_hours' => null,
            'category' => 'fallback',
            'status' => ModelStatus::Draft->value,
        ]);

        $venue->assignCanal($canal, isOwner: true);

        return $venue;
    }
}
