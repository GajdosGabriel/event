<?php

namespace App\Http\Controllers\Public;

use App\Enums\SlideTheme;
use App\Enums\SlideVariant;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Repositories\Contracts\EventRepository;
use App\Services\Questions\SlideComposer;
use App\Services\Questions\SlideRenderer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Pozvánka na akciu ako obrázok s QR kódom — štvorec, story, plátno 16:9
 * alebo plagát A4 (panel Propagácia v správe akcie).
 *
 * Kreslí ju ten istý SlideRenderer ako snímku nástenky otázok; rozdiel je len
 * v obsahu (SlideComposer::composeInvite). Verejné z rovnakého dôvodu ako
 * QR kód akcie: obrázok nesie len verejné údaje, náhľad je obyčajný `<img>`
 * a odkaz na stiahnutie sa dá poslať grafikovi. Viditeľnosť rieši
 * `publicShow()` — koncept pozvánku nemá.
 *
 * Nikde sa neukladá; cenu drží limiter `render`.
 */
class EventInviteController extends Controller
{
    public function __construct(
        private EventRepository $events,
        private SlideComposer $composer,
        private SlideRenderer $renderer,
    ) {}

    public function png(Request $request, string $id): Response
    {
        $event = ctype_digit($id) ? $this->events->publicShow($id) : null;
        abort_unless($event instanceof Event, 404);

        $variant = SlideVariant::tryFrom((string) $request->query('variant', SlideVariant::Square->value));
        abort_unless($variant !== null && in_array($variant->value, SlideVariant::inviteValues(), true), 422);

        // Neznámy motív je 422, nie tiché spadnutie na predvolený — rovnako
        // ako pri snímke otázok.
        $theme = SlideTheme::tryFrom((string) $request->query('theme', SlideTheme::Dark->value)) ?? abort(422);

        $this->applyLanguage($request);
        abort_unless($this->renderer->isAvailable(), 503, __('events.invite.unavailable'));

        $png = $this->renderer->render($this->composer->composeInvite($event), $variant, $theme);

        $slug = trim(Str::slug($event->name), '-');
        $filename = 'pozvanka-'.($slug !== '' ? Str::limit($slug, 60, '') : $event->id).'-'.$variant->value.'.png';
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Length' => (string) strlen($png),
            'Content-Disposition' => $disposition.'; filename="'.$filename.'"',
            // Krátka cache: organizátor po úprave akcie čaká novú pozvánku
            // a pri preklikávaní motívov stačí, že sa ten istý náhľad neťahá znova.
            'Cache-Control' => 'public, max-age=120',
        ]);
    }

    /**
     * Náhľad odkazu (`og:image`) pre akciu bez vlastnej fotky.
     *
     * Číta ho crawler Facebooku, WhatsAppu či Messengera pri každom vložení
     * odkazu — a tie chodia v dávkach z rovnakých adries. Preto sa obrázok
     * nevykresľuje zakaždým, ale drží v cache, kým sa akcia nezmení
     * (kľúč nesie `updated_at`), a nepodlieha limiteru `render`.
     */
    public function og(string $id): Response
    {
        $event = ctype_digit($id) ? $this->events->publicShow($id) : null;
        abort_unless($event instanceof Event, 404);
        abort_unless($this->renderer->isAvailable(), 503, __('events.invite.unavailable'));

        $key = sprintf('event-og:%d:%d:%s', $event->id, $event->updated_at?->getTimestamp() ?? 0, app()->getLocale());

        // base64: databázová cache (CACHE_STORE=database) binárne dáta neznesie.
        $png = base64_decode((string) Cache::remember($key, now()->addDays(7), fn () => base64_encode(
            $this->renderer->render($this->composer->composeInvite($event), SlideVariant::Og, SlideTheme::Bold),
        )));

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Length' => (string) strlen($png),
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * Jazyk ide parametrom `?lang=` — `<img>` a `<a download>` nepošlú
     * hlavičku `X-Locale` (rovnako ako QuestionSlideController).
     */
    private function applyLanguage(Request $request): void
    {
        $lang = (string) $request->query('lang', '');

        if ($lang !== '' && in_array($lang, (array) config('app.supported_locales', []), true)) {
            app()->setLocale($lang);
        }
    }
}
