<?php

namespace App\Services\Geocoding;

use App\Models\Municipality;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Nájde v krátkom reťazci názov obce z číselníka.
 *
 * `MunicipalityResolver` odpovedá na otázku „je TOTO obec?" — dostane hotový
 * názov mesta a hľadá k nemu záznam. Tu je otázka opačná: „je v tomto riadku
 * niekde obec?". Plagát totiž mesto málokedy uvádza ako samostatný údaj — býva
 * schované v adresnom riadku („Klokočov - Zemplínska Šírava"), v názve miesta
 * alebo v názve podujatia. Model ho potom vráti ako súčasť ulice a pole
 * „Mesto / obec" ostane prázdne, hoci obec na plagáte je.
 *
 * Hľadá sa najdlhšia zhoda (aby „Ves" nevyhrala nad „Spišská Nová Ves") a len
 * od slova s veľkým začiatočným písmenom — bez toho by bežné slovo trafilo
 * rovnomennú obec a podujatie by skončilo v inom okrese.
 *
 * Vracia sa názov, nie `village_id`: rovnomenných obcí je v SR viac (2× Klokočov)
 * a ktorú z nich vybrať, rozhoduje až človek v sprievodcovi.
 */
class MunicipalityNameFinder
{
    /** Najdlhší názov v číselníku má štyri slová („Bratislava - Devínska Nová Ves"). */
    private const MAX_NAME_WORDS = 4;

    /** @var array<string, string>|null  normalizovaný názov => názov z číselníka */
    private ?array $catalog = null;

    /** @return string|null  názov presne tak, ako je v číselníku (`fullname`) */
    public function find(?string $text): ?string
    {
        $words = $this->words($text);

        if ($words === []) {
            return null;
        }

        $catalog = $this->catalog();
        $count = count($words);

        for ($start = 0; $start < $count; $start++) {
            if (! $this->startsWithUpperCase($words[$start])) {
                continue;
            }

            for ($length = min(self::MAX_NAME_WORDS, $count - $start); $length >= 1; $length--) {
                $candidate = $this->normalize(implode(' ', array_slice($words, $start, $length)));

                if ($candidate !== null && isset($catalog[$candidate])) {
                    return $catalog[$candidate];
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function catalog(): array
    {
        if ($this->catalog !== null) {
            return $this->catalog;
        }

        $ttl = max(0, (int) config('services.municipality_resolver.cache_ttl', 86400));

        /** @var array<string, string> $catalog */
        $catalog = Cache::remember(
            'municipality_name_finder:catalog',
            now()->addSeconds($ttl),
            function (): array {
                $catalog = [];

                Municipality::query()
                    ->where('use', true)
                    ->orderBy('id')
                    ->select(['id', 'fullname', 'shortname'])
                    ->each(function (Municipality $municipality) use (&$catalog): void {
                        // Aj `shortname` — číselník má pri mestských častiach
                        // dlhý `fullname` („Bratislava - Devínska Nová Ves"),
                        // ale plagát píše kratší tvar. Von ide vždy `fullname`,
                        // lebo na ten sa napája výber obce v sprievodcovi.
                        foreach ([$municipality->fullname, $municipality->shortname] as $name) {
                            $key = $this->normalize((string) $name);

                            if ($key !== null && ! isset($catalog[$key])) {
                                $catalog[$key] = (string) $municipality->fullname;
                            }
                        }
                    });

                return $catalog;
            },
        );

        return $this->catalog = $catalog;
    }

    /** @return array<int, string> */
    private function words(?string $text): array
    {
        if (! is_string($text) || trim($text) === '') {
            return [];
        }

        $words = preg_split('/[^\p{L}\p{N}]+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        return is_array($words) ? array_values($words) : [];
    }

    private function startsWithUpperCase(string $word): bool
    {
        return preg_match('/^\p{Lu}/u', $word) === 1;
    }

    private function normalize(string $value): ?string
    {
        $normalized = Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->value();

        return $normalized === '' ? null : $normalized;
    }
}
