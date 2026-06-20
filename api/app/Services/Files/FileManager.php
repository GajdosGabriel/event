<?php

namespace App\Services\Files;

use App\Enums\FileType;
use App\Models\Event;
use App\Models\File;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FileManager
{
    public function __construct(
        private readonly UploadedFilePersister $uploadedFilePersister = new UploadedFilePersister(),
        private readonly RemoteAttachmentPersister $remoteAttachmentPersister = new RemoteAttachmentPersister(),
        private readonly FileLifecycleService $fileLifecycleService = new FileLifecycleService(),
    ) {}

    /**
     * @param UploadedFile|array<int, UploadedFile> $files
     * @param array<string, mixed> $meta
     */
    public function storeForModel(
        Model $model,
        UploadedFile|array $files,
        FileType $type = FileType::FILE,
        ?string $disk = null,
        ?string $directory = null,
        bool $makePrimary = false,
        array $meta = []
    ): Collection {
        $disk = $this->resolveDisk($disk);

        $uploadedFiles = $this->normalizeFiles($files);
        if ($uploadedFiles->isEmpty()) {
            return collect();
        }

        $storageDirectory = $directory ?? $this->defaultDirectory($model, $type);

        return DB::transaction(function () use ($model, $uploadedFiles, $type, $disk, $storageDirectory, $makePrimary, $meta) {
            if ($makePrimary) {
                $model->files()->where('type', $type->value)->update(['is_primary' => false]);
            }

            $storedFiles = $this->uploadedFilePersister->store(
                model: $model,
                uploadedFiles: $uploadedFiles,
                type: $type,
                disk: $disk,
                storageDirectory: $storageDirectory,
                makePrimary: $makePrimary,
                meta: $meta,
            );

            $this->ensureModelPrimaryImage($model, $storedFiles, $makePrimary);

            return $storedFiles;
        });
    }

    /**
     * @param UploadedFile|array<int, UploadedFile> $files
     * @param array<string, mixed> $meta
     */
    public function storeForEvent(
        Event $event,
        UploadedFile|array $files,
        FileType $type = FileType::FILE,
        ?string $disk = null,
        ?string $directory = null,
        bool $makePrimary = false,
        array $meta = []
    ): Collection {
        return $this->storeForModel($event, $files, $type, $disk, $directory, $makePrimary, $meta);
    }

    /**
     * @param array<int, array<string, mixed>> $attachments
     * @param array<string, mixed> $meta
     */
    public function storeRemoteForModel(
        Model $model,
        array $attachments,
        FileType $type = FileType::FILE,
        ?string $disk = null,
        ?string $directory = null,
        bool $makePrimary = false,
        array $meta = []
    ): Collection {
        $disk = $this->resolveDisk($disk);

        $normalizedAttachments = $this->normalizeRemoteAttachments($attachments);
        if ($normalizedAttachments->isEmpty()) {
            return collect();
        }

        $storageDirectory = $directory ?? $this->defaultDirectory($model, $type);

        return DB::transaction(function () use ($model, $normalizedAttachments, $type, $disk, $storageDirectory, $makePrimary, $meta) {
            if ($makePrimary) {
                $model->files()->where('type', $type->value)->update(['is_primary' => false]);
            }

            $storedFiles = $this->remoteAttachmentPersister->store(
                model: $model,
                attachments: $normalizedAttachments,
                type: $type,
                disk: $disk,
                storageDirectory: $storageDirectory,
                makePrimary: $makePrimary,
                meta: $meta,
            )->unique('id')->values();

            $this->ensureModelPrimaryImage($model, $storedFiles, $makePrimary);

            return $storedFiles;
        });
    }

    /**
     * @param array<int, array<string, mixed>> $attachments
     * @param array<string, mixed> $meta
     */
    public function storeRemoteForEvent(
        Event $event,
        array $attachments,
        FileType $type = FileType::FILE,
        ?string $disk = null,
        ?string $directory = null,
        bool $makePrimary = false,
        array $meta = []
    ): Collection {
        return $this->storeRemoteForModel($event, $attachments, $type, $disk, $directory, $makePrimary, $meta);
    }

    public function setPrimary(File $file): File
    {
        return $this->fileLifecycleService->setPrimary($file);
    }

    public function delete(File $file, bool $forceDelete = false): bool
    {
        return $this->fileLifecycleService->delete($file, $forceDelete);
    }

    public function deleteByModel(Model $model, ?FileType $type = null, bool $forceDelete = false): int
    {
        return $this->fileLifecycleService->deleteByModel($model, $type, $forceDelete);
    }

    private function defaultDirectory(Model $model, FileType $type): string
    {
        $modelName = strtolower(class_basename($model));

        return $modelName . '/' . $model->getKey() . '/' . $type->value;
    }

    private function resolveDisk(?string $disk): string
    {
        return $disk ?: config('filesystems.default', 'public');
    }

    /**
     * @param UploadedFile|array<int, UploadedFile> $files
     * @return Collection<int, UploadedFile>
     */
    private function normalizeFiles(UploadedFile|array $files): Collection
    {
        $array = is_array($files) ? $files : [$files];

        return collect($array)
            ->filter(fn ($file) => $file instanceof UploadedFile)
            ->values();
    }

    /**
     * @param array<int, array<string, mixed>> $attachments
     * @return Collection<int, array<string, mixed>>
     */
    private function normalizeRemoteAttachments(array $attachments): Collection
    {
        return collect($attachments)
            ->filter(
                fn ($attachment) => is_array($attachment)
                    && isset($attachment['url'])
                    && filter_var($attachment['url'], FILTER_VALIDATE_URL)
            )
            ->values();
    }

    private function ensureModelPrimaryImage(
        Model $model,
        Collection $storedFiles,
        bool $makePrimary
    ): void {
        if ($makePrimary) {
            return;
        }

        $image = $storedFiles->first(fn (File $file) => $this->isImageFile($file));
        if (! $image instanceof File) {
            return;
        }

        if ($this->modelHasPrimaryImage($model)) {
            return;
        }

        $this->fileLifecycleService->setPrimary($image);
    }

    private function modelHasPrimaryImage(Model $model): bool
    {
        return $model->files()
            ->where('is_primary', true)
            ->where('mime_type', 'like', 'image/%')
            ->exists();
    }

    private function isImageFile(File $file): bool
    {
        return is_string($file->mime_type) && str_starts_with(strtolower($file->mime_type), 'image/');
    }
}
