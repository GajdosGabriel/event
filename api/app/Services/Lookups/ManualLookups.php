<?php

namespace App\Services\Lookups;

use App\Jobs\RunManualLookup;
use App\Models\Canal;
use App\Models\CanalEmailSearch;
use App\Models\CoordinateLookup;
use App\Services\Canals\CanalEmailFinder;
use App\Services\Geocoding\PlaceCoordinateBackfill;
use App\Services\Profiles\ProfileEnricher;
use App\Support\LookupInputs;
use Illuminate\Support\Facades\Cache;

/**
 * Stav dopĺňania údajov kanála a jeho ručné spustenie z adminu.
 *
 * Tri kroky v poradí, v akom sa dopĺňajú automaticky: profil (web, adresa,
 * kontakty cez AI) → poloha (z adresy či obce cez Nominatim) → e-mail
 * (podujatia, web, AI). Admin vidí, čo sa naposledy hľadalo a prečo sa
 * nehľadá, a krok vie spustiť znova aj bez nových podkladov.
 */
class ManualLookups
{
    public const PROFILE = 'profile';

    public const COORDINATES = 'coordinates';

    public const EMAIL = 'email';

    public const KINDS = [self::PROFILE, self::COORDINATES, self::EMAIL];

    /** Ako dlho sa krok po spustení ukazuje ako bežiaci, ak job nedobehne. */
    private const RUNNING_TTL = 900;

    public function __construct(
        private readonly ProfileEnricher $profiles,
        private readonly PlaceCoordinateBackfill $coordinates,
        private readonly CanalEmailFinder $emails,
    ) {}

    /** Prečo sa krok spustiť nedá; null = dá. Kód sa prekladá v UI. */
    public function blocker(Canal $canal, string $kind): ?string
    {
        return match ($kind) {
            self::PROFILE => match (true) {
                ! $this->profiles->enabled() => 'disabled',
                ! $this->profiles->eligible($canal, ignoreDelay: true) => 'not_eligible',
                $this->profiles->missing($canal) === [] && ! $this->coordinates->needsLookup($canal) => 'complete',
                ! $this->profiles->budgetAvailable() => 'budget',
                default => null,
            },
            self::COORDINATES => $this->coordinates->needsLookup($canal) ? null : 'has_coordinates',
            self::EMAIL => $this->emails->manualSkipReason($canal),
        };
    }

    public function start(Canal $canal, string $kind): void
    {
        Cache::put($this->runningKey($canal->id, $kind), now()->toIso8601String(), self::RUNNING_TTL);

        RunManualLookup::dispatch($canal->id, $kind);
    }

    public function finished(int $canalId, string $kind): void
    {
        Cache::forget($this->runningKey($canalId, $kind));
    }

    /** @return array<string, mixed> */
    public function status(Canal $canal): array
    {
        $profile = $this->profiles->audit($canal);
        $coordinates = CoordinateLookup::for($canal);
        $email = CanalEmailSearch::query()->where('canal_id', $canal->id)->first();

        return [
            self::PROFILE => [
                'missing' => $this->profiles->missing($canal),
                'completedAt' => $profile?->completed_at?->toIso8601String(),
                'attempts' => (int) ($profile?->attempts ?? 0),
                'retryAt' => $profile?->retry_at?->toIso8601String(),
                'lastError' => $profile?->last_error,
                'changedFields' => array_keys((array) ($profile?->changes ?? [])),
                // Dokončené s inými podkladmi — najbližší beh ho otvorí znova.
                'inputsChanged' => $profile?->completed_at !== null && $profile->inputs_hash !== null
                    && $profile->inputs_hash !== LookupInputs::profile($canal),
                'blocker' => $this->blocker($canal, self::PROFILE),
                'running' => $this->running($canal->id, self::PROFILE),
            ],
            self::COORDINATES => [
                'coordinatesSource' => $canal->coordinates_source,
                'missing' => $this->coordinates->needsLookup($canal),
                'lookedUpAt' => $coordinates?->looked_up_at?->toIso8601String(),
                'foundSource' => $coordinates?->source,
                'inputsChanged' => $coordinates !== null && $coordinates->inputs_hash !== LookupInputs::coordinates($canal),
                'blocker' => $this->blocker($canal, self::COORDINATES),
                'running' => $this->running($canal->id, self::COORDINATES),
            ],
            self::EMAIL => [
                'result' => $email?->result,
                'foundVia' => $email?->found_via,
                'searchedAt' => $email?->searched_at?->toIso8601String(),
                'retryAt' => $email?->retry_at?->toIso8601String(),
                'attempts' => (int) ($email?->attempts ?? 0),
                'lastError' => $email?->last_error,
                // Prečo ho nehľadá automatické hľadanie (app:canal-emails-find).
                'autoSkipReason' => $this->emails->skipReason($canal),
                'blocker' => $this->blocker($canal, self::EMAIL),
                'running' => $this->running($canal->id, self::EMAIL),
            ],
        ];
    }

    private function running(int $canalId, string $kind): bool
    {
        return Cache::has($this->runningKey($canalId, $kind));
    }

    private function runningKey(int $canalId, string $kind): string
    {
        return "manual-lookup:{$canalId}:{$kind}";
    }
}
