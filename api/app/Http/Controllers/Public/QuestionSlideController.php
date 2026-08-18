<?php

namespace App\Http\Controllers\Public;

use App\Enums\SlideTheme;
use App\Enums\SlideVariant;
use App\Http\Controllers\Controller;
use App\Models\QuestionBoard;
use App\Services\Questions\BoardLocator;
use App\Services\Questions\PptxPackager;
use App\Services\Questions\SlideComposer;
use App\Services\Questions\SlideRenderer;
use App\Services\Tickets\QrCodeGenerator;
use App\Support\PublicUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Snímka s QR kódom na stiahnutie.
 *
 * Verejné zámerne: obsah snímky je ten istý QR kód, ktorý organizátor premieta
 * pred celú sálu, takže tajiť ho nemá zmysel. Vďaka tomu je sťahovanie
 * obyčajný odkaz a náhľad v dashboarde je obyčajný `<img>` — front nemusí
 * riešiť blob a hlavičky.
 *
 * Súbor sa nikde neukladá; vzniká pri každom stiahnutí nanovo (viď
 * SlideRenderer). Cenu drží limiter `render`.
 */
class QuestionSlideController extends Controller
{
    public function __construct(
        private BoardLocator $locator,
        private SlideComposer $composer,
        private SlideRenderer $renderer,
        private PptxPackager $packager,
        private QrCodeGenerator $qrCodes,
    ) {
    }

    public function png(Request $request, string $token): Response
    {
        $board = $this->locator->publicOrFail($token);
        $this->applyLanguage($request);

        $variant = $this->variant($request);
        $theme = $this->theme($request);

        $png = $this->renderPng($board, $variant, $theme);

        return $this->download(
            $png,
            'image/png',
            $this->filename($board, $variant->value . '-' . $theme->value, 'png'),
            $request->boolean('inline'),
        );
    }

    /**
     * Samotný QR kód bez snímky.
     *
     * Existuje kvôli premietacej stene, kde QR visí v rohu ako malý štvorček.
     * Zmenšiť tam celú snímku sa nedá — pri 120 pixeloch by z nej bola
     * nečitateľná miniatúra a kód by sa nenaskenoval. Tichú zónu si tu QR
     * nesie sám (`margin`), lebo pod ním nie je žiadna biela karta.
     */
    public function qr(Request $request, string $token): Response
    {
        $board = $this->locator->publicOrFail($token);

        $size = max(120, min(1200, (int) $request->query('size', 480)));
        $qr = $this->qrCodes->imageForUrl(PublicUrl::questionBoard($board->token), $size, (int) round($size * 0.06));

        ob_start();
        imagepng($qr, null, 6);
        $png = (string) ob_get_clean();
        imagedestroy($qr);

        return $this->download($png, 'image/png', $this->filename($board, 'qr', 'png'), inline: true);
    }

    /**
     * `.pptx` vždy nesie širokú snímku — štvorec do prezentácie nepatrí
     * a jeden parameter navyše by znamenal ďalší stav, ktorý treba podporovať.
     */
    public function pptx(Request $request, string $token): Response
    {
        $board = $this->locator->publicOrFail($token);
        $this->applyLanguage($request);

        $theme = $this->theme($request);
        $png = $this->renderPng($board, SlideVariant::Slide, $theme);

        return $this->download(
            $this->packager->package($png, $board->title()),
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            $this->filename($board, $theme->value, 'pptx'),
        );
    }

    private function renderPng(QuestionBoard $board, SlideVariant $variant, SlideTheme $theme): string
    {
        abort_unless($this->renderer->isAvailable(), 503, __('questions.errors.rendering_unavailable'));

        return $this->renderer->render($this->composer->compose($board), $variant, $theme);
    }

    /**
     * Jazyk snímky ide parametrom `?lang=`, nie hlavičkou `X-Locale`.
     * Sťahovacia adresa má byť sebapopisná: organizátor si ju môže poslať
     * e-mailom a musí z nej vzniknúť ten istý súbor, aj keď ju otvorí niekto
     * s iným prehliadačom. Zároveň odpadá `Vary: X-Locale` na verejnej,
     * cachovateľnej odpovedi.
     */
    private function applyLanguage(Request $request): void
    {
        $lang = (string) $request->query('lang', '');

        if ($lang !== '' && in_array($lang, (array) config('app.supported_locales', []), true)) {
            app()->setLocale($lang);
        }
    }

    private function variant(Request $request): SlideVariant
    {
        return SlideVariant::tryFrom((string) $request->query('variant', SlideVariant::Slide->value))
            ?? abort(422, __('questions.errors.unknown_variant'));
    }

    /**
     * Neznámy motív je 422, nie tiché spadnutie na predvolený — preklep
     * v odkaze by inak navždy potichu servíroval niečo iné, než si človek
     * vybral.
     */
    private function theme(Request $request): SlideTheme
    {
        return SlideTheme::tryFrom((string) $request->query('theme', SlideTheme::Dark->value))
            ?? abort(422, __('questions.errors.unknown_variant'));
    }

    private function filename(QuestionBoard $board, string $suffix, string $extension): string
    {
        // Str::slug prechádza cez Str::ascii, takže výsledok je čisté ASCII
        // a hlavička nepotrebuje kódovanie podľa RFC 5987.
        $slug = trim(Str::slug($board->title()), '-');
        $slug = $slug !== '' ? $slug : 'podujatie';

        return "otazky-{$slug}-{$suffix}.{$extension}";
    }

    private function download(string $bytes, string $mime, string $filename, bool $inline = false): Response
    {
        $disposition = $inline ? 'inline' : 'attachment';

        return response($bytes, 200, [
            'Content-Type' => $mime,
            'Content-Length' => (string) strlen($bytes),
            'Content-Disposition' => $disposition . '; filename="' . $filename . '"',
            // Desať minút znesie aj náhľad v dashboarde pri preklikávaní
            // motívov a zároveň to je jediná obrana proti opakovanému
            // sťahovaniu tej istej snímky.
            'Cache-Control' => 'public, max-age=600',
            'ETag' => '"' . md5($bytes) . '"',
        ]);
    }
}
