<?php

namespace App\Http\Middleware;

use App\Services\Security\Turnstile;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ochrana verejných formulárov, ktoré posielajú e-mail na zadanú adresu.
 *
 * 1. Honeypot: pole `website` je vo formulári skryté, človek ho nevyplní,
 *    bot áno. Odpoveď je falošný úspech bez odoslania — bot sa nemá
 *    z čoho naučiť, že ho niečo zastavilo.
 * 2. Cloudflare Turnstile (`turnstile_token`), ak je nastavený kľúč. Tu
 *    chyba je viditeľná, lebo zlyhať môže aj človeku (vypršaný widget).
 */
class GuardAgainstBots
{
    public const HONEYPOT_FIELD = 'website';

    public function __construct(private readonly Turnstile $turnstile) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (filled($request->input(self::HONEYPOT_FIELD))) {
            Log::info('Bot zachytený honeypotom', ['route' => $request->route()?->getName(), 'ip' => $request->ip()]);

            return response()->json(['message' => 'Request accepted.'], 202);
        }

        $token = $request->input('turnstile_token');
        if (! $this->turnstile->verify(is_string($token) ? $token : null, $request->ip())) {
            throw ValidationException::withMessages([
                'turnstile_token' => [__('auth.bot_check_failed')],
            ]);
        }

        return $next($request);
    }
}
