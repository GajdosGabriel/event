<?php

namespace App\Repositories\Eloquent;

use App\Models\Organization;
use App\Repositories\AbstractRepository;
use App\Repositories\Contracts\OrganizationRepository;

class EloquentOrganizationRepository extends AbstractRepository implements OrganizationRepository
{
    public function entity(): string
    {
        return Organization::class;
    }

    public function adminIndexQuery()
    {
        return $this->latestFirst($this->model()->withTrashed());
    }

    public function dashboardIndexQuery()
    {
        return $this->latestFirst($this->model());
    }

    public function dashboardShow($id)
    {
        return $this->dashboardIndexQuery()->where('id', $id)->firstOrFail();
    }

    public function publicIndexQuery()
    {
        return $this->latestFirst($this->model()->where('published', true));
    }
}
