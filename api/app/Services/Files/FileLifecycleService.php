<?php

namespace App\Services\Files;

use App\Enums\FileType;
use App\Models\File;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class FileLifecycleService
{
    public function setPrimary(File $file): File
    {
        return DB::transaction(function () use ($file) {
            File::query()
                ->where('fileable_type', $file->fileable_type)
                ->where('fileable_id', $file->fileable_id)
                ->where('type', $file->type instanceof FileType ? $file->type->value : $file->type)
                ->update(['is_primary' => false]);

            $file->is_primary = true;
            $file->save();

            return $file->refresh();
        });
    }

    public function delete(File $file, bool $forceDelete = false): bool
    {
        if ($forceDelete) {
            $this->deletePhysicalFile($file);
        }

        return $forceDelete ? (bool) $file->forceDelete() : (bool) $file->delete();
    }

    public function deleteByModel(Model $model, ?FileType $type = null, bool $forceDelete = false): int
    {
        $query = $model->files();
        if ($type) {
            $query->where('type', $type->value);
        }

        $deleted = 0;
        $query->get()->each(function (File $file) use ($forceDelete, &$deleted) {
            if ($this->delete($file, $forceDelete)) {
                $deleted++;
            }
        });

        return $deleted;
    }

    private function deletePhysicalFile(File $file): void
    {
        $disk = $file->disk ?: config('filesystems.default', 'public');

        if ($file->path) {
            Storage::disk($disk)->delete($file->path);
        }

        if ($file->thumb) {
            Storage::disk($disk)->delete($file->thumb);
        }

        if ($file->large) {
            Storage::disk($disk)->delete($file->large);
        }
    }
}
