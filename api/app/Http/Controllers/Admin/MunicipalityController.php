<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\MunicipalityResource; // Good practice to import JsonResponse
use App\Models\Municipality;
use App\Repositories\Contracts\MunicipalityRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MunicipalityController extends Controller
{
    protected $municipalityRepository;

    protected $venueRepository;

    public function __construct(MunicipalityRepository $municipalityRepository)
    {
        $this->municipalityRepository = $municipalityRepository;
    }

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Municipality::class);

        return response()->json(
            MunicipalityResource::collection($this->municipalityRepository->paginate(15))
                ->additional([
                    'meta' => [
                        'permissions' => [
                            'create' => request()->user()?->can('create', Municipality::class) ?? false,
                        ],
                    ],
                ])
        );
    }

    public function all(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Municipality::class);

        return MunicipalityResource::collection($this->municipalityRepository->all())
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
        $municipality = $this->municipalityRepository->adminShow($id);
        $this->authorize('view', $municipality);

        return response()->json(['admin-show' => $municipality]);
    }
}
