<?php

namespace App\Repositories\Eloquent;

use App\Models\Municipality;
use App\Repositories\AbstractRepository;
use App\Repositories\Contracts\MunicipalityRepository;

class EloquentMunicipalityRepository extends AbstractRepository implements MunicipalityRepository
{
    public function entity(): string
    {
        return Municipality::class;
    }

    public function adminIndexQuery()
    {
        return $this->all();
    }

    public function dashboardIndexQuery()
    {
        return $this->latestFirst($this->model());
    }

    public function dashboardShow($id)
    {
        return $this->dashboardIndexQuery()->where('id', $id)->firstOrFail();
    }

    public function dashboardAll()
    {
        return $this->dashboardIndexQuery()->get();
    }

    public function publicIndexQuery()
    {
        return $this->latestFirst($this->model());
    }
}
