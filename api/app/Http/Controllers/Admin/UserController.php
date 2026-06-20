<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexFilterRequest;
use App\Http\Resources\UserResource;
use App\Repositories\Contracts\UserRepository;
use Illuminate\Http\JsonResponse; // Good practice to import JsonResponse
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use App\Models\User;


class UserController extends Controller
{
    protected $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function index(IndexFilterRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        $filters = $request->getFilters();
        $data = $this->userRepository->adminIndexWithFilters($filters['per_page'], $filters);

        return UserResource::collection($data)
            ->additional([
                'meta' => [
                    'permissions' => [
                        'create' => request()->user()?->can('create', User::class) ?? false,
                    ],
                ],
            ]);
    }

    public function show($id): JsonResponse
    {
        $user = $this->userRepository->adminShow($id);
        $this->authorize('view', $user);

        return response()->json(['admin-show' => $user]);
    }

    public function restore(string $id): JsonResponse
    {
        $user = $this->userRepository->adminShow($id);
        $this->authorize('restore', $user);

        $user = $this->userRepository->restore($id);

        return response()->json(new UserResource($user), 200);
    }
}
