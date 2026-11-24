<?php

namespace App\Repositories\Contracts;

use App\Models\Venue;
use Illuminate\Support\Collection;

interface VenueRepository extends InterfaceRepository
{
    public function adminMunicipalityOverview();

    public function dashboardMunicipalityOverview();

    /**
     * Zverejnené miesta v obciach, zoradené podľa názvu — verejná ponuka na
     * výber (sprievodca plagátu), bez zberného „Celé Slovensko".
     *
     * @param  array<int, int>  $municipalityIds
     * @return Collection<int, Venue>
     */
    public function publishedInMunicipalities(array $municipalityIds, int $limit);

    /**
     * Resolve and store GPS coordinates (AI/Nominatim) for every venue that is
     * still missing them. Never throws; geocoding failures are skipped.
     *
     * @param  \Closure(Venue, bool):void|null  $onEach  Called after each venue with (venue, wasUpdated).
     * @return array{processed:int, updated:int, skipped:int}
     */
    public function backfillMissingCoordinates(?\Closure $onEach = null): array;
}
