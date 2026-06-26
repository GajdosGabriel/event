<?php

namespace App\Services\Imports;

use App\Enums\ModelStatus;
use App\Enums\RegistrationSource;
use App\Models\Canal;
use App\Models\User;
use Illuminate\Support\Str;

class ImportedCanalManager
{
    public function resolveOrCreate(string $canalName, ?string $detectedName, string $sourceOrigin): Canal
    {
        // Fuzzy name lookup: AI-detected organizer name matched against existing canals
        if ($detectedName !== null) {
            $existing = $this->findByFuzzyName($detectedName);
            if ($existing instanceof Canal) {
                $this->ensureSystemOwnership($existing);
                return $existing->fresh();
            }
        }

        $existing = Canal::query()
            ->where('website', $sourceOrigin)
            ->first();

        if (! $existing) {
            $existing = Canal::query()
                ->where('slug', Str::slug($canalName))
                ->first();
        }

        if ($existing) {
            $updates = [];

            if ($detectedName !== null && $this->shouldUpgradeName($existing->name, $detectedName, $sourceOrigin)) {
                $updates['name'] = $detectedName;
            }

            if (empty($existing->website)) {
                $updates['website'] = $sourceOrigin;
            }

            if ($updates !== []) {
                $existing->update($updates);
            }

            $this->ensureSystemOwnership($existing);

            return $existing->fresh();
        }

        $canal = Canal::query()->create([
            'municipality_id' => 4209,
            'name' => $canalName,
            'title_prefix' => null,
            'title_suffix' => null,
            'body' => 'Systémom vytvorený canal pre importované eventy.',
            'published_at' => now(),
            'status' => ModelStatus::Published->value,
            'website' => $sourceOrigin,
            'registration_source' => RegistrationSource::IMPORT->value,
        ]);

        $this->ensureSystemOwnership($canal);

        return $canal->fresh();
    }

    public function systemOwner(): User
    {
        $superAdmin = User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', 'super-admin'))
            ->orderBy('id')
            ->first();

        if (! $superAdmin instanceof User) {
            throw new \RuntimeException('Super-admin user is required for imported canals.');
        }

        return $superAdmin;
    }

    private function ensureSystemOwnership(Canal $canal): void
    {
        $superAdmin = $this->systemOwner();

        $superAdmin->canals()->syncWithoutDetaching([
            $canal->id => [
                'is_owner' => true,
                'status' => ModelStatus::Published->value,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $superAdmin->canals()->updateExistingPivot($canal->id, [
            'is_owner' => true,
            'status' => ModelStatus::Published->value,
            'updated_at' => now(),
        ]);

        if ($superAdmin->canal_id === null) {
            $superAdmin->forceFill(['canal_id' => $canal->id])->save();
        }
    }

    private function findByFuzzyName(string $name): ?Canal
    {
        $slug = Str::slug($name);
        return Canal::query()
            ->where(function ($q) use ($name, $slug) {
                $q->where('slug', $slug)
                  ->orWhere('name', $name)
                  ->orWhere('name', 'like', '%' . addslashes(Str::limit($name, 100, '')) . '%');
            })
            ->orderByDesc('created_at')
            ->first();
    }

    private function shouldUpgradeName(string $currentName, string $detectedName, string $sourceOrigin): bool
    {
        $current = trim($currentName);
        $detected = trim($detectedName);

        if ($detected === '' || mb_strtolower($current) === mb_strtolower($detected)) {
            return false;
        }

        $host = preg_replace('/^https?:\/\//i', '', $sourceOrigin) ?? $sourceOrigin;
        $host = preg_replace('/^www\./i', '', $host) ?? $host;

        return mb_strtolower($current) === mb_strtolower($host);
    }
}
