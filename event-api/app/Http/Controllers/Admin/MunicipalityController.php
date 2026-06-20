<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse; // Good practice to import JsonResponse
use App\Http\Resources\MunicipalityResource;
use App\Repositories\Contracts\MunicipalityRepository;
use App\Models\Municipality;
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
