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

        // Accept-Language: "cs-CZ,cs;q=0.9,sk;q=0.8" — kvalitu neriešime, berieme
        // prvý jazyk v poradí, ktorý vieme obslúžiť.
        foreach (explode(',', (string) $request->header('Accept-Language', '')) as $part) {
            $candidate = $this->normalize(explode(';', $part)[0]);

            if ($candidate !== null && in_array($candidate, $supported, true)) {
                return $candidate;
            }
        }

        return null;
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
