<?php

namespace App\Services\Imports;

use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\DuplicateDecision;
use App\Models\Municipality;
use App\Models\Venue;
use App\Services\OpenAI\ChatGPT;
use App\Support\NationwideCoordinates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Posúdi, či kanál/miesto, ktoré import práve chce založiť, nie je duplicitou
 * už existujúceho záznamu.
 *
 * Vrstvy (od lacnej po drahú):
 *  1. ImportedNameMatcher a presné zhody ostávajú v managerech — sem sa
 *     dostane až to, čo tam neprešlo.
 *  2. Deterministický výber kandidátov: podobné tokeny názvu, pri mieste
 *     v tej istej obci a/alebo na rovnakých súradniciach, pri kanáli rovnaký
 *     unikátny web.
 *  3. AI (len keď je zapnutá) rozhoduje medzi kandidátmi — nikdy nehľadá sama.
 *
 * Zlúčenie je ťažko vratné, preto sa zlúči len pri vysokej istote. Pri strednej
 * istote vznikne nový záznam a rozhodnutie ostane v `duplicate_decisions` ako
 * `uncertain` na kontrolu človekom. Každé rozhodnutie je zároveň cache — rovnaký
 * vstup sa AI nepýta dva razy.
 */
class DuplicateResolver
{
    /** Najviac toľko kandidátov sa posudzuje AI na jeden vstup. */
    private const MAX_CANDIDATES = 3;

    /** Do akej vzdialenosti (m) sa dve miesta s podobným názvom považujú za to isté. */
    private const SAME_PLACE_METERS = 120;

    /**
     * Slová, ktoré nenesú identitu — bez nich by "Rímskokatolícka farnosť X"
     * a "Farnosť X" mali nízku zhodu tokenov.
     */
    private const STOPWORDS = [
        'rimskokatolicka', 'rimskokatolicky', 'rimskokatolickej', 'obcianske',
        'zdruzenie', 'organizacia', 'spolocnost',
    ];

    /** @var array<int, int> id rozhodnutí `uncertain` z posledného posúdenia, čakajúce na id nového záznamu */
    private array $pendingUncertain = [];

    public function __construct(
        private readonly ChatGPT $chatGPT = new ChatGPT,
    ) {}

    public function findCanal(string $name, ?string $website = null): ?Canal
    {
        $inputKey = ImportedNameMatcher::baseSlug($name);
        $this->pendingUncertain = [];

        if ($inputKey === '') {
            return null;
        }

        $newTokens = self::tokens($name);
        $newHost = self::host($website);
        $candidates = [];

        foreach (Canal::query()->select(['id', 'name', 'website', 'municipality_id'])->get() as $canal) {
            $tokens = self::tokens((string) $canal->name);
            $sharesName = self::similarTokens($newTokens, $tokens, 0.6);
            $sameHost = $newHost !== null && self::host($canal->website) === $newHost;

            if (! $sharesName && ! $sameHost) {
                continue;
            }

            $candidates[] = [
                'model' => $canal,
                // Rovnaký web je dôkaz, len keď ho nemá viac kanálov — webom
                // zdroja (ecav.sk) sa kedysi označoval každý importovaný kanál.
                'sure' => $sameHost && $sharesName && $this->hostIsUnique($newHost),
                'score' => $sharesName ? self::jaccard($newTokens, $tokens) : 0.0,
            ];
        }

        return $this->decide(
            DuplicateDecision::ENTITY_CANAL,
            $inputKey,
            $name,
            $candidates,
            ['Názov' => $name, 'Web' => $website],
            fn (Canal $canal): array => [
                'Názov' => (string) $canal->name,
                'Web' => $canal->website,
                'Obec' => $this->municipalityName($canal->municipality_id),
            ],
        );
    }

