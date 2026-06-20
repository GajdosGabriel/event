<?php

namespace App\Jobs;

use App\Models\File;
use App\Services\Files\ImageVariantGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class GenerateFileVariantsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $fileId) {}

    public function handle(ImageVariantGenerator $generator): void
    {
        $file = File::withTrashed()->find($this->fileId);
        if (!$file || !$file->path) {
            return;
        }

        if ($file->trashed()) {
            return;
        }

        $disk = (string) ($file->disk ?: config('filesystems.default', 'public'));
        $variants = $generator->generate($disk, $file->path);

        $meta = is_array($file->meta) ? $file->meta : [];
        $meta['variant_generation'] = [
            'status' => ($variants['thumb'] || $variants['large']) ? 'generated' : 'not_available',
            'generated_at' => now()->toDateTimeString(),
        ];

        if (($variants['delete_original'] ?? false) === true && Storage::disk($disk)->exists($file->path)) {
            Storage::disk($disk)->delete($file->path);
            $meta['variant_generation']['original_deleted'] = true;
        }

        $file->update([
            'thumb' => $variants['thumb'] ?? $file->thumb,
            'large' => $variants['large'] ?? $file->large,
            'meta' => $meta,
        ]);
    }
}
