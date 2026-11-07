<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Overenie tokenu z Cloudflare Turnstile (náhrada captchy).
 *
 * Chráni formuláre, ktoré posielajú e-mail na adresu zadanú návštevníkom
 * (registrácia, opätovné overenie, zabudnuté heslo). Boty cez ne zahlcujú
 * cudzie schránky a vrátené či nahlásené maily kazia reputáciu odosielacej
 * domény — potom končia v spame aj lístky a potvrdenia skutočným ľuďom.
 *
 * Bez `TURNSTILE_SECRET_KEY` je overenie vypnuté (lokálny vývoj, testy).
 * Kľúč na backende a `VITE_TURNSTILE_SITE_KEY` vo frontende treba zapnúť
 * spolu: backend s kľúčom a frontend bez widgetu odmietne každú registráciu.
 */
class Turnstile
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function enabled(): bool
    {
        return (string) config('services.turnstile.secret_key') !== '';
    }

    public function verify(?string $token, ?string $ip): bool
    {
        if (! $this->enabled()) {
            return true;
        }

        // Cloudflare vydáva tokeny do 2048 znakov; prázdny či dlhší je podvrh.
        if (! is_string($token) || $token === '' || strlen($token) > 2048) {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(5)->post(self::VERIFY_URL, array_filter([
                'secret' => config('services.turnstile.secret_key'),
                'response' => $token,
                'remoteip' => $ip,
            ]));
        } catch (\Throwable $e) {
            // Výpadok Cloudflare nesmie zastaviť registráciu skutočným ľuďom.
            // Zvyšné poistky (honeypot, limity podľa IP a adresy) bežia ďalej.
            Log::warning('Turnstile nedostupný, požiadavka prepustená', ['error' => $e->getMessage()]);

            return true;
        }

        if (! $response->successful()) {
            Log::warning('Turnstile vrátil chybu, požiadavka prepustená', ['status' => $response->status()]);

            return true;
        }

        return $response->json('success') === true;
    }
}
