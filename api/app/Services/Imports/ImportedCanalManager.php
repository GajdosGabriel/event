<?php

namespace App\Services\Imports;

use App\Enums\CanalIdentityMode;
use App\Enums\ModelStatus;
use App\Enums\RegistrationSource;
use App\Models\Canal;
use App\Models\User;
use Illuminate\Support\Str;

class ImportedCanalManager
{
    public function __construct(
        private readonly ImportedProfileDescriber $describer = new ImportedProfileDescriber(),
    ) {}

    public function resolveOrCreate(string $canalName, ?string $detectedName, string $sourceOrigin): Canal
    {
        // Fuzzy name lookup: AI-detected organizer name matched against existing canals
        if ($detectedName !== null) {
            $existing = $this->findByFuzzyName($detectedName);
            if ($existing instanceof Canal) {
                $this->ensureSystemOwnership($existing);
                return $existing->fresh();
            }

            // A named organizer that has no existing match gets its own canal by
            // slug. It must never fall into the "website == source origin" bucket
            // below: that bucket is shared by every event from this scraper with
            // no detected name, and would otherwise pull unrelated organizers in.
            $existing = Canal::query()
                ->where('slug', Str::slug($canalName))
                ->first();
        } else {
            // Žiadny organizátor sa nenašiel: podujatie patrí do jedného
            // zberného kanála na zdroj, pomenovaného po hostovi (vyveska.sk).
            // Hľadá sa výlučne podľa slugu toho mena — $canalName je v tejto
            // vetve vždy hostLabel(), takže zhoda je deterministická.
            //
            // Pôvodné `->where('website', $sourceOrigin)` bola tichá zámena:
            // website == origin zdroja nesie KAŽDÝ importovaný kanál z toho
            // scrapera, teda aj každý reálny organizátor. ->first() preto
            // vrátil kanál s najnižším id a všetky nepriradené podujatia sa
            // nalepili naň — na tkkbs.sk skončilo 21 podujatí (Godzone tour,
            // HONTfest, Lurdy…) pod kanálom „Františkáni“, na ecav.sk pod
            // „Rada pre ekumenizmus Bratislavskej arcidiecézy“.
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
            'body' => $this->describer->forCanal($detectedName ?? $canalName, $sourceOrigin),
            'published_at' => now(),
            'status' => ModelStatus::Published->value,
            'website' => $sourceOrigin,
            'registration_source' => RegistrationSource::IMPORT->value,
            // Importované kanály nikdy nie sú osobné — patria organizátorovi
            // (farnosť, mesto, klub), nie fyzickej osobe, ktorá sa registrovala.
            'identity_mode' => CanalIdentityMode::Organization->value,
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
        $canal = Canal::query()
            ->where(function ($q) use ($name, $slug) {
                $q->where('slug', $slug)
                  ->orWhere('name', $name)
                  ->orWhere('name', 'like', '%' . addslashes(Str::limit($name, 100, '')) . '%');
            })
            ->orderByDesc('created_at')
            ->first();

        if ($canal instanceof Canal) {
            return $canal;
        }

        // LIKE '%nový názov%' zaberie len vtedy, keď je existujúci názov dlhší.
        // Keď AI pridá alias v zátvorke — "Spoločenstvo evanjelických žien"
        // → "… žien (SEŽ)" — je dlhší ten nový a zhoda zlyhá, takže vznikne
        // druhý kanál pre tú istú organizáciu. Porovnanie holých slugov to
        // podchytí v oboch smeroch (aj "ACN – Pomoc" vs "ACN .- Pomoc").
        return ImportedNameMatcher::firstByBaseName(Canal::query(), $name);
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
