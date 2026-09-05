<?php

namespace App\Services\Files;

use App\Enums\FileType;
use App\Models\File;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Skopíruje prílohy jedného záznamu na druhý — vrátane dát na disku.
 *
 * Prečo sa kopírujú aj bajty a nezdieľa sa cesta: zmazanie súboru maže fyzický
 * objekt podľa `path` ([FileLifecycleService::deletePhysicalFile]). Dva riadky
 * nad jednou cestou by znamenali, že zmazanie prílohy v jednom termíne rozbije
 * náhľad v ostatných — a to sa prejaví až mesiace po tom, čo to niekto spraví.
 *
 * Kópia si do `meta.copied_from` poznačí zdrojový riadok. Vďaka tomu vie
 * [EventSeriesManager] rozoznať „obrázok, ktorý sem prišiel zo série" od
 * obrázka, ktorý tam niekto nahral sám, a neprepísať ten druhý.
 */
class FileDuplicator
{
    /**
     * @param  array<int, FileType>  $types  Obmedzenie na typy; prázdne = všetko.
     * @return int Počet skutočne skopírovaných príloh.
     */
    public function copy(Model $source, Model $target, array $types = []): int
    {
        $query = $source->files();

        if ($types !== []) {
            $query->whereIn('type', array_map(fn (FileType $type) => $type->value, $types));
        }

        $copied = 0;

        foreach ($query->get() as $file) {
            if ($this->copyOne($file, $target) !== null) {
                $copied++;
            }
        }

        return $copied;
    }

    /**
     * Skopíruje jednu prílohu na iný záznam a vráti nový riadok.
     *
     * Verejné kvôli importu: keď sa z toho istého zdrojového odkazu ťahá
     * obrázok pre ďalšie podujatie, netreba ho nahrávať znova ani znova
     * prehnať generovaním variantov — stačí prekopírovať hotové objekty.
     * Bajty sa aj tak kopírujú, cesta sa nezdieľa (viď trieda).
     *
     * @param  array<string, mixed>  $attributes  Prepíše stĺpce nového riadku.
     */
    public function copyTo(File $file, Model $target, array $attributes = []): ?File
    {
        return $this->copyOne($file, $target, $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function copyOne(File $file, Model $target, array $attributes = []): ?File
    {
        $disk = $file->disk ?: config('filesystems.default', 'public');

        $paths = [];

        foreach (['path', 'thumb', 'large'] as $column) {
            $original = (string) ($file->{$column} ?? '');

            if ($original === '') {
                continue;
            }

            $copy = $this->copyObject($disk, $original);

            // Chýbajúci objekt na disku nie je dôvod zhodiť celé pridanie
            // termínu — riadok bez tejto varianty je stále použiteľný a
            // accessory na to majú fallback. Zapíše sa to a ide sa ďalej.
            if ($copy === null) {
                Log::warning('Prílohu sa nepodarilo skopírovať', [
                    'file_id' => $file->id,
                    'column' => $column,
                    'path' => $original,
                ]);

                continue;
            }

            $paths[$column] = $copy;
        }

        // Bez hlavnej cesty ani variantov by vznikol riadok, ktorý neukazuje
        // nikam. Taký je horší než chýbajúci obrázok.
        if ($paths === []) {
            return null;
        }

        $meta = is_array($file->meta) ? $file->meta : [];
        $meta['copied_from'] = $file->id;

        return File::create(array_merge([
            'fileable_id' => $target->getKey(),
            'fileable_type' => $target->getMorphClass(),
            'name' => $file->name,
            'original_name' => $file->original_name,
            'extension' => $file->extension,
            'size' => $file->size,
            'mime_type' => $file->mime_type,
            'disk' => $disk,
            'path' => $paths['path'] ?? ($paths['large'] ?? $paths['thumb']),
            'thumb' => $paths['thumb'] ?? null,
            'large' => $paths['large'] ?? null,
            'checksum' => $file->checksum,
            'type' => $file->type instanceof FileType ? $file->type->value : $file->type,
            'is_primary' => $file->is_primary,
            'sort_order' => $file->sort_order,
            'meta' => $meta,
        ], $attributes));
    }

    /** Nová cesta vedľa pôvodnej, s rovnakou príponou. Null, keď zdroj chýba. */
    private function copyObject(string $disk, string $path): ?string
    {
        $storage = Storage::disk($disk);

        if (! $storage->exists($path)) {
            return null;
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $directory = trim((string) pathinfo($path, PATHINFO_DIRNAME), '.');
        $name = Str::uuid()->toString().($extension !== '' ? '.'.$extension : '');

        $destination = $directory !== '' ? $directory.'/'.$name : $name;

        return $storage->copy($path, $destination) ? $destination : null;
    }
}
