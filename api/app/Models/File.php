<?php

namespace App\Models;

use App\Enums\FileType;
use App\Casts\StringLength250;
use App\Models\Traits\HasCommonFilters;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class File extends Model
{
    use SoftDeletes, HasCommonFilters;

    protected $fillable = [
        'fileable_id',
        'fileable_type',
        'name',
        'original_name',
        'extension',
        'size',
        'mime_type',
        'disk',
        'path',
        'thumb',
        'large',
        'checksum',
        'type',
        'is_primary',
        'sort_order',
        'meta',
    ];

    protected $casts = [
        'name' => StringLength250::class,
        'size' => 'integer',
        'is_primary' => 'boolean',
        'meta' => 'array',
        'type' => FileType::class,
    ];

    protected $appends = [
        'original_file_url',
        'thumb_image_url',
        'large_image_url',
    ];

    public function fileable()
    {
        return $this->morphTo();
    }

    public function getOriginalFileUrlAttribute(): ?string
    {
        $filesystem = $this->filesystem();

        if ($this->path && $filesystem->exists($this->path)) {
            return $filesystem->url($this->path);
        }

        if ($this->large && $filesystem->exists($this->large)) {
            return $filesystem->url($this->large);
        }

        if ($this->thumb && $filesystem->exists($this->thumb)) {
            return $filesystem->url($this->thumb);
        }

        return null;
    }

    public function getThumbImageUrlAttribute(): ?string
    {
        $filesystem = $this->filesystem();

        if ($this->thumb && $filesystem->exists($this->thumb)) {
            return $filesystem->url($this->thumb);
        }

        if ($this->isImage() && $this->path && $filesystem->exists($this->path)) {
            return $filesystem->url($this->path);
        }

        if ($this->large && $filesystem->exists($this->large)) {
            return $filesystem->url($this->large);
        }

        return null;
    }

    public function getLargeImageUrlAttribute(): ?string
    {
        $filesystem = $this->filesystem();

        if ($this->large && $filesystem->exists($this->large)) {
            return $filesystem->url($this->large);
        }

        if ($this->isImage() && $this->path && $filesystem->exists($this->path)) {
            return $filesystem->url($this->path);
        }

        if ($this->thumb && $filesystem->exists($this->thumb)) {
            return $filesystem->url($this->thumb);
        }

        return null;
    }

    private function isImage(): bool
    {
        return is_string($this->mime_type) && str_starts_with(strtolower($this->mime_type), 'image/');
    }

    private function filesystem(): \Illuminate\Filesystem\FilesystemAdapter
    {
        $disk = (string) ($this->disk ?? config('filesystems.default', 'public'));
        /** @var \Illuminate\Filesystem\FilesystemAdapter $filesystem */
        $filesystem = Storage::disk($disk);

        return $filesystem;
    }

    // Pridá prefix 'storage/' k ceste, pokud není již přítomen, aby bylo zajištěno správné URL pro přístup k souboru
    public function toArray(): array
    {
        $data = parent::toArray();

        if (!empty($data['path']) && is_string($data['path']) && !str_starts_with($data['path'], 'storage/')) {
            $data['path'] = 'storage/' . ltrim($data['path'], '/');
        }

        return $data;
    }
}
