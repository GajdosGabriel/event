<?php
/**
 * Created by PhpStorm.
 * User: Gabriel
 * Date: 19.11.2018
 * Time: 13:31
 */

namespace App\Repositories\Eloquent\Criteria;


use App\Repositories\Criteria\CriterionInterface;

class LatestFirst implements CriterionInterface
{
    public function apply($entity)
    {
        return $entity->latest();
    }

}