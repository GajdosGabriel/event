<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Resources\Traits\HasAllowedStatuses;
use App\Http\Requests\OrganizationStoreRequest;
use App\Http\Requests\IndexFilterRequest;
use App\Http\Resources\OrganizationResource;
use App\Models\Organization;
use App\Repositories\Contracts\OrganizationRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DashboardOrganizationController extends Controller
{
    use HasAllowedStatuses;
    protected OrganizationRepository $organizationRepository;

    public function __construct(OrganizationRepository $organizationRepository)
    {
        $this->organizationRepository = $organizationRepository;
    }

    public function index(IndexFilterRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Organization::class);

        $filters = $request->getFilters();
        $data = $this->organizationRepository->dashboardIndexWithFilters($filters['per_page'], $filters);

        return OrganizationResource::collection($data)
            ->additional([
                'meta' => [
                    'permissions' => [
                        'create' => request()->user()?->can('create', Organization::class) ?? false,
                    ],
                    'allowed_statuses' => $this->allowedStatuses($request),
                ],
            ]);
    }

    public function show(string $id): JsonResponse
    {
        $organization = $this->organizationRepository->dashboardShow($id);
        $this->authorize('view', $organization);

        return response()->json(new OrganizationResource($organization));
    }

    public function store(OrganizationStoreRequest $request): JsonResponse
    {
        $this->authorize('create', Organization::class);

        $organization = $this->organizationRepository->create($request->validated());

        return response()->json(new OrganizationResource($organization), 201);
    }

    public function update(string $id, OrganizationStoreRequest $request): JsonResponse
    {
        $organization = $this->organizationRepository->dashboardShow($id);
        $this->authorize('update', $organization);

        $organization = $this->organizationRepository->update($id, $request->validated());

        return response()->json(new OrganizationResource($organization));
    }

    public function destroy(string $id): JsonResponse
    {
        $organization = $this->organizationRepository->dashboardShow($id);
        $this->authorize('delete', $organization);

        $this->organizationRepository->delete($id);

        return response()->json(null, 204);
    }

    public function restore(string $id): JsonResponse
    {
        $this->organizationRepository->dashboardShowForRestore($id);

        $organization = $this->organizationRepository->restore($id);

        return response()->json(new OrganizationResource($organization));
    }
}
