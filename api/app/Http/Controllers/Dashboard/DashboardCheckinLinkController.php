<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\CheckinLink;
use App\Models\Event;
use App\Repositories\Contracts\EventRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Odkazy na skener pre dobrovoľníkov (CheckinLink) — vytvorenie, prehľad
 * a zrušenie. Odkaz púšťa ľudí na podujatie v mene organizátora, preto ho
 * spravuje len ten, kto smie podujatie upravovať (`event.update` + policy).
 */
class DashboardCheckinLinkController extends Controller
{
    /** Strop aktívnych odkazov na podujatie — viac vchodov sa nevidí. */
    public const MAX_ACTIVE = 20;

    public function __construct(private readonly EventRepository $events) {}

    public function index(int|string $event): JsonResponse
    {
        $event = $this->editableEvent($event);

        $links = CheckinLink::query()
            ->where('event_id', $event->id)
            ->active()
            ->orderBy('id')
            ->get();

        return response()->json(['data' => $links->map(fn (CheckinLink $link) => $this->present($link))->values()]);
    }

    public function store(Request $request, int|string $event): JsonResponse
    {
        $event = $this->editableEvent($event);
        $data = $request->validate(['label' => ['nullable', 'string', 'max:80']]);

        if (CheckinLink::query()->where('event_id', $event->id)->active()->count() >= self::MAX_ACTIVE) {
            abort(422);
        }

        $link = CheckinLink::query()->create([
            'event_id' => $event->id,
            'token' => CheckinLink::newToken(),
            'label' => filled($data['label'] ?? null) ? trim($data['label']) : null,
            'created_by' => $request->user()->id,
            'expires_at' => CheckinLink::expiryFor($event),
        ]);

        return response()->json(['data' => $this->present($link)], 201);
    }

    public function destroy(int|string $event, int $link): JsonResponse
    {
        $event = $this->editableEvent($event);

        $model = CheckinLink::query()->where('event_id', $event->id)->findOrFail($link);
        $model->forceFill(['revoked_at' => now()])->save();

        return response()->json(null, 204);
    }

    /** Podujatie z dashboardového scope a právo ho upravovať. */
    private function editableEvent(int|string $id): Event
    {
        $event = $this->events->dashboardShow($id);
        $this->authorize('update', $event);

        return $event;
    }

    /** @return array<string, mixed> */
    private function present(CheckinLink $link): array
    {
        return [
            'id' => $link->id,
            'label' => $link->label,
            'url' => $link->url(),
            'expires_at' => $link->expires_at,
            'last_used_at' => $link->last_used_at,
            'created_at' => $link->created_at,
        ];
    }
}
