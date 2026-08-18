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
        'sort_order' => 'integer',
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

    /**
     * URL adresy sa skladajú výhradne z toho, čo je v databáze — žiadne
     * overovanie cez `exists()`.
     *
     * Na S3 je `exists()` sieťová požiadavka (HEAD). Tri accessory v $appends,
     * každý s tromi vetvami, znamenali až deväť volaní na riadok — výpis
     * podujatí trval desiatky sekúnd, hoci samotný SQL dotaz desiatky
     * milisekúnd. Stĺpce `thumb`/`large` zapisuje GenerateFileVariantsJob až po
     * úspešnom uložení variantu, takže vyplnený stĺpec = existujúci objekt.
     */
    public function getOriginalFileUrlAttribute(): ?string
    {
        return $this->urlFor($this->originalPath())
            ?? $this->urlFor($this->large)
            ?? $this->urlFor($this->thumb);
    }

    public function getThumbImageUrlAttribute(): ?string
    {
        return $this->urlFor($this->thumb)
            ?? ($this->isImage() ? $this->urlFor($this->originalPath()) : null)
            ?? $this->urlFor($this->large);
    }

    public function getLargeImageUrlAttribute(): ?string
    {
        return $this->urlFor($this->large)
            ?? ($this->isImage() ? $this->urlFor($this->originalPath()) : null)
            ?? $this->urlFor($this->thumb);
    }

    /**
     * Originál, ktorý ešte leží na disku. GenerateFileVariantsJob po vytvorení
     * variantov pôvodný súbor maže, ale stĺpec `path` necháva — zmazanie si
     * značí do `meta`. Bez tejto kontroly by accessory vracali mŕtvu URL
     * namiesto toho, aby prepadli na `large`/`thumb`.
     */
    private function originalPath(): ?string
    {
        $deleted = data_get($this->meta, 'variant_generation.original_deleted') === true;

        return $deleted ? null : $this->path;
    }

    private function urlFor(?string $path): ?string
    {
        return $path ? $this->filesystem()->url($path) : null;
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

    // Prefix 'storage/' dáva zmysel len pre lokálny disk (mapuje sa na verejnú
    // URL cez storage:link); na S3 by z neho vznikol nezmyselný "storage/<s3-key>".
    public function toArray(): array
    {
        $data = parent::toArray();

        if (
            in_array($this->disk, ['local', 'public'], true)
            && !empty($data['path'])
            && is_string($data['path'])
            && !str_starts_with($data['path'], 'storage/')
        ) {
            $data['path'] = 'storage/' . ltrim($data['path'], '/');
        }

        return $data;
    }
}
