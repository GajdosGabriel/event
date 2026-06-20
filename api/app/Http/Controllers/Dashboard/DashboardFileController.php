<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\FileType;
use App\Http\Controllers\Controller;
use App\Http\Requests\FileStoreRequest;
use App\Http\Requests\FileUpdateRequest;
use App\Http\Resources\FileResource;
use App\Models\Canal;
use App\Models\Event;
use App\Models\File;
use App\Models\Venue;
use App\Services\Files\FileManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DashboardFileController extends Controller
{
    private const FILEABLE_MAP = [
        'canal' => Canal::class,
        'event' => Event::class,
        'venue' => Venue::class,
    ];

    public function __construct(private readonly FileManager $fileManager) {}

    /**
     * List files for a fileable model.
     *
     * Query params: fileable_type (canal|event|venue), fileable_id
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', File::class);

        $request->validate([
            'fileable_type' => ['required', 'string', 'in:canal,event,venue'],
            'fileable_id'   => ['required', 'integer', 'min:1'],
        ]);

        $modelClass = self::FILEABLE_MAP[$request->fileable_type];
        $model = $modelClass::findOrFail($request->fileable_id);

        $this->authorize('view', $model);

        $files = $model->files()->paginate(20);

        return FileResource::collection($files)
            ->additional([
                'meta' => [
                    'permissions' => [
                        'create' => $request->user()?->can('update', $model) ?? false,
                    ],
                ],
            ]);
    }

    public function show(string $id): JsonResponse
    {
        $file = File::findOrFail($id);
        $this->authorize('view', $file);

        return response()->json(new FileResource($file));
    }

    public function store(FileStoreRequest $request): JsonResponse
    {
        $modelClass = self::FILEABLE_MAP[$request->fileable_type];
        $model = $modelClass::findOrFail($request->fileable_id);

        $this->authorize('update', $model);

        $type = FileType::tryFrom($request->input('type', FileType::IMAGE->value)) ?? FileType::IMAGE;

        $files = $model->addFiles(
            files: $request->file('files', []),
            type: $type,
            disk: $request->input('disk', config('filesystems.default', 'public')),
            makePrimary: $request->boolean('make_primary', false),
        );

        return response()->json(FileResource::collection($files), 201);
    }

    public function update(string $id, FileUpdateRequest $request): JsonResponse
    {
        $file = File::findOrFail($id);
        $this->authorize('update', $file);

        if ($request->has('is_primary') && $request->boolean('is_primary')) {
            $file = $this->fileManager->setPrimary($file);
        } else {
            $file->update($request->only(['is_primary', 'meta']));
            $file->refresh();
        }

        return response()->json(new FileResource($file));
    }

    public function destroy(string $id): JsonResponse
    {
        $file = File::findOrFail($id);
        $this->authorize('delete', $file);

        $this->fileManager->delete($file, false);

        return response()->json(null, 204);
    }

    public function restore(string $id): JsonResponse
    {
        $file = File::withTrashed()->findOrFail($id);
        $this->authorize('restore', $file);

        $file->restore();
        $file->refresh();

        return response()->json(new FileResource($file));
    }
}
