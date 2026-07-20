<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\FileResource;
use App\Models\File;
use App\Services\Files\FileManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FileController extends Controller
{
    public function __construct(private readonly FileManager $fileManager) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', File::class);

        $request->validate([
            'fileable_type' => ['sometimes', 'string', 'in:canal,event,venue'],
            'fileable_id'   => ['sometimes', 'integer', 'min:1'],
            'search'        => ['sometimes', 'string', 'max:100'],
            'with_trashed'  => ['sometimes', 'boolean'],
        ]);

        $query = File::query();

        if ($request->boolean('with_trashed')) {
            $query->withTrashed();
        }

        if ($request->filled('fileable_type')) {
            $query->where('fileable_type', 'App\\Models\\' . ucfirst($request->fileable_type));
        }

        if ($request->filled('fileable_id')) {
            $query->where('fileable_id', $request->integer('fileable_id'));
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->string('search') . '%');
        }

        $files = $query->latest()->paginate(30);

        return FileResource::collection($files);
    }

    public function restore(string $id): JsonResponse
    {
        $file = File::withTrashed()->findOrFail($id);
        $this->authorize('restore', $file);

        $file->restore();
        $file->refresh();

        return response()->json(new FileResource($file));
    }

    /**
     * Soft delete — move the file to the trash. First stage of the two-step
     * removal: the file stays recoverable via restore() until it is force-deleted.
     *
     * A primary file is refused: it is the model's active image/attachment, so
     * removing it would leave the entity without one. The operator must promote
     * another file to primary first.
     */
    public function destroy(string $id): JsonResponse
    {
        $file = File::withTrashed()->findOrFail($id);
        $this->authorize('delete', $file);

        if ($file->trashed()) {
            abort(409, 'Súbor je už v koši. Na trvalé zmazanie použite „Zmazať natrvalo".');
        }

        if ($file->is_primary) {
            abort(422, 'Primárny súbor nie je možné zmazať. Najprv nastavte ako primárny iný súbor.');
        }

        $this->fileManager->delete($file, false);

        return response()->json(null, 204);
    }

    /**
     * Hard delete — permanently remove the DB row and the physical files.
     * Second stage: only a file already in the trash can be purged, which keeps
     * the destructive action behind a deliberate two-step flow.
     */
    public function forceDestroy(string $id): JsonResponse
    {
        $file = File::withTrashed()->findOrFail($id);
        $this->authorize('forceDelete', $file);

        if (! $file->trashed()) {
            abort(422, 'Najprv presuňte súbor do koša („Zmazať"), potom ho možno zmazať natrvalo.');
        }

        $this->fileManager->delete($file, true);

        return response()->json(null, 204);
    }
}
