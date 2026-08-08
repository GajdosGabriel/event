<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AnnouncementPlacement;
use App\Enums\AnnouncementVariant;
use App\Http\Controllers\Controller;
use App\Http\Requests\AnnouncementStoreRequest;
use App\Http\Resources\AnnouncementResource;
use App\Http\Resources\Traits\HasAllowedStatuses;
use App\Models\Announcement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Správa oznamov a bannerov. Bez policy — celá skupina je za `role:super-admin`.
 */
class AnnouncementController extends Controller
{
    use HasAllowedStatuses;

    public function index(Request $request): AnonymousResourceCollection
    {
        $announcements = Announcement::query()
            ->orderBy('placement')
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->paginate(20);

        return AnnouncementResource::collection($announcements)
            ->additional($this->formOptions($request));
    }

    public function store(AnnouncementStoreRequest $request): JsonResponse
    {
        $announcement = Announcement::create($request->announcementData());

        return (new AnnouncementResource($announcement))
            ->additional($this->formOptions($request))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Announcement $announcement): JsonResponse
    {
        return (new AnnouncementResource($announcement))
            ->additional($this->formOptions($request))
            ->response();
    }

    public function update(AnnouncementStoreRequest $request, Announcement $announcement): JsonResponse
    {
        $announcement->update($request->announcementData());

        return (new AnnouncementResource($announcement->refresh()))
            ->additional($this->formOptions($request))
            ->response();
    }

    public function destroy(Announcement $announcement): JsonResponse
    {
        $announcement->delete();

        return response()->json(null, 204);
    }

    /**
     * Číselníky pre formulár chodia v `meta` každej odpovede — front tak nedrží
     * popisky natvrdo a nemusí si ich doťahovať zvlášť.
     *
     * @return array<string, mixed>
     */
    private function formOptions(Request $request): array
    {
        return [
            'meta' => [
                'allowed_statuses' => $this->allowedStatuses($request),
                'placements' => AnnouncementPlacement::options(),
                'variants' => AnnouncementVariant::options(),
            ],
        ];
    }
}
