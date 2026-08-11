<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Prepne locale aplikácie podľa požiadavky klienta.
     *
     * SPA posiela zvolený jazyk v hlavičke X-Locale; Accept-Language je záloha
     * pre bežné prehliadačové requesty (prerender, odkazy z mailu). Nepodporovaný
     * jazyk sa ticho ignoruje a ostáva default z config/app.php — chýbajúce
     * kľúče v preklade aj tak spadnú na fallback_locale.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('app.supported_locales', []);

        $locale = $this->fromRequest($request, $supported);

        if ($locale !== null) {
            app()->setLocale($locale);
        }

        $response = $next($request);

        // Aby klient (a prípadná cache) vedeli, v akom jazyku odpoveď je.
        $response->headers->set('Content-Language', app()->getLocale());

        return $response;
    }

    /**
     * @param  array<int, string>  $supported
     */
    private function fromRequest(Request $request, array $supported): ?string
    {
        $explicit = $this->normalize((string) $request->header('X-Locale', ''));

        if ($explicit !== null && in_array($explicit, $supported, true)) {
            return $explicit;
        }

        foreach ($this->acceptedLanguages($request) as $candidate) {
            if (in_array($candidate, $supported, true)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Accept-Language: "en;q=0.3, cs-CZ, sk;q=0.9" → ['cs', 'sk', 'en'].
     *
     * Prehliadače zvyknú posielať zoznam už zoradený, ale hlavička to
     * negarantuje — rozhodujúca je q-hodnota (chýbajúca znamená 1.0).
     * "q=0" je explicitné odmietnutie jazyka, takže ho vyhadzujeme.
     *
     * @return array<int, string>
     */
    private function acceptedLanguages(Request $request): array
    {
        $candidates = [];

        foreach (explode(',', (string) $request->header('Accept-Language', '')) as $part) {
            $pieces = explode(';', $part);
            $language = $this->normalize($pieces[0]);

            if ($language === null) {
                continue;
            }

            $quality = 1.0;

            foreach (array_slice($pieces, 1) as $parameter) {
                if (preg_match('/^\s*q\s*=\s*(\d(?:\.\d+)?)\s*$/i', $parameter, $matches) === 1) {
                    $quality = (float) $matches[1];
                }
            }

            // Ten istý jazyk môže prísť viackrát ("cs-CZ, cs;q=0.9") — platí
            // najvyššia kvalita, aby varianta neznížila hodnotenie základu.
            if ($quality > 0 && $quality > ($candidates[$language] ?? 0.0)) {
                $candidates[$language] = $quality;
            }
        }

        // usort/arsort je v PHP 8 stabilné, takže pri rovnakej q ostáva poradie
        // z hlavičky.
        arsort($candidates);

        return array_keys($candidates);
    }

    /**
     * "cs-CZ" → "cs". Vráti null, ak to nevyzerá ako kód jazyka.
     */
    private function normalize(string $value): ?string
    {
        $base = strtolower(trim(explode('-', trim($value))[0]));

        return preg_match('/^[a-z]{2}$/', $base) === 1 ? $base : null;
    }
}
