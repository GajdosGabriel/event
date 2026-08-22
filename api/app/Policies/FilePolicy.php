<?php

namespace App\Policies;

use App\Models\File;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class FilePolicy
{
    /**
     * Rozlúsknuté `fileable` záznamy v rámci požiadavky. Nad jedným súborom sa
     * policy volá štyrikrát (view/update/delete/restore), tak nech to nie sú
     * štyri dotazy.
     *
     * @var array<string, ?Model>
     */
    private array $fileableCache = [];

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, File $file): bool
    {
        return $this->canManageFileable($user, $file);
    }

    public function create(User $user): bool
    {
        // Fileable-level authorization is handled in the controller.
        return true;
    }

    public function update(User $user, File $file): bool
    {
        return $this->canManageFileable($user, $file);
    }

    public function delete(User $user, File $file): bool
    {
        return $this->canManageFileable($user, $file);
    }

    public function restore(User $user, File $file): bool
    {
        return $this->canManageFileable($user, $file);
    }

    public function forceDelete(User $user, File $file): bool
    {
        return false;
    }

    private function canManageFileable(User $user, File $file): bool
    {
        $fileable = $this->resolveFileable($file);

        if (!$fileable) {
            return false;
        }

        return $user->can('update', $fileable);
    }

    /**
     * Vzťah zámerne neťaháme cez `$file->fileable` — lazy loading je mimo
     * produkcie vypnutý a výpis súborov ho nemá (a ani nemôže mať) načítaný:
     * eager load morphTo by vo FileResource vyliahol celý Event aj s jeho
     * `$appends`. Preto dotaz naslepo, s výsledkom v pamäti a bez `setRelation`,
     * aby sa vzťah nedostal do serializovanej odpovede.
     */
    private function resolveFileable(File $file): ?Model
    {
        if ($file->relationLoaded('fileable')) {
            return $file->getRelation('fileable');
        }

        if (!$file->fileable_type || !$file->fileable_id) {
            return null;
        }

        $key = $file->fileable_type . ':' . $file->fileable_id;

        if (!array_key_exists($key, $this->fileableCache)) {
            // Aj vymazaný záznam — súbory archivovaného podujatia sa vo výpise
            // zobrazujú a majú mať rovnaké práva ako jeho živá verzia.
            $this->fileableCache[$key] = $file->fileable()->withTrashed()->first();
        }

        return $this->fileableCache[$key];
    }
}
