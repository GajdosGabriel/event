<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\CanalRepository;
use App\Services\Lookups\ManualLookups;
use Illuminate\Http\JsonResponse;

/**
 * Doplnenie údajov kanála (profil, poloha, e-mail): stav a ručné spustenie.
 * Viď App\Services\Lookups\ManualLookups.
 */
class CanalLookupController extends Controller
{
    public function __construct(
        private readonly CanalRepository $canals,
        private readonly ManualLookups $lookups,
    ) {}

    public function show(string $id): JsonResponse
    {
        $canal = $this->canals->adminShow($id);
        $this->authorize('view', $canal);

        return response()->json(['data' => $this->lookups->status($canal)]);
    }

    public function run(string $id, string $kind): JsonResponse
    {
        $canal = $this->canals->adminShow($id);
        // Hľadanie môže zmeniť údaje kanála (web, adresu, kontakty).
        $this->authorize('update', $canal);

        if ($blocker = $this->lookups->blocker($canal, $kind)) {
            return response()->json([
                'message' => __('lookups.blocked.'.$kind.'.'.$blocker),
                'blocker' => $blocker,
            ], 422);
        }

        $this->lookups->start($canal, $kind);

        return response()->json(['data' => $this->lookups->status($canal->fresh())], 202);
    }
}
