<?php

namespace App\Services\Files;

use App\Jobs\GenerateFileVariantsJob;
use App\Enums\FileType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class UploadedFilePersister
{
    public function __construct(
        private readonly \App\Services\Files\FileDisplayNameResolver $fileDisplayNameResolver = new \App\Services\Files\FileDisplayNameResolver(),
    ) {}

    /**
     * @param Collection<int, UploadedFile> $uploadedFiles
     * @param array<string, mixed> $meta
     */
    public function store(
        Model $model,
        Collection $uploadedFiles,
        FileType $type,
        string $disk,
        string $storageDirectory,
        bool $makePrimary,
        array $meta
    ): Collection {
        return $uploadedFiles->values()->map(function (UploadedFile $uploadedFile, int $index) use ($model, $type, $disk, $storageDirectory, $makePrimary, $meta) {
            $path = $uploadedFile->store($storageDirectory, $disk);
            if (!$path) {
                throw new InvalidArgumentException('Failed to store uploaded file.');
            }

            $originalName = $uploadedFile->getClientOriginalName();
            $mimeType = $uploadedFile->getMimeType() ?: $uploadedFile->getClientMimeType();

            $file = $model->files()->create([
                'name' => $this->fileDisplayNameResolver->resolve($model, $originalName),
                'original_name' => $originalName,
                'extension' => strtolower((string) $uploadedFile->getClientOriginalExtension()),
                'size' => (int) $uploadedFile->getSize(),
                'mime_type' => $mimeType,
                'disk' => $disk,
                'path' => $path,
                'checksum' => hash_file('sha256', $uploadedFile->getRealPath()),
                'type' => $type->value,
                'is_primary' => $makePrimary && $index === 0,
                'meta' => $meta ?: null,
            ]);

            GenerateFileVariantsJob::dispatch((int) $file->id);

            return $file;
        });
    }
}
