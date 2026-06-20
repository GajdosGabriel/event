<?php

/**
 * Created by PhpStorm.
 * User: Gabriel
 * Date: 17.11.2018
 * Time: 8:49
 */

namespace App\Repositories;

use Illuminate\Support\Arr;
use App\Repositories\Criteria\CriteriaInterface;
use App\Repositories\Contracts\InterfaceRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Pagination\LengthAwarePaginator;

abstract class AbstractRepository implements InterfaceRepository, CriteriaInterface
{
    protected $entity;
    protected $user;

    public function __construct()
    {
        $this->entity = $this->entity();
        $this->user = Auth::user();
    }

    abstract public function entity(): string;

    /**
     * Vrati instanciu modelu.
     */
    protected function model()
    {
        return app($this->entity);
    }

    protected function latestFirst($query, string $column = 'created_at')
    {
        return $query->orderByDesc($column)->orderByDesc('id');
    }

    public function all()
    {
        return $this->model()->get();
    }

    public function find($id)
    {
        return $this->model()->findOrFail($id);
    }

    public function findWhere($column, $value)
    {
        return $this->model()->where($column, $value)->get();
    }

    public function findWhereFirst($column, $value)
    {
        return $this->model()->where($column, $value)->first();
    }

    public function paginate($perPage = 15)
    {
        return $this->model()->paginate($perPage);
    }

    public function create(array $properties)
    {
        return $this->model()->create($properties);
    }

    public function update($id, array $properties)
    {
        $entity = $this->find($id);
        $entity->update($properties);

        return $entity;
    }

    public function delete($id)
    {
        return $this->find($id)->delete();
    }

    public function restore($id)
    {
        $entity = $this->model()->withTrashed()->findOrFail($id);
        $entity->restore();

        return $entity->fresh();
    }

    public function withCriteria(...$criteria)
    {
        $criteria = Arr::flatten($criteria);

        $query = $this->model();

        foreach ($criteria as $criterion) {
            $query = $criterion->apply($query);
        }

        return $query;
    }

    // Start Controller method to resolve the entity
    protected function resolveEntity()
    {
        return app()->make($this->entity());
    }

    public function adminIndex($perPage = 15)
    {
        Gate::authorize('viewAny', $this->entity);

        return $this->adminIndexQuery()->paginate($perPage);
    }

    public function indexWithFilters($perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return $this->adminIndexWithFilters($perPage, $filters);
    }

    public function adminIndexWithFilters($perPage = 15, array $filters = []): LengthAwarePaginator
    {
        Gate::authorize('viewAny', $this->entity);

        return $this->paginateFilteredQuery($this->adminIndexQuery(), $perPage, $filters);
    }

    public function dashboardIndexWithFilters($perPage = 15, array $filters = []): LengthAwarePaginator
    {
        Gate::authorize('viewAny', $this->entity);

        return $this->paginateFilteredQuery($this->dashboardIndexQuery(), $perPage, $filters);
    }

    public function adminIndexQuery()
    {
        return $this->latestFirst($this->model()->withTrashed());
    }

    public function adminShow($id)
    {
        $entity = $this->model()->withTrashed()->findOrFail($id);
        Gate::authorize('view', $entity);

        return $entity;
    }

    public function publicIndex($perPage = 15)
    {
        return $this->publicIndexQuery()->paginate($perPage);
    }

    public function publicIndexWithFilters($perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return $this->paginateFilteredQuery($this->publicIndexQuery(), $perPage, $filters);
    }

    public function publicShow($id)
    {
        return $this->model()->find($id);
    }

    public function dashboardIndex($perPage = 15)
    {
        Gate::authorize('viewAny', $this->entity);

        return $this->dashboardIndexQuery()->paginate($perPage);
    }

    protected function paginateFilteredQuery($query, $perPage, array $filters): LengthAwarePaginator
    {
        if ($query instanceof Relation) {
            $query = $query->getQuery();
        }

        if ($query instanceof Builder && method_exists($query->getModel(), 'scopeApplyCommonFilters')) {
            $query->applyCommonFilters($filters);
        }

        return $query->paginate($perPage);
    }

    public function dashboardShowForRestore($id)
    {
        $query = $this->dashboardIndexQuery();

        if ($this->usesSoftDeletes()) {
            $query = $query->withTrashed();
        }

        $entity = $query->whereKey($id)->firstOrFail();
        Gate::authorize('restore', $entity);

        return $entity;
    }

    protected function usesSoftDeletes(): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($this->entity), true);
    }
}
