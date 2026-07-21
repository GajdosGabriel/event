<?php

namespace App\Providers;

use App\Models\Canal;
use App\Models\Event;
use App\Models\User;
use App\Observers\CanalObserver;
use App\Observers\UserObserver;
use App\Observers\EventObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        User::observe(UserObserver::class);
        Canal::observe(CanalObserver::class);
        Event::observe(EventObserver::class);

        $this->configureRateLimiting();
    }

    /**
     * Pomenované rate limitery. Aplikujú sa v routes/api.php cez `throttle:<meno>`.
     *
     * Hostí kľúčujeme podľa IP, prihlásených podľa user ID — aby jeden účet za
     * NATom nezablokoval ostatných a naopak.
     */
    private function configureRateLimiting(): void
    {
        // Základný strop na celé API (zapnutý cez throttleApi() v bootstrap/app.php).
        // Laravel žiadny "api" limiter sám neregistruje — bez tohto by middleware
        // reťazec 'api' interpretoval ako počet pokusov a limit by nefungoval.
        //
        // Zámerne voľný: tento limiter beží pred `auth:sanctum`, takže požiadavky
        // s Bearer tokenom sa kľúčujú podľa IP a celá kancelária za jednou NATovanou
        // adresou zdieľa jeden kôš. Je to len hrubá poistka proti scrapovaniu —
        // skutočnú ochranu robia prísne limitery na citlivých endpointoch nižšie.
        RateLimiter::for('api', fn (Request $request) => [
            Limit::perMinute(300)->by($this->identify($request))
                ->response($this->tooManyRequests('Priveľa požiadaviek. Skúste to o chvíľu znova.')),
        ]);

        // Brute-force na heslá. Kľúč zahŕňa e-mail, takže útok na jeden účet
        // nezablokuje prihlásenie ostatným z tej istej siete.
        RateLimiter::for('auth', fn (Request $request) => [
            Limit::perMinute(5)
                ->by($request->ip() . '|' . Str::lower((string) $request->input('email')))
                ->response($this->tooManyRequests('Priveľa pokusov o prihlásenie. Skúste to o chvíľu znova.')),
        ]);

        // Registrácia zakladá účty a posiela overovacie e-maily — bez limitu
        // je to nástroj na spamovanie cudzích schránok.
        RateLimiter::for('register', fn (Request $request) => [
            Limit::perMinute(3)->by($request->ip())
                ->response($this->tooManyRequests('Priveľa pokusov o registráciu. Skúste to o chvíľu znova.')),
            Limit::perHour(10)->by($request->ip())
                ->response($this->tooManyRequests('Priveľa pokusov o registráciu. Skúste to neskôr.')),
        ]);

        // Verejné zápisy bez prihlásenia: rezervácia lístkov a RSVP z e-mailu.
        RateLimiter::for('public-write', fn (Request $request) => [
            Limit::perMinute(10)->by($request->ip())
                ->response($this->tooManyRequests('Priveľa požiadaviek. Skúste to o chvíľu znova.')),
        ]);

        // Správy môže posielať len overený používateľ, ale aj ten by inak mohol
        // spamovať neobmedzene.
        RateLimiter::for('messages', fn (Request $request) => [
            Limit::perHour(10)->by($this->identify($request))
                ->response($this->tooManyRequests('Priveľa odoslaných správ. Skúste to neskôr.')),
        ]);

        // Každé volanie ide do OpenAI a stojí peniaze.
        RateLimiter::for('ai', fn (Request $request) => [
            Limit::perMinute(10)->by($this->identify($request))
                ->response($this->tooManyRequests('Priveľa AI požiadaviek. Skúste to o chvíľu znova.')),
            Limit::perDay(100)->by($this->identify($request))
                ->response($this->tooManyRequests('Vyčerpaný denný limit AI požiadaviek.')),
        ]);

        // Údržbové endpointy spúšťané cez URL (nemáme shell na hostingu).
        RateLimiter::for('ops', fn (Request $request) => [
            Limit::perMinute(6)->by($request->ip())
                ->response($this->tooManyRequests('Priveľa požiadaviek.')),
        ]);
    }

    /**
     * Prihláseného kľúčujeme podľa ID, hosťa podľa IP.
     *
     * Ide zámerne cez guard `sanctum`, nie cez $request->user(): limitery bežia
     * ešte pred `auth:sanctum` a časť routes (napr. POST /messages) toto
     * middleware ani nemá — používateľa si rieši až samotný controller.
     * Bez guardu by sa všetci prihlásení kľúčovali podľa IP.
     */
    private function identify(Request $request): string
    {
        $id = auth('sanctum')->id();

        return $id ? 'user:' . $id : 'ip:' . $request->ip();
    }

    /**
     * API vracia JSON aj pri prekročení limitu — nie Laravel HTML chybovku.
     */
    private function tooManyRequests(string $message): callable
    {
        return fn (Request $request, array $headers) => response()->json(
            ['message' => $message],
            429,
            $headers,
        );
    }
}
