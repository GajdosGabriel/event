<?php

namespace App\Repositories\Eloquent;

use App\Models\File;
use App\Repositories\AbstractRepository;
use App\Repositories\Contracts\FileRepository;

class EloquentFileRepository extends AbstractRepository implements FileRepository
{
    public function entity(): string
    {
        return File::class;
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
        return $this->latestFirst($this->model()->where('is_primary', true));
    }
}
