<?php

namespace App\Repositories\Contracts;

interface VenueRepository extends InterfaceRepository
{
    public function adminMunicipalityOverview();

    public function dashboardMunicipalityOverview();

    /**
     * Resolve and store GPS coordinates (AI/Nominatim) for every venue that is
     * still missing them. Never throws; geocoding failures are skipped.
     *
     * @param \Closure(\App\Models\Venue, bool):void|null $onEach Called after each venue with (venue, wasUpdated).
     * @return array{processed:int, updated:int, skipped:int}
     */
    public function backfillMissingCoordinates(?\Closure $onEach = null): array;
}
