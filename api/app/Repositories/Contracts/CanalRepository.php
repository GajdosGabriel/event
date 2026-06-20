<?php

/**
 * Created by PhpStorm.
 * User: Gabriel
 * Date: 29.01.2019
 * Time: 9:34
 */

namespace App\Repositories\Contracts;


interface CanalRepository extends InterfaceRepository
{
    public function create(array $properties);

    /**
     * Get paginated canals for dashboard view.
     */
    public function dashboardIndex($perPage = 15);

    /**
     * Get query builder for dashboard canals.
     */
    public function dashboardIndexQuery();

    public function adminMunicipalityOverview();

    public function dashboardMunicipalityOverview();
}
