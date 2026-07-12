<?php

namespace App\Http\Controllers\Public;

use App\Enums\ModelStatus;
use App\Http\Controllers\Controller;
use App\Models\Canal;
use App\Models\Event;
use App\Repositories\Contracts\CanalRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CanalController extends Controller
{
    protected $canalRepository;

    public function __construct(CanalRepository $canalRepository)
    {
        $this->canalRepository = $canalRepository;
    }

    public function index()
    {
        return response()->json($this->canalRepository->publicIndex());
    }

    public function show($id)
    {
        $canal = $this->canalRepository->publicShow($id);

        if (! $canal) {
            abort(404);
        }

        $data = $canal->toArray();
        // Kontaktovateľné len ak má kanál aktívneho majiteľa (self/admin
        // registrácia, overený e-mail) a návštevník ním nie je sám.
        $data['contactable'] = $canal->isContactableBy(auth('sanctum')->user());

        // Obec a (publikované) miesta pre verejný detail – rovnaký tvar ako
        // v CanalResource, aby ich front vedel zobraziť.
        if ($canal->relationLoaded('municipality') && $canal->municipality) {
            $data['municipality'] = [
                'id' => $canal->municipality->id,
                'name' => $canal->municipality->fullname,
            ];
        }

        if ($canal->relationLoaded('venues')) {
            $data['venues_list'] = $canal->venues->map(fn ($v) => [
                'id' => $v->id,
                'name' => $v->name,
                'is_owner' => (bool) $v->pivot->is_owner,
            ])->values()->all();
        }

        return response()->json($data);
    }

    /**
     * Publikované eventy organizované týmto kanálom (verejný zoznam).
     */
    public function events($id): JsonResponse
    {
        $canal = Canal::findOrFail($id);

        $events = Event::where('canal_id', $canal->id)
            ->where('status', ModelStatus::Published->value)
            ->orderByDesc('start_at')
            ->limit(100)
            ->get(['id', 'name', 'start_at', 'end_at', 'status']);

        return response()->json($events->map(fn ($ev) => [
            'id' => $ev->id,
            'name' => $ev->name,
            'start_at' => $ev->start_at,
            'end_at' => $ev->end_at,
            'status' => $ev->status,
        ]));
    }
}
