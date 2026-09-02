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

    /**
     * Úprava účtu. Formulár posiela profil, detail len blokovanie — preto sa
     * berie výhradne to, čo v požiadavke naozaj prišlo (viď
     * AdminUserUpdateRequest, kde je všetko `sometimes`).
     */
    public function update(AdminUserUpdateRequest $request, string $id): JsonResponse
    {
        $user = $this->userRepository->adminShow($id);
        $this->authorize('update', $user);
        abort_if((int) $user->id === (int) $request->user()->id, 403, __('users.errors.self_update'));

        $data = $request->validated();
        $attributes = [];

        foreach (['email', 'status', 'canal_id'] as $key) {
            if (array_key_exists($key, $data)) {
                $attributes[$key] = $data[$key];
            }
        }

        // Prázdne pole znamená „nechať staré heslo“, nie prázdne heslo.
        // Zahašuje ho cast na modeli.
        if (! empty($data['password'])) {
            $attributes['password'] = $data['password'];
        }

        // Pôvodnú značku overenia neprepisujeme — dokladuje, kedy si účet
        // adresu overil sám. Odškrtnutie ju zmaže a overenie sa vyžiada znova.
        if (array_key_exists('email_verified', $data)) {
            $attributes['email_verified_at'] = $data['email_verified']
                ? ($user->email_verified_at ?? now())
                : null;
        }

        if (array_key_exists('blocked', $data)) {
            $blocked = (bool) $data['blocked'];

            $attributes['blocked_at']     = $blocked ? ($user->blocked_at ?? now()) : null;
            $attributes['blocked_until']  = $blocked ? ($data['blocked_until'] ?? null) : null;
            $attributes['blocked_reason'] = $blocked ? ($data['blocked_reason'] ?? null) : null;
        }

        if ($attributes !== []) {
            $user->forceFill($attributes)->save();
        }

        return response()->json(new UserResource($this->userRepository->adminShow($id)), 200);
    }

    public function destroy(string $id): JsonResponse
    {
        $user = $this->userRepository->adminShow($id);
        $this->authorize('delete', $user);
        abort_if((int) $user->id === (int) request()->user()->id, 403, __('users.errors.self_delete'));

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
