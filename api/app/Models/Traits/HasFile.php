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
        $loaded = $this->loadedImageFiles();

        if ($loaded !== null) {
            return $loaded->contains(fn (File $file) => $file->is_primary === true);
        }

        return $this->images()->where('is_primary', true)->exists();
    }

    public function getPrimaryImageAttribute(): array
    {
        $loaded = $this->loadedImageFiles();

        $image = $loaded !== null
            ? $loaded->first(fn (File $file) => $file->is_primary === true)
            : $this->images()->where('is_primary', true)->first();

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
        return $this->relationLoaded('files')
            ? $this->getRelation('files')
            : $this->files()->get();
    }

    /**
     * Images from the eager-loaded files relation, or null when files aren't loaded
     * (callers then fall back to a direct query). Keeps image accessors free of the
     * per-serialization N+1 they'd otherwise cause on list endpoints.
     */
    protected function loadedImageFiles(): ?Collection
    {
        if (! $this->relationLoaded('files')) {
            return null;
        }

        return $this->getRelation('files')
            ->filter(fn (File $file) => $file->type === FileType::IMAGE)
            ->values();
    }

    protected function defaultThumbImageUrl(): string
    {
        return $this->publicImageUrl('images/default.svg');
    }

    /**
     * Build a URL for a bundled default image, returning the first candidate
     * that exists (falling back to the last one either way).
     *
     * The images live in public/ — they're part of the code, so they ship with
     * every deploy. Do NOT move them onto the public disk: that directory is
     * gitignored and holds uploads only, so a fresh deploy (or the switch to S3)
     * leaves it without them and every fallback 404s.
     */
    protected function publicImageUrl(string ...$candidates): string
    {
        $last = 'images/default.svg';

        foreach ($candidates as $path) {
            $last = $path;

            if (is_file(public_path($path))) {
                return $this->bundledAssetUrl($path);
            }
        }

        return $this->bundledAssetUrl($last);
    }

    /**
     * URL of a file in public/. Can't use the url() helper: production serves
     * public/ under /api while APP_URL has no /api, so url('images/…') would
     * drop the prefix and hit the SPA instead. The public disk already knows
     * where public/ sits — its URL is that root plus '/storage'.
     */
    private function bundledAssetUrl(string $path): string
    {
        $diskUrl = (string) config('filesystems.disks.public.url');

        $root = $diskUrl !== ''
            ? dirname($diskUrl)
            : rtrim((string) config('app.url'), '/');

        return $root . '/' . ltrim($path, '/');
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
