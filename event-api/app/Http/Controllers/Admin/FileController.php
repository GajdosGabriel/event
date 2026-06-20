<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\FileResource;
use App\Models\File;
use App\Services\Files\FileManager;
use Illuminate\Http\JsonResponse;

class FileController extends Controller
{
    public function __construct(private readonly FileManager $fileManager) {}

    public function restore(string $id): JsonResponse
    {
        $file = File::withTrashed()->findOrFail($id);
        $this->authorize('restore', $file);

        $file->restore();
        $file->refresh();

        return response()->json(new FileResource($file));
    }

    public function destroy(string $id): JsonResponse
    {
        $file = File::withTrashed()->findOrFail($id);
        $this->authorize('forceDelete', $file);

        $this->fileManager->delete($file, true);

        return response()->json(null, 204);
    }
}
