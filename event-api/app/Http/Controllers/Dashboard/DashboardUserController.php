<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\ModelStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexFilterRequest;
use App\Repositories\Contracts\UserRepository;
use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Http\Requests\ActiveCanalRequest;
use App\Http\Resources\UserResource;
use App\Models\PendingProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse; // Good practice to import JsonResponse
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;


class DashboardUserController extends Controller
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

        return UserResource::collection($this->userRepository->dashboardIndexWithFilters($filters['per_page'], $filters))
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
        $user = $this->userRepository->dashboardShow((int) request()->user()->id);
        $this->authorize('view', $user);

        return response()->json([$user]);
    }

    public function update(string $id, UserUpdateRequest $request): JsonResponse
    {
        $user = $this->userRepository->dashboardShow($id);
        $this->authorize('update', $user);

        $this->userRepository->update($id, $request->validated());
        return response()->json(
            new UserResource($this->userRepository->dashboardShow($id)), 200
        );
    }

    public function store(UserStoreRequest $request): JsonResponse
    {
        $user = $this->userRepository->create($request->validated());
        PendingProfile::create([
            'user_id' => $user->id,
            'display_name' => $request->input('display_name'),
        ]);
        return response()->json(
            new UserResource($this->userRepository->dashboardShow($user->id)),
            201
        );
    }

    public function setActiveCanal(ActiveCanalRequest $request): JsonResponse
    {
        $user = $request->user();
        $canalId = (int) $request->input('canal_id');

        DB::transaction(function () use ($user, $canalId) {
            DB::table('canal_user')
                ->where('user_id', $user->id)
                ->update(['status' => ModelStatus::Draft->value, 'updated_at' => now()]);

            $user->canals()->updateExistingPivot($canalId, [
                'status' => ModelStatus::Published->value,
                'updated_at' => now(),
            ]);

            $user->canal_id = $canalId;
            $user->save();
        });

        return response()->json(new UserResource($user->fresh()), 200);
    }

    public function destroy(string $id): JsonResponse
    {
        $user = $this->userRepository->dashboardShow($id);
        $this->authorize('delete', $user);
        $this->userRepository->delete($id);

        return response()->json(null, 204);
    }

    public function restore(string $id): JsonResponse
    {
        $this->userRepository->dashboardShowForRestore($id);

        $user = $this->userRepository->restore($id);

        return response()->json(new UserResource($user), 200);
    }
}
