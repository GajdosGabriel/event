<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\MunicipalityRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use App\Http\Resources\MunicipalityResource;
use App\Models\Municipality;

class DashboardMunicipalityController extends Controller
{
    protected $municipalityRepository;

    public function __construct(MunicipalityRepository $municipalityRepository)
    {
        $this->municipalityRepository = $municipalityRepository;
    }

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Municipality::class);

        return MunicipalityResource::collection($this->municipalityRepository->dashboardIndex(15))
            ->additional([
                'meta' => [
                    'permissions' => [
                        'create' => request()->user()?->can('create', Municipality::class) ?? false,
                    ],
                ],
            ]);
    }

    public function all(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Municipality::class);

        return MunicipalityResource::collection($this->municipalityRepository->dashboardAll())
            ->additional([
                'meta' => [
                    'permissions' => [
                        'create' => request()->user()?->can('create', Municipality::class) ?? false,
                    ],
                ],
            ]);
    }

    public function show($id): JsonResponse
    {
        $municipality = $this->municipalityRepository->dashboardShow($id);
        $this->authorize('view', $municipality);

        return response()->json(new MunicipalityResource($municipality));
    }

    public function destroy(string $id): JsonResponse
    {
        $municipality = $this->municipalityRepository->dashboardShow($id);
        $this->authorize('delete', $municipality);
        $this->municipalityRepository->delete($id);

        return response()->json(null, 204);
    }
}