    /**
     * @param  array<int, int>|null  $visibleToCanalIds  ručné zadanie: brať len zverejnené miesta
     *                                                    a miesta týchto kanálov (koncept cudzieho
     *                                                    kanála sa nesmie potichu pripojiť); null =
     *                                                    import, bez obmedzenia
     */
    public function findVenue(string $name, ?int $villageId, ?string $street = null, ?float $latitude = null, ?float $longitude = null, ?array $visibleToCanalIds = null): ?Venue
    {
        $this->pendingUncertain = [];
        $inputKey = ImportedNameMatcher::baseSlug($name, $villageId !== null ? $this->municipalityName($villageId) : null);

        // Bez obce nie je s čím porovnávať — názvy miest sa opakujú po celom
        // Slovensku („Kostol Nanebovzatia Panny Márie").
        if ($inputKey === '' || $villageId === null) {
            return null;
        }

        $baseSlug = $inputKey;
        $inputKey .= '@'.$villageId;
        $village = $this->municipalityName($villageId);
        $newTokens = self::tokens($name);
        $hasPoint = $latitude !== null && $longitude !== null && ! NationwideCoordinates::needsLookup($latitude, $longitude);
        $candidates = [];

        $venues = Venue::query()
            ->where(fn ($q) => $q->whereNull('category')->orWhere('category', '!=', 'fallback'))
            ->where(fn ($q) => $q->whereNull('village_id')->orWhere('village_id', $villageId))
            ->when($visibleToCanalIds !== null, fn ($q) => $q->where(fn ($visible) => $visible
                ->where('status', ModelStatus::Published->value)
                ->orWhereHas('canals', fn ($canals) => $canals->whereIn('canals.id', $visibleToCanalIds))))
            ->select(['id', 'name', 'street', 'latitude', 'longitude', 'village_id'])
            ->get();

        foreach ($venues as $venue) {
            $tokens = self::tokens((string) $venue->name);
            $distance = $hasPoint && $venue->latitude !== null && $venue->longitude !== null
                && ! NationwideCoordinates::needsLookup($venue->latitude, $venue->longitude)
                ? self::distanceMeters($latitude, $longitude, (float) $venue->latitude, (float) $venue->longitude)
                : null;

            $sharesName = self::similarTokens($newTokens, $tokens, 0.5);
            $samePlace = $distance !== null && $distance <= self::SAME_PLACE_METERS && array_intersect($newTokens, $tokens) !== [];

            if (! $sharesName && ! $samePlace) {
                continue;
            }

            $candidates[] = [
                'model' => $venue,
                // Rovnaký holý názov v tej istej obci je to isté miesto — import
                // túto zhodu chytá už v manageri, ručné zadanie až tu.
                'sure' => (int) $venue->village_id === $villageId
                    && ImportedNameMatcher::baseSlug((string) $venue->name, $village) === $baseSlug,
                'reason' => 'Rovnaký názov v tej istej obci.',
                'score' => self::jaccard($newTokens, $tokens),
                'distance' => $distance,
            ];
        }

        return $this->decide(
            DuplicateDecision::ENTITY_VENUE,
            $inputKey,
            $name,
            $candidates,
            ['Názov' => $name, 'Obec' => $village, 'Ulica' => $street],
            fn (Venue $venue, array $candidate): array => [
                'Názov' => (string) $venue->name,
                'Obec' => $village,
                'Ulica' => $venue->street,
                'Vzdialenosť súradníc od nového záznamu' => isset($candidate['distance']) ? round($candidate['distance']).' m' : null,
            ],
        );
    }

    /**
     * Doplní id nového záznamu k rozhodnutiam `uncertain`, ktoré vznikli pri
     * jeho posúdení — kontrolujúci človek tak vie, ktorý záznam je podozrivý.
     */
    public function markCreated(Model $created): void
    {
        if ($this->pendingUncertain !== []) {
            DuplicateDecision::query()->whereIn('id', $this->pendingUncertain)->update(['created_id' => $created->getKey()]);
        }

        $this->pendingUncertain = [];
    }

