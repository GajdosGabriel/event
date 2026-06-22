<?php

namespace App\Models\Traits;

use App\Enums\FileType;
use App\Models\File;
use App\Services\Files\FileManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

trait HasFile
{
    public function files()
    {
        return $this->morphMany(File::class, 'fileable');
    }

    // Backward compatible alias used in older code paths.
    public function images()
    {
        return $this->files()->where('type', FileType::IMAGE->value);
    }

    public function getHasPrimaryImageAttribute(): bool
    {
        return $this->images()->where('is_primary', true)->exists();
    }

    public function getPrimaryImageAttribute(): array
    {
        $image = $this->images()
            ->where('is_primary', true)
            ->first();

        if ($image instanceof File) {
            return $this->formatPrimaryImage($image);
        }

        return $this->defaultPrimaryImage();
    }

    public function getThumbImageAttribute(): string
    {
        return $this->getPrimaryImageAttribute()['thumb'];
    }

    public function getFilesAttribute(): Collection
    {
        return $this->files()->get();
    }

    protected function defaultThumbImageUrl(): string
    {
        return url('images/foto.jpg');
    }

    protected function defaultPrimaryImage(): array
    {
        $fallback = $this->defaultThumbImageUrl();

        return [
            'thumb' => $fallback,
            'large' => $fallback,
            'original' => $fallback,
        ];
    }

    protected function formatPrimaryImage(File $image): array
    {
        $thumb = $image->thumb_image_url
            ?? $image->original_file_url
            ?? $this->defaultThumbImageUrl();

        $large = $image->large_image_url
            ?? $image->original_file_url
            ?? $thumb;

        $original = $image->original_file_url
            ?? $large;

        return [
            'thumb' => $thumb,
            'large' => $large,
            'original' => $original,
        ];
    }

    public function destroyImages(): void
    {
        $this->removeFiles(FileType::IMAGE);
    }

    /**
     * @param UploadedFile|array<int, UploadedFile> $files
     * @param array<string, mixed> $meta
     */
    public function addFiles(
        UploadedFile|array $files,
        FileType $type = FileType::FILE,
        ?string $disk = null,
        ?string $directory = null,
        bool $makePrimary = false,
        array $meta = []
    ): Collection {
        return app(FileManager::class)->storeForModel(
            model: $this,
            files: $files,
            type: $type,
            disk: $disk,
            directory: $directory,
            makePrimary: $makePrimary,
            meta: $meta
        );
    }

    public function setPrimaryFile(File $file): File
    {
        return app(FileManager::class)->setPrimary($file);
    }

    public function removeFile(File $file, bool $forceDelete = false): bool
    {
        return app(FileManager::class)->delete($file, $forceDelete);
    }

    public function removeFiles(?FileType $type = null, bool $forceDelete = false): int
    {
        return app(FileManager::class)->deleteByModel($this, $type, $forceDelete);
    }
}
