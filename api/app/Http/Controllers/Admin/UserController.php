<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminUserUpdateRequest;
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

        return response()->json(new UserResource($user), 200);
    }

    public function update(AdminUserUpdateRequest $request, string $id): JsonResponse
    {
        $user = $this->userRepository->adminShow($id);
        $this->authorize('update', $user);
        abort_if((int) $user->id === (int) $request->user()->id, 403, 'Nemôžete upraviť vlastný účet.');

        $blocked = $request->boolean('blocked');

        $user->forceFill([
            'blocked_at'     => $blocked ? ($user->blocked_at ?? now()) : null,
            'blocked_until'  => $blocked ? $request->input('blocked_until') : null,
            'blocked_reason' => $blocked ? $request->input('blocked_reason') : null,
        ])->save();

        return response()->json(new UserResource($user->fresh()), 200);
    }

    public function destroy(string $id): JsonResponse
    {
        $user = $this->userRepository->adminShow($id);
        $this->authorize('delete', $user);
        abort_if((int) $user->id === (int) request()->user()->id, 403, 'Nemôžete zmazať vlastný účet.');

        $this->userRepository->delete($id);

        return response()->json(null, 204);
    }

    public function restore(string $id): JsonResponse
    {
        $user = $this->userRepository->adminShow($id);
        $this->authorize('restore', $user);

        $user = $this->userRepository->restore($id);

        return response()->json(new UserResource($user), 200);
    }
}