    /**
     * @param  array<int, array{model: Model, sure: bool, score: float, distance?: float|null, reason?: string}>  $candidates
     * @param  array<string, string|null>  $newFields
     * @param  \Closure(Model, array): array<string, string|null>  $existingFields
     */
    private function decide(string $entity, string $inputKey, string $name, array $candidates, array $newFields, \Closure $existingFields): ?Model
    {
        if ($candidates === []) {
            return null;
        }

        usort($candidates, fn (array $a, array $b): int => [(int) $b['sure'], $b['score']] <=> [(int) $a['sure'], $a['score']]);

        foreach (array_slice($candidates, 0, self::MAX_CANDIDATES) as $candidate) {
            /** @var Model $model */
            $model = $candidate['model'];

            $known = DuplicateDecision::query()
                ->where('entity', $entity)
                ->where('input_key', $inputKey)
                ->where('candidate_id', $model->getKey())
                ->latest('id')
                ->first();

            if ($known instanceof DuplicateDecision) {
                if ($known->decision === DuplicateDecision::SAME) {
                    return $model;
                }

                continue;
            }

            if ($candidate['sure']) {
                $this->record($entity, $inputKey, $name, $model, DuplicateDecision::SAME, 0.95, DuplicateDecision::SOURCE_RULE, $candidate['reason'] ?? 'Rovnaký unikátny web a podobný názov.');

                return $model;
            }

            if (! (bool) config('services.imports.dedupe_with_ai', false)) {
                $this->record($entity, $inputKey, $name, $model, DuplicateDecision::UNCERTAIN, null, DuplicateDecision::SOURCE_RULE, 'Podobný názov; AI kontrola je vypnutá.');

                continue;
            }

            try {
                $verdict = $this->chatGPT->judgeDuplicate(
                    $entity,
                    $newFields,
                    $existingFields($model, $candidate),
                );
            } catch (\Throwable $e) {
                // Výpadok AI nesmie zablokovať import — záznam sa založí a pri
                // ďalšom behu sa posúdi znova (rozhodnutie sa nezapísalo).
                Log::warning('Posúdenie duplicity zlyhalo.', ['entity' => $entity, 'name' => $name, 'error' => $e->getMessage()]);

                continue;
            }

            $auto = (float) config('services.imports.dedupe_auto_threshold', 0.85);
            $review = (float) config('services.imports.dedupe_review_threshold', 0.5);

            if ($verdict['same'] && $verdict['confidence'] >= $auto) {
                $this->record($entity, $inputKey, $name, $model, DuplicateDecision::SAME, $verdict['confidence'], DuplicateDecision::SOURCE_AI, $verdict['reason']);

                return $model;
            }

            $this->record(
                $entity,
                $inputKey,
                $name,
                $model,
                $verdict['same'] && $verdict['confidence'] >= $review ? DuplicateDecision::UNCERTAIN : DuplicateDecision::DISTINCT,
                $verdict['confidence'],
                DuplicateDecision::SOURCE_AI,
                $verdict['reason'],
            );
        }

        return null;
    }

    private function record(string $entity, string $inputKey, string $name, Model $candidate, string $decision, ?float $confidence, string $source, ?string $reason): void
    {
        $row = DuplicateDecision::query()->create([
            'entity' => $entity,
            'input_key' => Str::limit($inputKey, 250, ''),
            'input_name' => Str::limit($name, 250, ''),
            'candidate_id' => $candidate->getKey(),
            'candidate_name' => Str::limit((string) $candidate->getAttribute('name'), 250, ''),
            'decision' => $decision,
            'confidence' => $confidence,
            'source' => $source,
            'reason' => $reason !== null && $reason !== '' ? $reason : null,
        ]);

        if ($decision === DuplicateDecision::UNCERTAIN) {
            $this->pendingUncertain[] = $row->id;
        }
    }

    private function hostIsUnique(string $host): bool
    {
        return Canal::query()->where('website', 'like', '%'.$host.'%')->count() <= 1;
    }

    private function municipalityName(?int $id): ?string
    {
        return $id === null ? null : Municipality::query()->find($id)?->fullname;
    }

    /**
     * Významové tokeny názvu: bez diakritiky, bez krátkych slov a spojok.
     *
     * @return array<int, string>
     */
    public static function tokens(string $value): array
    {
        $slug = Str::slug(ImportedNameMatcher::normalize($value));

        return array_values(array_unique(array_filter(
            explode('-', $slug),
            static fn (string $token): bool => strlen($token) >= 3 && ! in_array($token, self::STOPWORDS, true),
        )));
    }

    /**
     * Podobné, keď sa tokeny prekrývajú aspoň na $minJaccard, alebo keď je
     * kratší názov (aspoň dvojslovný) celý obsiahnutý v dlhšom.
     *
     * @param  array<int, string>  $a
     * @param  array<int, string>  $b
     */
    private static function similarTokens(array $a, array $b, float $minJaccard): bool
    {
        if ($a === [] || $b === []) {
            return false;
        }

        $common = count(array_intersect($a, $b));

        if ($common === 0) {
            return false;
        }

        return self::jaccard($a, $b) >= $minJaccard || ($common === min(count($a), count($b)) && min(count($a), count($b)) >= 2);
    }

    /**
     * @param  array<int, string>  $a
     * @param  array<int, string>  $b
     */
    private static function jaccard(array $a, array $b): float
    {
        $union = count(array_unique([...$a, ...$b]));

        return $union === 0 ? 0.0 : count(array_intersect($a, $b)) / $union;
    }

    private static function host(mixed $website): ?string
    {
        if (! is_string($website) || trim($website) === '') {
            return null;
        }

        $url = str_contains($website, '://') ? $website : 'https://'.$website;
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        return preg_replace('/^www\./i', '', mb_strtolower($host));
    }

    private static function distanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $radius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $radius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
