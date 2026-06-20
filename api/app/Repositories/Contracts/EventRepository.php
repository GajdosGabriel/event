<?php

namespace App\Repositories\Contracts;

use App\Models\User;

interface EventRepository extends InterfaceRepository
{
    public function dashboardIndex($perPage = 15);
    public function events($user = null);
    public function createForUser(User $user, array $properties);
    public function publish($id);
    public function dashboardMunicipalityOverview(string $scope = 'all');
    public function adminMunicipalityOverview(string $scope = 'all');
    public function publicMunicipalityOverview(string $scope = 'all');
}
