<?php

namespace App\Repositories\Contracts;

interface VenueRepository extends InterfaceRepository
{
    public function adminMunicipalityOverview();

    public function dashboardMunicipalityOverview();
}
