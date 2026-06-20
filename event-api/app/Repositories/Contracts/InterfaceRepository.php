<?php

/**
 * Created by PhpStorm.
 * User: Gabriel
 * Date: 17.11.2018
 * Time: 9:43
 */

namespace App\Repositories\Contracts;


interface InterfaceRepository
{
    public function all();
    public function find($id);
    public function findWhere($column, $value);
    public function findWhereFirst($column, $value);
    public function paginate($perPage = 10);
    public function create(array $properties);
    public function update($id, array $properties);
    public function delete($id);
    public function restore($id);

    public function adminIndex($perPage = 15);
    public function adminIndexWithFilters($perPage = 15, array $filters = []);
    public function adminIndexQuery();
    public function adminShow($id);

    public function publicIndex();
    public function publicIndexWithFilters($perPage = 15, array $filters = []);
    public function publicIndexQuery();
    public function publicShow($id);

    public function dashboardIndex($perPage = 15);
    public function dashboardIndexWithFilters($perPage = 15, array $filters = []);
    public function dashboardShow($id);
    public function dashboardShowForRestore($id);
    public function dashboardIndexQuery();
}
