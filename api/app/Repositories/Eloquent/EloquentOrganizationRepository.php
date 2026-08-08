<?php

namespace App\Repositories\Eloquent;

use App\Models\Organization;
use App\Repositories\AbstractRepository;
use App\Repositories\Contracts\OrganizationRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class EloquentOrganizationRepository extends AbstractRepository implements OrganizationRepository
{
    /**
     * Kanály aj s tímom. Načítava sa len v detaile — vo výpise by to bol
     * dotaz na riadok; tam stačí `canals_count`.
     *
     * @var array<int, string>
     */
    private const DETAIL_RELATIONS = ['canals.users'];

    public function entity(): string
    {
        return Organization::class;
    }

    public function adminIndexQuery()
    {
        return $this->latestFirst($this->model()->withTrashed()->withCount('canals'));
    }

    public function adminShow($id)
    {
        $organization = $this->model()
            ->withTrashed()
            ->withCount('canals')
            ->with(self::DETAIL_RELATIONS)
            ->findOrFail($id);

        Gate::authorize('view', $organization);

        return $organization;
    }

    /**
     * Dashboard vidí len organizácie naviazané na kanály používateľa.
     *
     * Doteraz tu bol `$this->model()` bez akéhokoľvek scope — a keďže rolu
     * `organization.view` seeder priraďuje aj `canal-owner` a `canal-editor`,
     * hociktorý organizátor si vypísal všetky firmy v systéme aj s väzbou
     * na Account. Rovnaký scope ako pri kanáloch (EloquentCanalRepository).
     */
    public function dashboardIndexQuery()
    {
        $user = Auth::user();

        $query = $this->model()
            ->whereIn('id', $user?->organizationIds() ?? [])
            ->withCount('canals');

        return $this->latestFirst($query);
    }

    public function dashboardShow($id)
    {
        return $this->dashboardIndexQuery()
            ->with(self::DETAIL_RELATIONS)
            ->where('id', $id)
            ->firstOrFail();
    }

    public function publicIndexQuery()
    {
        return $this->latestFirst($this->model()->where('published', true));
    }
}
